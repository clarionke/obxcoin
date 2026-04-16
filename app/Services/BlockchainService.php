<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BlockchainService  v2
 *
 * PHP layer for all on-chain interactions with OBXToken + OBXPresale.
 * Uses plain HTTP (no web3.php dependency):
 *   â€¢ Read calls  â†’ eth_call via BSC JSON-RPC
 *   â€¢ Write calls â†’ Node.js signer subprocess (contracts/signer.js)
 *   â€¢ Events      â†’ BSCScan getLogs API
 *
 * Multi-chain: same ABI works on BSC, ETH, Polygon, etc.
 * Every operation is visible on the chain's block explorer.
 *
 * Required .env:
 *   BSC_RPC_URL           = https://bsc-dataseed.binance.org/
 *   PRESALE_CONTRACT      = 0x...   (OBXPresale contract address)
 *   OBX_TOKEN_CONTRACT    = 0x...   (OBXToken contract address)
 *   OWNER_PRIVATE_KEY     = 0x...   (admin wallet â€” keep secret)
 *   BSCSCAN_API_KEY       = ...
 *   PRESALE_CHAIN_ID      = 56      (56=BSC, 97=testnet, 1=ETH, 137=Polygon)
 *   PRESALE_WEBHOOK_SECRET = ...    (HMAC secret for webhook endpoint)
 *   PRESALE_SYNC_API_KEY  = ...     (bearer key for cron endpoint)
 */
class BlockchainService
{
    private string $rpcUrl;
    private string $contractAddress;
    private string $obxTokenAddress;
    private string $bscscanKey;
    private int    $chainId;

    // â”€â”€â”€ Verified function selectors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Computed via: ethers.utils.id("funcName(types)").slice(0,10)
    // DO NOT use ltrim('0x') â€” these are complete 4-byte selectors

    /** activePhaseIndex() */
    private const SEL_ACTIVE_PHASE   = '0x9e9535eb';
    /** remainingTokens(uint256) */
    private const SEL_REMAINING      = '0x171ee95a';
    /** previewPurchase(uint256,uint256) */
    private const SEL_PREVIEW        = '0x311b0d56';
    /** totalPhases() */
    private const SEL_TOTAL_PHASES   = '0x3c5d1812';
    /** obxReserve() */
    private const SEL_OBX_RESERVE    = '0x5d531308';

    // â”€â”€â”€ Verified event topic0 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // keccak256("TokensPurchased(address,uint256,uint256,uint256,uint256,uint256,uint256)")
    private const TOPIC_TOKENS_PURCHASED =
        '0xb176b33ad40225c8f67dc6ef0cba96c0c88f67afd36396e2198a3f48a44fc7a0';

    public function __construct()
    {
        $this->chainId = (int) config('blockchain.presale_chain_id', 56);

        $rpcUrls          = (array) config('blockchain.rpc_urls', []);
        $presaleContracts = (array) config('blockchain.presale_contracts', []);
        $tokenContracts   = (array) config('blockchain.obx_token_addresses', []);

        // Load admin_settings — these take priority over .env for all contract/chain config.
        $s = [];
        try { $s = allsetting(); } catch (\Throwable $e) {}

        // Chain ID: admin_settings 'presale_chain_id' > admin_settings 'chain_id' > .env
        if (!empty($s['presale_chain_id'])) {
            $this->chainId = (int) $s['presale_chain_id'];
        } elseif (!empty($s['chain_id'])) {
            $this->chainId = (int) $s['chain_id'];
        }

        $configuredRpc = (string) ($rpcUrls[$this->chainId]
            ?? config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/'));

        // Protect against empty env/config values which otherwise break signer network detection.
        $this->rpcUrl = trim($configuredRpc) !== ''
            ? trim($configuredRpc)
            : 'https://bsc-dataseed.binance.org/';

        // RPC URL: admin_settings 'bsc_rpc_url' / 'chain_link' override .env BSC_RPC_URL
        if (!empty($s['bsc_rpc_url'])) {
            $this->rpcUrl = trim($s['bsc_rpc_url']);
        } elseif (!empty($s['chain_link'])) {
            $this->rpcUrl = trim($s['chain_link']);
        }

        // Presale contract: admin_settings 'presale_contract' > .env PRESALE_CONTRACT
        $this->contractAddress = (string) ($presaleContracts[$this->chainId]
            ?? config('blockchain.presale_contract', ''));
        if (!empty($s['presale_contract'])) {
            $this->contractAddress = trim($s['presale_contract']);
        }

        // OBX token contract: admin_settings 'contract_address' > .env OBX_TOKEN_CONTRACT
        $this->obxTokenAddress = (string) ($tokenContracts[$this->chainId]
            ?? config('blockchain.obx_token_contract', ''));
        if (!empty($s['contract_address'])) {
            $this->obxTokenAddress = trim($s['contract_address']);
        }

        // BSCScan key: admin_settings 'bscscan_api_key' > .env BSCSCAN_API_KEY
        $this->bscscanKey = !empty($s['bscscan_api_key'])
            ? trim($s['bscscan_api_key'])
            : (string) config('blockchain.bscscan_api_key', '');
    }

    // â”€â”€â”€ Read: active phase â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Get the currently active phase index from the contract (-1 if none).
     */
    public function getActivePhaseIndex(): int
    {
        $result = $this->ethCall(self::SEL_ACTIVE_PHASE);
        if (!$result) return -1;

        $hex = substr($result, 2); // strip 0x prefix ONLY (not ltrim mask)
        if (strlen($hex) !== 64) return -1;

        // int256 two's complement: if high bit set â†’ negative (=-1 means none)
        $value = gmp_intval(gmp_init($hex, 16));
        if (hexdec(substr($hex, 0, 2)) >= 128) {
            // negative: subtract 2^256
            $value = gmp_intval(gmp_sub(gmp_init($hex, 16), gmp_pow('2', 256)));
        }
        return (int) $value;
    }

    /**
     * Get remaining OBX tokens for a phase index.
     */
    public function getRemainingTokens(int $phaseIndex): string
    {
        $data   = self::SEL_REMAINING . str_pad(dechex($phaseIndex), 64, '0', STR_PAD_LEFT);
        $result = $this->ethCall($data);
        if (!$result) return '0';

        $raw = $this->hexToDecimal(substr($result, 2));
        return bcdiv($raw, '1000000000000000000', 18); // 18 decimals â†’ human
    }

    /**
     * Preview: how many OBX tokens usdtAmount (human, e.g. "100") buys in a phase.
     * Returns ['baseObx', 'bonusObx', 'totalObx'] as human-readable strings.
     */
    public function previewPurchase(int $phaseIndex, string $usdtHuman): array
    {
        // Convert human USDT to 6-decimal raw value
        $usdtRaw = bcmul($usdtHuman, '1000000', 0);
        $data    = self::SEL_PREVIEW
            . str_pad(dechex($phaseIndex), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval(gmp_init($usdtRaw, 10), 16), 64, '0', STR_PAD_LEFT);

        $result = $this->ethCall($data);
        if (!$result) return ['baseObx' => '0', 'bonusObx' => '0', 'totalObx' => '0'];

        $hex  = substr($result, 2); // strip 0x
        $base  = bcdiv($this->hexToDecimal(substr($hex,   0, 64)), '1000000000000000000', 18);
        $bonus = bcdiv($this->hexToDecimal(substr($hex,  64, 64)), '1000000000000000000', 18);
        $total = bcdiv($this->hexToDecimal(substr($hex, 128, 64)), '1000000000000000000', 18);

        return ['baseObx' => $base, 'bonusObx' => $bonus, 'totalObx' => $total];
    }

    /**
     * Get total number of phases on the contract.
     */
    public function getTotalPhases(): int
    {
        $result = $this->ethCall(self::SEL_TOTAL_PHASES);
        if (!$result) return 0;
        return (int) $this->hexToDecimal(substr($result, 2));
    }

    /**
     * Get OBX token reserve held by the presale contract.
     */
    public function getObxReserve(): string
    {
        $result = $this->ethCall(self::SEL_OBX_RESERVE);
        if (!$result) return '0';
        $raw = $this->hexToDecimal(substr($result, 2));
        return bcdiv($raw, '1000000000000000000', 18);
    }

    /**
     * Get the on-chain OBX token balance for any address.
     * Calls balanceOf(address) on the OBXToken contract.
     * Returns a human-readable string with 18 decimal places.
     */
    public function getObxBalance(string $address): string
    {
        if (!$this->obxTokenAddress || !str_starts_with($address, '0x')) return '0';

        // balanceOf(address) selector: keccak256("balanceOf(address)") → 0x70a08231
        $paddedAddr = str_pad(ltrim($address, '0x'), 64, '0', STR_PAD_LEFT);
        $data       = '0x70a08231' . $paddedAddr;

        try {
            $response = Http::timeout(10)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'eth_call',
                'params'  => [
                    ['to' => $this->obxTokenAddress, 'data' => $data],
                    'latest',
                ],
                'id' => 1,
            ]);

            $json   = $response->json();
            $result = $json['result'] ?? null;

            if (!$result || $result === '0x') return '0';

            $raw = $this->hexToDecimal(substr($result, 2));
            return bcdiv($raw, '1000000000000000000', 18);

        } catch (\Exception $e) {
            Log::error('BlockchainService::getObxBalance failed for ' . $address . ': ' . $e->getMessage());
            return '0';
        }
    }

    // â”€â”€â”€ Write: phase management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Push a new phase to the contract.
     * Returns ['txHash' => '0x...', 'blockNumber' => N] or null on failure.
     */
    public function pushPhaseToContract(array $phase): ?array
    {
        $payload = [
            'action'          => 'addPhase',
            'contractAddress' => $this->contractAddress,
            'chainId'         => $this->chainId,
            'rpcUrl'          => $this->rpcUrl,
            'params'          => [
                'name'       => $phase['phase_name'],
                'startTime'  => strtotime($phase['start_date']),
                'endTime'    => strtotime($phase['end_date']),
                'rateUsdt'   => $this->usdRateToContractRate((string)($phase['rate'] ?? '0')),
                'tokenCap'   => bcmul((string)($phase['amount'] ?? '0'), '1000000000000000000', 0),
                'bonusBps'   => min((int)(($phase['bonus'] ?? 0) * 100), 5000), // cap at 50%
                'dbPhaseId'  => (int)$phase['id'],
            ],
        ];

        return $this->callSignerScript($payload);
    }

    /**
     * Update an existing phase on-chain.
     */
    public function updatePhaseOnContract(array $phase): ?array
    {
        if ($phase['contract_phase_index'] === null) {
            Log::warning("BlockchainService::updatePhaseOnContract called with null contract_phase_index for phase #{$phase['id']}");
            return null;
        }

        $payload = [
            'action'          => 'updatePhase',
            'contractAddress' => $this->contractAddress,
            'chainId'         => $this->chainId,
            'rpcUrl'          => $this->rpcUrl,
            'params'          => [
                'contractPhaseIndex' => (int)$phase['contract_phase_index'],
                'name'               => $phase['phase_name'],
                'startTime'          => strtotime($phase['start_date']),
                'endTime'            => strtotime($phase['end_date']),
                'rateUsdt'           => $this->usdRateToContractRate((string)($phase['rate'] ?? '0')),
                'tokenCap'           => bcmul((string)($phase['amount'] ?? '0'), '1000000000000000000', 0),
                'bonusBps'           => min((int)(($phase['bonus'] ?? 0) * 100), 5000),
                'active'             => (bool)$phase['active'],
            ],
        ];

        return $this->callSignerScript($payload);
    }

    /**
     * Toggle phase active/inactive on-chain.
     */
    public function setPhaseActiveOnContract(int $contractPhaseIndex, bool $active): ?array
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

    /**
     * Fund the presale contract with OBX tokens (admin wallet â†’ presale contract).
     * Call this after deploying a new phase or replenishing the reserve.
     */
    public function fundPresale(string $obxAmountHuman): ?array
    {
        $amountRaw = bcmul($obxAmountHuman, '1000000000000000000', 0);

        $payload = [
            'action'          => 'fundPresale',
            'contractAddress' => $this->contractAddress,
            'chainId'         => $this->chainId,
            'rpcUrl'          => $this->rpcUrl,
            'params'          => [
                'obxTokenAddress' => $this->obxTokenAddress,
                'amount'          => $amountRaw,
            ],
        ];

        return $this->callSignerScript($payload);
    }

    // â”€â”€â”€ Events: BSCScan polling â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    /**
     * Transfer OBX tokens on-chain from the platform signer wallet to a recipient.
     * Triggers OBXToken.sol's built-in 0.05% deflationary burn on every transfer.
     * The transaction will be visible on BSCScan / block explorer.
     *
     * @param  string $toAddress   Recipient 0x... BSC/EVM address
     * @param  string $amountHuman Human-readable amount (e.g. "100.5")
     * @return array|null  ['txHash' => '0x...', 'blockNumber' => N] or null on failure
     */
    public function transferObxOnChain(string $toAddress, string $amountHuman): ?array
    {
        $settings = [];
        try {
            $settings = allsetting();
        } catch (\Throwable $e) {
            // Fallback to constructor config if settings are unavailable.
        }

        $runtimeRpcUrl = trim((string) ($settings['chain_link'] ?? $this->rpcUrl));
        $runtimeChainId = (int) ($settings['chain_id'] ?? $this->chainId);
        $runtimeTokenAddress = trim((string) ($settings['contract_address'] ?? $this->obxTokenAddress));

        if ($runtimeRpcUrl === '') {
            $runtimeRpcUrl = $this->rpcUrl;
        }

        if ($runtimeChainId <= 0) {
            $runtimeChainId = $this->chainId;
        }

        if ($runtimeTokenAddress === '') {
            Log::error('BlockchainService::transferObxOnChain: contract_address not configured in settings');
            return null;
        }

        // Convert human amount to wei (18 decimals)
        $amountWei = bcmul($amountHuman, '1000000000000000000', 0);

        $payload = [
            'action' => 'transferObx',
            'chainId' => $runtimeChainId,
            'rpcUrl' => $runtimeRpcUrl,
            'params' => [
                'obxTokenAddress' => $runtimeTokenAddress,
                'to'              => $toAddress,
                'amount'          => $amountWei,
            ],
        ];

        return $this->callSignerScript($payload);
    }


    /**
     * Fetch TokensPurchased events from BSCScan API.
     * Uses the hardcoded verified topic0 â€” does NOT use keccak256 at runtime.
     *
     * @param  int $fromBlock  Start block (persisted in admin_settings)
     * @return array  Array of decoded event objects ready for processEvent()
     */
    public function getPurchaseEvents(int $fromBlock = 0): array
    {
        if (!$this->contractAddress || !$this->bscscanKey) {
            Log::warning('BlockchainService::getPurchaseEvents: contract or API key not configured');
            return [];
        }

        // Determine explorer API base by chain
        $apiBase = $this->resolveExplorerApiBase();

        $url = $apiBase . '?' . http_build_query([
            'module'    => 'logs',
            'action'    => 'getLogs',
            'address'   => $this->contractAddress,
            'fromBlock' => $fromBlock,
            'toBlock'   => 'latest',
            'topic0'    => self::TOPIC_TOKENS_PURCHASED,
            'apikey'    => $this->bscscanKey,
        ]);

        try {
            $response = Http::timeout(20)->get($url);
            $data = $response->json();

            if (($data['status'] ?? '0') !== '1') {
                // status=0 with message=No records is not an error
                if (($data['message'] ?? '') !== 'No records found') {
                    Log::warning('BlockchainService::getPurchaseEvents bad status', ['response' => $data]);
                }
                return [];
            }

            $events = [];
            foreach ($data['result'] as $log) {
                try {
                    $events[] = $this->decodeTokensPurchasedLog($log);
                } catch (\Throwable $e) {
                    Log::error('BlockchainService: failed to decode log: ' . $e->getMessage(), ['log' => $log]);
                }
            }
            return $events;

        } catch (\Exception $e) {
            Log::error('BlockchainService::getPurchaseEvents failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Verify a transaction receipt from the RPC â€” used to cross-check webhook payloads.
     * Returns the decoded TokensPurchased data from the receipt, or null if not found/confirmed.
     */
    public function verifyPurchaseTransaction(string $txHash): ?array
    {
        if (!$txHash || !str_starts_with($txHash, '0x')) return null;

        try {
            $response = Http::timeout(10)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'eth_getTransactionReceipt',
                'params'  => [$txHash],
                'id'      => 1,
            ]);

            $json    = $response->json();
            $receipt = $json['result'] ?? null;

            if (!$receipt || ($receipt['status'] ?? '0x0') !== '0x1') {
                return null; // tx failed or not found
            }

            // Find the TokensPurchased log in the receipt
            foreach ($receipt['logs'] ?? [] as $log) {
                if (
                    isset($log['topics'][0]) &&
                    strtolower($log['topics'][0]) === strtolower(self::TOPIC_TOKENS_PURCHASED)
                ) {
                    $decoded = $this->decodeTokensPurchasedLog($log);
                    $decoded['block_number'] = hexdec($receipt['blockNumber']);
                    return $decoded;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('BlockchainService::verifyPurchaseTransaction failed for ' . $txHash . ': ' . $e->getMessage());
            return null;
        }
    }

    // â”€â”€â”€ Internal helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function ethCall(string $data): ?string
    {
        if (!$this->contractAddress) return null;

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
            $result = $json['result'] ?? null;

            // eth_call returns '0x' for reverts
            if (!$result || $result === '0x') return null;
            return $result;

        } catch (\Exception $e) {
            Log::error('BlockchainService::ethCall failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Call the Node.js signer script (contracts/signer.js).
     * Payload is sent via stdin; result JSON is read from stdout.
     * Private key is passed via environment â€” never appears in logs.
     *
     * @return array|null  ['txHash' => '0x...', 'blockNumber' => N] or null
     */
    private function callSignerScript(array $payload): ?array
    {
        $signerPath = base_path('contracts/signer.js');
        if (!file_exists($signerPath)) {
            Log::error('BlockchainService: signer.js not found at ' . $signerPath);
            return null;
        }

        $json    = json_encode($payload, JSON_THROW_ON_ERROR);

        // Owner private key: admin_settings 'owner_private_key' > .env OWNER_PRIVATE_KEY
        $s = [];
        try { $s = allsetting(); } catch (\Throwable $e) {}
        $privKey = !empty($s['owner_private_key'])
            ? trim($s['owner_private_key'])
            : config('blockchain.owner_private_key', '');

        if (!$privKey) {
            Log::error('BlockchainService: OWNER_PRIVATE_KEY not configured');
            return null;
        }

        $nodeBinary = config('blockchain.node_binary', 'node');
        $cmd = escapeshellarg($nodeBinary) . ' ' . escapeshellarg($signerPath);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Pass private key via env only — do NOT include in the JSON payload
        $env  = array_merge($_ENV, ['OWNER_PRIVATE_KEY' => $privKey]);
        $signerKey = config('blockchain.signer_private_key', '');
        if ($signerKey) {
            $env['SIGNER_PRIVATE_KEY'] = $signerKey;
        }

        // Propagate admin-settings contract addresses so signer.js can resolve them
        if (!empty($s['presale_contract']))  $env['PRESALE_CONTRACT']     = $s['presale_contract'];
        if (!empty($s['contract_address'])) $env['OBX_TOKEN_CONTRACT']   = $s['contract_address'];
        if (!empty($s['airdrop_contract']))  $env['AIRDROP_CONTRACT']     = $s['airdrop_contract'];
        if (!empty($s['staking_contract']))  $env['STAKING_CONTRACT']     = $s['staking_contract'];
        if (!empty($s['bsc_rpc_url']))        $env['BSC_RPC_URL']          = $s['bsc_rpc_url'];
        if (!empty($s['presale_chain_id']))   $env['PRESALE_CHAIN_ID']     = (string) $s['presale_chain_id'];
        $proc = proc_open($cmd, $descriptors, $pipes, base_path(), $env);

        if (!is_resource($proc)) {
            Log::error('BlockchainService: could not open signer process');
            return null;
        }

        fwrite($pipes[0], $json);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($stderr) {
            Log::warning('BlockchainService signer stderr: ' . $stderr);
        }

        $result = json_decode($stdout, true);

        if (isset($result['error'])) {
            Log::error('BlockchainService signer error: ' . $result['error']);
            return null;
        }

        if (!isset($result['txHash'])) {
            Log::error('BlockchainService: signer returned no txHash. stdout=' . $stdout);
            return null;
        }

        return [
            'txHash'      => $result['txHash'],
            'blockNumber' => $result['blockNumber'] ?? null,
        ];
    }

    /**
     * Convert USD price per OBX (as exact decimal string) to contract rateUsdt.
     * rateUsdt = (OBX per 1 USDT) * 1e18
     *
     * Example: rate = "0.01" (1 OBX costs $0.01)
     *   â†’ OBX per USDT = 1 / 0.01 = 100
     *   â†’ rateUsdt     = 100 * 1e18 = "100000000000000000000"
     *
     * Uses bcmath throughout â€” no float rounding.
     */
    public function usdRateToContractRate(string $usdPerObx): string
    {
        if (bccomp($usdPerObx, '0', 18) <= 0) return '0';

        // Scale numerator to 36 decimals to preserve precision through division
        // 1e36 / usdPerObx_scaled_1e18 = (1/usdPerObx) * 1e18
        // Simpler: shift usdPerObx to integer, divide into 1e(18+decimals)
        // Method: rateUsdt = bcdiv("1" * 1e18, usdPerObx, 0)
        //   where usdPerObx is treated as a decimal (e.g., "0.01")
        //   bcdiv("1000000000000000000", "0.01", 0) = "100000000000000000000"
        return bcdiv('1000000000000000000', $usdPerObx, 0);
    }

    /**
     * Convert a hex string (with or without 0x prefix) to a decimal string via GMP.
     * Handles 32-byte (64 hex char) values correctly â€” no float overflow.
     */
    public function hexToDecimal(string $hex): string
    {
        if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X')) {
            $hex = substr($hex, 2); // strip prefix with substr, NOT ltrim
        }
        if ($hex === '') return '0';
        return gmp_strval(gmp_init($hex, 16), 10);
    }

    /**
     * Decode a raw BSCScan/RPC log entry for the TokensPurchased event.
     *
     * Log structure:
     *   topics[0] = TOPIC_TOKENS_PURCHASED (event sig hash)
     *   topics[1] = buyer address (indexed, padded to 32 bytes)
     *   topics[2] = contractPhaseIndex (indexed, uint256, 32 bytes)
     *   topics[3] = dbPhaseId (indexed, uint256, 32 bytes)
     *   data      = abi.encode(usdtAmount, obxAllocated, bonusObx, timestamp)
     */
    public function decodeTokensPurchasedLog(array $log): array
    {
        if (count($log['topics'] ?? []) < 4) {
            throw new \InvalidArgumentException('TokensPurchased log requires 4 topics');
        }

        // topics[1]: last 40 hex chars (20 bytes) = buyer address
        $buyer = '0x' . substr($log['topics'][1], -40);

        // topics[2] and [3]: strip 0x prefix with substr, NOT ltrim mask
        $contractPhaseIndex = (int) $this->hexToDecimal(substr($log['topics'][2], 2));
        $dbPhaseId          = (int) $this->hexToDecimal(substr($log['topics'][3], 2));

        // data: strip '0x' prefix with substr (NOT ltrim which strips all 0s and xs)
        $rawData = $log['data'] ?? '0x';
        $data = substr($rawData, 2); // remove the '0x' prefix only

        // Each ABI-encoded word is 32 bytes = 64 hex chars
        $usdtAmountRaw  = $this->hexToDecimal(substr($data,   0, 64));
        $obxAllocatedRaw= $this->hexToDecimal(substr($data,  64, 64));
        $bonusObxRaw    = $this->hexToDecimal(substr($data, 128, 64));
        $timestamp      = $this->hexToDecimal(substr($data, 192, 64));

        return [
            'tx_hash'              => $log['transactionHash'],
            'block_number'         => hexdec(ltrim($log['blockNumber'] ?? '0x0', '0x') ?: '0'),
            'buyer'                => strtolower($buyer),
            'contract_phase_index' => $contractPhaseIndex,
            'db_phase_id'          => $dbPhaseId,
            // Convert raw token amounts to human-readable strings
            'usdt_amount'          => bcdiv($usdtAmountRaw,   '1000000',              6),  // USDT 6 dec
            'obx_allocated'        => bcdiv($obxAllocatedRaw, '1000000000000000000',  18), // OBX  18 dec
            'bonus_obx'            => bcdiv($bonusObxRaw,     '1000000000000000000',  18),
            'timestamp'            => $timestamp,
        ];
    }

    /**
     * Resolve the block explorer API base URL by chain ID.
     * Centralises multi-chain support.
     */
    private function resolveExplorerApiBase(): string
    {
        return match ($this->chainId) {
            56      => 'https://api.bscscan.com/api',
            97      => 'https://api-testnet.bscscan.com/api',
            1       => 'https://api.etherscan.io/api',
            137     => 'https://api.polygonscan.com/api',
            43114   => 'https://api.snowtrace.io/api',
            42161   => 'https://api.arbiscan.io/api',
            10      => 'https://api-optimistic.etherscan.io/api',
            default => 'https://api.bscscan.com/api',
        };
    }
}

