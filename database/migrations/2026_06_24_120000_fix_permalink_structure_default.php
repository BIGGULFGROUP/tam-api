<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix the permalink_structure default so it doesn't violate the CHECK constraint.
     * Also backfill any existing rows with an invalid value.
     */
    public function up(): void
    {
        // Fix any existing rows with invalid values
        DB::table('site_settings')
            ->whereNotIn('permalink_structure', ['plain', 'type-slug', 'type-date-slug'])
            ->orWhereNull('permalink_structure')
            ->update(['permalink_structure' => 'plain']);

        // Change column default from '/{slug}' to 'plain'
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE site_settings ALTER COLUMN permalink_structure SET DEFAULT 'plain'");
        } elseif ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE site_settings ALTER COLUMN permalink_structure SET DEFAULT 'plain'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE site_settings ALTER COLUMN permalink_structure SET DEFAULT '/{slug}'");
        } elseif ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE site_settings ALTER COLUMN permalink_structure SET DEFAULT '/{slug}'");
        }
    }
};
