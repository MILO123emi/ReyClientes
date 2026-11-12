<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false);
            $table->boolean('can_manage_cards')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
        });

        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX users_single_owner ON users (is_owner) WHERE is_owner');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_single_owner');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_owner', 'can_manage_cards', 'must_change_password', 'last_login_at']);
        });
    }
};
