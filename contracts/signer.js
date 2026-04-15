/**
 * contracts/signer.js  v3
 *
 * Signs and broadcasts EVM transactions for OBXPresale admin operations.
 * Called by BlockchainService::callSignerScript() with JSON payload via stdin.
 *
 * Requires: ethers v5  (npm install in contracts/ directory)
 *
 * Environment variable (passed by BlockchainService â€” NEVER log it):
 *   OWNER_PRIVATE_KEY  â€” admin BSC wallet private key
 *
 * Usage via pipe:
 *   echo '{"action":"addPhase",...}' | node contracts/signer.js
 *
 * Actions:
 *   â”€â”€ Presale write â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 *   addPhase           OBXPresale.addPhase(...)
 *   updatePhase        OBXPresale.updatePhase(...)
 *   setPhaseActive     OBXPresale.setPhaseActive(index, bool)
 *   withdrawUnsoldObx  OBXPresale.withdrawUnsoldObx(to, amount)
 *   flushLiquidity     OBXPresale.flushLiquidity()
 *   setRouter          OBXPresale.setRouter(address)
 *   setLiquidityBps    OBXPresale.setLiquidityBps(bps)
 *   acceptOwnership    OBXPresale.acceptOwnership()
 *   transferOwnership  OBXPresale.transferOwnership(address)
 *   setPaused          OBXPresale.setPaused(bool)
 *   setTreasury        OBXPresale.setTreasury(address)
 *   â”€â”€ OBXToken write â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 *   fundPresale        OBXToken.transfer(presaleContract, amount)
 *   setFeeExempt       OBXToken.setFeeExempt(address, bool)
 *   â”€â”€ Utility (no RPC needed) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 *   generateWallet     Generate a random Ethereum-compatible wallet
 *   computeTopic0      keccak256("EventName(types)")
 *   computeSelector    First 4 bytes of keccak256("funcName(types)")
 */

'use strict';

const { ethers } = require('ethers');

const RPC_URLS = {
    56: process.env.BSC_RPC_URL || 'https://bsc-dataseed.binance.org/',
    97: process.env.BSC_TESTNET_RPC_URL || 'https://data-seed-prebsc-1-s1.bnbchain.org:8545/',
    1: process.env.ETH_RPC_URL || 'https://eth.llamarpc.com',
    137: process.env.POLYGON_RPC_URL || 'https://polygon-rpc.com',
};

const PRESALE_CONTRACTS = {
    56: process.env.PRESALE_CONTRACT_BSC || process.env.PRESALE_CONTRACT || '',
    97: process.env.PRESALE_CONTRACT_BSC_TEST || process.env.PRESALE_CONTRACT || '',
    1: process.env.PRESALE_CONTRACT_ETH || process.env.PRESALE_CONTRACT || '',
    137: process.env.PRESALE_CONTRACT_POLYGON || process.env.PRESALE_CONTRACT || '',
};

const OBX_TOKEN_CONTRACTS = {
    56: process.env.OBX_TOKEN_BSC || process.env.OBX_TOKEN_CONTRACT || '',
    97: process.env.OBX_TOKEN_BSC_TEST || process.env.OBX_TOKEN_CONTRACT || '',
    1: process.env.OBX_TOKEN_ETH || process.env.OBX_TOKEN_CONTRACT || '',
    137: process.env.OBX_TOKEN_POLYGON || process.env.OBX_TOKEN_CONTRACT || '',
};

// â”€â”€â”€ ABIs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const PRESALE_ABI = [
    // Phase management
    'function addPhase(string name, uint256 startTime, uint256 endTime, uint256 rateUsdt, uint256 tokenCap, uint256 bonusBps, uint256 dbPhaseId)',
    'function updatePhase(uint256 contractPhaseIndex, string name, uint256 startTime, uint256 endTime, uint256 rateUsdt, uint256 tokenCap, uint256 bonusBps, bool active)',
    'function setPhaseActive(uint256 contractPhaseIndex, bool active)',
    // Liquidity
    'function flushLiquidity()',
    'function setRouter(address router)',
    'function setLiquidityBps(uint256 bps)',
    'function setLiquidityThreshold(uint256 threshold)',
    // Config
    'function setPaused(bool paused)',
    'function setTreasury(address treasury)',
    'function setUsdtAddress(address usdt)',
    'function setObxTokenAddress(address obxToken)',
    'function transferOwnership(address newOwner)',
    'function acceptOwnership()',
    'function withdrawUnsoldObx(address to, uint256 amount)',
    'function recoverToken(address token, uint256 amount)',
    // Views
    'function totalPhases() view returns (uint256)',
    'function activePhaseIndex() view returns (int256)',
    'function remainingTokens(uint256 index) view returns (uint256)',
    'function obxReserve() view returns (uint256)',
    'function liquidityReserveUsdt() view returns (uint256)',
    'function liquidityReserveObx() view returns (uint256)',
    'function previewPurchase(uint256 contractPhaseIndex, uint256 usdtAmount) view returns (uint256 baseObx, uint256 bonusObx, uint256 totalObx)',
    // Events
    'event TokensPurchased(address indexed buyer, uint256 indexed contractPhaseIndex, uint256 indexed dbPhaseId, uint256 usdtAmount, uint256 obxAllocated, uint256 bonusObx, uint256 timestamp)',
    'event PhaseAdded(uint256 indexed contractPhaseIndex, uint256 indexed dbPhaseId, string name, uint256 rateUsdt, uint256 tokenCap, uint256 startTime, uint256 endTime)',
    'event PhaseUpdated(uint256 indexed contractPhaseIndex, uint256 indexed dbPhaseId, uint256 rateUsdt, uint256 tokenCap, uint256 startTime, uint256 endTime, bool active)',
    'event LiquidityAdded(uint256 obxAmount, uint256 usdtAmount, uint256 lpTokens, address indexed pair)',
    'event LiquidityAddFailed(uint256 reserveUsdt, uint256 reserveObx)',
];

const OBX_TOKEN_ABI = [
    'function transfer(address to, uint256 amount) returns (bool)',
    'function approve(address spender, uint256 amount) returns (bool)',
    'function balanceOf(address account) view returns (uint256)',
    'function allowance(address owner, address spender) view returns (uint256)',
    'function setFeeExempt(address account, bool exempt)',
    'function totalSupply() view returns (uint256)',
    'function feeExempt(address account) view returns (bool)',
    'event Burn(address indexed from, uint256 burnAmount, uint256 newTotalSupply)',
    'event FeeExemptUpdated(address indexed account, bool exempt)',
];

function normalizePrivateKey(privateKey) {
    if (!privateKey) return '';
    return privateKey.startsWith('0x') ? privateKey : `0x${privateKey}`;
}

function normalizeAddress(address) {
    return ethers.utils.getAddress(String(address).trim());
}

function resolveRpcUrl(payload) {
    if (payload.rpcUrl) {
        return String(payload.rpcUrl).trim();
    }

    const chainId = Number(payload.chainId || process.env.PRESALE_CHAIN_ID || 56);
    return RPC_URLS[chainId] || RPC_URLS[56];
}

function resolveChainId(payload) {
    return Number(payload.chainId || process.env.PRESALE_CHAIN_ID || 56);
}

function resolveChainName(chainId) {
    switch (Number(chainId)) {
        case 56:
            return 'bnb';
        case 97:
            return 'bnbt';
        case 1:
            return 'homestead';
        case 137:
            return 'matic';
        default:
            return 'unknown';
    }
}

function createProvider(rpcUrl, chainId) {
    return new ethers.providers.StaticJsonRpcProvider(rpcUrl, {
        chainId: Number(chainId),
        name: resolveChainName(chainId),
    });
}

async function resolveWorkingProvider(payload, chainId) {
    const rpcUrl = resolveRpcUrl(payload);
    if (!rpcUrl) {
        throw new Error('RPC unavailable: no rpcUrl provided');
    }

    const provider = createProvider(rpcUrl, chainId);
    await provider.getBlockNumber();
    return { provider, rpcUrl };
}

function inferContractType(action) {
    switch (action) {
        case 'fundPresale':
        case 'setFeeExempt':
        case 'transferObx':
            return 'token';
        default:
            return 'presale';
    }
}

function resolveContractAddress(payload) {
    if (payload.contractAddress) {
        return normalizeAddress(payload.contractAddress);
    }

    const chainId = Number(payload.chainId || process.env.PRESALE_CHAIN_ID || 56);
    const contractType = payload.contractType || inferContractType(payload.action);

    if (contractType === 'token') {
        const tokenAddress = OBX_TOKEN_CONTRACTS[chainId];
        return tokenAddress ? normalizeAddress(tokenAddress) : '';
    }

    const presaleAddress = PRESALE_CONTRACTS[chainId];
    return presaleAddress ? normalizeAddress(presaleAddress) : '';
}

// â”€â”€â”€ Entry point â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

async function main() {
    let raw = '';
    for await (const chunk of process.stdin) raw += chunk;

    let payload;
    try {
        payload = JSON.parse(raw);
    } catch (e) {
        out({ error: 'Invalid JSON payload: ' + e.message });
        process.exit(1);
    }

    // â”€â”€ Wallet generation â€” no RPC or private key needed â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if (payload.action === 'generateWallet') {
        const w = ethers.Wallet.createRandom();
        out({
            address:    w.address.toLowerCase(),
            privateKey: w.privateKey,   // caller must encrypt immediately and never log
            success:    true,
        });
        return;
    }

    // -- User wallet transfer: uses SIGNER_PRIVATE_KEY, not OWNER_PRIVATE_KEY --
    if (payload.action === 'transferObx') {
        const signerKey = normalizePrivateKey(process.env.SIGNER_PRIVATE_KEY);
        if (!signerKey) { out({ error: 'SIGNER_PRIVATE_KEY not set for transferObx' }); process.exit(1); }
        const p = payload.params || {};
        const chainId = resolveChainId(payload);
        const obxTokenAddress = p.obxTokenAddress ? normalizeAddress(p.obxTokenAddress) : resolveContractAddress({ ...payload, contractType: 'token' });

        if (!obxTokenAddress || !p.to || !p.amount) {
            out({ error: 'transferObx requires rpcUrl, token address, params.to, params.amount' });
            process.exit(1);
        }
        try {
            const { provider, rpcUrl } = await resolveWorkingProvider(payload, chainId);
            const userWallet = new ethers.Wallet(signerKey, provider);
            const obxToken   = new ethers.Contract(obxTokenAddress, OBX_TOKEN_ABI, userWallet);
            const tx         = await obxToken.transfer(normalizeAddress(p.to), ethers.BigNumber.from(p.amount));
            const receipt    = await tx.wait(1);
            out({ txHash: tx.hash, blockNumber: receipt.blockNumber, gasUsed: receipt.gasUsed.toString(), rpcUrl, success: true });
        } catch (e) {
            out({ error: e.reason || (e.error && e.error.message) || e.message });
            process.exit(1);
        }
        return;
    }


    // â”€â”€ Utility â€” keccak helpers (no wallet needed) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if (payload.action === 'computeTopic0') {
        try {
            const iface  = new ethers.utils.Interface(PRESALE_ABI);
            const frag   = iface.getEvent(payload.eventName);
            out({ topic0: ethers.utils.id(frag.format('sighash')), success: true });
        } catch (e) { out({ error: e.message }); process.exit(1); }
        return;
    }

    if (payload.action === 'computeSelector') {
        try {
            const iface  = new ethers.utils.Interface(PRESALE_ABI);
            const frag   = iface.getFunction(payload.functionName);
            out({ selector: ethers.utils.id(frag.format('sighash')).slice(0, 10), success: true });
        } catch (e) { out({ error: e.message }); process.exit(1); }
        return;
    }

    // â”€â”€ All write actions require private key + RPC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const privateKey = normalizePrivateKey(process.env.OWNER_PRIVATE_KEY);
    if (!privateKey) {
        out({ error: 'OWNER_PRIVATE_KEY not set in environment' });
        process.exit(1);
    }

    const chainId = resolveChainId(payload);
    const contractAddress = resolveContractAddress(payload);

    if (!contractAddress) {
        out({ error: 'Could not resolve rpcUrl or contractAddress for write action' });
        process.exit(1);
    }

    const { provider, rpcUrl } = await resolveWorkingProvider(payload, chainId);
    const wallet   = new ethers.Wallet(privateKey, provider);
    const contract = new ethers.Contract(contractAddress, PRESALE_ABI, wallet);
    const p        = payload.params || {};

    let tx;

    try {
        switch (payload.action) {

            // â”€â”€ Presale phase management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

            case 'addPhase':
                tx = await contract.addPhase(
                    p.name,
                    p.startTime,
                    p.endTime,
                    ethers.BigNumber.from(p.rateUsdt),
                    ethers.BigNumber.from(p.tokenCap),
                    p.bonusBps,
                    p.dbPhaseId
                );
                break;

            case 'updatePhase':
                tx = await contract.updatePhase(
                    p.contractPhaseIndex,
                    p.name,
                    p.startTime,
                    p.endTime,
                    ethers.BigNumber.from(p.rateUsdt),
                    ethers.BigNumber.from(p.tokenCap),
                    p.bonusBps,
                    p.active
                );
                break;

            case 'setPhaseActive':
                tx = await contract.setPhaseActive(p.contractPhaseIndex, p.active);
                break;

            // â”€â”€ Liquidity management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

            case 'flushLiquidity':
                tx = await contract.flushLiquidity();
                break;

            case 'setRouter':
                tx = await contract.setRouter(normalizeAddress(p.router));
                break;

            case 'setLiquidityBps':
                tx = await contract.setLiquidityBps(p.bps);
                break;

            case 'setLiquidityThreshold':
                tx = await contract.setLiquidityThreshold(
                    ethers.BigNumber.from(p.threshold)
                );
                break;

            // â”€â”€ Config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

            case 'setPaused':
                tx = await contract.setPaused(p.paused);
                break;

            case 'setTreasury':
                tx = await contract.setTreasury(normalizeAddress(p.treasury));
                break;

            case 'withdrawUnsoldObx':
                tx = await contract.withdrawUnsoldObx(
                    normalizeAddress(p.to),
                    ethers.BigNumber.from(p.amount)
                );
                break;

            case 'acceptOwnership':
                tx = await contract.acceptOwnership();
                break;

            case 'transferOwnership':
                tx = await contract.transferOwnership(normalizeAddress(p.newOwner));
                break;

            // â”€â”€ OBXToken operations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

            case 'fundPresale': {
                const obxTokenAddress = p.obxTokenAddress ? normalizeAddress(p.obxTokenAddress) : resolveContractAddress({ ...payload, contractType: 'token' });
                const obxContract = new ethers.Contract(obxTokenAddress, OBX_TOKEN_ABI, wallet);
                tx = await obxContract.transfer(
                    contractAddress,
                    ethers.BigNumber.from(p.amount)
                );
                break;
            }

            case 'setFeeExempt': {
                const obxTokenAddress = p.obxTokenAddress ? normalizeAddress(p.obxTokenAddress) : resolveContractAddress({ ...payload, contractType: 'token' });
                const obxContract = new ethers.Contract(obxTokenAddress, OBX_TOKEN_ABI, wallet);
                tx = await obxContract.setFeeExempt(normalizeAddress(p.account), p.exempt);
                break;
            }

            default:
                out({ error: 'Unknown action: ' + payload.action });
                process.exit(1);
        }

        const receipt = await tx.wait(1);

        out({
            txHash:      tx.hash,
            blockNumber: receipt.blockNumber,
            gasUsed:     receipt.gasUsed.toString(),
            rpcUrl,
            success:     true,
        });

    } catch (e) {
        const msg = e.reason || e.error?.message || e.message;
        out({ error: msg });
        process.exit(1);
    }
}

function out(obj) {
    process.stdout.write(JSON.stringify(obj));
}

main().catch(e => {
    out({ error: e.message });
    process.exit(1);
});
