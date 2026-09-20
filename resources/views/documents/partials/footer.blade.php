@php
    $notes = [];
    if (in_array($type, ['invoice', 'proforma', 'quotation'], true)) {
        if ($bank) {
            $notes[] = "Please make payment to {$bank['label']} {$bank['acct']} {$bank['name']}.";
        }
        $notes[] = 'Please indicate '.($type === 'quotation' ? 'quotation' : 'invoice').' number when making payment to us.';
        $notes[] = 'Full payment needed for invoice below RM2000 and 80% deposit must be paid before making the first draft for invoice price RM2000 and above.';
        $notes[] = 'Progress will be done in 14 days after final draft has been confirmed by customer.';
        $notes[] = 'Deposit is not refundable after the booking confirmed and first draft has been made.';
    } elseif ($type === 'receipt') {
        $notes[] = $receiptNote;
    }
    $notes[] = 'Email us at '.config('kretivco.brand.email');
    $notes[] = 'Whatsapp us at '.config('kretivco.brand.phone');
@endphp
<div class="note-title">Note:</div>
@foreach ($notes as $i => $note)
    <div class="note">{{ $i + 1 }}. {{ $note }}</div>
@endforeach
<div class="thanks">Thank you for your business!</div>
<table class="sign">
    <tr>
        <td style="width:228.5pt;"><b>Issued by:</b><div class="sign-line"></div></td>
        <td><b>Accepted by:</b><div class="sign-line"></div></td>
    </tr>
</table>
