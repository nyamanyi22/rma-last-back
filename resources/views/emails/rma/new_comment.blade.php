<x-mail::message>
# New Comment on RMA

Dear {{ $rma->customer->first_name }},

A new comment has been added to your RMA request (**{{ $rma->rma_number }}**) by our support team.

**Message:**
> {{ $comment->comment }}

<x-mail::button :url="config('app.frontend_url') . '/client/rma/' . $rma->id">
View RMA Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
