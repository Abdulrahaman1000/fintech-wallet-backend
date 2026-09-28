<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FundWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user may fund their own wallet
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'in:NGN,USD,USDT'],
            'amount' => ['required', 'numeric', 'min:0.01'], // major units, e.g. 500.00
            'idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }
}