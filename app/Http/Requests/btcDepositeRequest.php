<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class btcDepositeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $check = [
            'payment_type' => ['required', 'in:' . NOWPAYMENTS . ',' . WALLETCONNECT],
            'coin' => ['required', 'numeric'],
        ];

        if ((int)$this->input('payment_type') === NOWPAYMENTS) {
            $check['pay_currency'] = ['required', 'string', 'max:20'];
        }

        if ((int)$this->input('payment_type') === WALLETCONNECT) {
            $check['wc_buyer_address'] = ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'];
            $check['tx_hash'] = ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'];
        }

        return $check;
    }
    public function messages()
    {
        $data['payment_type.required'] = __('Select your payment method');
        $data['payment_type.in'] = __('Invalid payment method selected.');
        $data['wc_buyer_address.required'] = __('Wallet address is required for WalletConnect.');
        $data['wc_buyer_address.regex'] = __('Invalid wallet address format.');
        $data['tx_hash.required'] = __('Transaction hash is required for WalletConnect.');
        $data['tx_hash.regex'] = __('Invalid transaction hash format.');
        $data['bank_id.required'] = __('Must be select a bank');
        $data['sleep.required'] = __('Bank document is required');
        $data['payment_method_nonce.required'] = __('Invalid card ID or CVV');


        return $data;
    }


}


