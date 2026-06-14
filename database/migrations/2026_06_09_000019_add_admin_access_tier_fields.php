<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_profiles', 'access_tier')) {
                $table->string('access_tier')->nullable()->after('role');
            }
            if (! Schema::hasColumn('admin_profiles', 'can_access_frontend_panel')) {
                $table->boolean('can_access_frontend_panel')->default(false)->after('access_tier');
            }
            if (! Schema::hasColumn('admin_profiles', 'can_access_backend_panel')) {
                $table->boolean('can_access_backend_panel')->default(false)->after('can_access_frontend_panel');
            }
        });

        DB::statement("
            UPDATE admin_profiles
            SET
                access_tier = CASE
                    WHEN role IN ('super_admin', 'support', 'hr', 'analyst', 'moderator') THEN 'backend_admin'
                    ELSE 'frontend_admin'
                END,
                can_access_frontend_panel = CASE
                    WHEN role IN ('super_admin', 'editor', 'video_producer', 'writer', 'researcher', 'contributor', 'author', 'guest_writer') THEN true
                    ELSE false
                END,
                can_access_backend_panel = CASE
                    WHEN role IN ('super_admin', 'support', 'hr', 'analyst', 'moderator') THEN true
                    ELSE false
                END
        ");

        DB::statement("ALTER TABLE admin_profiles ALTER COLUMN access_tier SET NOT NULL");

        if (! $this->hasConstraint('admin_profiles_access_tier_check')) {
            DB::statement(
                "ALTER TABLE admin_profiles ADD CONSTRAINT admin_profiles_access_tier_check " .
                "CHECK (access_tier IN ('frontend_admin', 'backend_admin'))"
            );
        }
    }

    public function down(): void
    {
        if ($this->hasConstraint('admin_profiles_access_tier_check')) {
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT admin_profiles_access_tier_check');
        }

        Schema::table('admin_profiles', function (Blueprint $table) {
            $drops = [];

            foreach (['access_tier', 'can_access_frontend_panel', 'can_access_backend_panel'] as $column) {
                if (Schema::hasColumn('admin_profiles', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }

    private function hasConstraint(string $constraintName): bool
    {
        return DB::table('pg_constraint')->where('conname', $constraintName)->exists();
    }
};
