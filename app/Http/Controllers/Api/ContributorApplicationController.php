<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContributorApproved;
use App\Models\AdminProfile;
use App\Models\ContributorApplication;
use App\Services\BrevoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContributorApplicationController extends Controller
{
    /**
     * Public application endpoint — no auth required.
     */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'portfolio_url' => ['nullable', 'url', 'max:500'],
            'expertise_areas' => ['nullable', 'array'],
            'expertise_areas.*' => ['string', 'max:100'],
            'content_types' => ['nullable', 'array'],
            'content_types.*' => ['string', 'max:100'],
            'motivation' => ['nullable', 'string', 'max:3000'],
        ]);

        $application = ContributorApplication::create([
            'email' => $data['email'],
            'full_name' => $data['full_name'],
            'bio' => $data['bio'] ?? null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'expertise_areas' => $data['expertise_areas'] ?? null,
            'content_types' => $data['content_types'] ?? null,
            'motivation' => $data['motivation'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Application submitted successfully.',
            'application' => $application,
        ], 201);
    }

    /**
     * List all applications (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContributorApplication::query()
            ->with('reviewer:id,display_name,email')
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    /**
     * Approve an application and create an AdminProfile for the contributor.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $application = ContributorApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => "Application is already {$application->status}.",
            ], 422);
        }

        $password = Str::password(16);

        $profile = AdminProfile::create([
            'email' => $application->email,
            'password' => $password,
            'display_name' => $application->full_name,
            'full_name' => $application->full_name,
            'role' => 'contributor',
            'is_active' => true,
            'is_public' => true,
        ]);

        $application->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        // Send approval email via Brevo
        $brevo = app(BrevoService::class);
        $brevo->sendTransactional(
            templateId: (int) config('services.brevo.template_contributor_approved', 1),
            toEmail: $application->email,
            toName: $application->full_name,
            params: [
                'FULL_NAME' => $application->full_name,
                'EMAIL' => $application->email,
                'PASSWORD' => $password,
                'LOGIN_URL' => config('app.frontend_url', 'https://theafricanmail.com') . '/login',
            ]
        );

        return response()->json([
            'message' => 'Application approved. Contributor account created.',
            'profile' => [
                'id' => $profile->id,
                'email' => $profile->email,
                'display_name' => $profile->display_name,
                'role' => $profile->role,
            ],
        ]);
    }

    /**
     * Reject an application with reviewer notes.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $application = ContributorApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => "Application is already {$application->status}.",
            ], 422);
        }

        $data = $request->validate([
            'reviewer_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update([
            'status' => 'rejected',
            'reviewer_notes' => $data['reviewer_notes'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Application rejected.',
            'application' => $application->fresh(),
        ]);
    }
}
