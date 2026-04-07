<x-mail::message>
# Daily Pending RMA Summary

The following RMAs have been in 'Pending' or 'Under Review' status for more than 2 days.

<x-mail::table>
| RMA # | Customer | Status | Days Pending |
|:------|:---------|:-------|:-------------|
@foreach($rmas as $rma)
| {{ $rma->rma_number }} | {{ $rma->customer->first_name }} {{ $rma->customer->last_name }} | {{ ucwords(str_replace('_', ' ', $rma->status->value ?? $rma->status)) }} | {{ now()->diffInDays($rma->created_at) }} |
@endforeach
</x-mail::table>

<x-mail::button :url="config('app.frontend_url') . '/admin/rma'">
View All RMAs
</x-mail::button>

Thanks,<br>
{{ \App\Models\Setting::portalName() }} Automated System
</x-mail::message>
