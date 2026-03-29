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
        Schema::table('rma_attachments', function (Blueprint $table) {
            // Make Cloudinary fields nullable
            $table->string('cloudinary_public_id')->nullable()->change();
            $table->string('cloudinary_url')->nullable()->change();
            
            // Add local file path
            $table->string('file_path')->nullable()->after('file_size');
            
            // Update storage_type default (optional, as it's already there but we want it clear)
            $table->string('storage_type')->default('local')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rma_attachments', function (Blueprint $table) {
            $table->string('cloudinary_public_id')->nullable(false)->change();
            $table->string('cloudinary_url')->nullable(false)->change();
            $table->dropColumn('file_path');
            $table->string('storage_type')->default('cloudinary')->change();
        });
    }
};
