<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clients', 'authorized_people'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('search_name', 120)->default('')->index();
            });
            DB::table($tableName)->select('id', 'full_name')->chunkById(200, function (Collection $rows) use ($tableName): void {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update([
                        'search_name' => Str::squish(Str::lower(Str::ascii($row->full_name))),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['clients', 'authorized_people'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['search_name']);
                $table->dropColumn('search_name');
            });
        }
    }
};
