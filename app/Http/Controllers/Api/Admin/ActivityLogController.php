<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::orderByDesc('created_at');

        if ($adminId = $request->query('admin_id')) {
            $query->where('admin_id', $adminId);
        }
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        return response()->json($query->paginate($limit));
    }
}
