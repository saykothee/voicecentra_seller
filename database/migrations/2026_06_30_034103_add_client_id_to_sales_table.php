<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // External reference to the client the sale was made for (CRM id, etc.).
            $table->string('client_id')->nullable()->after('seller_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
