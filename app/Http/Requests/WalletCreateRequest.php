<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletCreateRequest extends FormRequest
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
        if($this->coin_type == 'LTCT') {
            $rules = [
                'wallet_name' => 'required|max:100',
                'coin_type' => 'required'
            ];
        } else {
            $rules = [
                'wallet_name' => 'required|max:100',
                'coin_type' => 'required|exists:coins,type'
            ];
        }
        if(co_wallet_feature_active())
        $rules['type'] = 'required|in:'.PERSONAL_WALLET.','.CO_WALLET;

        if (co_wallet_feature_active() && (int)$this->type === CO_WALLET) {
            $rules['max_co_users'] = 'required|integer|min:2|max:100';
        }

        return $rules;
    }

    public function messages()
    {
        return [
                    'wallet_name.required' => __('Wallet name is required'),
                    'type.required' => __('Wallet type is required'),
                    'type.in' => __('Invalid wallet type'),
          'coin_type.required' => __('Coin type is required'),
          'coin_type.exists' => __('Invalid coin type'),
                    'max_co_users.required' => __('Maximum user capacity is required for multi-signature wallet'),
                    'max_co_users.min' => __('Multi-signature wallet must allow at least 2 members'),
        ];
    }
}
