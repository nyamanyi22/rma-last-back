<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rma_attachments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('rma_request_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('uploaded_by')
                ->constrained('users');

            // File Information
            $table->string('original_name');        // receipt.jpg
            $table->string('mime_type');            // image/jpeg
            $table->integer('file_size');           // 5242880 (bytes)

            // Cloudinary Specific
            $table->string('cloudinary_public_id'); // rma-attachments/user-1/rma-5/abc123
            $table->string('cloudinary_url');       // https://res.cloudinary.com/...
            $table->json('cloudinary_metadata')      // width, height, format, version
                ->nullable();

            // Tracking
            $table->string('storage_type')
                ->default('cloudinary');          // Always 'cloudinary'

            $table->timestamps();

            // Indexes for fast queries
            $table->index('rma_request_id');
            $table->index('uploaded_by');
            $table->index('cloudinary_public_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rma_attachments');
    }
};