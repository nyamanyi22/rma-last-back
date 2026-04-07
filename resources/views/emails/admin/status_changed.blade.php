<x-mail::message>
# RMA Status Updated

RMA **#{{ $rma->rma_number }}** has changed status.

- Previous status: **{{ str($oldStatus)->replace('_', ' ')->title() }}**
- New status: **{{ str($newStatus)->replace('_', ' ')->title() }}**
- Updated by: **{{ $changedBy }}**

@if($rma->customer)
- Customer: **{{ $rma->customer->full_name ?? trim(($rma->customer->first_name ?? '') . ' ' . ($rma->customer->last_name ?? '')) }}**
@endif

<x-mail::button :url="rtrim(config('app.frontend_url', config('app.url')), '/') . '/admin/rma'">
Open RMA Dashboard
</x-mail::button>

Thanks,<br>
{{ \App\Models\Setting::portalName() }}
</x-mail::message>
