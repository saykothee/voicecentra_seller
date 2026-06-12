<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->dateTime('sold_at');
            $table->enum('status', ['pending', 'approved', 'rejected', 'refunded'])->default('pending');
            $table->string('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status', 'sold_at']); // activity-gate query
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
