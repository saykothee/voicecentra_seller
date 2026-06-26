<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dateTime('paid_at')->nullable()->after('sold_at');
            $table->boolean('paid')->default(false)->after('paid_at');
            $table->boolean('trial')->default(false)->after('paid');
        });

        // External sales are now recorded directly in the `sales` table.
        Schema::dropIfExists('external_sales');
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'paid', 'trial']);
        });

        Schema::create('external_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->date('sale_date');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('paid')->default(false);
            $table->boolean('free_trial')->default(false);
            $table->timestamps();
            $table->index(['seller_id', 'sale_date']);
        });
    }
};
