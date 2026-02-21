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
        Schema::create('rma_requests', function (Blueprint $table) {
            $table->id();
            $table->string('rma_number')->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->string('rma_type'); // Enum RMAType
            $table->string('reason'); // Enum RMAReason
            $table->boolean('requires_warranty_check')->default(false);
            $table->boolean('is_warranty_valid')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->string('serial_number_provided')->nullable();
            $table->string('receipt_number')->nullable();
            $table->text('issue_description');
            $table->json('attachments')->nullable();
            $table->string('status')->default('pending'); // Enum RMAStatus
            $table->string('priority')->default('medium'); // Enum RMAPriority
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('r_m_a_requests');
    }
};
