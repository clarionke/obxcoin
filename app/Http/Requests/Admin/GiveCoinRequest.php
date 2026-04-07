<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GiveCoinRequest extends FormRequest
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
        return [
            'amount' => 'required|numeric|gt:0|min:'.settings('admin_send_default_minimum').'|max:'.settings('admin_send_default_maximum'),
            'user_id' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => __('Please select user')
        ];
    }
}
