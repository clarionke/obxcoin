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
            'payment_type' => ['required'],
            'coin' => ['required', 'numeric'],
        ];

        if ($this->payment_type == NOWPAYMENTS) {
            $check['pay_currency'] = ['required', 'string', 'max:20'];
        }

        return $check;
    }
    public function messages()
    {
        $data['payment_type.required'] = __('Select your payment method');
        $data['bank_id.required'] = __('Must be select a bank');
        $data['sleep.required'] = __('Bank document is required');
        $data['payment_method_nonce.required'] = __('Invalid card ID or CVV');


        return $data;
    }


}


