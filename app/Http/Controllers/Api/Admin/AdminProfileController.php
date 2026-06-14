<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminProfile;
use App\Models\Video;
use App\Support\AdminRoleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('access_tier');

        $admins = AdminProfile::query()
            ->withCount([
                'content as article_count' => fn ($query) => $query->where('content_type', 'article'),
                'content as video_count' => fn ($query) => $query->where('content_type', '!=', 'article'),
            ])
            ->when(
                in_array($scope, [AdminRoleRegistry::FRONTEND_ADMIN, AdminRoleRegistry::BACKEND_ADMIN], true),
                fn ($query) => $query->where('access_tier', $scope)
            )
            ->orderBy('display_name')
            ->get([
                'id',
                'email',
                'display_name',
                'full_name',
                'username',
                'role',
                'access_tier',
                'can_access_frontend_panel',
                'can_access_backend_panel',
                'avatar_url',
                'author_slug',
                'last_login',
                'is_active',
                'created_at',
            ]);

        return response()->json(
            $admins->map(fn (AdminProfile $admin) => $this->serializeAdmin($admin))->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'        => ['required', 'email', 'unique:admin_profiles,email'],
            'password'     => ['required', 'string', 'min:8'],
            'display_name' => ['required', 'string', 'max:100'],
            'full_name'    => ['nullable', 'string', 'max:150'],
            'username'     => ['nullable', 'string', 'max:50', 'unique:admin_profiles,username'],
            'role'         => ['required', 'string', 'in:'.implode(',', AdminRoleRegistry::allRoles())],
            'bio'          => ['nullable', 'string'],
            'avatar_url'   => ['nullable', 'url'],
            'website_url'  => ['nullable', 'url'],
            'twitter_url'  => ['nullable', 'url'],
            'instagram_url'=> ['nullable', 'url'],
            'linkedin_url' => ['nullable', 'url'],
            'location'     => ['nullable', 'string', 'max:150'],
            'is_active'    => ['boolean'],
            'is_public'    => ['boolean'],
        ]);

        $admin = AdminProfile::create([
            ...$data,
            'id'       => Str::uuid(),
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'id' => $admin->id,
            'role' => $admin->role,
            'access_tier' => $admin->access_tier,
            'panel_access' => $admin->panel_access,
            'author_slug' => $admin->author_slug,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $admin = AdminProfile::query()
            ->withCount([
                'content as article_count' => fn ($query) => $query->where('content_type', 'article'),
                'content as video_count' => fn ($query) => $query->where('content_type', '!=', 'article'),
            ])
            ->findOrFail($id);

        return response()->json($this->serializeAdmin($admin));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $admin = AdminProfile::findOrFail($id);

        $data = $request->validate([
            'display_name'  => ['sometimes', 'string', 'max:100'],
            'full_name'     => ['sometimes', 'nullable', 'string', 'max:150'],
            'username'      => ['sometimes', 'nullable', 'string', 'max:50', 'unique:admin_profiles,username,'.$id],
            'role'          => ['sometimes', 'string', 'in:'.implode(',', AdminRoleRegistry::allRoles())],
            'bio'           => ['sometimes', 'nullable', 'string'],
            'avatar_url'    => ['sometimes', 'nullable', 'url'],
            'website_url'   => ['sometimes', 'nullable', 'url'],
            'twitter_url'   => ['sometimes', 'nullable', 'url'],
            'instagram_url' => ['sometimes', 'nullable', 'url'],
            'linkedin_url'  => ['sometimes', 'nullable', 'url'],
            'location'      => ['sometimes', 'nullable', 'string'],
            'author_slug'   => ['sometimes', 'nullable', 'string', 'max:120', 'unique:admin_profiles,author_slug,'.$id],
            'is_public'     => ['sometimes', 'boolean'],
            'is_active'     => ['sometimes', 'boolean'],
        ]);

        $admin->update($data);

        return response()->json($this->serializeAdmin($admin->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $admin = AdminProfile::findOrFail($id);
        $admin->tokens()->delete();
        $admin->delete();

        return response()->json(null, 204);
    }

    public function updatePassword(Request $request, string $id): JsonResponse
    {
        $admin = AdminProfile::findOrFail($id);
        $data  = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $admin->update(['password' => Hash::make($data['password'])]);
        $admin->tokens()->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    public function content(Request $request, string $id): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 100)));

        return response()->json([
            'content' => Video::where('created_by', $id)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get([
                    'id',
                    'title',
                    'content_type',
                    'status',
                    'niche',
                    'views',
                    'word_count',
                    'published_at',
                ]),
        ]);
    }

    public function authors(Request $request): JsonResponse
    {
        $authors = AdminProfile::where('is_active', true)
            ->where('is_public', true)
            ->where('can_access_frontend_panel', true)
            ->orderBy('display_name')
            ->get([
                'id',
                'display_name',
                'full_name',
                'email',
                'role',
                'access_tier',
                'author_slug',
            ])
            ->map(fn ($a) => [
                'id'          => $a->id,
                'displayName' => trim($a->display_name ?: $a->full_name ?: $a->email),
                'email'       => $a->email,
                'role'        => $a->role,
                'accessTier'  => $a->access_tier,
                'authorSlug'  => $a->author_slug,
            ])
            ->filter(fn ($a) => $a['displayName'] !== '')
            ->values();

        return response()->json(['authors' => $authors]);
    }

    public function linkOrphans(): JsonResponse
    {
        // Placeholder — links content rows whose `author` string matches a profile display_name
        return response()->json(['linked' => 0]);
    }

    private function serializeAdmin(AdminProfile $admin): array
    {
        $data = $admin->makeHidden(['password', 'remember_token'])->toArray();
        $data['panel_access'] = $admin->panel_access;
        $data['permissions'] = $admin->permissions();

        return $data;
    }
}
