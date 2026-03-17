<x-mail::message>
# New RMA Submission Received

A new RMA request has been submitted by a customer.

**Details:**
- **RMA Number:** {{ $rma->rma_number }}
- **Customer:** {{ $rma->customer->first_name }} {{ $rma->customer->last_name }}
- **Product:** {{ $rma->product->name ?? 'N/A' }}
- **Type:** {{ $rma->rma_type }}
- **Priority:** {{ ucfirst($rma->priority->value ?? $rma->priority) }}

<x-mail::button :url="config('app.frontend_url') . '/admin/rma/' . $rma->id">
View RMA in Admin Panel
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Automated System
</x-mail::message>
