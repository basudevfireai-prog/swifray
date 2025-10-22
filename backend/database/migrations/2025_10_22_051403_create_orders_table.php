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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('customer_id')->constrained('users')->onDelete('restrict'); // Customer who placed the order
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null'); // Driver assigned to the order

            // Delivery Details
            $table->enum('delivery_type', ['parcel', 'grocery', 'food', 'catering']);
            $table->text('parcel_details');

            // Financials
            $table->decimal('total_amount', 10, 2);

            // Status
            $table->enum('status', [
                'pending',       // Initial booking
                'accepted',      // Driver accepted
                'in_transit',    // On the way to pickup or delivery
                'delivered',     // Completed
                'cancelled'      // Cancelled by customer or admin
            ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
