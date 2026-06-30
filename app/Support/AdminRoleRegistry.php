<?php

namespace App\Support;

final class AdminRoleRegistry
{
    public const FRONTEND_ADMIN = 'frontend_admin';
    public const BACKEND_ADMIN = 'backend_admin';

    private const ROLE_MATRIX = [
        'reader' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => false,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => false,
                'view_logs' => false,
                'upload_media' => false,
                'delete_media' => false,
            ],
        ],
        'super_admin' => [
            'tier' => self::BACKEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => true],
            'permissions' => [
                'manage_platform' => true,
                'manage_users' => true,
                'manage_roles' => true,
                'manage_settings' => true,
                'manage_content' => true,
                'publish_content' => true,
                'review_submissions' => true,
                'moderate_content' => true,
                'view_analytics' => true,
                'view_logs' => true,
                'upload_media' => true,
                'delete_media' => true,
            ],
        ],
        'support' => [
            'tier' => self::BACKEND_ADMIN,
            'panels' => ['frontend' => false, 'backend' => true],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => true,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => false,
                'publish_content' => false,
                'review_submissions' => true,
                'moderate_content' => true,
                'view_analytics' => true,
                'view_logs' => true,
                'upload_media' => false,
                'delete_media' => false,
            ],
        ],
        'hr' => [
            'tier' => self::BACKEND_ADMIN,
            'panels' => ['frontend' => false, 'backend' => true],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => true,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => false,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => false,
                'view_logs' => true,
                'upload_media' => false,
                'delete_media' => false,
            ],
        ],
        'analyst' => [
            'tier' => self::BACKEND_ADMIN,
            'panels' => ['frontend' => false, 'backend' => true],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => false,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => true,
                'view_logs' => true,
                'upload_media' => false,
                'delete_media' => false,
            ],
        ],
        'moderator' => [
            'tier' => self::BACKEND_ADMIN,
            'panels' => ['frontend' => false, 'backend' => true],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => false,
                'publish_content' => false,
                'review_submissions' => true,
                'moderate_content' => true,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => false,
                'delete_media' => true,
            ],
        ],
        'editor' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => true,
                'review_submissions' => true,
                'moderate_content' => true,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
        'video_producer' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => true,
                'review_submissions' => true,
                'moderate_content' => false,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
        'writer' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => true,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
        'researcher' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
        'contributor' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
        'author' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => true,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
        'guest_writer' => [
            'tier' => self::FRONTEND_ADMIN,
            'panels' => ['frontend' => true, 'backend' => false],
            'permissions' => [
                'manage_platform' => false,
                'manage_users' => false,
                'manage_roles' => false,
                'manage_settings' => false,
                'manage_content' => true,
                'publish_content' => false,
                'review_submissions' => false,
                'moderate_content' => false,
                'view_analytics' => false,
                'view_logs' => false,
                'upload_media' => true,
                'delete_media' => false,
            ],
        ],
    ];

    public static function normalize(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return array_key_exists($normalized, self::ROLE_MATRIX) ? $normalized : 'reader';
    }

    public static function allRoles(): array
    {
        return array_keys(self::ROLE_MATRIX);
    }

    public static function isKnownRole(?string $role): bool
    {
        return array_key_exists(self::normalize($role), self::ROLE_MATRIX);
    }

    public static function tierFor(?string $role): string
    {
        return self::ROLE_MATRIX[self::normalize($role)]['tier'];
    }

    public static function panelAccessFor(?string $role): array
    {
        return self::ROLE_MATRIX[self::normalize($role)]['panels'];
    }

    public static function permissionsFor(?string $role): array
    {
        return self::ROLE_MATRIX[self::normalize($role)]['permissions'];
    }

    public static function isFrontendAdminRole(?string $role): bool
    {
        return self::tierFor($role) === self::FRONTEND_ADMIN;
    }

    public static function isBackendAdminRole(?string $role): bool
    {
        return self::tierFor($role) === self::BACKEND_ADMIN;
    }
}
