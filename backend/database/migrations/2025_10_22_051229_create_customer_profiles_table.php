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
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            // Foreign Key to the existing 'users' table
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');

            // Customer specific fields
            $table->string('default_address')->nullable();

            // AI Chat placeholder (for future use)
            $table->text('ai_chat_settings')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
