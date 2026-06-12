<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bonus_pool_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->nullable(); // null = rounding/reversal row
            $table->bigInteger('amount_cents'); // signed: negative on refund reversal
            $table->enum('reason', ['no_upline', 'inactive_upline', 'rounding', 'refund_reversal']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_pool_entries');
    }
};
