<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000000; }
        strong, b { font-weight: bold; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .header td { border: none; padding: 0; vertical-align: top; }
        .brand { font-size: 16px; font-weight: bold; }
        .muted { color: #666666; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border: 1px solid #cccccc; text-align: left; }
        th { background: #F5F3F7; font-size: 11px; font-weight: bold; }
        .text-right { text-align: right; }
        .totals { margin-top: 12px; width: 260px; float: right; }
        .totals td { border: none; padding: 4px 8px; }
        .notes { margin-top: 60px; font-size: 10px; }
        .notes li { margin-bottom: 4px; }
        .signatures { width: 100%; margin-top: 60px; border-collapse: collapse; }
        .signatures td { border: none; padding: 0; width: 50%; }
        .signature-line { margin-top: 40px; border-top: 1px solid #000000; width: 200px; }
        .header-divider { border: none; border-top: 1px solid #cccccc; margin: 16px 0 24px; }
    </style>
</head>
<body>
    @php
        $noLabel = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'][$type] ?? 'No#';
    @endphp
    <table class="header">
        <tr>
            <td style="width: 70px;">
                <img src="{{ public_path('images/kretivco-logo.png') }}" style="width: 60px; height: 60px;">
            </td>
            <td>
                <div class="brand">{{ config('kretivco.brand.name') }}</div>
                <div class="muted">{{ config('kretivco.brand.ssm') }}</div>
                <div class="muted">{{ config('kretivco.brand.address_line_1') }}</div>
                <div class="muted">{{ config('kretivco.brand.address_line_2') }}</div>
            </td>
            <td style="text-align: right;">
                <div class="title">{{ $type === 'proforma' ? 'PROFORMA INVOICE' : strtoupper($type) }}</div>
                <div><strong>{{ $noLabel }}:</strong> {{ $docNumber }}</div>
                <div><strong>Date:</strong> {{ now()->format('d/m/y') }}</div>
                <div><strong>By:</strong> {{ $generatedBy }}</div>
            </td>
        </tr>
    </table>

    <hr class="header-divider">

    <div>
        <strong>Customer:</strong><br>
        {{ $job->customer?->name }}<br>
        @if ($job->customer?->company) {{ $job->customer->company }}<br> @endif
        @if ($job->customer?->address_line_1) {{ $job->customer->address_line_1 }}<br> @endif
        @if ($job->customer?->address_line_2) {{ $job->customer->address_line_2 }}<br> @endif
        @php $cityLine = trim(trim(($job->customer?->postcode ?? '').' '.($job->customer?->city ?? '')).($job->customer?->state ? ', '.$job->customer->state : ''), ' ,'); @endphp
        @if ($cityLine) {{ $cityLine }}<br> @endif
        @if ($job->customer?->phone) {{ $job->customer->phone }} @endif
    </div>

    <div style="margin-top: 12px"><strong>Title:</strong> {{ $job->job_type }}</div>

    @php
        $lineItems = $job->line_items ?? [];
        $showBreakdown = in_array($type, ['quotation', 'proforma'], true);
        $subtotal = $showBreakdown && count($lineItems)
            ? collect($lineItems)->sum(fn ($i) => (float) ($i['qty'] ?? 1) * (float) ($i['price'] ?? 0))
            : $amount;
        $delivery = $showBreakdown ? (float) ($job->delivery_amount ?? 0) : 0;
        $discount = $showBreakdown ? (float) ($job->discount_amount ?? 0) : 0;
        $total = $showBreakdown ? $subtotal + $delivery - $discount : $amount;
    @endphp

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Description</th>
                <th>Unit</th>
                <th class="text-right">Price</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @if (count($lineItems))
                @foreach ($lineItems as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            @if (! empty($item['item']) && ($item['item'] ?? null) !== ($item['desc'] ?? null))
                                {{ $item['item'] }}<br>
                            @endif
                            {{ $item['desc'] ?? '' }}
                        </td>
                        <td>{{ $item['qty'] ?? 1 }}</td>
                        <td class="text-right">RM {{ number_format($item['price'] ?? 0, 2) }}</td>
                        <td class="text-right">RM {{ number_format(($item['qty'] ?? 1) * ($item['price'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>1</td>
                    <td>{{ $job->job_type }}</td>
                    <td>1</td>
                    <td class="text-right">RM {{ number_format($amount, 2) }}</td>
                    <td class="text-right">RM {{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        @if ($showBreakdown)
            <tr><td><strong>Subtotal:</strong></td><td class="text-right">RM {{ number_format($subtotal, 2) }}</td></tr>
            <tr><td><strong>Delivery:</strong></td><td class="text-right">RM {{ number_format($delivery, 2) }}</td></tr>
            <tr><td><strong>Discount:</strong></td><td class="text-right">(RM {{ number_format($discount, 2) }})</td></tr>
        @endif
        <tr><td><strong>Total (MYR):</strong></td><td class="text-right">RM {{ number_format($total, 2) }}</td></tr>
    </table>

    <div style="clear: both"></div>

    @php
        $bank = $job->bank ? config("kretivco.bank_details.{$job->bank}") : null;
        $docNoun = $type === 'quotation' ? 'quotation' : 'invoice';
        $notes = [];
        if (in_array($type, ['invoice', 'proforma', 'quotation'], true)) {
            if ($bank) {
                $notes[] = "Please make payment to {$bank['label']} {$bank['acct']} {$bank['name']}.";
            }
            $notes[] = "Please indicate {$docNoun} number when making payment to us.";
            $notes[] = 'Full payment needed for invoice below RM2000 and 80% deposit must be paid before making the first draft for invoice price RM2000 and above.';
            $notes[] = 'Progress will be done in 14 days after final draft has been confirmed by customer.';
            $notes[] = 'Deposit is not refundable after the booking confirmed and first draft has been made.';
        } elseif ($type === 'receipt') {
            $notes[] = 'This receipt confirms payment received for the above job/invoice.';
        }
        $notes[] = 'Email us at '.config('kretivco.brand.email');
        $notes[] = 'Whatsapp us at '.config('kretivco.brand.phone');
    @endphp
    <div class="notes">
        <div><strong>Note:</strong></div>
        <ol>
            @foreach ($notes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ol>
        Thank you for your business!
    </div>

    <table class="signatures">
        <tr>
            <td>
                <strong>Issued by:</strong>
                <div class="signature-line"></div>
            </td>
            <td>
                <strong>Accepted by:</strong>
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>
</body>
</html>
