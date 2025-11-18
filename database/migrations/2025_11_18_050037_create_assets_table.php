<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama akun (misal: "Akun Shopee 1")
            $table->string('platform'); // Platform (Shopee, Tokopedia, TikTok)
            $table->text('credentials')->nullable(); // Username/password (encrypt)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};