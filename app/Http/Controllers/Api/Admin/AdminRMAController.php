<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RMARequest;
use App\Models\RMAComment;
use App\Models\RMAStatusHistory;
use App\Enums\RMAStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRMAController extends Controller
{
    public function index(Request $request)
    {
        $rmas = RMARequest::with(['customer', 'product', 'assignedTo'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
            ->latest()
            ->paginate();

        return response()->json($rmas);
    }

    public function show($id)
    {
        $rma = RMARequest::with(['customer', 'product', 'sale', 'assignedTo', 'approvedBy', 'comments.user', 'statusHistory.changedBy'])
            ->findOrFail($id);

        return response()->json($rma);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $rma = RMARequest::findOrFail($id);
            $oldStatus = $rma->status;
            $newStatus = $request->status;

            $rma->status = $newStatus;

            if ($newStatus === RMAStatus::APPROVED->value) {
                $rma->approved_by = $request->user()->id;
                $rma->approved_at = now();
            }

            if ($request->rejection_reason && $newStatus === RMAStatus::REJECTED->value) {
                $rma->rejection_reason = $request->rejection_reason;
            }

            $rma->save();

            // Record status history
            RMAStatusHistory::create([
                'rma_id' => $rma->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $request->user()->id,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'message' => 'Status updated successfully',
                'rma' => $rma,
            ]);
        });
    }

    public function assign(Request $request, $id)
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $rma = RMARequest::findOrFail($id);
        $rma->assigned_to = $request->assigned_to;
        $rma->save();

        return response()->json([
            'message' => 'RMA assigned successfully',
            'rma' => $rma,
        ]);
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string',
            'type' => 'required|in:internal,external',
            'notify_customer' => 'boolean',
        ]);

        $rma = RMARequest::findOrFail($id);

        $comment = RMAComment::create([
            'rma_id' => $rma->id,
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'comment' => $request->comment,
            'notify_customer' => $request->notify_customer ?? false,
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment->load('user'),
        ]);
    }
}
