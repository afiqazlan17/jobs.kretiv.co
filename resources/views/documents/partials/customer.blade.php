@php
    $addr2 = collect([
        $customer?->address_line_2,
        trim(($customer?->postcode ?? '').' '.($customer?->city ?? '')),
        $customer?->state,
    ])->filter()->implode(', ');
@endphp
<div class="block">
    <div class="cust-label"><b>Customer:</b></div>
    @if ($customer?->name)<div class="cust-line">{{ $customer->name }}</div>@endif
    @if ($customer?->company)<div class="cust-line">{{ $customer->company }}</div>@endif
    @if ($customer?->address_line_1)<div class="cust-line">{{ $customer->address_line_1 }}</div>@endif
    @if ($addr2)<div class="cust-line">{{ $addr2 }}</div>@endif
</div>
