<x-mail::message>
# ⚠️ Urgent: High Priority RMA Submission

A high-priority RMA request has been submitted and requires immediate attention.

**Details:**
- **RMA Number:** {{ $rma->rma_number }}
- **Customer:** {{ $rma->customer->first_name }} {{ $rma->customer->last_name }}
- **Product:** {{ $rma->product->name ?? 'N/A' }}
- **Priority:** **{{ strtoupper($rma->priority->value ?? $rma->priority) }}**

<x-mail::button :url="config('app.frontend_url') . '/admin/rma/' . $rma->id">
View Urgent RMA
</x-mail::button>

Thanks,<br>
{{ \App\Models\Setting::portalName() }} Automated System
</x-mail::message>
