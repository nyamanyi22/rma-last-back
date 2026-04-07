<x-mail::message>
# RMA Status Update

Dear {{ $rma->customer->first_name }},

The status of your RMA request (**{{ $rma->rma_number }}**) has been updated.

- **Previous Status:** {{ ucwords(str_replace('_', ' ', $oldStatus)) }}
- **New Status:** {{ ucwords(str_replace('_', ' ', $newStatus)) }}

@if($rma->isApproved())
Your RMA has been approved. Please check your account for further details and status update.
@elseif($rma->isRejected())
Unfortunately, your RMA request has been rejected. The reason for rejection will be available in your account.
@elseif($rma->isInRepair())
We are now processing your return (repair, replacement, or refund).
@elseif($rma->isShipped())
Your replacement or repaired item has been shipped out. Tracking info, if available, can be found in your account.
@elseif($rma->isCompleted())
Your RMA request has been marked as fully completed.
@endif

<x-mail::button :url="config('app.frontend_url') . '/client/rma/' . $rma->id">
View RMA Details
</x-mail::button>

Thanks,<br>
{{ \App\Models\Setting::portalName() }}
</x-mail::message>
