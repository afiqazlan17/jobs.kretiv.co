<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #000000; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .header td { border: none; padding: 0; vertical-align: top; }
        .brand { font-size: 16px; }
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
        $total = $amounts->sum();
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
                <div>{{ $noLabel }}: {{ $docNumber }}</div>
                <div>Date: {{ now()->format('d/m/y') }}</div>
                <div>By: {{ $generatedBy }}</div>
            </td>
        </tr>
    </table>

    <hr class="header-divider">

    <div>
        Customer:<br>
        {{ $customer?->name }}<br>
        @if ($customer?->company) {{ $customer->company }}<br> @endif
        @if ($customer?->address_line_1) {{ $customer->address_line_1 }}<br> @endif
        @if ($customer?->address_line_2) {{ $customer->address_line_2 }}<br> @endif
        @php $cityLine = trim(trim(($customer?->postcode ?? '').' '.($customer?->city ?? '')).($customer?->state ? ', '.$customer->state : ''), ' ,'); @endphp
        @if ($cityLine) {{ $cityLine }}<br> @endif
        @if ($customer?->phone) {{ $customer->phone }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Department</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jobs as $j)
                <tr>
                    <td>{{ $j->job_id }}</td>
                    <td>{{ config('kretivco.departments.'.$j->department.'.label', $j->department) }}</td>
                    <td>{{ $j->job_type }}</td>
                    <td class="text-right">RM {{ number_format($amounts[$j->id] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $showBreakdown = in_array($type, ['quotation', 'proforma'], true);
        $subtotal = $total;
        $delivery = $showBreakdown ? (float) $jobs->sum('delivery_amount') : 0;
        $discount = $showBreakdown ? (float) $jobs->sum('discount_amount') : 0;
        $grandTotal = $showBreakdown ? $subtotal + $delivery - $discount : $total;
    @endphp

    <table class="totals">
        @if ($showBreakdown)
            <tr><td>Subtotal:</td><td class="text-right">RM {{ number_format($subtotal, 2) }}</td></tr>
            <tr><td>Delivery:</td><td class="text-right">RM {{ number_format($delivery, 2) }}</td></tr>
            <tr><td>Discount:</td><td class="text-right">(RM {{ number_format($discount, 2) }})</td></tr>
        @endif
        <tr><td>Total (MYR):</td><td class="text-right">RM {{ number_format($grandTotal, 2) }}</td></tr>
    </table>

    <div style="clear: both"></div>

    @php
        $bank = $jobs->first()->bank ? config('kretivco.bank_details.'.$jobs->first()->bank) : null;
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
            $notes[] = 'This receipt confirms payment received for the above jobs/invoice.';
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
                Issued by:
                <div class="signature-line"></div>
            </td>
            <td>
                Accepted by:
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>
</body>
</html>
