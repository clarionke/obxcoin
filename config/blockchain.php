<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BSC / EVM Chain Configuration
    |--------------------------------------------------------------------------
    |
    | Set these in your .env file. Never commit private keys to git.
    |
    | PRESALE_CHAIN_ID (Etherscan V2 supported in app):
    |   1   = Ethereum Mainnet
    |   56  = BSC Mainnet
    |   97  = BSC Testnet
    |   137 = Polygon Mainnet
    |
    */

    'bsc_rpc_url'          => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
    'node_binary'          => env('NODE_BINARY', 'node'),
    'rpc_urls' => [
        56  => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
        97  => env('BSC_TESTNET_RPC_URL', 'https://data-seed-prebsc-1-s1.bnbchain.org:8545/'),
        1   => env('ETH_RPC_URL', 'https://eth.llamarpc.com'),
        137 => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
    ],
    'presale_contract'     => env('PRESALE_CONTRACT', ''),
    'presale_contracts' => [
        56  => env('PRESALE_CONTRACT_BSC', env('PRESALE_CONTRACT', '')),
        97  => env('PRESALE_CONTRACT_BSC_TEST', env('PRESALE_CONTRACT', '')),
        1   => env('PRESALE_CONTRACT_ETH', env('PRESALE_CONTRACT', '')),
        137 => env('PRESALE_CONTRACT_POLYGON', env('PRESALE_CONTRACT', '')),
    ],
    'obx_token_contract'   => env('OBX_TOKEN_CONTRACT', ''),  // OBXToken.sol address
    'owner_private_key'    => env('OWNER_PRIVATE_KEY', ''),
    'bscscan_api_key'      => env('BSCSCAN_API_KEY', ''),
    'presale_chain_id'     => env('PRESALE_CHAIN_ID', 56),
    // Hot-wallet key used to sign OBX token transfers (Team Wallet withdrawals).
    // This wallet must hold OBX tokens to fund user withdrawals.
    'signer_private_key'   => env('SIGNER_PRIVATE_KEY', ''),

    // Webhook signing secret (REQUIRED — no webhook calls are accepted without this)
    'webhook_secret'       => env('PRESALE_WEBHOOK_SECRET', ''),
    // Bearer API key for the cron sync endpoint
    'sync_api_key'         => env('PRESALE_SYNC_API_KEY', ''),

    // USDT contract addresses by chain ID (used by signer.js)
    'usdt_addresses' => [
        56  => env('USDT_BSC_ADDRESS',      '0x55d398326f99059fF775485246999027B3197955'), // BSC Mainnet
        97  => env('USDT_BSC_TEST_ADDRESS', '0x337610d27c682E347C9cD60BD4b3b107C9d34eDd'), // BSC Testnet
        1   => env('USDT_ETH_ADDRESS',      '0xdAC17F958D2ee523a2206206994597C13D831ec7'), // Ethereum
        137 => env('USDT_POLYGON_ADDRESS',  '0xc2132D05D31c914a87C6611C10748AEb04B58e8F'), // Polygon
    ],

    // OBXToken contract addresses by chain (same ABI, different addresses per chain)
    'obx_token_addresses' => [
        56  => env('OBX_TOKEN_BSC',      env('OBX_TOKEN_CONTRACT', '')),
        97  => env('OBX_TOKEN_BSC_TEST', env('OBX_TOKEN_CONTRACT', '')),
        1   => env('OBX_TOKEN_ETH',      env('OBX_TOKEN_CONTRACT', '')),
        137 => env('OBX_TOKEN_POLYGON',  env('OBX_TOKEN_CONTRACT', '')),
    ],

    'airdrop_contracts' => [
        56  => env('AIRDROP_CONTRACT_BSC', ''),
        97  => env('AIRDROP_CONTRACT_BSC_TEST', ''),
        1   => env('AIRDROP_CONTRACT_ETH', ''),
        137 => env('AIRDROP_CONTRACT_POLYGON', ''),
    ],

    'staking_contracts' => [
        56  => env('STAKING_CONTRACT_BSC', ''),
        97  => env('STAKING_CONTRACT_BSC_TEST', ''),
        1   => env('STAKING_CONTRACT_ETH', ''),
        137 => env('STAKING_CONTRACT_POLYGON', ''),
    ],

    // DEX pair used for automatic OBX market data fallback (DexScreener).
    // Example: OBX/USDT pair on PancakeSwap V2 (BSC) => obx_dex_chain=bsc
    'obx_dex_pair' => env('OBX_DEX_PAIR', ''),
    'obx_dex_chain' => env('OBX_DEX_CHAIN', 'bsc'),

    // Block number to start scanning for TokensPurchased events (set to deployment block)
    // OBXToken name, contract address, decimals and logo are managed via
    // Admin Panel > Settings > OBXCoin Send Settings (stored in admin_settings table).
    'start_block' => env('PRESALE_START_BLOCK', 0),

];
