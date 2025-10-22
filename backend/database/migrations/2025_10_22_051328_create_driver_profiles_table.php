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
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            // Foreign Key to the existing 'users' table
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');

            // Driver-specific registration details
            $table->string('license_number')->unique();
            $table->text('insurance_details')->nullable();
            $table->string('vehicle_type')->nullable(); // e.g., 'bike', 'car', 'van'

            // Document Management
            $table->string('license_doc_url')->nullable();
            $table->string('insurance_doc_url')->nullable();

            // Status Management
            $table->enum('document_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->boolean('is_available')->default(false); // Driver Status Management (Available / Busy)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
