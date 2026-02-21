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
        Schema::create('rma_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_id')->constrained('rma_requests')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->string('type'); // Internal, External, System
            $table->text('comment');
            $table->boolean('notify_customer')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('r_m_a_comments');
    }
};
