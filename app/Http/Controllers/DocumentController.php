<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobDocument;
use App\Services\LedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

// Generates Quotation/Proforma Invoice/Invoice/Receipt PDFs — the Laravel
// dompdf equivalent of the old app's jsPDF generator (lib/pdf-generator.js).
// Only Invoice/Receipt post a LedgerService entry (money has actually
// moved or been formally billed); Quotation/Proforma are pre-sale
// documents and never touch the ledger. Every generated document is
// archived to storage and recorded as a JobDocument row so it shows up
// in the job's Documents history, regardless of type.
class DocumentController extends Controller
{
    public function quotation(Request $request, Job $job)
    {
        $this->authorize('update', $job);

        return $this->renderPdf('quotation', $job, (float) ($job->estimation_value ?? 0), $this->docNumber('QT', $job), $request);
    }

    public function proforma(Request $request, Job $job)
    {
        $this->authorize('update', $job);

        return $this->renderPdf('proforma', $job, (float) ($job->estimation_value ?? 0), $this->docNumber('PI', $job), $request);
    }

    public function invoice(Request $request, Job $job, LedgerService $ledger)
    {
        $this->authorize('update', $job);

        $docNumber = $this->docNumber('INV', $job);
        $entry = $ledger->postInvoiceEntry($job, $docNumber, $request->user()->name);

        if (! $entry) {
            return back()->with('success', 'Nothing to post — amount is empty.');
        }

        return $this->renderPdf('invoice', $job, (float) $entry->amount, $docNumber, $request);
    }

    public function receipt(Request $request, Job $job, LedgerService $ledger)
    {
        $this->authorize('update', $job);

        $docNumber = $this->docNumber('RC', $job);
        $entry = $ledger->postReceiptEntry($job, $docNumber, $request->user()->name);

        if (! $entry) {
            return back()->with('success', 'Nothing to post — amount is empty.');
        }

        return $this->renderPdf('receipt', $job, (float) $entry->amount, $docNumber, $request);
    }

    /** Downloads a previously generated document from storage — see JobDocument. */
    public function showDocument(Job $job, JobDocument $document): Response
    {
        $this->authorize('view', $job);
        abort_unless($document->job_id === $job->id, 404);
        abort_unless(Storage::disk('public')->exists($document->storage_path), 404);

        return Storage::disk('public')->response($document->storage_path, $document->filename);
    }

    private function renderPdf(string $type, Job $job, float $amount, string $docNumber, Request $request): Response
    {
        $pdf = Pdf::loadView('documents.pdf', [
            'type' => $type,
            'job' => $job,
            'amount' => $amount,
            'docNumber' => $docNumber,
        ]);

        $filename = "{$docNumber}_{$job->job_id}.pdf";
        $bytes = $pdf->output();

        $path = "{$job->job_id}/document/".time()."_{$filename}";
        Storage::disk('public')->put($path, $bytes);

        JobDocument::create([
            'job_id' => $job->id,
            'doc_type' => $type,
            'doc_number' => $docNumber,
            'storage_path' => $path,
            'filename' => $filename,
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);

        $label = ['quotation' => 'a Quotation', 'proforma' => 'a Proforma Invoice', 'invoice' => 'an Invoice', 'receipt' => 'a Receipt'][$type] ?? "a {$type}";
        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'document_generated',
            'detail' => "generated {$label} ({$docNumber})",
        ]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Not a persisted running counter — derived from the job's own ID
     * digits, same as the old app's genDocNumber(). Regenerating the same
     * doc_type for a job reuses the same number (the newest JobDocument
     * row of that type is simply "current"; older ones stay archived).
     */
    private function docNumber(string $prefix, Job $job): string
    {
        return $prefix.'-'.now()->year.'-'.str_pad((string) preg_replace('/\D/', '', $job->job_id) ?: '001', 3, '0', STR_PAD_LEFT);
    }
}
