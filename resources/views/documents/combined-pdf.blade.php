<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1A1025; }
        .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .brand { font-size: 16px; font-weight: bold; }
        .muted { color: #6B6080; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border-bottom: 1px solid #E8E4ED; text-align: left; }
        th { background: #F5F3F7; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .totals { margin-top: 12px; width: 260px; float: right; }
        .totals td { border: none; padding: 4px 8px; }
        .notes { margin-top: 60px; font-size: 10px; color: #6B6080; }
        .notes li { margin-bottom: 4px; }
        .signatures { margin-top: 60px; display: flex; justify-content: space-between; }
        .signatures .block { width: 45%; }
        .signature-line { margin-top: 40px; border-top: 1px solid #1A1025; width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; align-items: center;">
            <img src="{{ public_path('images/kretivco-logo.png') }}" style="width: 60px; height: 60px; margin-right: 12px;">
            <div>
                <div class="brand">{{ config('kretivco.brand.name') }} {{ config('kretivco.brand.ssm') }}</div>
                <div class="muted">{{ config('kretivco.brand.address_line_1') }}</div>
                <div class="muted">{{ config('kretivco.brand.address_line_2') }}</div>
                <div class="muted">{{ config('kretivco.brand.email') }} · {{ config('kretivco.brand.phone') }}</div>
            </div>
        </div>
        @php
            $noLabel = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'][$type] ?? 'No#';
            $total = $amounts->sum();
        @endphp
        <div style="text-align: right">
            <div class="title">{{ $type === 'proforma' ? 'PROFORMA INVOICE' : strtoupper($type) }}</div>
            <div>{{ $noLabel }} {{ $docNumber }}</div>
            <div class="muted">{{ now()->format('d M Y') }}</div>
            <div class="muted">By: {{ $generatedBy }}</div>
        </div>
    </div>

    <div>
        <strong>Customer:</strong><br>
        {{ $customer?->name }}<br>
        @if ($customer?->company) {{ $customer->company }}<br> @endif
        @if ($customer?->fullAddress()) {{ $customer->fullAddress() }}<br> @endif
        @if ($customer?->phone) {{ $customer->phone }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Department</th>
                <th>Description</th>
                <th class="text-right">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jobs as $j)
                <tr>
                    <td>{{ $j->job_id }}</td>
                    <td>{{ config('kretivco.departments.'.$j->department.'.label', $j->department) }}</td>
                    <td>{{ $j->job_type }}</td>
                    <td class="text-right">{{ number_format($amounts[$j->id] ?? 0, 2) }}</td>
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
            <tr><td>Subtotal</td><td class="text-right">RM {{ number_format($subtotal, 2) }}</td></tr>
            <tr><td>Delivery</td><td class="text-right">RM {{ number_format($delivery, 2) }}</td></tr>
            <tr><td>Discount</td><td class="text-right">(RM {{ number_format($discount, 2) }})</td></tr>
        @endif
        <tr><td><strong>Total (MYR)</strong></td><td class="text-right"><strong>RM {{ number_format($grandTotal, 2) }}</strong></td></tr>
    </table>

    <div style="clear: both"></div>

    @php
        $bank = $jobs->first()->bank ? config('kretivco.bank_details.'.$jobs->first()->bank) : null;
        $notes = [];
        if (in_array($type, ['invoice', 'proforma', 'quotation'], true)) {
            if ($bank) {
                $notes[] = "Please make payment to {$bank['label']} {$bank['acct']} {$bank['name']}.";
            }
            $notes[] = 'Please indicate the '.($noLabel === 'QNo#' ? 'reference' : 'invoice').' number when making payment to us.';
            $notes[] = 'Full payment needed for invoice below RM2000; 80% deposit must be paid before starting work for invoice RM2000 and above.';
            $notes[] = 'Progress will be done within 14 days after the final draft has been confirmed by the customer.';
            $notes[] = 'Deposit is not refundable after the booking is confirmed and the first draft has been made.';
        } elseif ($type === 'receipt') {
            $notes[] = 'This receipt confirms payment received for the above jobs/invoice.';
        }
        if ($type === 'quotation') {
            $notes[] = 'This quotation is valid for 30 days from the date above.';
        }
        $notes[] = 'Email us at '.config('kretivco.brand.email');
        $notes[] = 'WhatsApp us at '.config('kretivco.brand.phone');
    @endphp
    <div class="notes">
        <ol>
            @foreach ($notes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ol>
        <strong>Thank you for your business!</strong>
    </div>

    <div class="signatures">
        <div class="block">
            Issued by:
            <div class="signature-line"></div>
        </div>
        <div class="block">
            Accepted by:
            <div class="signature-line"></div>
        </div>
    </div>
</body>
</html>
