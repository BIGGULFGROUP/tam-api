<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_profiles', 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('admin_profiles', 'author_slug')) {
                $table->string('author_slug')->nullable()->after('is_public');
            }
            if (! Schema::hasColumn('admin_profiles', 'article_count')) {
                $table->unsignedInteger('article_count')->default(0)->after('author_slug');
            }
            if (! Schema::hasColumn('admin_profiles', 'video_count')) {
                $table->unsignedInteger('video_count')->default(0)->after('article_count');
            }
        });

        $this->backfillAuthorSlugs();
        $this->syncAdminProfileContentCounts();

        if (! $this->hasIndex('admin_profiles', 'admin_profiles_author_slug_unique')) {
            Schema::table('admin_profiles', function (Blueprint $table) {
                $table->unique('author_slug', 'admin_profiles_author_slug_unique');
            });
        }

        Schema::table('videos', function (Blueprint $table) {
            if (! Schema::hasColumn('videos', 'collaborator_ids')) {
                $table->jsonb('collaborator_ids')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('videos', 'source_channel_id')) {
                $table->string('source_channel_id')->nullable()->after('youtube_id');
            }
            if (! Schema::hasColumn('videos', 'source_channel_name')) {
                $table->string('source_channel_name')->nullable()->after('source_channel_id');
            }
            if (! Schema::hasColumn('videos', 'source_channel_slug')) {
                $table->string('source_channel_slug')->nullable()->after('source_channel_name');
            }
            if (! Schema::hasColumn('videos', 'key_takeaways')) {
                $table->jsonb('key_takeaways')->nullable()->after('body');
            }
        });

        DB::table('videos')
            ->whereNull('collaborator_ids')
            ->update(['collaborator_ids' => DB::raw("'[]'::jsonb")]);

        DB::table('videos')
            ->whereNull('source_channel_name')
            ->whereNotNull('author')
            ->update(['source_channel_name' => DB::raw('author')]);

        DB::table('videos')
            ->whereNull('source_channel_slug')
            ->whereNotNull('source_channel_name')
            ->update([
                'source_channel_slug' => DB::raw("NULLIF(regexp_replace(lower(source_channel_name), '[^a-z0-9]+', '-', 'g'), '')"),
            ]);

        DB::table('videos')
            ->whereNotNull('created_by')
            ->whereNotIn('created_by', DB::table('admin_profiles')->select('id'))
            ->update(['created_by' => null]);

        DB::table('videos')
            ->whereNotNull('reviewed_by')
            ->whereNotIn('reviewed_by', DB::table('admin_profiles')->select('id'))
            ->update(['reviewed_by' => null]);

        if (! $this->hasIndex('videos', 'videos_source_channel_id_index')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->index('source_channel_id', 'videos_source_channel_id_index');
            });
        }

        if (! $this->hasIndex('videos', 'videos_source_channel_slug_index')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->index('source_channel_slug', 'videos_source_channel_slug_index');
            });
        }

        if (! $this->hasConstraint('videos_created_by_foreign')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->foreign('created_by', 'videos_created_by_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasConstraint('videos_reviewed_by_foreign')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->foreign('reviewed_by', 'videos_reviewed_by_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }

        DB::table('content_revisions')
            ->whereNotNull('saved_by')
            ->whereNotIn('saved_by', DB::table('admin_profiles')->select('id'))
            ->update(['saved_by' => null]);

        if (! $this->hasConstraint('content_revisions_saved_by_foreign')) {
            Schema::table('content_revisions', function (Blueprint $table) {
                $table->foreign('saved_by', 'content_revisions_saved_by_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }

        DB::table('comments')
            ->whereNotNull('content_id')
            ->whereNotIn('content_id', DB::table('videos')->select('id'))
            ->update(['content_id' => null]);

        if (! $this->hasConstraint('comments_content_id_foreign')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->foreign('content_id', 'comments_content_id_foreign')
                    ->references('id')
                    ->on('videos')
                    ->cascadeOnDelete();
            });
        }

        DB::table('media')
            ->whereNotNull('uploaded_by')
            ->whereNotIn('uploaded_by', DB::table('admin_profiles')->select('id'))
            ->update(['uploaded_by' => null]);

        if (! $this->hasConstraint('media_uploaded_by_foreign')) {
            Schema::table('media', function (Blueprint $table) {
                $table->foreign('uploaded_by', 'media_uploaded_by_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }

        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_subscribers', 'name')) {
                $table->string('name')->nullable()->after('email');
            }
            if (! Schema::hasColumn('newsletter_subscribers', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('source');
            }
            if (! Schema::hasColumn('newsletter_subscribers', 'subscription_context')) {
                $table->jsonb('subscription_context')->nullable()->after('popup_type');
            }
        });

        DB::table('newsletter_subscribers')
            ->whereNull('subscription_context')
            ->update(['subscription_context' => DB::raw("'{}'::jsonb")]);

        Schema::table('newsletter_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_campaigns', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('is_active');
            }
        });

        DB::table('newsletter_campaigns')
            ->whereNotNull('created_by')
            ->whereNotIn('created_by', DB::table('admin_profiles')->select('id'))
            ->update(['created_by' => null]);

        if (! $this->hasConstraint('newsletter_campaigns_created_by_foreign')) {
            Schema::table('newsletter_campaigns', function (Blueprint $table) {
                $table->foreign('created_by', 'newsletter_campaigns_created_by_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }

        Schema::table('newsletter_popup_events', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_popup_events', 'page_path')) {
                $table->text('page_path')->nullable()->after('category_slug');
            }
        });

        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'articles_enabled')) {
                $table->boolean('articles_enabled')->default(true)->after('newsletter_enabled');
            }
            if (! Schema::hasColumn('site_settings', 'review_workflow')) {
                $table->boolean('review_workflow')->default(false)->after('articles_enabled');
            }
            if (! Schema::hasColumn('site_settings', 'autosave_interval')) {
                $table->unsignedSmallInteger('autosave_interval')->default(90)->after('review_workflow');
            }
            if (! Schema::hasColumn('site_settings', 'youtube_channel_id')) {
                $table->string('youtube_channel_id')->default('')->after('youtube_api_key');
            }
        });

        DB::table('site_settings')
            ->whereNull('permalink_structure')
            ->orWhereNotIn('permalink_structure', ['plain', 'type-slug', 'type-date-slug'])
            ->update(['permalink_structure' => 'plain']);

        if (! $this->hasConstraint('site_settings_permalink_structure_check')) {
            DB::statement(
                "ALTER TABLE site_settings ADD CONSTRAINT site_settings_permalink_structure_check " .
                "CHECK (permalink_structure IN ('plain', 'type-slug', 'type-date-slug'))"
            );
        }

        if (! $this->hasConstraint('site_settings_newsletter_popup_interval_hours_check')) {
            DB::statement(
                "ALTER TABLE site_settings ADD CONSTRAINT site_settings_newsletter_popup_interval_hours_check " .
                "CHECK (newsletter_popup_interval_hours BETWEEN 1 AND 168)"
            );
        }

        if (! $this->hasConstraint('site_settings_shorts_autofetch_interval_hours_check')) {
            DB::statement(
                "ALTER TABLE site_settings ADD CONSTRAINT site_settings_shorts_autofetch_interval_hours_check " .
                "CHECK (shorts_autofetch_interval_hours BETWEEN 1 AND 168)"
            );
        }

        if (! $this->hasConstraint('site_settings_max_shorts_per_channel_check')) {
            DB::statement(
                "ALTER TABLE site_settings ADD CONSTRAINT site_settings_max_shorts_per_channel_check " .
                "CHECK (max_shorts_per_channel BETWEEN 1 AND 50)"
            );
        }

        DB::statement('ALTER TABLE content_submissions ALTER COLUMN content_id DROP NOT NULL');

        DB::table('content_submissions')
            ->whereNotNull('content_id')
            ->whereNotIn('content_id', DB::table('videos')->select('id'))
            ->update(['content_id' => null]);

        DB::table('content_submissions')
            ->whereNotNull('reviewer_id')
            ->whereNotIn('reviewer_id', DB::table('admin_profiles')->select('id'))
            ->update(['reviewer_id' => null, 'reviewer_label' => null]);

        if (! $this->hasConstraint('content_submissions_content_id_foreign')) {
            Schema::table('content_submissions', function (Blueprint $table) {
                $table->foreign('content_id', 'content_submissions_content_id_foreign')
                    ->references('id')
                    ->on('videos')
                    ->cascadeOnDelete();
            });
        }

        if (
            ! $this->hasConstraint('content_submissions_submitted_by_foreign')
            && ! DB::table('content_submissions')
                ->whereNotIn('submitted_by', DB::table('admin_profiles')->select('id'))
                ->exists()
        ) {
            Schema::table('content_submissions', function (Blueprint $table) {
                $table->foreign('submitted_by', 'content_submissions_submitted_by_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->hasConstraint('content_submissions_reviewer_id_foreign')) {
            Schema::table('content_submissions', function (Blueprint $table) {
                $table->foreign('reviewer_id', 'content_submissions_reviewer_id_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }

        DB::table('submission_events')
            ->whereNotNull('actor_id')
            ->whereNotIn('actor_id', DB::table('admin_profiles')->select('id'))
            ->update(['actor_id' => null, 'actor_label' => null]);

        if (! $this->hasConstraint('submission_events_submission_id_foreign')) {
            Schema::table('submission_events', function (Blueprint $table) {
                $table->foreign('submission_id', 'submission_events_submission_id_foreign')
                    ->references('id')
                    ->on('content_submissions')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->hasConstraint('submission_events_actor_id_foreign')) {
            Schema::table('submission_events', function (Blueprint $table) {
                $table->foreign('actor_id', 'submission_events_actor_id_foreign')
                    ->references('id')
                    ->on('admin_profiles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropConstraintIfExists('submission_events_actor_id_foreign');
        $this->dropConstraintIfExists('submission_events_submission_id_foreign');
        $this->dropConstraintIfExists('content_submissions_reviewer_id_foreign');
        $this->dropConstraintIfExists('content_submissions_submitted_by_foreign');
        $this->dropConstraintIfExists('content_submissions_content_id_foreign');
        $this->dropConstraintIfExists('newsletter_campaigns_created_by_foreign');
        $this->dropConstraintIfExists('media_uploaded_by_foreign');
        $this->dropConstraintIfExists('comments_content_id_foreign');
        $this->dropConstraintIfExists('content_revisions_saved_by_foreign');
        $this->dropConstraintIfExists('videos_reviewed_by_foreign');
        $this->dropConstraintIfExists('videos_created_by_foreign');

        $this->dropConstraintIfExists('site_settings_max_shorts_per_channel_check');
        $this->dropConstraintIfExists('site_settings_shorts_autofetch_interval_hours_check');
        $this->dropConstraintIfExists('site_settings_newsletter_popup_interval_hours_check');
        $this->dropConstraintIfExists('site_settings_permalink_structure_check');

        if ($this->hasIndex('admin_profiles', 'admin_profiles_author_slug_unique')) {
            Schema::table('admin_profiles', function (Blueprint $table) {
                $table->dropUnique('admin_profiles_author_slug_unique');
            });
        }

        if ($this->hasIndex('videos', 'videos_source_channel_id_index')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->dropIndex('videos_source_channel_id_index');
            });
        }

        if ($this->hasIndex('videos', 'videos_source_channel_slug_index')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->dropIndex('videos_source_channel_slug_index');
            });
        }

        Schema::table('newsletter_popup_events', function (Blueprint $table) {
            if (Schema::hasColumn('newsletter_popup_events', 'page_path')) {
                $table->dropColumn('page_path');
            }
        });

        Schema::table('newsletter_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('newsletter_campaigns', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $drops = [];
            foreach (['name', 'confirmed_at', 'subscription_context'] as $column) {
                if (Schema::hasColumn('newsletter_subscribers', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $drops = [];
            foreach (['articles_enabled', 'review_workflow', 'autosave_interval', 'youtube_channel_id'] as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });

        Schema::table('videos', function (Blueprint $table) {
            $drops = [];
            foreach (['collaborator_ids', 'source_channel_id', 'source_channel_name', 'source_channel_slug', 'key_takeaways'] as $column) {
                if (Schema::hasColumn('videos', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            $drops = [];
            foreach (['last_login', 'author_slug', 'article_count', 'video_count'] as $column) {
                if (Schema::hasColumn('admin_profiles', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }

    private function backfillAuthorSlugs(): void
    {
        $usedSlugs = [];

        $profiles = DB::table('admin_profiles')
            ->select('id', 'email', 'display_name', 'full_name', 'author_slug')
            ->orderBy('created_at')
            ->get();

        foreach ($profiles as $profile) {
            $base = $profile->author_slug ?: Str::slug(
                $profile->display_name ?: $profile->full_name ?: Str::before((string) $profile->email, '@')
            );

            if ($base === '') {
                $base = 'author';
            }

            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $usedSlugs[] = $slug;

            DB::table('admin_profiles')
                ->where('id', $profile->id)
                ->update(['author_slug' => $slug]);
        }
    }

    private function syncAdminProfileContentCounts(): void
    {
        DB::table('admin_profiles')->update([
            'article_count' => 0,
            'video_count' => 0,
        ]);

        $counts = DB::table('videos')
            ->selectRaw("
                created_by,
                SUM(CASE WHEN content_type = 'article' THEN 1 ELSE 0 END) AS article_count,
                SUM(CASE WHEN content_type <> 'article' OR content_type IS NULL THEN 1 ELSE 0 END) AS video_count
            ")
            ->whereNotNull('created_by')
            ->groupBy('created_by')
            ->get();

        foreach ($counts as $row) {
            DB::table('admin_profiles')
                ->where('id', $row->created_by)
                ->update([
                    'article_count' => (int) $row->article_count,
                    'video_count' => (int) $row->video_count,
                ]);
        }
    }

    private function hasConstraint(string $constraintName): bool
    {
        return DB::table('pg_constraint')->where('conname', $constraintName)->exists();
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return DB::table('pg_indexes')
            ->where('tablename', $tableName)
            ->where('indexname', $indexName)
            ->exists();
    }

    private function dropConstraintIfExists(string $constraintName): void
    {
        if ($this->hasConstraint($constraintName)) {
            DB::statement("ALTER TABLE {$this->tableForConstraint($constraintName)} DROP CONSTRAINT {$constraintName}");
        }
    }

    private function tableForConstraint(string $constraintName): string
    {
        return match ($constraintName) {
            'videos_created_by_foreign', 'videos_reviewed_by_foreign' => 'videos',
            'content_revisions_saved_by_foreign' => 'content_revisions',
            'comments_content_id_foreign' => 'comments',
            'media_uploaded_by_foreign' => 'media',
            'newsletter_campaigns_created_by_foreign' => 'newsletter_campaigns',
            'content_submissions_content_id_foreign',
            'content_submissions_submitted_by_foreign',
            'content_submissions_reviewer_id_foreign' => 'content_submissions',
            'submission_events_submission_id_foreign',
            'submission_events_actor_id_foreign' => 'submission_events',
            'site_settings_permalink_structure_check',
            'site_settings_newsletter_popup_interval_hours_check',
            'site_settings_shorts_autofetch_interval_hours_check',
            'site_settings_max_shorts_per_channel_check' => 'site_settings',
            default => throw new InvalidArgumentException("Unknown constraint: {$constraintName}"),
        };
    }
};
