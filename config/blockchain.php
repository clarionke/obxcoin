<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BSC / EVM Chain Configuration
    |--------------------------------------------------------------------------
    |
    | Set these in your .env file. Never commit private keys to git.
    |
    | PRESALE_CHAIN_ID:
    |   56  = BSC Mainnet      97  = BSC Testnet
    |   1   = Ethereum         137 = Polygon
    |
    */

    'bsc_rpc_url'          => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
    'presale_contract'     => env('PRESALE_CONTRACT', ''),
    'obx_token_contract'   => env('OBX_TOKEN_CONTRACT', ''),  // OBXToken.sol address
    'owner_private_key'    => env('OWNER_PRIVATE_KEY', ''),
    'bscscan_api_key'      => env('BSCSCAN_API_KEY', ''),
    'presale_chain_id'     => env('PRESALE_CHAIN_ID', 56),

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
        56  => env('OBX_TOKEN_BSC',        ''),
        97  => env('OBX_TOKEN_BSC_TEST',   ''),
        1   => env('OBX_TOKEN_ETH',        ''),
        137 => env('OBX_TOKEN_POLYGON',    ''),
    ],

    // Block number to start scanning for TokensPurchased events (set to deployment block)
    // OBXToken name, contract address, decimals and logo are managed via
    // Admin Panel > Settings > OBXCoin Send Settings (stored in admin_settings table).
    'start_block' => env('PRESALE_START_BLOCK', 0),

];
