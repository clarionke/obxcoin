/**
 * contracts/signer.js  v3
 *
 * Signs and broadcasts EVM transactions for OBXPresale admin operations.
 * Called by BlockchainService::callSignerScript() with JSON payload via stdin.
 *
 * Requires: ethers v5  (npm install in contracts/ directory)
 *
 * Environment variable (passed by BlockchainService — NEVER log it):
 *   OWNER_PRIVATE_KEY  — admin BSC wallet private key
 *
 * Usage via pipe:
 *   echo '{"action":"addPhase",...}' | node contracts/signer.js
 *
 * Actions:
 *   ── Presale write ──────────────────────────────────────────────────────
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
 *   ── OBXToken write ─────────────────────────────────────────────────────
 *   fundPresale        OBXToken.transfer(presaleContract, amount)
 *   setFeeExempt       OBXToken.setFeeExempt(address, bool)
 *   ── Utility (no RPC needed) ─────────────────────────────────────────────
 *   generateWallet     Generate a random Ethereum-compatible wallet
 *   computeTopic0      keccak256("EventName(types)")
 *   computeSelector    First 4 bytes of keccak256("funcName(types)")
 */

'use strict';

const { ethers } = require('ethers');

// ─── ABIs ────────────────────────────────────────────────────────────────────

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

// ─── Entry point ─────────────────────────────────────────────────────────────

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

    // ── Wallet generation — no RPC or private key needed ─────────────────────
    if (payload.action === 'generateWallet') {
        const w = ethers.Wallet.createRandom();
        out({
            address:    w.address.toLowerCase(),
            privateKey: w.privateKey,   // caller must encrypt immediately and never log
            success:    true,
        });
        return;
    }

    // ── Utility — keccak helpers (no wallet needed) ───────────────────────────
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

    // ── All write actions require private key + RPC ──────────────────────────
    const privateKey = process.env.OWNER_PRIVATE_KEY;
    if (!privateKey) {
        out({ error: 'OWNER_PRIVATE_KEY not set in environment' });
        process.exit(1);
    }

    if (!payload.rpcUrl || !payload.contractAddress) {
        out({ error: 'rpcUrl and contractAddress are required for write actions' });
        process.exit(1);
    }

    const provider = new ethers.providers.JsonRpcProvider(payload.rpcUrl);
    const wallet   = new ethers.Wallet(privateKey, provider);
    const contract = new ethers.Contract(payload.contractAddress, PRESALE_ABI, wallet);
    const p        = payload.params || {};

    let tx;

    try {
        switch (payload.action) {

            // ── Presale phase management ──────────────────────────────────────

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

            // ── Liquidity management ──────────────────────────────────────────

            case 'flushLiquidity':
                tx = await contract.flushLiquidity();
                break;

            case 'setRouter':
                tx = await contract.setRouter(p.router);
                break;

            case 'setLiquidityBps':
                tx = await contract.setLiquidityBps(p.bps);
                break;

            case 'setLiquidityThreshold':
                tx = await contract.setLiquidityThreshold(
                    ethers.BigNumber.from(p.threshold)
                );
                break;

            // ── Config ────────────────────────────────────────────────────────

            case 'setPaused':
                tx = await contract.setPaused(p.paused);
                break;

            case 'setTreasury':
                tx = await contract.setTreasury(p.treasury);
                break;

            case 'withdrawUnsoldObx':
                tx = await contract.withdrawUnsoldObx(
                    p.to,
                    ethers.BigNumber.from(p.amount)
                );
                break;

            case 'acceptOwnership':
                tx = await contract.acceptOwnership();
                break;

            case 'transferOwnership':
                tx = await contract.transferOwnership(p.newOwner);
                break;

            // ── OBXToken operations ───────────────────────────────────────────

            case 'fundPresale': {
                const obxContract = new ethers.Contract(p.obxTokenAddress, OBX_TOKEN_ABI, wallet);
                tx = await obxContract.transfer(
                    payload.contractAddress,
                    ethers.BigNumber.from(p.amount)
                );
                break;
            }

            case 'setFeeExempt': {
                const obxContract = new ethers.Contract(p.obxTokenAddress, OBX_TOKEN_ABI, wallet);
                tx = await obxContract.setFeeExempt(p.account, p.exempt);
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

 *
 * Signs and broadcasts EVM transactions for OBXPresale admin operations.
 * Called by BlockchainService::callSignerScript() with JSON payload via stdin.
 *
 * Requires: ethers v5  (npm install in contracts/ directory)
 *
 * Environment variable (passed by BlockchainService, never logged):
 *   OWNER_PRIVATE_KEY  — admin BSC wallet private key
 *
 * Usage via pipe:
 *   echo '{"action":"addPhase",...}' | node contracts/signer.js
 *
 * Actions supported:
 *   addPhase           — OBXPresale.addPhase(...)
 *   updatePhase        — OBXPresale.updatePhase(...)
 *   setPhaseActive     — OBXPresale.setPhaseActive(index, bool)
 *   withdrawUnsoldObx  — OBXPresale.withdrawUnsoldObx(to, amount)
 *   acceptOwnership    — OBXPresale.acceptOwnership() (new owner claims)
 *   setPaused          — OBXPresale.setPaused(bool)
 *   fundPresale        — OBXToken.transfer(presaleContract, amount) from admin
 *   computeTopic0      — Returns keccak256 of an event sig (no RPC needed)
 *   computeSelector    — Returns 4-byte function selector (no RPC needed)
 */

'use strict';

const { ethers } = require('ethers');

// Full human-readable ABI for OBXPresale v2
const PRESALE_ABI = [
    'function addPhase(string name, uint256 startTime, uint256 endTime, uint256 rateUsdt, uint256 tokenCap, uint256 bonusBps, uint256 dbPhaseId)',
    'function updatePhase(uint256 contractPhaseIndex, string name, uint256 startTime, uint256 endTime, uint256 rateUsdt, uint256 tokenCap, uint256 bonusBps, bool active)',
    'function setPhaseActive(uint256 contractPhaseIndex, bool active)',
    'function setPaused(bool paused)',
    'function setTreasury(address treasury)',
    'function setUsdtAddress(address usdt)',
    'function setObxTokenAddress(address obxToken)',
    'function transferOwnership(address newOwner)',
    'function acceptOwnership()',
    'function withdrawUnsoldObx(address to, uint256 amount)',
    'function recoverToken(address token, uint256 amount)',
    'function totalPhases() view returns (uint256)',
    'function activePhaseIndex() view returns (int256)',
    'function remainingTokens(uint256 index) view returns (uint256)',
    'function obxReserve() view returns (uint256)',
    'function previewPurchase(uint256 contractPhaseIndex, uint256 usdtAmount) view returns (uint256 baseObx, uint256 bonusObx, uint256 totalObx)',
    'event TokensPurchased(address indexed buyer, uint256 indexed contractPhaseIndex, uint256 indexed dbPhaseId, uint256 usdtAmount, uint256 obxAllocated, uint256 bonusObx, uint256 timestamp)',
    'event PhaseAdded(uint256 indexed contractPhaseIndex, uint256 indexed dbPhaseId, string name, uint256 rateUsdt, uint256 tokenCap, uint256 startTime, uint256 endTime)',
    'event PhaseUpdated(uint256 indexed contractPhaseIndex, uint256 indexed dbPhaseId, uint256 rateUsdt, uint256 tokenCap, uint256 startTime, uint256 endTime, bool active)',
];

const ERC20_ABI = [
    'function transfer(address to, uint256 amount) returns (bool)',
    'function approve(address spender, uint256 amount) returns (bool)',
    'function balanceOf(address account) view returns (uint256)',
    'function allowance(address owner, address spender) view returns (uint256)',
];

async function main() {
    let raw = '';
    for await (const chunk of process.stdin) raw += chunk;

    let payload;
    try {
        payload = JSON.parse(raw);
    } catch (e) {
        process.stdout.write(JSON.stringify({ error: 'Invalid JSON payload: ' + e.message }));
        process.exit(1);
    }

    // ── Utility actions (no wallet/RPC needed) ───────────────────────────────
    if (payload.action === 'computeTopic0') {
        try {
            const iface = new ethers.utils.Interface(PRESALE_ABI);
            const eventFrag = iface.getEvent(payload.eventName);
            const topic0 = ethers.utils.id(eventFrag.format('sighash'));
            process.stdout.write(JSON.stringify({ topic0, success: true }));
        } catch (e) {
            process.stdout.write(JSON.stringify({ error: e.message }));
            process.exit(1);
        }
        return;
    }

    if (payload.action === 'computeSelector') {
        try {
            const iface = new ethers.utils.Interface(PRESALE_ABI);
            const funcFrag = iface.getFunction(payload.functionName);
            const selector = ethers.utils.id(funcFrag.format('sighash')).slice(0, 10);
            process.stdout.write(JSON.stringify({ selector, success: true }));
        } catch (e) {
            process.stdout.write(JSON.stringify({ error: e.message }));
            process.exit(1);
        }
        return;
    }

    // ── All write actions require RPC + private key ──────────────────────────
    const privateKey = process.env.OWNER_PRIVATE_KEY;
    if (!privateKey) {
        process.stdout.write(JSON.stringify({ error: 'OWNER_PRIVATE_KEY not set' }));
        process.exit(1);
    }

    if (!payload.rpcUrl || !payload.contractAddress) {
        process.stdout.write(JSON.stringify({ error: 'rpcUrl and contractAddress required' }));
        process.exit(1);
    }

    const provider = new ethers.providers.JsonRpcProvider(payload.rpcUrl);
    const wallet   = new ethers.Wallet(privateKey, provider);
    const contract = new ethers.Contract(payload.contractAddress, PRESALE_ABI, wallet);

    let tx;
    const p = payload.params || {};

    try {
        switch (payload.action) {

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

            case 'setPaused':
                tx = await contract.setPaused(p.paused);
                break;

            case 'setTreasury':
                tx = await contract.setTreasury(p.treasury);
                break;

            case 'withdrawUnsoldObx':
                tx = await contract.withdrawUnsoldObx(
                    p.to,
                    ethers.BigNumber.from(p.amount)
                );
                break;

            case 'acceptOwnership':
                tx = await contract.acceptOwnership();
                break;

            case 'transferOwnership':
                tx = await contract.transferOwnership(p.newOwner);
                break;

            // Fund presale: transfer OBX from admin wallet → presale contract
            case 'fundPresale': {
                const obxContract = new ethers.Contract(p.obxTokenAddress, ERC20_ABI, wallet);
                tx = await obxContract.transfer(
                    payload.contractAddress,
                    ethers.BigNumber.from(p.amount)
                );
                break;
            }

            default:
                process.stdout.write(JSON.stringify({ error: 'Unknown action: ' + payload.action }));
                process.exit(1);
        }

        const receipt = await tx.wait(1);

        process.stdout.write(JSON.stringify({
            txHash:      tx.hash,
            blockNumber: receipt.blockNumber,
            gasUsed:     receipt.gasUsed.toString(),
            success:     true,
        }));

    } catch (e) {
        const msg = e.reason || e.error?.message || e.message;
        process.stdout.write(JSON.stringify({ error: msg }));
        process.exit(1);
    }
}

main().catch(e => {
    process.stdout.write(JSON.stringify({ error: e.message }));
    process.exit(1);
});

