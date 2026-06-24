<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminProfile;
use App\Support\AdminRoleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/{frontend|backend}/auth/login
     * Returns a Sanctum personal access token on success.
     */
    public function loginFrontend(Request $request): JsonResponse
    {
        return $this->loginForPanel($request, 'frontend');
    }

    public function loginBackend(Request $request): JsonResponse
    {
        return $this->loginForPanel($request, 'backend');
    }

    public function login(Request $request): JsonResponse
    {
        return $this->loginForPanel($request, 'frontend');
    }

    /**
     * GET /api/{frontend|backend}/auth/me
     * Returns the authenticated admin's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profileData($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();

        $data = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:admin_profiles,username,'.$admin->id],
            'bio' => ['sometimes', 'nullable', 'string'],
            'website_url' => ['sometimes', 'nullable', 'url'],
            'twitter_url' => ['sometimes', 'nullable', 'url'],
            'instagram_url' => ['sometimes', 'nullable', 'url'],
            'linkedin_url' => ['sometimes', 'nullable', 'url'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'is_public' => ['sometimes', 'boolean'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $admin->update($data);

        return response()->json($this->profileData($admin->fresh()));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $currentTokenId = $admin->currentAccessToken()?->id;
        $admin->update([
            'password' => Hash::make($data['password']),
        ]);

        if ($currentTokenId) {
            $admin->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * POST /api/{frontend|backend}/auth/logout
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function loginForPanel(Request $request, string $panel): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = AdminProfile::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $admin->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        // Use role registry directly instead of model columns — columns may be stale after migration.
        // The model's saving boot event would recalculate them, but that runs AFTER this check.
        $panelAccess = AdminRoleRegistry::panelAccessFor($admin->role);
        $canAccessPanel = match ($panel) {
            'frontend' => $panelAccess['frontend'] ?? false,
            'backend' => $panelAccess['backend'] ?? false,
            default => false,
        };

        if (! $canAccessPanel) {
            return response()->json([
                'message' => 'Forbidden.',
                'required_panel' => $panel,
                'access_tier' => $admin->access_tier,
            ], 403);
        }

        // Revoke all previous tokens for this admin (single-session policy)
        $admin->tokens()->delete();
        $admin->forceFill(['last_login' => now()])->save();

        $token = $admin->createToken($panel.'-admin-token', [$panel.':access'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->profileData($admin),
            'panel' => $panel,
        ]);
    }

    private function profileData(AdminProfile $admin): array
    {
        return [
            'id'           => $admin->id,
            'email'        => $admin->email,
            'display_name' => $admin->display_name,
            'full_name'    => $admin->full_name,
            'username'     => $admin->username,
            'role'         => $admin->role,
            'access_tier'  => $admin->access_tier,
            'panel_access' => $admin->panel_access,
            'permissions'  => AdminRoleRegistry::permissionsFor($admin->role),
            'bio'          => $admin->bio,
            'avatar_url'   => $admin->avatar_url,
            'website_url'  => $admin->website_url,
            'twitter_url'  => $admin->twitter_url,
            'instagram_url'=> $admin->instagram_url,
            'linkedin_url' => $admin->linkedin_url,
            'location'     => $admin->location,
            'is_public'    => $admin->is_public,
            'is_active'    => $admin->is_active,
            'last_login'   => $admin->last_login,
            'author_slug'  => $admin->author_slug,
            'article_count'=> $admin->article_count,
            'video_count'  => $admin->video_count,
            'created_at'   => $admin->created_at,
            'updated_at'   => $admin->updated_at,
        ];
    }

    /**
     * GET /api/{panel}-admin/auth/status — no-auth health check for session debugging.
     */
    public function status(Request $request): JsonResponse
    {
        $dbOk = false;
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            // DB unreachable
        }

        $hasCache = file_exists(base_path('bootstrap/cache/config.php'));
        $envKey = config('app.key') ? 'present' : 'missing';

        return response()->json([
            'ok' => true,
            'database' => $dbOk ? 'connected' : 'unreachable',
            'config_cached' => $hasCache,
            'app_key' => $envKey,
            'app_url' => config('app.url'),
            'sanctum_expiration_minutes' => config('sanctum.expiration'),
        ]);
    }
}
