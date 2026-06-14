<?php

namespace App\Http\Middleware;

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

        $allowed = match ($panel) {
            'frontend' => $admin->canAccessFrontendPanel(),
            'backend' => $admin->canAccessBackendPanel(),
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
