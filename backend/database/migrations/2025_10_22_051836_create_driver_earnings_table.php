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
        Schema::create('driver_earnings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('driver_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('order_id')->unique()->constrained()->onDelete('restrict'); // One earning record per order

            $table->decimal('amount', 10, 2);
            $table->dateTime('payment_date')->nullable(); // Date when the driver was actually paid out
            $table->enum('status', ['pending', 'paid'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_earnings');
    }
};
