<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarangResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'nama' => $this->nama,
            'harga' => $this->harga,
            'stok' => $this->stok,
            'stok_minimum' => $this->stok_minimum,
        ];
    }
}
