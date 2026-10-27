<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->id();                                   // urutan kirim
            $table->uuid('message_id')->unique();           // penolak duplikat
            $table->string('jenis', 60);                    // StokDisesuaikan
            $table->string('routing_key', 60);              // stok.penjualan
            $table->json('payload');                        // seluruh amplop event
            $table->timestamp('dikirim_pada')->nullable()->index(); // NULL = belum terkirim
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
