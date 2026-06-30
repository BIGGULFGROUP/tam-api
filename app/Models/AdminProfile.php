<?php

namespace App\Models;

use App\Support\AdminRoleRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class AdminProfile extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'admin_profiles';

    protected $fillable = [
        'email',
        'password',
        'email_verified_at',
        'email_verification_token',
        'email_verification_sent_at',
        'display_name',
        'full_name',
        'username',
        'role',
        'access_tier',
        'can_access_frontend_panel',
        'can_access_backend_panel',
        'bio',
        'avatar_url',
        'website_url',
        'twitter_url',
        'instagram_url',
        'linkedin_url',
        'location',
        'is_public',
        'is_active',
        'last_login',
        'author_slug',
        'article_count',
        'video_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
    ];

    protected function casts(): array
    {
        return [
            'password'  => 'hashed',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'can_access_frontend_panel' => 'boolean',
            'can_access_backend_panel' => 'boolean',
            'last_login' => 'datetime',
            'email_verified_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'article_count' => 'integer',
            'video_count' => 'integer',
        ];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $admin): void {
            $normalizedRole = AdminRoleRegistry::normalize($admin->role);
            $panelAccess = AdminRoleRegistry::panelAccessFor($normalizedRole);

            $admin->role = $normalizedRole;
            $admin->access_tier = AdminRoleRegistry::tierFor($normalizedRole);
            $admin->can_access_frontend_panel = $panelAccess['frontend'];
            $admin->can_access_backend_panel = $panelAccess['backend'];

            if ($admin->author_slug) {
                return;
            }

            $base = Str::slug($admin->display_name ?: $admin->full_name ?: Str::before($admin->email, '@'));
            $base = $base !== '' ? $base : 'author';
            $slug = $base;
            $suffix = 2;

            while (
                static::query()
                    ->where('author_slug', $slug)
                    ->when($admin->exists, fn ($query) => $query->whereKeyNot($admin->getKey()))
                    ->exists()
            ) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $admin->author_slug = $slug;
        });
    }

    /** Roles that can access the backend admin panel. */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isFrontendAdmin(): bool
    {
        return $this->access_tier === AdminRoleRegistry::FRONTEND_ADMIN;
    }

    public function isBackendAdmin(): bool
    {
        return $this->access_tier === AdminRoleRegistry::BACKEND_ADMIN;
    }

    public function permissions(): array
    {
        return AdminRoleRegistry::permissionsFor($this->role);
    }

    public function hasPermission(string $permission): bool
    {
        return (bool) ($this->permissions()[$permission] ?? false);
    }

    public function canAccessFrontendPanel(): bool
    {
        return (bool) $this->can_access_frontend_panel;
    }

    public function canAccessBackendPanel(): bool
    {
        return (bool) $this->can_access_backend_panel;
    }

    public function content(): HasMany
    {
        return $this->hasMany(Video::class, 'created_by');
    }

    protected function panelAccess(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'frontend' => (bool) $this->can_access_frontend_panel,
                'backend' => (bool) $this->can_access_backend_panel,
            ],
        );
    }
}
