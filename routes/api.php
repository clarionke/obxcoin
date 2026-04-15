<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::post('/coin-payment-notifier','Api\WalletNotifier@coinPaymentNotifier')->name('coinPaymentNotifier');

// ── Public OBX price endpoint (AJAX polling from staking/dashboard pages) ──
Route::get('/obx-price', 'Api\CmcTickerController@obxPrice')->name('api.obx.price');

// ── CoinMarketCap exchange integration endpoints (CMC polls these during listing) ──
Route::get('/cmc/summary', 'Api\CmcTickerController@summary')->name('api.cmc.summary');
Route::get('/cmc/ticker',  'Api\CmcTickerController@ticker')->name('api.cmc.ticker');

// On-chain presale webhook — called by event relay (Alchemy Notify / Moralis Stream / cron)
Route::post('/presale/webhook', 'Api\PresaleWebhookController@handleWebhook')->name('presale.webhook');
// Cron-based BSCScan polling — protected by X-Api-Key header
Route::post('/presale/sync-events', 'Api\PresaleWebhookController@syncEvents')->name('presale.syncEvents');
// Public read endpoint — returns live on-chain phase data (used by buy page for real-time info)
Route::get('/presale/phase-info/{index}', 'Api\PresaleWebhookController@phaseInfo')->name('presale.phaseInfo');
Route::get('/presale/phase-info/{index}/preview/{usdt}', 'Api\PresaleWebhookController@previewPurchase')->name('presale.previewPurchase');

// NOWPayments IPN webhook — unauthenticated, signature-verified internally
Route::post('/nowpayments/ipn', 'Api\NowPaymentsWebhookController@handleIpn')->name('nowpayments.ipn');

// ── Payment Gateway (merchant API) ────────────────────────────────────────────
// Public: coin list + status polling + deposit check (no auth required)
Route::prefix('payment')->group(function () {
    Route::get('coins',                          'Api\PaymentGatewayController@listCoins')->name('payment.coins');
    Route::get('orders/{uuid}/status',           'Api\PaymentGatewayController@pollStatus')->name('payment.order.status');
    Route::post('orders/{uuid}/check',           'Api\PaymentGatewayController@checkDeposit')->name('payment.order.check');
});

// Authenticated (HMAC-signed): create order + read order
Route::prefix('payment')->middleware('merchant.api')->group(function () {
    Route::post('orders',             'Api\PaymentGatewayController@createOrder')->name('payment.order.create');
    Route::get('orders/{uuid}',       'Api\PaymentGatewayController@getOrder')->name('payment.order.get');
});

// Merchant self-service (Passport Bearer token): manage keys + view own orders
Route::prefix('merchant')->namespace('Api')->middleware(['auth:api', 'two_step'])->group(function () {
    Route::get('keys',                  'MerchantKeyController@index')->name('merchant.keys.index');
    Route::post('keys',                 'MerchantKeyController@store')->name('merchant.keys.store');
    Route::patch('keys/{id}',           'MerchantKeyController@update')->name('merchant.keys.update');
    Route::delete('keys/{id}',          'MerchantKeyController@destroy')->name('merchant.keys.destroy');
    Route::get('orders',                'MerchantKeyController@orders')->name('merchant.orders.index');
    Route::get('orders/{uuid}',         'MerchantKeyController@showOrder')->name('merchant.orders.show');
});

Route::group(['namespace' => 'Api'], function () {
    Route::post('sign-up','AuthController@signUp');
    Route::post('login','AuthController@login');
    Route::post('email-verify','AuthController@emailVerify');
    Route::post('forgot-password','AuthController@sendResetCode');
    Route::post('reset-password','AuthController@resetPassword');
});

Route::group(['namespace' => 'Api', 'middleware' => 'auth:api'], function () {
    Route::post('g2f-verify-app','AuthController@g2fVerifyApp')->name('g2fVerifyApp');
});

Route::group(['namespace' => 'Api', 'middleware' => ['auth:api','two_step']], function () {
    // ── OBX Wallet management ──────────────────────────────────────────────────
    // Users can generate multiple BSC/ETH-compatible OBX wallets.
    // Balances are sourced live from the OBXToken contract.
    Route::get('/user/wallets',                        'WalletController@index');
    Route::post('/user/wallets/generate',              'WalletController@generate');
    Route::patch('/user/wallets/{id}/label',           'WalletController@updateLabel');
    Route::post('/user/wallets/{id}/refresh-balance',  'WalletController@refreshBalance');
    Route::get('notification-list', 'user\DashboardController@notificationList');
    Route::get('faq-list', 'user\DashboardController@faqList');
    Route::get('activity-list', 'user\DashboardController@activityList');
    Route::get('trending-coin-list', 'user\DashboardController@trendingCoinList');
    Route::get('overview-coin-list', 'user\DashboardController@overviewCoinList');
    Route::get('profile-view', 'user\ProfileController@profileView');
    Route::get('profile-edit', 'user\ProfileController@profileEdit');
    Route::get('phone-verification', 'user\ProfileController@phoneVerification');
    Route::post('send-phone-verification-code', 'user\ProfileController@sendPhoneVerificationCode');
    Route::post('save-edited-profile', 'user\ProfileController@saveEditedProfile');
    Route::post('change-password', 'AuthController@changePassword');
    Route::get('id-verification', 'user\ProfileController@idVerificationInfo');
    Route::post('submit-nid-photo', 'AuthController@submitNIDPhoto');
    Route::post('submit-nid-photo', 'user\ProfileController@submitNIDPhoto');
    Route::post('submit-passport-photo', 'user\ProfileController@submitPassportPhoto');
    Route::post('submit-driving-license-photo', 'user\ProfileController@submitDrivingLincensePhoto');
    Route::get('my-pocket-list', 'user\WalletController@myPocketList');
    Route::get('my-team-wallet-pocket-list', 'user\WalletController@myMultiSignaturePocketList');
    Route::get('my-multi-signature-pocket-list', 'user\WalletController@myMultiSignaturePocketList');
    Route::get('pocket-coin-list', 'user\WalletController@pocketCoinList');
    Route::post('create-wallet','user\WalletController@createWallet');
    Route::post('import-wallet-by-key','user\WalletController@importWalletByKey');
    Route::get('wallet-details-by-id/{id}', 'user\WalletController@walletDetailsByid');
    Route::get('co-user-list/{id}', 'user\WalletController@coUserList');
    Route::get('goto-address-app','user\WalletController@gotoAddressApp');
    Route::post('generate-new-address-app','user\WalletController@generateNewAddressApp');
    Route::get('show-pass-address','user\WalletController@showPassAddress');
    Route::get('deposite-list','user\WalletController@depositeList');
    Route::get('withdraw-list','user\WalletController@withdrawList');
    Route::get('deposite-or-withdraw-list','user\DashboardController@depositeOrWithdrawList');
    Route::get('deposite-and-withdraw-list','user\DashboardController@depositeAndWithdrawList');
    Route::get('pending-coin-request', 'user\CoinController@pendingCoinRequest')->name('pendingCoinRequest');
    Route::get('request-coin-app', 'user\CoinController@requestCoinApp')->name('requestCoinApp');
    Route::post('default-coin-request-app', 'user\CoinController@sendCoinRequestApp')->name('sendCoinRequestApp');
    Route::post('give-coin-app', 'user\CoinController@giveCoinApp')->name('giveCoinApp');
    Route::post('approval-action-for-coin-request', 'user\CoinController@approvalActionForCoinRequest')->name('approvalActionForCoinRequest');
    Route::get('send-coin-history', 'user\CoinController@sendCoinHistory')->name('sendCoinHistory');
    Route::get('receive-coin-history', 'user\CoinController@receiveCoinHistory')->name('receiveCoinHistory');
    Route::get('membership-plan-list', 'user\ClubController@membershipPlanList')->name('membershipPlanList');
    Route::get('membership-bonus-history', 'user\ClubController@membershipBonusHistory')->name('membershipBonusHistory');
    Route::get('membership-details', 'user\ClubController@membershipDetails')->name('membershipDetails');
    Route::post('transfer-coin-to-club-wallet', 'user\ClubController@transferCoinToClubWallet')->name('transferCoinToClubWallet');
    Route::post('transfer-coin-to-main-wallet', 'user\ClubController@transferCoinToMainWallet')->name('transferCoinToMainWallet');
    Route::get('generate-referral-link','user\ReferralController@generateReferralLink');
    Route::get('my-reference-referral','user\ReferralController@myReferenceReferral');
    Route::get('my-reference-list','user\ReferralController@myReferenceList');
    Route::get('my-earnings','user\ReferralController@myEarnings');
    Route::get('get-buy-coin-and-phase-information', 'user\CoinController@getBuyCoinAndPhaseInformation')->name('getBuyCoinAndPhaseInformation');
    Route::post('buy-coin-through-app', 'user\CoinController@buyCoinThroughApp')->name('buyCoinThroughApp');
    Route::get('buy-coin-history-app', 'user\CoinController@buyCoinHistoryApp')->name('buyCoinHistoryApp');
    Route::get('coin-swap-app', 'user\CoinController@coinSwapApp')->name('coinSwapApp')->middleware('swap-check');
    Route::post('swap-coin-app', 'user\CoinController@swapCoinApp')->name('swapCoinApp')->middleware('swap-check');
    Route::post('get-coin-rate', 'user\CoinController@getCoinRate')->name('getCoinRate');
    Route::get('show-swap-coin-history', 'user\CoinController@showSwapCoinHistory')->name('showSwapCoinHistory')->middleware('swap-check');
    Route::post('save-language', 'user\ProfileController@saveLanguage')->name('saveLanguage');
    Route::post('google-secret-save', 'user\ProfileController@googleSecretSave')->name('googleSecretSave');
    Route::post('withdrawal-process', 'user\WalletController@withdrawalProcess')->name('withdrawalProcess');
    Route::get('co-wallet-pending-withdraw-list', 'user\WalletController@coWalletPendingWithdrawList')->name('coWalletPendingWithdrawList');
    Route::get('co-wallet-user-status-pending-withdrawal', 'user\WalletController@coWalletUserStatusPendingWithdrawal')->name('coWalletUserStatusPendingWithdrawal');
    Route::post('pending-withdrawal-request-approve', 'user\WalletController@pendingWithdrawalRequestApprove')->name('pendingWithdrawalRequestApprove');
    Route::post('pending-withdrawal-request-reject', 'user\WalletController@pendingWithdrawalRequestReject')->name('pendingWithdrawalRequestReject');
    Route::get('user-dashboard-app', 'user\DashboardController@userDashboardApp');
    Route::post('log-out-app','AuthController@logOutApp')->name('logOutApp');
    Route::get('general-settings','user\ProfileController@generalSettings')->name('generalSettings');
    Route::get('goto-setting-page','user\ProfileController@gotoSettingPage')->name('gotoSettingPage');
    Route::post('google-login-enable-or-disable', 'user\ProfileController@googleLoginEnableDisable')->name('googleLoginEnableDisable');
    Route::post('buy-coin-rate-app', 'user\CoinController@buyCoinRateApp')->name('buyCoinRateApp');
    Route::get('buy-coin-referral-history-app', 'user\CoinController@buyCoinReferralHistoryApp')->name('buyCoinReferralHistoryApp');
    Route::get('membership-details-home', 'user\ClubController@membershipDetailsHome')->name('membershipDetailsHome');
    Route::group(['middleware'=> ['co-wallet']], function () {
        Route::any('/wallet-import', 'user\WalletController@importWalletApp')->name('importWalletApp');
        Route::get('/wallet/users', 'user\WalletController@coWalletUsersApp')->name('coWalletUsersApp');
    });
});
