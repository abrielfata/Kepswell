<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Host
            $table->foreignId('asset_id')->constrained()->onDelete('cascade'); // Akun yang digunakan
            $table->dateTime('scheduled_at'); // Waktu dijadwalkan
            $table->string('google_calendar_event_id')->nullable(); // ID dari Google Calendar
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->decimal('gmv', 15, 2)->nullable(); // GMV dari OCR
            $table->string('screenshot_path')->nullable(); // Path screenshot
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};