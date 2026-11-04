<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('payment_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('qr_image_path')->nullable();
            $table->timestamps();
        });
    }
};
