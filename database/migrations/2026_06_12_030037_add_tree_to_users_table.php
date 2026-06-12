<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('approved_by')
                ->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(1)->after('parent_id');
            $table->string('referral_code', 8)->nullable()->unique()->after('depth');
        });

        // Backfill referral codes for existing users.
        foreach (DB::table('users')->whereNull('referral_code')->pluck('id') as $id) {
            do {
                $code = strtoupper(Str::random(8));
            } while (DB::table('users')->where('referral_code', $code)->exists());
            DB::table('users')->where('id', $id)->update(['referral_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['depth', 'referral_code']);
        });
    }
};
