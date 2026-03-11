<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Add customer_email after invoice_number if it doesn't exist
            if (!Schema::hasColumn('sales', 'customer_email')) {
                $table->string('customer_email')->after('invoice_number');
            }

            // Add customer_name after customer_email if it doesn't exist
            if (!Schema::hasColumn('sales', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('customer_email');
            }

            // Add quantity after product_id if it doesn't exist
            if (!Schema::hasColumn('sales', 'quantity')) {
                $table->integer('quantity')->default(1)->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['customer_email', 'customer_name', 'quantity']);
        });
    }
};