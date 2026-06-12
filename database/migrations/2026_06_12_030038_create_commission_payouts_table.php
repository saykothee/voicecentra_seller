<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('level'); // 0 = seller, 1..9 = upline
            $table->unsignedSmallInteger('rate_numerator'); // n of n/5120
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('recipient_was_active')->default(true);
            $table->enum('status', ['paid', 'reversed'])->default('paid');
            $table->timestamps();
            $table->index(['recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
    }
};
