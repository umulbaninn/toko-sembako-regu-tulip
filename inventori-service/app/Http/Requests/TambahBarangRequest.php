<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TambahBarangRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'regex:/^BRG-\d{3}$/', 'unique:barang,sku'],
            'nama' => ['required', 'string', 'max:100'],
            'harga' => ['required', 'integer', 'min:1'],
            'stok' => ['sometimes', 'integer', 'min:0'],
            'stok_minimum' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
