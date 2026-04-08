/**
 * hardhat.config.js
 *
 * Hardhat configuration for OBXCoin contracts.
 * Supports BSC Mainnet, BSC Testnet, Ethereum, and Polygon.
 *
 * Usage:
 *   npm install --save-dev hardhat @nomicfoundation/hardhat-toolbox dotenv
 *   npx hardhat compile
 *   npx hardhat run scripts/deploy.js --network bsc_mainnet
 *   npx hardhat run scripts/deploy.js --network bsc_testnet
 *   npx hardhat verify --network bsc_mainnet <CONTRACT_ADDRESS> <CONSTRUCTOR_ARGS>
 */

'use strict';

require('@nomicfoundation/hardhat-toolbox');
require('dotenv').config();

const DEPLOYER_PRIVATE_KEY = process.env.OWNER_PRIVATE_KEY || '';
const BSCSCAN_API_KEY      = process.env.BSCSCAN_API_KEY   || '';
const ETHERSCAN_API_KEY    = process.env.ETHERSCAN_API_KEY  || '';
const POLYGONSCAN_API_KEY  = process.env.POLYGONSCAN_API_KEY|| '';

if (!DEPLOYER_PRIVATE_KEY) {
    console.warn('[hardhat.config] Warning: OWNER_PRIVATE_KEY not set in .env — deployment will fail.');
}

/** @type {import('hardhat/config').HardhatUserConfig} */
module.exports = {
    solidity: {
        version: '0.8.20',
        settings: {
            optimizer: {
                enabled: true,
                runs: 200,
            },
            evmVersion: 'paris',
        },
    },

    networks: {
        // ─── BNB Smart Chain ───────────────────────────────────────────────
        bsc_mainnet: {
            url: process.env.BSC_RPC_URL || 'https://bsc-dataseed.binance.org/',
            chainId: 56,
            accounts: DEPLOYER_PRIVATE_KEY ? [DEPLOYER_PRIVATE_KEY] : [],
            gasPrice: 3_000_000_000, // 3 gwei
        },
        bsc_testnet: {
            url: process.env.BSC_TESTNET_RPC_URL || 'https://data-seed-prebsc-1-s1.binance.org:8545/',
            chainId: 97,
            accounts: DEPLOYER_PRIVATE_KEY ? [DEPLOYER_PRIVATE_KEY] : [],
            gasPrice: 10_000_000_000, // 10 gwei
        },

        // ─── Ethereum ─────────────────────────────────────────────────────
        ethereum: {
            url: process.env.ETH_RPC_URL || 'https://eth.llamarpc.com',
            chainId: 1,
            accounts: DEPLOYER_PRIVATE_KEY ? [DEPLOYER_PRIVATE_KEY] : [],
        },
        sepolia: {
            url: process.env.SEPOLIA_RPC_URL || 'https://rpc.sepolia.org',
            chainId: 11155111,
            accounts: DEPLOYER_PRIVATE_KEY ? [DEPLOYER_PRIVATE_KEY] : [],
        },

        // ─── Polygon ──────────────────────────────────────────────────────
        polygon: {
            url: process.env.POLYGON_RPC_URL || 'https://polygon-rpc.com',
            chainId: 137,
            accounts: DEPLOYER_PRIVATE_KEY ? [DEPLOYER_PRIVATE_KEY] : [],
        },
        polygon_testnet: {
            url: process.env.POLYGON_TESTNET_RPC_URL || 'https://rpc-amoy.polygon.technology',
            chainId: 80002,
            accounts: DEPLOYER_PRIVATE_KEY ? [DEPLOYER_PRIVATE_KEY] : [],
        },

        // ─── Local dev ────────────────────────────────────────────────────
        hardhat: {
            chainId: 31337,
        },
        localhost: {
            url: 'http://127.0.0.1:8545',
            chainId: 31337,
        },
    },

    etherscan: {
        apiKey: {
            bsc:            BSCSCAN_API_KEY,
            bscTestnet:     BSCSCAN_API_KEY,
            mainnet:        ETHERSCAN_API_KEY,
            sepolia:        ETHERSCAN_API_KEY,
            polygon:        POLYGONSCAN_API_KEY,
            polygonAmoy:    POLYGONSCAN_API_KEY,
        },
        customChains: [
            {
                network: 'polygonAmoy',
                chainId: 80002,
                urls: {
                    apiURL:   'https://api-amoy.polygonscan.com/api',
                    browserURL: 'https://amoy.polygonscan.com',
                },
            },
        ],
    },

    paths: {
        sources:   './contracts',
        tests:     './test',
        cache:     './cache',
        artifacts: './artifacts',
    },

    mocha: {
        timeout: 60_000,
    },
};
