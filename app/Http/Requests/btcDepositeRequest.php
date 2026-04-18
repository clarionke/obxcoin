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
            'payment_type' => ['required', 'in:' . NOWPAYMENTS],
            'usdt_amount' => ['required', 'numeric', 'min:10'],
            'coin' => ['nullable', 'numeric'],
        ];

        if ((int)$this->input('payment_type') === NOWPAYMENTS) {
            $check['pay_currency'] = ['required', 'string', 'max:20'];
        }

        return $check;
    }
    public function messages()
    {
        $data['payment_type.required'] = __('Select your payment method');
        $data['payment_type.in'] = __('Invalid payment method selected.');
        $data['usdt_amount.required'] = __('Enter the USDT amount you want to pay.');
        $data['usdt_amount.numeric'] = __('USDT amount must be numeric.');
        $data['usdt_amount.min'] = __('USDT amount must be greater than zero.');
        $data['bank_id.required'] = __('Must be select a bank');
        $data['sleep.required'] = __('Bank document is required');
        $data['payment_method_nonce.required'] = __('Invalid card ID or CVV');


        return $data;
    }


}


