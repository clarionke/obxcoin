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
// phase  save process
    public function phaseAddProcess($request)
    {
        $response = ['success' => false, 'message' => __('Invalid request')];
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
                $phase = IcoPhase::updateOrCreate(['id' => decrypt($request->edit_id)], $data);
                $response = ['success' => true, 'message' => __('Phase updated successfully')];
            } else {
                $phase = IcoPhase::create($data);
                $response = ['success' => true, 'message' => __('New phase created successfully')];
            }

            DB::commit();

            // ── On-chain sync ──────────────────────────────────────────────────
            if (config('blockchain.presale_contract')) {
                try {
                    $blockchain = new BlockchainService();
                    $phaseData  = $phase->toArray();

                    if ($isEdit && $phase->contract_synced && $phase->contract_phase_index !== null) {
                        // Update existing phase on chain
                        $txHash = $blockchain->updatePhaseOnContract(array_merge($phaseData, [
                            'active' => $phase->status == STATUS_ACTIVE,
                        ]));
                        if ($txHash) {
                            Log::info("Phase #{$phase->id} updated on-chain: $txHash");
                        }
                    } else {
                        // Push new phase to contract, get its array index
                        $txHash = $blockchain->pushPhaseToContract($phaseData);
                        if ($txHash) {
                            // contract_phase_index = current total phases count before this push
                            // The contract appends so index = previous length
                            // We store it after confirmation in the webhook; mark synced=false until confirmed
                            $phase->update(['contract_synced' => false]);
                            Log::info("Phase #{$phase->id} pushed on-chain (pending): $txHash");
                        }
                    }
                } catch (\Exception $chainEx) {
                    // On-chain failure does NOT rollback the DB record — admin can retry
                    Log::error("On-chain phase sync failed for phase #{$phase->id}: " . $chainEx->getMessage());
                }
            }

        } catch (\Exception $exception) {
            DB::rollback();
            $response = ['success' => false, 'message' => __('Something went wrong')];
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
