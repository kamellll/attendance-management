<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class LoginRequest extends FormRequest
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
            'email' => [
                'required',
                'string',
                'email',
            ],
            'password' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        // エラーメッセージ内で使われる項目名（日本語にしておくと便利）
        return [
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => 'メールアドレスのはメール形式で入力してください',
            'password.required' => 'パスワードを入力してください',
        ];
    }
}
