<?php

namespace App\Exceptions;

use RuntimeException;

class StokTidakCukup extends RuntimeException
{
    public function __construct(
        public readonly string $nama,
        public readonly int $tersedia,
        public readonly int $diminta,
    ) {
        parent::__construct("Stok {$nama} tersisa {$tersedia}, diminta {$diminta}.");
    }
}