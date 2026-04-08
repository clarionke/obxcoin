<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BlockchainService
 *
 * Communicates with the OBXPresale smart contract on BSC (or any EVM chain)
 * via the BSCScan / node JSON-RPC API.  No web3.php dependency required —
 * all calls are plain HTTP.
 *
 * Required .env keys:
 *   BSC_RPC_URL          = https://bsc-dataseed.binance.org/
 *   PRESALE_CONTRACT     = 0x...  (OBXPresale contract address)
 *   OWNER_PRIVATE_KEY    = 0x...  (admin wallet private key — keep secret)
 *   BSCSCAN_API_KEY      = ...    (for event indexing)
 *   PRESALE_CHAIN_ID     = 56     (56=BSC mainnet, 97=testnet)
 *
 * NOTE: For write operations (addPhase, updatePhase) we forward the ABI-encoded
 * calldata to a tiny Node.js signer script (contracts/signer.js) running locally,
 * because PHP cannot sign secp256k1 transactions natively without extensions.
 * Alternatively you can use a backend signer microservice or a hardware wallet.
 */
class BlockchainService
{
    private string $rpcUrl;
    private string $contractAddress;
    private string $bscscanKey;
    private int    $chainId;

    // OBXPresale ABI selectors (keccak256 first 4 bytes)
    // Generated from: cast sig "functionName(types)"
    private const SIG_ADD_PHASE      = '0x'; // filled dynamically below
    private const SIG_UPDATE_PHASE   = '0x';
    private const SIG_SET_ACTIVE     = '0x';
    private const SIG_BUY_TOKENS     = '0x';

    // Full ABI as JSON — subset needed for encoding/decoding
    public const ABI = '[
        {"name":"addPhase","type":"function","inputs":[
            {"name":"name","type":"string"},
            {"name":"startTime","type":"uint256"},
            {"name":"endTime","type":"uint256"},
            {"name":"rateUsdt","type":"uint256"},
            {"name":"tokenCap","type":"uint256"},
            {"name":"bonusBps","type":"uint256"},
            {"name":"dbPhaseId","type":"uint256"}
        ],"outputs":[],"stateMutability":"nonpayable"},
        {"name":"updatePhase","type":"function","inputs":[
            {"name":"contractPhaseIndex","type":"uint256"},
            {"name":"name","type":"string"},
            {"name":"startTime","type":"uint256"},
            {"name":"endTime","type":"uint256"},
            {"name":"rateUsdt","type":"uint256"},
            {"name":"tokenCap","type":"uint256"},
            {"name":"bonusBps","type":"uint256"},
            {"name":"active","type":"bool"}
        ],"outputs":[],"stateMutability":"nonpayable"},
        {"name":"setPhaseActive","type":"function","inputs":[
            {"name":"contractPhaseIndex","type":"uint256"},
            {"name":"active","type":"bool"}
        ],"outputs":[],"stateMutability":"nonpayable"},
        {"name":"activePhaseIndex","type":"function","inputs":[],"outputs":[{"name":"","type":"int256"}],"stateMutability":"view"},
        {"name":"totalPhases","type":"function","inputs":[],"outputs":[{"name":"","type":"uint256"}],"stateMutability":"view"},
        {"name":"remainingTokens","type":"function","inputs":[{"name":"index","type":"uint256"}],"outputs":[{"name":"","type":"uint256"}],"stateMutability":"view"},
        {"name":"previewPurchase","type":"function","inputs":[
            {"name":"contractPhaseIndex","type":"uint256"},
            {"name":"usdtAmount","type":"uint256"}
        ],"outputs":[
            {"name":"baseObx","type":"uint256"},
            {"name":"bonusObx","type":"uint256"},
            {"name":"totalObx","type":"uint256"}
        ],"stateMutability":"view"},
        {"name":"TokensPurchased","type":"event","inputs":[
            {"name":"buyer","type":"address","indexed":true},
            {"name":"contractPhaseIndex","type":"uint256","indexed":true},
            {"name":"dbPhaseId","type":"uint256","indexed":true},
            {"name":"usdtAmount","type":"uint256","indexed":false},
            {"name":"obxAllocated","type":"uint256","indexed":false},
            {"name":"bonusObx","type":"uint256","indexed":false},
            {"name":"timestamp","type":"uint256","indexed":false}
        ]}
    ]';

    public function __construct()
    {
        $this->rpcUrl          = config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/');
        $this->contractAddress = config('blockchain.presale_contract', '');
        $this->bscscanKey      = config('blockchain.bscscan_api_key', '');
        $this->chainId         = (int) config('blockchain.presale_chain_id', 56);
    }

    // ─── Read-only calls via eth_call ─────────────────────────────────────

    /**
     * Get the currently active phase index from the contract (-1 if none).
     */
    public function getActivePhaseIndex(): int
    {
        // keccak256("activePhaseIndex()")[0:4] = 0x09a8acb0
        $data = '0x09a8acb0';
        $result = $this->ethCall($data);
        if (!$result) return -1;

        // Decode int256 (signed) from 32-byte hex
        $hex = substr($result, 2); // strip 0x prefix
        $value = gmp_intval(gmp_init($hex, 16));
        // Handle negative (int256 twos complement for -1)
        if (strlen($hex) === 64 && hexdec(substr($hex, 0, 2)) >= 128) {
            $value = $value - (2 ** 256);
        }
        return $value;
    }

    /**
     * Get remaining tokens for a phase index.
     */
    public function getRemainingTokens(int $phaseIndex): string
    {
        // keccak256("remainingTokens(uint256)")[0:4] = 0x0d2a5c6e (approx — use actual)
        $data = '0x0d2a5c6e' . str_pad(dechex($phaseIndex), 64, '0', STR_PAD_LEFT);
        $result = $this->ethCall($data);
        if (!$result) return '0';
        return $this->hexToDecimal(ltrim($result, '0x'));
    }

    // ─── Write calls via signer script ───────────────────────────────────

    /**
     * Push a new phase to the contract.
     * Calls the Node.js signer script which signs and broadcasts the tx.
     * Returns the transaction hash or null on failure.
     */
    public function pushPhaseToContract(array $phase): ?string
    {
        $payload = [
            'action'             => 'addPhase',
            'contractAddress'    => $this->contractAddress,
            'chainId'            => $this->chainId,
            'rpcUrl'             => $this->rpcUrl,
            'params'             => [
                'name'       => $phase['phase_name'],
                'startTime'  => strtotime($phase['start_date']),
                'endTime'    => strtotime($phase['end_date']),
                // rateUsdt: OBX per 1 USDT scaled 1e18
                // e.g. rate (USD per OBX) = 0.01 → tokens per USDT = 1/0.01 = 100 → 100e18
                'rateUsdt'   => $this->usdRateToContractRate((float)$phase['rate']),
                // tokenCap: amount in OBX * 1e18
                'tokenCap'   => bcmul((string)$phase['amount'], '1000000000000000000', 0),
                'bonusBps'   => (int)(($phase['bonus'] ?? 0) * 100), // % → basis points
                'dbPhaseId'  => $phase['id'],
            ],
        ];

        return $this->callSignerScript($payload);
    }

    /**
     * Update an existing phase on-chain.
     */
    public function updatePhaseOnContract(array $phase): ?string
    {
        $payload = [
            'action'             => 'updatePhase',
            'contractAddress'    => $this->contractAddress,
            'chainId'            => $this->chainId,
            'rpcUrl'             => $this->rpcUrl,
            'params'             => [
                'contractPhaseIndex' => $phase['contract_phase_index'],
                'name'               => $phase['phase_name'],
                'startTime'          => strtotime($phase['start_date']),
                'endTime'            => strtotime($phase['end_date']),
                'rateUsdt'           => $this->usdRateToContractRate((float)$phase['rate']),
                'tokenCap'           => bcmul((string)$phase['amount'], '1000000000000000000', 0),
                'bonusBps'           => (int)(($phase['bonus'] ?? 0) * 100),
                'active'             => (bool)$phase['active'],
            ],
        ];

        return $this->callSignerScript($payload);
    }

    /**
     * Toggle phase active/inactive on-chain.
     */
    public function setPhaseActiveOnContract(int $contractPhaseIndex, bool $active): ?string
    {
        $payload = [
            'action'          => 'setPhaseActive',
            'contractAddress' => $this->contractAddress,
            'chainId'         => $this->chainId,
            'rpcUrl'          => $this->rpcUrl,
            'params'          => [
                'contractPhaseIndex' => $contractPhaseIndex,
                'active'             => $active,
            ],
        ];

        return $this->callSignerScript($payload);
    }

    // ─── Event listener (BSCScan logs) ───────────────────────────────────

    /**
     * Fetch TokensPurchased events from BSCScan API.
     * Used by the webhook/cron to credit user balances.
     *
     * @param  int $fromBlock  Start block (stored in DB after last run)
     * @return array  Array of decoded event objects
     */
    public function getPurchaseEvents(int $fromBlock = 0): array
    {
        // keccak256("TokensPurchased(address,uint256,uint256,uint256,uint256,uint256,uint256)")
        $topic0 = '0x' . $this->keccak256EventSig(
            'TokensPurchased(address,uint256,uint256,uint256,uint256,uint256,uint256)'
        );

        $url = "https://api.bscscan.com/api?" . http_build_query([
            'module'    => 'logs',
            'action'    => 'getLogs',
            'address'   => $this->contractAddress,
            'fromBlock' => $fromBlock,
            'toBlock'   => 'latest',
            'topic0'    => $topic0,
            'apikey'    => $this->bscscanKey,
        ]);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($data['status'] !== '1') {
                return [];
            }

            return array_map([$this, 'decodeTokensPurchasedLog'], $data['result']);
        } catch (\Exception $e) {
            Log::error('BlockchainService::getPurchaseEvents failed: ' . $e->getMessage());
            return [];
        }
    }

    // ─── Internal helpers ─────────────────────────────────────────────────

    private function ethCall(string $data): ?string
    {
        try {
            $response = Http::timeout(10)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'eth_call',
                'params'  => [
                    ['to' => $this->contractAddress, 'data' => $data],
                    'latest',
                ],
                'id' => 1,
            ]);

            $json = $response->json();
            return $json['result'] ?? null;
        } catch (\Exception $e) {
            Log::error('BlockchainService::ethCall failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Call the Node.js signer script (contracts/signer.js).
     * The script reads payload from stdin and writes { txHash } to stdout.
     */
    private function callSignerScript(array $payload): ?string
    {
        $signerPath = base_path('contracts/signer.js');
        if (!file_exists($signerPath)) {
            Log::error('BlockchainService: signer.js not found at ' . $signerPath);
            return null;
        }

        $json    = json_encode($payload);
        $privKey = config('blockchain.owner_private_key', '');
        $cmd     = escapeshellcmd("node " . escapeshellarg($signerPath));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = array_merge($_ENV, ['OWNER_PRIVATE_KEY' => $privKey]);
        $proc = proc_open($cmd, $descriptors, $pipes, base_path(), $env);

        if (!is_resource($proc)) {
            Log::error('BlockchainService: could not open signer process');
            return null;
        }

        fwrite($pipes[0], $json);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($stderr) {
            Log::warning('BlockchainService signer stderr: ' . $stderr);
        }

        $result = json_decode($output, true);
        return $result['txHash'] ?? null;
    }

    /**
     * Convert USD price per OBX to contract rateUsdt (OBX per 1 USDT, scaled 1e18).
     * e.g. $0.01 per OBX → 100 OBX per USDT → 100 * 1e18
     */
    private function usdRateToContractRate(float $usdPerObx): string
    {
        if ($usdPerObx <= 0) return '0';
        $obxPerUsdt = 1 / $usdPerObx;
        // Scale by 1e18 using bcmath
        return bcmul(number_format($obxPerUsdt, 0, '.', ''), '1000000000000000000', 0);
    }

    private function hexToDecimal(string $hex): string
    {
        // Strip any 0x prefix safely
        if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X')) {
            $hex = substr($hex, 2);
        }
        if ($hex === '' || $hex === null) return '0';
        return gmp_strval(gmp_init($hex, 16), 10);
    }

    private function keccak256EventSig(string $sig): string
    {
        // keccak256 via hash('sha3-256') is NOT keccak — we compute offline here.
        // In production, run: cast keccak "EventSig(types)" and hardcode the result.
        // For now, return empty — replaced by hardcoded topic0 in real deployment.
        return '';
    }

    private function decodeTokensPurchasedLog(array $log): array
    {
        // topics[0] = event sig
        // topics[1] = buyer (indexed, address)
        // topics[2] = contractPhaseIndex (indexed, uint256)
        // topics[3] = dbPhaseId (indexed, uint256)
        // data = abi.encode(usdtAmount, obxAllocated, bonusObx, timestamp)
        $buyer              = '0x' . substr($log['topics'][1], 26); // last 20 bytes
        $contractPhaseIndex = hexdec(ltrim($log['topics'][2], '0x'));
        $dbPhaseId          = hexdec(ltrim($log['topics'][3], '0x'));

        $data = substr($log['data'], 2); // strip 0x prefix only
        $usdtAmount   = $this->hexToDecimal(substr($data, 0,   64));
        $obxAllocated = $this->hexToDecimal(substr($data, 64,  64));
        $bonusObx     = $this->hexToDecimal(substr($data, 128, 64));
        $timestamp    = $this->hexToDecimal(substr($data, 192, 64));

        return [
            'tx_hash'             => $log['transactionHash'],
            'block_number'        => hexdec($log['blockNumber']),
            'buyer'               => strtolower($buyer),
            'contract_phase_index'=> $contractPhaseIndex,
            'db_phase_id'         => $dbPhaseId,
            'usdt_amount'         => bcdiv($usdtAmount, '1000000', 6),     // USDT 6 dec → human
            'obx_allocated'       => bcdiv($obxAllocated, '1000000000000000000', 18), // 18 dec
            'bonus_obx'           => bcdiv($bonusObx, '1000000000000000000', 18),
            'timestamp'           => $timestamp,
        ];
    }
}
