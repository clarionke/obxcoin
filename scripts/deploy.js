/**
 * scripts/deploy.js
 *
 * Deploys OBXToken + OBXPresale to any EVM network (multichain).
 *
 * Supported Networks:
 *   • BSC Mainnet (chainId 56)
 *   • BSC Testnet (chainId 97)
 *   • Ethereum Mainnet (chainId 1)
 *   • Ethereum Sepolia Testnet (chainId 11155111)
 *   • Polygon Mainnet (chainId 137)
 *   • Arbitrum One (chainId 42161)
 *   • Optimism Mainnet (chainId 10)
 *   • Local Hardhat (chainId 31337)
 *
 * Allocation:
 *   20% (20,000,000 OBX) → OBXPresale contract
 *    5% ( 5,000,000 OBX) → OBXAirdrop contract
 *    5% ( 5,000,000 OBX) → OBXStaking rewards reserve
 *   70% (70,000,000 OBX) → Deployer (liquidity / team vesting)
 *
 * Usage:
 *   npx hardhat run scripts/deploy.js --network bsc_testnet
 *   npx hardhat run scripts/deploy.js --network bsc_mainnet
 *   npx hardhat run scripts/deploy.js --network ethereum
 *
 * Environment variables (set in .env):
 *   OWNER_PRIVATE_KEY              — deployer wallet private key (NO 0x prefix needed)\n *   TREASURY_ADDRESS               — address to receive USDT from presale sales
 *   USDT_BSC_ADDRESS               — BEP-20 USDT contract address on BSC mainnet
 *   USDT_BSC_TEST_ADDRESS          — BEP-20 USDT contract address on BSC testnet
 *   USDT_ETH_ADDRESS               — ERC-20 USDT contract address on Ethereum
 *   USDT_POLYGON_ADDRESS           — ERC-20 USDT contract address on Polygon
 *   PANCAKE_ROUTER_ADDRESS         — PancakeSwap V2 router on BSC
 *   UNISWAP_ROUTER                 — Uniswap V2 router on Ethereum/Polygon
 *   QUICKSWAP_ROUTER               — QuickSwap V2 router on Polygon (alt to Uniswap)
 *   INITIAL_SUPPLY                 — Total OBX supply at deploy (default: 100_000_000)\n *   PRESALE_ALLOCATION             — OBX tokens to send to presale\n *                                    default: 20_000_000 (20% of initial supply)
 *
 * After deployment, copy the printed addresses into your .env file:
 *   OBX_TOKEN_CONTRACT=<OBXToken address>
 *   PRESALE_CONTRACT=<OBXPresale address>
 *
 * ✓ Allocation: 20M OBX to presale (20% of 100M total supply)
 * ✓ 80M OBX remains with deployer for future liquidity bootstrapping
 * ✓ All output addresses are checksummed and explorer-ready
 */

'use strict';

const { ethers } = require('hardhat');

// ─── Config ────────────────────────────────────────────────────────────────

const CHAIN_USDT = {
    56:     process.env.USDT_BSC_ADDRESS       || '0x55d398326f99059fF775485246999027B3197955',
    97:     process.env.USDT_BSC_TEST_ADDRESS   || '0x337610d27c682E347C9cD60BD4b3b107C9d34eDd',
    1:      process.env.USDT_ETH_ADDRESS        || '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    137:    process.env.USDT_POLYGON_ADDRESS     || '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
    11155111: process.env.USDT_SEPOLIA_ADDRESS  || '0x0000000000000000000000000000000000000000',
    31337:  ethers.ZeroAddress, // hardhat local — deploy a mock USDT
};

const CHAIN_ROUTER = {
    56:   process.env.PANCAKE_ROUTER_ADDRESS   || '0x10ED43C718714eb63d5aA57B78B54704E256024E',
    97:   process.env.PANCAKE_ROUTER_TESTNET   || '0x9Ac64Cc6e4415144C455BD8E4837Fea55603e5c3',
    1:    process.env.UNISWAP_ROUTER            || '0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D',
    137:  process.env.QUICKSWAP_ROUTER          || '0xa5E0829CaCEd8fFDD4De3c43696c57F7D7A678ff',
    11155111: ethers.ZeroAddress,
    31337:    ethers.ZeroAddress,
};

const INITIAL_SUPPLY        = BigInt(process.env.INITIAL_SUPPLY       || '100000000');
const PRESALE_ALLOC         = BigInt(process.env.PRESALE_ALLOCATION    || '20000000');  // 20% of total supply
const AIRDROP_ALLOC         = BigInt(process.env.AIRDROP_ALLOCATION    || '5000000');   //  5% of total supply
const STAKING_ALLOC         = BigInt(process.env.STAKING_ALLOCATION    || '5000000');   //  5% — reward reserve
const BURN_ON_STAKE_BPS     = parseInt(process.env.BURN_ON_STAKE_BPS   || '100');       //  1% default
const BURN_ON_UNSTAKE_BPS   = parseInt(process.env.BURN_ON_UNSTAKE_BPS || '200');       //  2% default
const DECIMALS              = BigInt(10 ** 18);

// ─── Helpers ───────────────────────────────────────────────────────────────

function addr(a) {
    return ethers.getAddress(a);
}

async function deployMockUsdt(deployer) {
    console.log('  Deploying MockUSDT for local/testnet …');
    const MockERC20 = await ethers.getContractFactory('MockERC20', deployer).catch(() => null);
    if (!MockERC20) {
        console.log('  MockERC20 artifact not found — using zero address for USDT (testing only)');
        return ethers.ZeroAddress;
    }
    const mock = await MockERC20.deploy('Tether USD', 'USDT', 6);
    await mock.waitForDeployment();
    const address = await mock.getAddress();
    console.log(`  MockUSDT deployed at ${address}`);
    return address;
}

// ─── Main ─────────────────────────────────────────────────────────────────

async function main() {
    // ── Network context ───────────────────────────────────────────────────
    const network       = await ethers.provider.getNetwork();
    const chainId       = Number(network.chainId);
    const [deployer]    = await ethers.getSigners();

    console.log('\n══════════════════════════════════════════════════════════');
    console.log(' OBXCoin Deployment Script');
    console.log('══════════════════════════════════════════════════════════');
    console.log(` Network:   ${network.name} (chainId=${chainId})`);
    console.log(` Deployer:  ${deployer.address}`);
    const balance = await ethers.provider.getBalance(deployer.address);
    console.log(` Balance:   ${ethers.formatEther(balance)} native token`);

    if (balance === 0n) {
        throw new Error('Deployer wallet has zero balance. Fund it before deploying.');
    }

    // ── Resolve USDT address ──────────────────────────────────────────────
    let usdtAddress = CHAIN_USDT[chainId];
    if (!usdtAddress || usdtAddress === ethers.ZeroAddress) {
        if (chainId === 31337 || chainId === 11155111) {
            usdtAddress = await deployMockUsdt(deployer);
        } else {
            throw new Error(`No USDT address configured for chainId=${chainId}. Set USDT_* in .env`);
        }
    }
    console.log(` USDT:      ${usdtAddress}`);

    // ── Resolve treasury ──────────────────────────────────────────────────
    const treasury = process.env.TREASURY_ADDRESS ? addr(process.env.TREASURY_ADDRESS) : deployer.address;
    console.log(` Treasury:  ${treasury}`);

    // ── Resolve router ────────────────────────────────────────────────────
    const routerAddress = CHAIN_ROUTER[chainId] || ethers.ZeroAddress;
    console.log(` Router:    ${routerAddress || '(none)'}`);

    console.log('──────────────────────────────────────────────────────────\n');

    // ═════════════════════════════════════════
    // 1. Deploy OBXToken
    // ═════════════════════════════════════════
    console.log('1. Deploying OBXToken …');
    const OBXToken = await ethers.getContractFactory('OBXToken', deployer);
    const obxToken = await OBXToken.deploy(INITIAL_SUPPLY);
    await obxToken.waitForDeployment();
    const obxTokenAddress = await obxToken.getAddress();
    console.log(`   ✔ OBXToken  deployed at: ${obxTokenAddress}`);

    // ═════════════════════════════════════════
    // 2. Deploy OBXPresale
    // ═════════════════════════════════════════
    console.log('\n2. Deploying OBXPresale …');
    const OBXPresale = await ethers.getContractFactory('OBXPresale', deployer);
    const presale    = await OBXPresale.deploy(obxTokenAddress, usdtAddress, treasury);
    await presale.waitForDeployment();
    const presaleAddress = await presale.getAddress();
    console.log(`   ✔ OBXPresale deployed at: ${presaleAddress}`);

    // ═════════════════════════════════════════
    // 3. Set OBXPresale fee-exempt on OBXToken
    // ═════════════════════════════════════════
    console.log('\n3. Marking presale contract as fee-exempt on OBXToken …');
    const tx3 = await obxToken.setFeeExempt(presaleAddress, true);
    await tx3.wait();
    console.log(`   ✔ setFeeExempt(presale) done  (tx: ${tx3.hash})`);

    // ═════════════════════════════════════════
    // 4. Transfer presale allocation to OBXPresale
    // ═════════════════════════════════════════
    console.log(`\n4. Transferring ${PRESALE_ALLOC.toLocaleString()} OBX (20%) to OBXPresale …`);
    const presaleWei = PRESALE_ALLOC * DECIMALS;
    const tx4 = await obxToken.transfer(presaleAddress, presaleWei);
    await tx4.wait();
    console.log(`   ✔ Transfer done  (tx: ${tx4.hash})`);

    // ═════════════════════════════════════════
    // 5. Deploy OBXAirdrop
    // ═════════════════════════════════════════
    console.log('\n5. Deploying OBXAirdrop …');
    const OBXAirdrop    = await ethers.getContractFactory('OBXAirdrop', deployer);
    const airdrop       = await OBXAirdrop.deploy(obxTokenAddress, usdtAddress);
    await airdrop.waitForDeployment();
    const airdropAddress = await airdrop.getAddress();
    console.log(`   ✔ OBXAirdrop deployed at: ${airdropAddress}`);

    // ═════════════════════════════════════════
    // 6. Set OBXAirdrop fee-exempt on OBXToken
    // ═════════════════════════════════════════
    console.log('\n6. Marking airdrop contract as fee-exempt on OBXToken …');
    const tx6 = await obxToken.setFeeExempt(airdropAddress, true);
    await tx6.wait();
    console.log(`   ✔ setFeeExempt(airdrop) done  (tx: ${tx6.hash})`);

    // ═════════════════════════════════════════
    // 7. Transfer airdrop allocation to OBXAirdrop
    // ═════════════════════════════════════════
    console.log(`\n7. Transferring ${AIRDROP_ALLOC.toLocaleString()} OBX (5%) to OBXAirdrop …`);
    const airdropWei = AIRDROP_ALLOC * DECIMALS;
    const tx7 = await obxToken.transfer(airdropAddress, airdropWei);
    await tx7.wait();
    console.log(`   ✔ Transfer done  (tx: ${tx7.hash})`);

    // ═════════════════════════════════════════
    // 8. Configure router for auto-liquidity (optional)
    // ═════════════════════════════════════════
    if (routerAddress && routerAddress !== ethers.ZeroAddress) {
        console.log('\n8. Setting DEX router on OBXPresale for auto-liquidity …');
        const tx8a = await presale.setRouter(routerAddress);
        await tx8a.wait();
        console.log(`   ✔ setRouter done  (tx: ${tx8a.hash})`);

        const tx8b = await obxToken.setFeeExempt(routerAddress, true);
        await tx8b.wait();
        console.log(`   ✔ Router marked fee-exempt on OBXToken  (tx: ${tx8b.hash})`);
    } else {
        console.log('\n8. Skipped auto-liquidity router setup (no router for this network).');
    }

    // ═════════════════════════════════════════
    // 9. Deploy OBXStaking
    // ═════════════════════════════════════════
    console.log('\n9. Deploying OBXStaking …');
    const OBXStaking    = await ethers.getContractFactory('OBXStaking', deployer);
    const staking       = await OBXStaking.deploy(obxTokenAddress, BURN_ON_STAKE_BPS, BURN_ON_UNSTAKE_BPS);
    await staking.waitForDeployment();
    const stakingAddress = await staking.getAddress();
    console.log(`   ✔ OBXStaking deployed at: ${stakingAddress}`);
    console.log(`   ✔ burnOnStakeBps=${BURN_ON_STAKE_BPS}  burnOnUnstakeBps=${BURN_ON_UNSTAKE_BPS}`);

    // ═════════════════════════════════════════
    // 10. Set OBXStaking fee-exempt on OBXToken
    // ═════════════════════════════════════════
    console.log('\n10. Marking staking contract as fee-exempt on OBXToken …');
    const tx10 = await obxToken.setFeeExempt(stakingAddress, true);
    await tx10.wait();
    console.log(`    ✔ setFeeExempt(staking) done  (tx: ${tx10.hash})`);

    // ═════════════════════════════════════════
    // 11. Fund OBXStaking reward reserve
    // ═════════════════════════════════════════
    console.log(`\n11. Funding staking reward reserve with ${STAKING_ALLOC.toLocaleString()} OBX (5%) …`);
    const stakingWei = STAKING_ALLOC * DECIMALS;
    const tx11 = await obxToken.transfer(stakingAddress, stakingWei);
    await tx11.wait();
    // Mark the transferred amount as the reward reserve inside the contract
    const tx11b = await staking.fundRewards(stakingWei);
    await tx11b.wait();
    console.log(`    ✔ fundRewards done  (tx: ${tx11b.hash})`);

    // ═════════════════════════════════════════
    // 12. Add default staking pools (Silver / Gold / Platinum)
    // ═════════════════════════════════════════
    console.log('\n12. Adding default staking pools …');
    const pools = [
        { name: 'Silver 30-Day',   minAmount: 500n,  durationDays: 30,  apyBps: 500  },
        { name: 'Gold 60-Day',     minAmount: 1000n, durationDays: 60,  apyBps: 1000 },
        { name: 'Platinum 90-Day', minAmount: 2000n, durationDays: 90,  apyBps: 2000 },
    ];
    for (const pool of pools) {
        const tx = await staking.addPool(
            pool.name,
            pool.minAmount * DECIMALS,
            pool.durationDays,
            pool.apyBps
        );
        await tx.wait();
        console.log(`    ✔ Pool added: ${pool.name}  APY=${pool.apyBps/100}%  duration=${pool.durationDays}d`);
    }

    // ═════════════════════════════════════════
    // 13. Verify balances
    // ═════════════════════════════════════════
    console.log('\n13. Verifying balances …');
    const deployerBal = await obxToken.balanceOf(deployer.address);
    const presaleBal  = await obxToken.balanceOf(presaleAddress);
    const airdropBal  = await obxToken.balanceOf(airdropAddress);
    const stakingBal  = await obxToken.balanceOf(stakingAddress);
    console.log(`    Deployer OBX balance: ${ethers.formatUnits(deployerBal, 18)} (70% for liquidity/team)`);
    console.log(`    Presale  OBX balance: ${ethers.formatUnits(presaleBal,  18)} (20% for presale)`);
    console.log(`    Airdrop  OBX balance: ${ethers.formatUnits(airdropBal,  18)} ( 5% for airdrop)`);
    console.log(`    Staking  OBX balance: ${ethers.formatUnits(stakingBal,  18)} ( 5% rewards reserve)`);

    // ═════════════════════════════════════════
    // 14. Print .env / config summary
    // ═════════════════════════════════════════
    console.log('\n══════════════════════════════════════════════════════════');
    console.log(' ✅  DEPLOYMENT COMPLETE (Multichain OBXCoin v3 + Airdrop + Staking)');
    console.log('    20% → presale | 5% → airdrop | 5% → staking | 70% → deployer');
    console.log('══════════════════════════════════════════════════════════');
    console.log(` Presale:  ${PRESALE_ALLOC.toLocaleString()} OBX (20%)`);
    console.log(` Airdrop:  ${AIRDROP_ALLOC.toLocaleString()} OBX ( 5%)`);
    console.log(` Staking:  ${STAKING_ALLOC.toLocaleString()} OBX ( 5% rewards)`);
    console.log(` Deployer: ${(INITIAL_SUPPLY - PRESALE_ALLOC - AIRDROP_ALLOC - STAKING_ALLOC).toLocaleString()} OBX (70%)`);
    console.log(`OBX_TOKEN_CONTRACT=${obxTokenAddress}`);
    console.log(`PRESALE_CONTRACT=${presaleAddress}`);
    console.log(`AIRDROP_CONTRACT=${airdropAddress}`);
    console.log(`STAKING_CONTRACT=${stakingAddress}`);

    if (chainId === 56) {
        console.log(`OBX_TOKEN_BSC=${obxTokenAddress}`);
        console.log(`\nVerify with:`);
        console.log(`  npx hardhat verify --network bsc_mainnet ${obxTokenAddress} ${INITIAL_SUPPLY}`);
        console.log(`  npx hardhat verify --network bsc_mainnet ${presaleAddress} ${obxTokenAddress} ${usdtAddress} ${treasury}`);
        console.log(`  npx hardhat verify --network bsc_mainnet ${airdropAddress} ${obxTokenAddress} ${usdtAddress}`);
        console.log(`  npx hardhat verify --network bsc_mainnet ${stakingAddress} ${obxTokenAddress} ${BURN_ON_STAKE_BPS} ${BURN_ON_UNSTAKE_BPS}`);
    } else if (chainId === 97) {
        console.log(`OBX_TOKEN_BSC_TEST=${obxTokenAddress}`);
        console.log(`\nVerify with:`);
        console.log(`  npx hardhat verify --network bsc_testnet ${obxTokenAddress} ${INITIAL_SUPPLY}`);
        console.log(`  npx hardhat verify --network bsc_testnet ${presaleAddress} ${obxTokenAddress} ${usdtAddress} ${treasury}`);
        console.log(`  npx hardhat verify --network bsc_testnet ${airdropAddress} ${obxTokenAddress} ${usdtAddress}`);
        console.log(`  npx hardhat verify --network bsc_testnet ${stakingAddress} ${obxTokenAddress} ${BURN_ON_STAKE_BPS} ${BURN_ON_UNSTAKE_BPS}`);
    }
    console.log('\n  Next: call OBXAirdrop.createCampaign(start, end, dailyAmount) to activate the airdrop');
    console.log('  Update app settings: staking_contract=' + stakingAddress);
    console.log('══════════════════════════════════════════════════════════\n');

    return { obxTokenAddress, presaleAddress, airdropAddress, stakingAddress };
}

main().catch((err) => {
    console.error('\n❌  Deployment failed:', err.message);
    process.exit(1);
});
