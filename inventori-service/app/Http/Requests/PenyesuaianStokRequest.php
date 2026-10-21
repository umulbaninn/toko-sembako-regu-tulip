<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jumlah' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'in:penjualan,restok'],
            'referensi' => ['required', 'string', 'max:50'],
        ];
    }
}
