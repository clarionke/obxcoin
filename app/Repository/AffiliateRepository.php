<?php

namespace App\Repository;

use App\Model\AffiliationCode;
use App\Model\AffiliationHistory;
use App\Model\BuyCoinReferralHistory;
use App\Model\ReferralSignBonusHistory;
use App\Model\ReferralUser;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Services\BlockchainService;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AffiliateRepository
{
    public const MAX_REFERRAL_LEVELS = 5;

    // create affiliation code
    public function create($userId)
    {
        $existing = AffiliationCode::where('user_id', $userId)->first();
        if ($existing) {
            return $existing->id;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return AffiliationCode::create([
                    'user_id' => $userId,
                    'code' => uniqid((string) $userId, true),
                    'status' => 1,
                ])->id;
            } catch (QueryException $e) {
                $errorCode = $e->errorInfo[1] ?? null;
                if ((string) $errorCode !== '1062') {
                    throw $e;
                }
            }
        }

        return 0;

    }

    // create referral user
    public function createReferralUser($userId, $parentId)
    {
        $created = 0;
        try {
            $data['user_id'] = $userId;
            $data['parent_id'] = $parentId;
            $created = ReferralUser::create($data)->id;
            $signUpBonus = (float) (allsetting()['referral_signup_reward'] ?? 0);
            if ($signUpBonus <= 0) {
                return $created;
            }

            $maxReferralLevel = $this->normalizeReferralLevel(max_level());
            $userAffiliation = $this->parentReferrals($maxReferralLevel, $userId);
            if (empty($userAffiliation)) {
                return $created;
            }

            $adminSettings = $this->checkAdminSettings();
            for ($i = 1; $i <= $maxReferralLevel; $i++) {
                $parentLevel = 'parent_level_user_' . $i;
                $feesLevel = 'fees_level' . $i;
                if (empty($userAffiliation->{$parentLevel})) {
                    break;
                }

                $amount = round(($signUpBonus * ((float) ($adminSettings[$feesLevel] ?? 0))) / 100, 8);
                if ($amount <= 0) {
                    continue;
                }

                $wallet = get_primary_wallet($userAffiliation->{$parentLevel}, DEFAULT_COIN_TYPE);
                if (!isset($wallet)) {
                    continue;
                }

                $payout = $this->payoutReferralOnChain($wallet, (string) $amount, 'signup');
                if ($payout['success'] !== true) {
                    Log::warning('Referral signup bonus payout failed', [
                        'child_id' => $userId,
                        'parent_id' => $userAffiliation->{$parentLevel},
                        'level' => $i,
                        'amount' => $amount,
                        'reason' => $payout['message'] ?? 'unknown',
                    ]);
                    continue;
                }

                $wallet->increment('balance', $amount);
                ReferralSignBonusHistory::create([
                    'parent_id' => $userAffiliation->{$parentLevel},
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('createReferralUser failed', ['message' => $e->getMessage()]);
        }
        return $created;
    }

    // store data to affiliation history
    public function storeAffiliationHistory($transaction = null)
    {
        Log::info('Withdrawal referral bonuses are disabled; signup and buy bonuses are on-chain only');
        return 1;
    }

    // check referral fees setting
    public function checkAdminSettings()
    {
        $adminSettings = allsetting(['fees_level1', 'fees_level2', 'fees_level3', 'fees_level4', 'fees_level5']);
        if (empty($adminSettings['fees_level1'])) {
            $adminSettings['fees_level1'] = 10;
        }
        if (empty($adminSettings['fees_level2'])) {
            $adminSettings['fees_level2'] = 5;
        }
        if (empty($adminSettings['fees_level3'])) {
            $adminSettings['fees_level3'] = 10;
        }
        if (empty($adminSettings['fees_level4'])) {
            $adminSettings['fees_level4'] = 0;
        }
        if (empty($adminSettings['fees_level5'])) {
            $adminSettings['fees_level5'] = 0;
        }
        return $adminSettings;
    }



    // get parent referral
    public function parentReferrals($maxReferralLevel = 1, $user_id)
    {
        $maxReferralLevel = $this->normalizeReferralLevel($maxReferralLevel);
        $affiliation = DB::table('referral_users AS ru1')
            ->where('ru1.user_id', $user_id);

        $selectQuery = 'ru1.user_id as user_id, ru1.parent_id as parent_level_user_1';
        for ($i = 1; $i < $maxReferralLevel; $i++) {
            $ru_parent = "ru" . ($i + 1);
            $ru = "ru" . $i;
            $parent_level_user = 'parent_level_user_' . ($i + 1);
            $affiliation = $affiliation->leftJoin("referral_users AS $ru_parent", "$ru.parent_id", '=', "$ru_parent.user_id");
            $selectQuery = $selectQuery . ',' . " $ru_parent.parent_id as $parent_level_user";
        }
        $data = $affiliation->select(DB::raw($selectQuery))->first();

        return $data;
    }

    // calculate referral fees
    protected function calculateReferralFees($adminSettings, $transactionId, $affiliateUsers, $systemFees, $maxReferralLevel = 1, $coinType)
    {
        return 1;
    }


    // deposit the affiliation fees
    public function depositAffiliationFees()
    {
        $firstDay = $start = Carbon::now()->startOfMonth();

        $limit = 100;
        while (true) {
            $affiliationHistory = AffiliationHistory::where('created_at', '<', $firstDay)
                ->where('status', 0)
                ->select('user_id', DB::raw('SUM(amount) AS total'))
                ->groupBy('user_id')
                ->limit($limit)
                ->pluck('total', 'user_id');
            $affiliationHistory = $affiliationHistory->toArray();
            Log::info(json_encode($affiliationHistory));
            $eligibleUsers = array_keys($affiliationHistory);
            Log::info(json_encode($eligibleUsers));

            $userWallets = Wallet::whereIn('user_id', $eligibleUsers)
                ->where('is_primary', '1')
                ->get();
            Log::info(json_encode($userWallets));

            foreach ($userWallets as $userWallet) {
                $userWallet->referral_balance = ($userWallet->referral_balance + $affiliationHistory[$userWallet->user_id]);
                $userWallet->save();
                Log::info('This user get bonus '.$affiliationHistory[$userWallet->user_id]. ' amount and the wallet id is '.$userWallet->id. ' and user id is '.$userWallet->user_id);
                AffiliationHistory::where('created_at', '<', $firstDay)
                    ->where('status', 0)
                    ->where('user_id', $userWallet->user_id)
                    ->update(['status' => 1]);
            }

            if (count($affiliationHistory) < $limit) {
//
                break;
            }
        }

    }


    // referral children
    public function childrenReferralQuery($maxReferralLevel = 1)
    {
        $maxReferralLevel = $this->normalizeReferralLevel($maxReferralLevel);

//        $maxReferralLevel = 3;
        $referralAll = DB::table('referral_users AS ru1')->where('ru1.deleted_at', null);
        $selectQuery = 'COUNT(DISTINCT(ru1.user_id)) as level1';
        $allSumQuery = 'COUNT(parent_id) AS referralsLevel0, SUM(level1) as  referralsLevel1';

        for ($i = 1; $i < $maxReferralLevel; $i++) {
            $ru_child = "ru" . ($i + 1);
            $ru = "ru" . $i;
            $level = 'level' . ($i + 1);
            $referralsLevel = 'referralsLevel' . ($i + 1);

            $referralAll->leftJoin("referral_users AS $ru_child", "$ru.user_id", '=', "$ru_child.parent_id");
            $selectQuery = $selectQuery . ', ' . "COUNT(DISTINCT($ru_child.user_id)) as $level";
            $allSumQuery = $allSumQuery . ', ' . "SUM($level) as $referralsLevel";
        }

        $data['referral_all'] = $referralAll;
        $data['select_query'] = $selectQuery;
        $data['all_sum_query'] = $allSumQuery;
        return $data;
    }

    // store data to affiliation history for buy coin
    public function storeAffiliationHistoryForBuyCoin($transaction)
    {
        if ($transaction) {
            if (BuyCoinReferralHistory::where('buy_id', (int) $transaction->id)->exists()) {
                return 1;
            }

            $adminSettings = $this->checkAdminSettings();
            $user_id = (int) $transaction->user_id;
            if ($user_id <= 0) {
                return 1;
            }

            $maxReferralLevel = $this->normalizeReferralLevel($transaction->referral_level);
            try {
                $userAffiliation = $this->parentReferrals($maxReferralLevel, $user_id);
                if (!empty($userAffiliation)) {
                    $baseAmount = (float) ($transaction->requested_amount ?? 0);
                    if ($baseAmount <= 0) {
                        $baseAmount = (float) ($transaction->coin ?? 0);
                    }
                    if ($baseAmount <= 0) {
                        $baseAmount = (float) ($transaction->bonus ?? 0);
                    }

                    $this->calculateReferralFeesForBuyCoin($adminSettings, $transaction, $userAffiliation, $baseAmount,  $maxReferralLevel);
                }
            } catch (\Exception $e) {
                Log::info($e->getMessage());
            }
        }

        return 1;
    }

    // calculate referral fees when buy coin
    protected function calculateReferralFeesForBuyCoin($adminSettings, $transaction, $affiliateUsers, $systemFees, $maxReferralLevel= 1 )
    {
        try {
            $maxReferralLevel = $this->normalizeReferralLevel($maxReferralLevel);
            $affiliationHistoryData['buy_id'] = $transaction->id;
            $affiliationHistoryData['phase_id'] = $transaction->phase_id;
            $affiliationHistoryData['system_fees'] = $systemFees;
            $affiliationHistoryData['child_id'] = $affiliateUsers->user_id;
            $affiliationHistoryData['status'] = STATUS_ACTIVE;
            Log::info('start buy coin referral bonus distribution');

            for ($i = 1; $i <= $maxReferralLevel; $i++) {

                $parent_level = 'parent_level_user_' . $i;
                $fees_level = 'fees_level' . $i;

                if ($affiliateUsers->{$parent_level}) {
                    try {
                        $affiliationHistoryData['user_id'] = $affiliateUsers->{$parent_level};
                        $fees_percent = isset($adminSettings[$fees_level]) ? $adminSettings[$fees_level] : '0';
                        $affiliationHistoryData['amount'] = round(($systemFees * $fees_percent) / 100, 8);
                        $affiliationHistoryData['level'] = $i;
                        $userWallet = get_primary_wallet($affiliationHistoryData['user_id'], DEFAULT_COIN_TYPE);
                        if (isset($userWallet) && (float) $affiliationHistoryData['amount'] > 0) {
                            $payout = $this->payoutReferralOnChain($userWallet, (string) $affiliationHistoryData['amount'], 'buy');
                            if ($payout['success'] !== true) {
                                Log::warning('Buy referral payout failed', [
                                    'user_id' => $affiliationHistoryData['user_id'],
                                    'child_id' => $affiliateUsers->user_id,
                                    'buy_id' => $transaction->id,
                                    'level' => $i,
                                    'amount' => $affiliationHistoryData['amount'],
                                    'reason' => $payout['message'] ?? 'unknown',
                                ]);
                                continue;
                            }
                            $affiliationHistoryData['wallet_id'] = $userWallet->id;
                            $userWallet->increment('balance', $affiliationHistoryData['amount']);
                            Log::info('Buy referral reward paid on-chain', [
                                'user_id' => $affiliationHistoryData['user_id'],
                                'child_id' => $affiliateUsers->user_id,
                                'buy_id' => $transaction->id,
                                'level' => $i,
                                'amount' => $affiliationHistoryData['amount'],
                                'tx_hash' => $payout['tx_hash'] ?? null,
                            ]);
                        }
                        BuyCoinReferralHistory::create($affiliationHistoryData);
                    } catch (\Exception $e) {
                        Log::info($e->getMessage());
                    }
                } else {
                    break;
                }
            }
            return 1;
        } catch (\Exception $e) {
            return 1;
        }
    }

    public function getUpline(int $userId, int $maxLevels = self::MAX_REFERRAL_LEVELS): array
    {
        $upline = [];
        $currentUserId = $userId;
        $maxLevels = $this->normalizeReferralLevel($maxLevels);

        for ($level = 1; $level <= $maxLevels; $level++) {
            $referral = ReferralUser::where('user_id', $currentUserId)->first();
            if (!$referral || !$referral->parent_id) {
                break;
            }

            $user = User::find($referral->parent_id);
            if (!$user) {
                break;
            }

            $upline[] = [
                'level' => $level,
                'user' => $user,
            ];
            $currentUserId = $user->id;
        }

        return $upline;
    }

    public function getDownlineTree(int $userId, int $maxLevels = self::MAX_REFERRAL_LEVELS, int $currentLevel = 1): array
    {
        $maxLevels = $this->normalizeReferralLevel($maxLevels);
        if ($currentLevel > $maxLevels) {
            return [];
        }

        $children = ReferralUser::where('parent_id', $userId)->get();
        $tree = [];

        foreach ($children as $childLink) {
            $childUser = User::find($childLink->user_id);
            if (!$childUser) {
                continue;
            }

            $tree[] = [
                'level' => $currentLevel,
                'user' => $childUser,
                'children' => $this->getDownlineTree($childUser->id, $maxLevels, $currentLevel + 1),
            ];
        }

        return $tree;
    }

    private function normalizeReferralLevel($maxReferralLevel): int
    {
        $level = (int) $maxReferralLevel;
        if ($level < 1) {
            $level = 1;
        }

        return min($level, self::MAX_REFERRAL_LEVELS);
    }

    private function payoutReferralOnChain(Wallet $wallet, string $amount, string $context): array
    {
        try {
            if ((float) $amount <= 0) {
                return ['success' => false, 'message' => 'Invalid reward amount'];
            }

            $toAddress = strtolower(trim((string) WalletAddressHistory::where('wallet_id', (int) $wallet->id)
                ->where('coin_type', DEFAULT_COIN_TYPE)
                ->orderBy('id', 'desc')
                ->value('address')));

            if (!preg_match('/^0x[a-f0-9]{40}$/', $toAddress)) {
                return ['success' => false, 'message' => 'Recipient default wallet address is missing or invalid'];
            }

            $blockchain = app(BlockchainService::class);
            $tx = $blockchain->transferObxOnChain($toAddress, $amount);

            if (empty($tx) || empty($tx['txHash'])) {
                return ['success' => false, 'message' => $blockchain->getLastSignerError() ?: 'On-chain referral payout failed'];
            }

            Log::info('Referral payout sent on-chain with gas sponsored by platform signer', [
                'context' => $context,
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'amount' => $amount,
                'to' => $toAddress,
                'tx_hash' => $tx['txHash'],
            ]);

            return ['success' => true, 'tx_hash' => $tx['txHash']];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

}
