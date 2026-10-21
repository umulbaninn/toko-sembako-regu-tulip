<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_supplier', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // kunci idempotensi
            $table->string('sku', 7);
            $table->string('nama');
            $table->unsignedInteger('stok_sisa');
            $table->unsignedInteger('stok_minimum');
            $table->string('status')->default('menunggu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_supplier');
    }
};
