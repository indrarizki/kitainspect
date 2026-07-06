<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\InspectionOrder;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = InspectionOrder::query()->where('company_id', $user->company_id);

        if (! $user->hasPermission('inspection.view-all')) {
            $query->where('assigned_to', $user->id);
        }

        $statusCounts = (clone $query)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingApprovals = ApprovalWorkflow::where('approver_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $unreadNotifications = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'summary' => [
                'total'       => $query->count(),
                'draft'       => $statusCounts['draft']       ?? 0,
                'in_progress' => $statusCounts['in_progress'] ?? 0,
                'submitted'   => $statusCounts['submitted']   ?? 0,
                'in_review'   => $statusCounts['in_review']   ?? 0,
                'approved'    => $statusCounts['approved']    ?? 0,
                'rejected'    => $statusCounts['rejected']    ?? 0,
            ],
            'pending_approvals'    => $pendingApprovals,
            'unread_notifications' => $unreadNotifications,
        ]);
    }

    /**
     * GET /api/v1/dashboard/recent
     */
    public function recent(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = InspectionOrder::with(['assignedTo', 'template.inspectionType'])
            ->where('company_id', $user->company_id)
            ->latest()
            ->limit(10);

        if (! $user->hasPermission('inspection.view-all')) {
            $query->where('assigned_to', $user->id);
        }

        return response()->json([
            'inspections' => $query->get()->map(fn ($o) => [
                'id'              => $o->id,
                'po_number'       => $o->po_number,
                'customer'        => $o->customer,
                'product_name'    => $o->product_name,
                'status'          => $o->status,
                'inspection_date' => $o->inspection_date?->toDateString(),
                'submitted_at'    => $o->submitted_at?->toISOString(),
                'created_at'      => $o->created_at->toISOString(),
                'inspection_type' => $o->template?->inspectionType?->name,
                'assigned_to'     => ['id' => $o->assignedTo->id, 'name' => $o->assignedTo->name],
            ]),
        ]);
    }

    /**
     * GET /api/v1/dashboard/stats
     * Weekly chart data for last 30 days.
     */
    public function stats(Request $request): JsonResponse
    {
        $user      = $request->user();
        $startDate = now()->subDays(29)->startOfDay();

        $base = InspectionOrder::where('company_id', $user->company_id)
            ->where('created_at', '>=', $startDate);

        $weekly = (clone $base)
            ->select(
                DB::raw("date_trunc('week', created_at) as week"),
                DB::raw('count(*) as total'),
                DB::raw("count(*) filter (where status = 'approved') as approved"),
                DB::raw("count(*) filter (where status = 'submitted') as submitted"),
                DB::raw("count(*) filter (where status = 'rejected') as rejected"),
            )
            ->groupBy(DB::raw("date_trunc('week', created_at)"))
            ->orderBy('week')
            ->get()
            ->map(fn ($row) => [
                'week'      => \Carbon\Carbon::parse($row->week)->format('d M'),
                'total'     => (int) $row->total,
                'approved'  => (int) $row->approved,
                'submitted' => (int) $row->submitted,
                'rejected'  => (int) $row->rejected,
            ]);

        $breakdown = (clone $base)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Breakdown by inspection type
        $byType = InspectionOrder::where('company_id', $user->company_id)
            ->where('created_at', '>=', $startDate)
            ->join('inspection_templates', 'inspection_orders.template_id', '=', 'inspection_templates.id')
            ->join('inspection_types', 'inspection_templates.inspection_type_id', '=', 'inspection_types.id')
            ->select('inspection_types.name', DB::raw('count(*) as total'))
            ->groupBy('inspection_types.name')
            ->pluck('total', 'name');

        return response()->json([
            'weekly'    => $weekly,
            'breakdown' => [
                'draft'       => $breakdown['draft']       ?? 0,
                'in_progress' => $breakdown['in_progress'] ?? 0,
                'submitted'   => $breakdown['submitted']   ?? 0,
                'in_review'   => $breakdown['in_review']   ?? 0,
                'approved'    => $breakdown['approved']    ?? 0,
                'rejected'    => $breakdown['rejected']    ?? 0,
                'total'       => $breakdown->sum(),
            ],
            'by_type' => $byType,
        ]);
    }

    /**
     * GET /api/v1/dashboard/notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($notifications);
    }

    /**
     * PUT /api/v1/dashboard/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    /**
     * PUT /api/v1/dashboard/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}