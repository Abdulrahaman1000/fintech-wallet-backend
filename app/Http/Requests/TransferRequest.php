<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email', 'exists:users,email'],
            'currency' => ['required', 'in:NGN,USD,USDT'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:191', 'unique:transfers,reference'],
            'narration' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('recipient_email') === $this->user()?->email) {
                $validator->errors()->add('recipient_email', 'You cannot transfer funds to yourself.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'recipient_email.exists' => 'No user was found with that email address.',
            'reference.unique' => 'This transfer reference has already been used.',
        ];
    }
}