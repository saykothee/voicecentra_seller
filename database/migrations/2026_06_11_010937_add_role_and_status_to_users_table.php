<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['seller', 'admin'])->default('seller')->after('email');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('role');
            $table->string('phone')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('phone');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['role', 'status', 'phone', 'approved_at', 'approved_by']);
        });
    }
};
