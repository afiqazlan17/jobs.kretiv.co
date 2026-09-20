<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
</head>
<body>
    @php
        $noLabel = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'][$type] ?? 'No#';
        $showBreakdown = in_array($type, ['quotation', 'proforma'], true);
        $subtotal = $amounts->sum();
        $delivery = $showBreakdown ? (float) $jobs->sum('delivery_amount') : 0;
        $discount = $showBreakdown ? (float) $jobs->sum('discount_amount') : 0;
        $total = $showBreakdown ? $subtotal + $delivery - $discount : $subtotal;
        $bank = $jobs->first()->bank ? config('kretivco.bank_details.'.$jobs->first()->bank) : null;
    @endphp

    @include('documents.partials.header')
    @include('documents.partials.customer', ['customer' => $customer])

    <table class="grid">
        <thead>
            <tr>
                <th style="width:69.75pt;">Job ID</th>
                <th style="width:79.75pt;">Department</th>
                <th style="width:177.75pt;">Description</th>
                <th style="width:99.75pt;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jobs as $j)
                <tr>
                    <td>{{ $j->job_id }}</td>
                    <td>{{ config('kretivco.departments.'.$j->department.'.label', $j->department) }}</td>
                    <td>{{ $j->job_type }}</td>
                    <td class="rt">RM {{ number_format($amounts[$j->id] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        @if ($showBreakdown)
            <div><b>Subtotal:</b> RM {{ number_format($subtotal, 2) }}</div>
            <div><b>Delivery:</b> RM {{ number_format($delivery, 2) }}</div>
            <div><b>Discount:</b> (RM {{ number_format($discount, 2) }})</div>
        @endif
        <div><b>Total (MYR):</b> RM {{ number_format($total, 2) }}</div>
    </div>

    @include('documents.partials.footer', ['bank' => $bank, 'receiptNote' => 'This receipt confirms payment received for the above jobs/invoice.'])
</body>
</html>
