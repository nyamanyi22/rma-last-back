<x-mail::message>
# RMA Submitted Successfully

Dear {{ $rma->customer->first_name }},

We have successfully received your RMA request. Below are the details:

- **RMA Number:** {{ $rma->rma_number }}
- **Product:** {{ $rma->product->name ?? 'N/A' }}
- **Type:** {{ $rma->rma_type }}
- **Reason:** {{ $rma->reason }}

We will review your request and get back to you shortly. You can track the status of your RMA by logging into your account.

<x-mail::button :url="config('app.frontend_url') . '/client/rma/' . $rma->id">
View RMA Status
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
