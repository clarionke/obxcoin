<?php

namespace Database\Seeders;

use App\Model\AdminSetting;
use Illuminate\Database\Seeder;

class AdminSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AdminSetting::firstOrCreate(['slug'=>'coin_price'],['value'=>'2.50']);
        AdminSetting::firstOrCreate(['slug'=>'coin_name'],['value'=>'OBXCoin']);
        AdminSetting::firstOrCreate(['slug'=>'app_title'],['value'=>'OBXCoin']);
        AdminSetting::firstOrCreate(['slug'=>'maximum_withdrawal_daily'],['value'=>'3']);
        AdminSetting::firstOrCreate(['slug'=>'mail_from'],['value'=>'noreply@cpoket.com']);
        AdminSetting::firstOrCreate(['slug'=>'admin_coin_address'],['value'=>'address']);
        AdminSetting::firstOrCreate(['slug'=>'base_coin_type'],['value'=>'BTC']);
        AdminSetting::firstOrCreate(['slug'=>'minimum_withdrawal_amount'],['value'=>.005]);
        AdminSetting::firstOrCreate(['slug'=>'maximum_withdrawal_amount'],['value'=>12]);

        AdminSetting::firstOrCreate(['slug' => 'maintenance_mode'],['value' => 'no']);
        AdminSetting::firstOrCreate(['slug' => 'logo'],['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'login_logo'],['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'landing_logo'],['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'favicon'], ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'copyright_text'], ['value' => 'Copyright@2020']);
        AdminSetting::firstOrCreate(['slug' => 'pagination_count'], ['value' => '10']);
        AdminSetting::firstOrCreate(['slug' => 'point_rate'], ['value' => '1']);
        //General Settings
        AdminSetting::firstOrCreate(['slug' => 'lang'], ['value' => 'en']);
        AdminSetting::firstOrCreate(['slug' => 'company_name'], ['value' => 'OBXCoin']);
        AdminSetting::firstOrCreate(['slug' => 'primary_email'], ['value' => 'test@email.com']);

        AdminSetting::firstOrCreate(['slug' => 'sms_getway_name'], ['value' => 'twillo']);
        AdminSetting::firstOrCreate(['slug' => 'twillo_secret_key'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'twillo_auth_token'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'twillo_number'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'ssl_verify'], ['value' => '']);

        AdminSetting::firstOrCreate(['slug' => 'mail_driver'], ['value' => 'SMTP']);
        AdminSetting::firstOrCreate(['slug' => 'mail_host'], ['value' => 'smtp.mailtrap.io']);
        AdminSetting::firstOrCreate(['slug' => 'mail_port'], ['value' => 2525]);
        AdminSetting::firstOrCreate(['slug' => 'mail_username'], ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'mail_password'], ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'mail_encryption'], ['value' => 'null']);
        AdminSetting::firstOrCreate(['slug' => 'mail_from_address'], ['value' => '']);


        AdminSetting::firstOrCreate(['slug' => 'braintree_client_token'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'braintree_environment'], ['value' => 'sandbox']);
        AdminSetting::firstOrCreate(['slug' => 'braintree_merchant_id'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'braintree_public_key'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'braintree_private_key'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'sms_getway_name'], ['value' => 'twillo']);
        AdminSetting::firstOrCreate(['slug' => 'clickatell_api_key'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'number_of_confirmation'], ['value' => '6']);
        AdminSetting::firstOrCreate(['slug' => 'referral_commission_percentage'], ['value' => '10']);
        AdminSetting::firstOrCreate(['slug' => 'referral_signup_reward'], ['value' => 10]);
        AdminSetting::firstOrCreate(['slug' => 'max_affiliation_level'], ['value' => 10]);


        // Coin Api
        AdminSetting::firstOrCreate(['slug' => 'coin_api_user'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'coin_api_pass'], ['value' => 'test']);
        AdminSetting::firstOrCreate(['slug' => 'coin_api_host'], ['value' => 'test5']);
        AdminSetting::firstOrCreate(['slug' => 'coin_api_port'], ['value' => 'test']);


        // Send Fees
        AdminSetting::firstOrCreate(['slug' => 'send_fees_type'], ['value' => 1]);
        AdminSetting::firstOrCreate(['slug' => 'send_fees_fixed'], ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'send_fees_percentage'], ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'max_send_limit'], ['value' => 0]);
        //order settings
        AdminSetting::firstOrCreate(['slug' => 'deposit_time'], ['value' => 1]);

        // NOWPayments settings
        AdminSetting::firstOrCreate(['slug' => 'nowpayments_api_key'],      ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'nowpayments_ipn_secret'],   ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'nowpayments_enabled'],      ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'nowpayments_sandbox_mode'], ['value' => 1]);

        // WalletConnect settings
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_project_id'], ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_chain_id'],   ['value' => 56]);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_enabled'],    ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'obx_withdraw_walletconnect_fee_enabled'], ['value' => 1]);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_hidden_fee_usd'], ['value' => '0.2']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_fee_wallet'], ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_signer_wallet'], ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_gas_topup_enabled'], ['value' => 1]);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_gas_topup_user_usd'], ['value' => '0.8']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_gas_topup_admin_usd'], ['value' => '0.2']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_gas_min_bnb'], ['value' => '0.00035']);
        AdminSetting::firstOrCreate(['slug' => 'walletconnect_gas_topup_cooldown_minutes'], ['value' => 15]);

        // Presale / on-chain contract settings (set via Admin Panel > Settings > Payment)
        AdminSetting::firstOrCreate(['slug' => 'presale_contract'],         ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'presale_chain_id'],         ['value' => 56]);
        AdminSetting::firstOrCreate(['slug' => 'airdrop_contract'],         ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'staking_contract'],         ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'treasury_wallet'],          ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'bsc_rpc_url'],              ['value' => 'https://bsc-dataseed.binance.org/']);
        AdminSetting::firstOrCreate(['slug' => 'bscscan_api_key'],         ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'owner_private_key'],        ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'presale_start_block'],      ['value' => 0]);

        // OBX token logo (shown when user adds OBX to MetaMask / Trust Wallet via wallet_watchAsset)
        AdminSetting::firstOrCreate(['slug' => 'obx_token_logo_url'],       ['value' => '']);

        // Send Fees
        AdminSetting::firstOrCreate(['slug' => 'membership_bonus_type'], ['value' => 1]);
        AdminSetting::firstOrCreate(['slug' => 'membership_bonus_fixed'], ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'membership_bonus_percentage'], ['value' => 0]);

        //OBXCoin withdrawal system
        AdminSetting::firstOrCreate(['slug' => 'chain_link'], ['value' => "https://data-seed-prebsc-1-s1.binance.org:8545"]);
        AdminSetting::firstOrCreate(['slug' => 'contract_address'], ['value' => ""]);
        AdminSetting::firstOrCreate(['slug' => 'wallet_address'], ['value' => ""]);
        AdminSetting::firstOrCreate(['slug' => 'private_key'], ['value' => ""]);
        AdminSetting::firstOrCreate(['slug' => 'contract_decimal'], ['value' => 18]);
        AdminSetting::firstOrCreate(['slug' => 'gas_limit'], ['value' => 43000]);
        AdminSetting::firstOrCreate(['slug' => 'contract_coin_name'], ['value' => 'ETH']);
        AdminSetting::firstOrCreate(['slug' => 'previous_block_count'], ['value' => 100]);

        // kyc setting
        AdminSetting::firstOrCreate(['slug' => 'kyc_enable_for_withdrawal'], ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'kyc_nid_enable_for_withdrawal'], ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'kyc_passport_enable_for_withdrawal'], ['value' => 0]);
        AdminSetting::firstOrCreate(['slug' => 'kyc_driving_enable_for_withdrawal'], ['value' => 0]);

        //swap disable/enable
        AdminSetting::firstOrCreate(['slug' => 'swap_enabled'], ['value' => 1]);

        AdminSetting::firstOrCreate(['slug' => 'plan_minimum_amount'], ['value' => 1]);
        AdminSetting::firstOrCreate(['slug' => 'plan_maximum_amount'], ['value' => 99999]);
        AdminSetting::firstOrCreate(['slug' => 'admin_send_default_minimum'], ['value' => 1]);
        AdminSetting::firstOrCreate(['slug' => 'admin_send_default_maximum'], ['value' => 10000]);

        // CoinMarketCap integration
        AdminSetting::firstOrCreate(['slug' => 'coinmarketcap_api_key'],   ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'coinmarketcap_obx_id'],    ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'obx_total_supply'],        ['value' => '100000000']);
        AdminSetting::firstOrCreate(['slug' => 'obx_circulating_supply'],  ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'obx_price_change_24h'],    ['value' => '0']);
        AdminSetting::firstOrCreate(['slug' => 'obx_market_cap'],          ['value' => '0']);
        AdminSetting::firstOrCreate(['slug' => 'obx_volume_24h'],          ['value' => '0']);
        AdminSetting::firstOrCreate(['slug' => 'obx_price_last_updated'],  ['value' => '']);

        // Landing page — hero
        AdminSetting::firstOrCreate(['slug' => 'landing_hero_badge'],       ['value' => 'Live & Secure Platform']);

        // Landing page — token info bar
        AdminSetting::firstOrCreate(['slug' => 'coin_blockchain_name'],     ['value' => 'Ethereum']);
        AdminSetting::firstOrCreate(['slug' => 'coin_launch_date'],         ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'landing_show_token_info'],  ['value' => '1']);

        // Landing page — tokenomics section
        AdminSetting::firstOrCreate(['slug' => 'landing_show_tokenomics'],         ['value' => '1']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_section_title'],        ['value' => 'Token Distribution']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_section_subtitle'],     ['value' => 'OBXCoin has a transparent, fixed supply designed for long-term sustainability.']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_public_sale_pct'],      ['value' => '40']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_public_sale_label'],    ['value' => 'Public Sale']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_team_pct'],             ['value' => '15']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_team_label'],           ['value' => 'Team & Advisors']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_ecosystem_pct'],        ['value' => '20']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_ecosystem_label'],      ['value' => 'Ecosystem Fund']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_reserve_pct'],          ['value' => '10']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_reserve_label'],        ['value' => 'Reserve']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_liquidity_pct'],        ['value' => '10']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_liquidity_label'],      ['value' => 'Liquidity']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_marketing_pct'],        ['value' => '5']);
        AdminSetting::firstOrCreate(['slug' => 'tokenomics_marketing_label'],      ['value' => 'Marketing']);

        // Landing page — social & links
        AdminSetting::firstOrCreate(['slug' => 'landing_telegram_link'],    ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'landing_discord_link'],     ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'landing_github_link'],      ['value' => '']);
        AdminSetting::firstOrCreate(['slug' => 'whitepaper_url'],           ['value' => '']);

    }
}
