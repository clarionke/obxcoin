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
        $rules = [
            'wallet_name' => 'required|max:100',
            'coin_type' => 'required|exists:coins,type'
        ];
        if(co_wallet_feature_active())
        $rules['type'] = 'required|in:'.PERSONAL_WALLET.','.CO_WALLET;

        if (co_wallet_feature_active() && (int)$this->type === CO_WALLET) {
            $rules['max_co_users'] = 'required|integer|min:2|max:100';
            $rules['approval_timeout_minutes'] = 'nullable|integer|min:5|max:10080';
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
                    'max_co_users.required' => __('Maximum user capacity is required for Team Wallet'),
                    'max_co_users.min' => __('Team Wallet must allow at least 2 members'),
                    'approval_timeout_minutes.min' => __('Approval duration must be at least 5 minutes'),
                    'approval_timeout_minutes.max' => __('Approval duration must not exceed 10080 minutes (7 days)'),
        ];
    }
}
