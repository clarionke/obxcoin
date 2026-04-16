<?php
namespace App\Repository;
use App\Model\Bank;
use App\Model\IcoPhase;
use App\Services\BlockchainService;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PhaseRepository
{
    private function resolvedPresaleContract(): string
    {
        return trim((string) (settings('presale_contract') ?: config('blockchain.presale_contract', '')));
    }

    private function broadcastPhaseWithRetry(BlockchainService $blockchain, bool $isEdit, IcoPhase $phase): ?array
    {
        $attempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $phaseData = $phase->toArray();

            if ($isEdit && $phase->contract_phase_index !== null) {
                $result = $blockchain->updatePhaseOnContract(array_merge($phaseData, [
                    'active' => (int)$phase->status === STATUS_ACTIVE,
                ]));
            } else {
                $result = $blockchain->pushPhaseToContract($phaseData);
            }

            if (!empty($result['txHash'])) {
                return $result;
            }

            $lastError = $blockchain->getLastSignerError();
            Log::warning('PhaseRepository broadcast attempt failed', [
                'attempt' => $attempt,
                'phase_id' => $phase->id,
                'is_edit' => $isEdit,
                'error' => $lastError,
            ]);

            if ($attempt < $attempts) {
                usleep(500000);
            }
        }

        return ['error' => $lastError];
    }

    private function buildBroadcastErrorMessage(?string $error): string
    {
        $base = __('Failed to broadcast phase transaction to blockchain.');
        $hint = __('Please verify RPC URL, chain ID, contract address, signer private key, and server SSL certificates.');

        if (empty($error)) {
            return $base . ' ' . $hint;
        }

        // Trim very long raw stderr to keep admin flash message readable.
        $safe = mb_substr(trim($error), 0, 280);
        return $base . ' ' . $safe . ' ' . $hint;
    }

// phase  save process
    public function phaseAddProcess($request)
    {
        $response = ['success' => false, 'message' => __('Invalid request')];

        if ($this->resolvedPresaleContract() === '') {
            return ['success' => false, 'message' => __('Presale contract is not configured. Configure blockchain settings first.')];
        }

        try {
            $st_date = date('Y-m-d H:i:s', strtotime($request->start_date));
            $end_date = date('Y-m-d H:i:s', strtotime($request->end_date));

            $check = IcoPhase::where(function ($query) use ($st_date, $end_date) {
                $query->whereRaw('? between start_date and end_date', [$st_date])
                    ->OrwhereRaw('? between start_date and end_date', [$end_date]);
            });

            if ( !empty($request->edit_id) ) {
                $check = $check->where('id', '!=', decrypt($request->edit_id));
            }
            $check = $check->where('status', '!=', STATUS_DELETED);
            $check = $check->exists();

            if ( $check ) {
                $response = ['success' => false, 'message' => __('Phase is already active in this date')];
                return $response;
            }
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => __('Something went wrong')];
            return $response;
        }

        DB::beginTransaction();
        try {
            $data = [
                'start_date'        => date("Y-m-d H:i:s", strtotime($request->start_date)),
                'end_date'          => date("Y-m-d H:i:s", strtotime($request->end_date)),
                'rate'              => $request->rate,
                'amount'            => $request->amount,
                'affiliation_level' => $request->affiliation_level,
                'phase_name'        => $request->phase_name,
                'fees'              => 0,
                'bonus'             => isset($request->bonus) ? $request->bonus : 0,
                'status'            => $request->status,
            ];

            $isEdit = !empty($request->edit_id);

            if ($isEdit) {
                $phase = IcoPhase::find(decrypt($request->edit_id));
                if (!$phase) {
                    DB::rollBack();
                    return ['success' => false, 'message' => __('Invalid phase selected')];
                }

                if (!empty($phase->pending_onchain_tx)) {
                    DB::rollBack();
                    return ['success' => false, 'message' => __('This phase has a pending on-chain transaction: ') . $phase->pending_onchain_tx];
                }

                $phase->fill($data);
                $phase->save();
            } else {
                $phase = IcoPhase::create($data);
            }

            // ── On-chain sync ──────────────────────────────────────────────────
            $blockchain = new BlockchainService();
            $result = $this->broadcastPhaseWithRetry($blockchain, $isEdit, $phase);

            if (!$result || !isset($result['txHash'])) {
                $err = is_array($result) ? ($result['error'] ?? null) : null;
                throw new \RuntimeException($this->buildBroadcastErrorMessage($err));
            }

            $phase->contract_synced = false;
            $phase->pending_onchain_tx = $result['txHash'];
            $phase->save();

            DB::commit();

            $response = [
                'success' => true,
                'message' => $isEdit
                    ? __('Phase updated and broadcast on-chain. Pending tx: :tx', ['tx' => $result['txHash']])
                    : __('New phase created and broadcast on-chain. Pending tx: :tx', ['tx' => $result['txHash']]),
            ];

        } catch (\Exception $exception) {
            DB::rollback();
            Log::error('PhaseRepository::phaseAddProcess failed: ' . $exception->getMessage());
            $response = ['success' => false, 'message' => $exception->getMessage() ?: __('Something went wrong')];
            return $response;
        }

        return $response;
    }

// delete bank
    public function deleteBank($id)
    {
        $response = ['success' => false, 'message' => __('Invalid request')];
        DB::beginTransaction();
        try {
            $item = Bank::where('id',$id)->first();
            if (isset($item)) {
                $delete = $item->update(['status' => 5]);
                if ($delete) {
                    $response = [
                        'success' => true,
                        'message' => __('Bank deleted successfully.')
                    ];
                } else {
                    DB::rollBack();
                    $response = [
                        'success' => false,
                        'message' => __('Operation failed.')
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => __('Data not found.')
                ];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            return $response;
        }
        DB::commit();
        return $response;
    }

}
