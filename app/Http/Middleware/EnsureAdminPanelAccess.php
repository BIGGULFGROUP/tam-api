<?php

namespace App\Http\Middleware;

use App\Support\AdminRoleRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        $admin = $request->user();

        if (! $admin) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $admin->isActive()) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        // Use role registry — model columns may be stale after migration.
        $panelAccess = AdminRoleRegistry::panelAccessFor($admin->role);
        $allowed = match ($panel) {
            'frontend' => $panelAccess['frontend'] ?? false,
            'backend' => $panelAccess['backend'] ?? false,
            default => false,
        };

        $token = $admin->currentAccessToken();

        if ($token && ! $token->can($panel.':access')) {
            $allowed = false;
        }

        if (! $allowed) {
            return response()->json([
                'message' => 'Forbidden.',
                'required_panel' => $panel,
                'access_tier' => $admin->access_tier,
            ], 403);
        }

        return $next($request);
    }
}
