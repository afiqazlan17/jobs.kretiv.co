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
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ config('kretivco.brand.name') }} {{ config('kretivco.brand.ssm') }}</div>
            <div class="muted">{{ config('kretivco.brand.address_line_1') }}</div>
            <div class="muted">{{ config('kretivco.brand.address_line_2') }}</div>
            <div class="muted">{{ config('kretivco.brand.email') }} · {{ config('kretivco.brand.phone') }}</div>
        </div>
        @php
            $noLabel = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'][$type] ?? 'No#';
            $total = $amounts->sum();
        @endphp
        <div style="text-align: right">
            <div class="title">{{ $type === 'proforma' ? 'PROFORMA INVOICE' : strtoupper($type) }}</div>
            <div>{{ $noLabel }} {{ $docNumber }}</div>
            <div class="muted">{{ now()->format('d M Y') }}</div>
        </div>
    </div>

    <div>
        <strong>Bill To:</strong> {{ $customer?->name }}<br>
        @if ($customer?->company) {{ $customer->company }}<br> @endif
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

    <table class="totals">
        <tr><td><strong>Total</strong></td><td class="text-right"><strong>RM {{ number_format($total, 2) }}</strong></td></tr>
    </table>

    <div style="clear: both"></div>

    @if (in_array($type, ['invoice', 'proforma']) && $jobs->first()->bank)
        @php $bank = config('kretivco.bank_details.'.$jobs->first()->bank); @endphp
        <div class="notes">
            <ul>
                <li>Please make payment to {{ $bank['label'] }} {{ $bank['acct'] }} {{ $bank['name'] }}.</li>
                <li>Please indicate {{ $noLabel === 'QNo#' ? 'reference' : 'invoice' }} number when making payment to us.</li>
                <li>Email us at {{ config('kretivco.brand.email') }}</li>
            </ul>
        </div>
    @elseif ($type === 'receipt')
        <div class="notes">
            <ul>
                <li>This receipt confirms payment received for the above jobs/invoice.</li>
                <li>Email us at {{ config('kretivco.brand.email') }}</li>
            </ul>
        </div>
    @else
        <div class="notes">
            <ul>
                <li>This quotation is valid for 30 days from the date above.</li>
                <li>Email us at {{ config('kretivco.brand.email') }}</li>
            </ul>
        </div>
    @endif
</body>
</html>
