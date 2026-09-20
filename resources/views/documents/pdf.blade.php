<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
</head>
<body>
    @php
        $noLabel = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'][$type] ?? 'No#';
        $lineItems = $job->line_items ?? [];
        $showBreakdown = in_array($type, ['quotation', 'proforma'], true);
        $subtotal = $showBreakdown && count($lineItems)
            ? collect($lineItems)->sum(fn ($i) => (float) ($i['qty'] ?? 1) * (float) ($i['price'] ?? 0))
            : $amount;
        $delivery = $showBreakdown ? (float) ($job->delivery_amount ?? 0) : 0;
        $discount = $showBreakdown ? (float) ($job->discount_amount ?? 0) : 0;
        $total = $showBreakdown ? $subtotal + $delivery - $discount : $amount;
        $rows = count($lineItems) ? $lineItems : [['item' => $job->job_type, 'desc' => null, 'qty' => 1, 'price' => $amount]];
        $bank = $job->bank ? config("kretivco.bank_details.{$job->bank}") : null;
    @endphp

    @include('documents.partials.header')
    @include('documents.partials.customer', ['customer' => $job->customer])

    <div class="title-line"><b>Title:</b> {{ $job->job_type }}</div>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:24.75pt;">No</th>
                <th style="width:217.75pt;">Description</th>
                <th style="width:49.75pt;">Unit</th>
                <th style="width:59.75pt;">Price</th>
                <th style="width:64.75pt;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $item)
                @php $qty = $item['qty'] ?? 1; $price = (float) ($item['price'] ?? 0); @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>
                        @foreach (collect([$item['item'] ?? null, $item['desc'] ?? null])->filter()->unique()->values() as $line)
                            <div>{!! nl2br(e($line)) !!}</div>
                        @endforeach
                    </td>
                    <td class="c">{{ $qty }}</td>
                    <td class="rt">RM {{ number_format($price, 2) }}</td>
                    <td class="rt">RM {{ number_format($qty * $price, 2) }}</td>
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

    @include('documents.partials.footer', ['bank' => $bank, 'receiptNote' => 'This receipt confirms payment received for the above job/invoice.'])
</body>
</html>
