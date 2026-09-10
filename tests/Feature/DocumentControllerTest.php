<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function job(): Job
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);

        return Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print',
            'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 1000,
        ]);
    }

    public function test_generating_an_invoice_downloads_a_pdf_and_posts_a_ledger_entry(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->get(route('jobs.invoice', $job));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('ledger_entries', ['job_id' => 'KP-2026-001', 'type' => 'invoice', 'amount' => 1000]);
        $this->assertSame(1, LedgerEntry::count());
        $this->assertDatabaseHas('job_documents', ['job_id' => $job->id, 'doc_type' => 'invoice']);
    }

    public function test_generating_a_quotation_does_not_post_a_ledger_entry(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->get(route('jobs.quotation', $job));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame(0, LedgerEntry::count());
        $this->assertDatabaseHas('job_documents', ['job_id' => $job->id, 'doc_type' => 'quotation']);
    }

    public function test_generating_a_proforma_invoice_does_not_post_a_ledger_entry(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->get(route('jobs.proforma', $job));

        $response->assertOk();
        $this->assertSame(0, LedgerEntry::count());
        $this->assertDatabaseHas('job_documents', ['job_id' => $job->id, 'doc_type' => 'proforma']);
    }

    public function test_a_generated_document_can_be_re_downloaded_from_its_history(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->get(route('jobs.quotation', $job));
        $document = JobDocument::first();

        $response = $this->actingAs($bod)->get(route('jobs.documents.show', [$job, $document]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_regenerating_the_same_document_type_supersedes_the_previous_one(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->get(route('jobs.quotation', $job));
        $this->actingAs($bod)->get(route('jobs.quotation', $job));

        $this->assertSame(2, JobDocument::where('job_id', $job->id)->where('doc_type', 'quotation')->count());
    }

    public function test_combining_two_same_customer_jobs_creates_one_document_shared_by_both(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $sibling = Job::create([
            'job_id' => 'KW-2026-001', 'customer_id' => $job->customer_id, 'department' => 'work',
            'job_type' => 'Design', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->post(route('jobs.documents.combine', $job), [
            'doc_type' => 'quotation',
            'job_ids' => [$sibling->id],
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $jobDoc = JobDocument::where('job_id', $job->id)->first();
        $siblingDoc = JobDocument::where('job_id', $sibling->id)->first();
        $this->assertNotNull($jobDoc);
        $this->assertNotNull($siblingDoc);
        $this->assertSame($jobDoc->doc_number, $siblingDoc->doc_number);
        $this->assertStringEndsWith('-C', $jobDoc->doc_number);
        $this->assertSame($jobDoc->storage_path, $siblingDoc->storage_path);
    }

    public function test_combining_jobs_with_mismatched_statuses_is_rejected(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $sibling = Job::create([
            'job_id' => 'KW-2026-001', 'customer_id' => $job->customer_id, 'department' => 'work',
            'job_type' => 'Design', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->post(route('jobs.documents.combine', $job), [
            'doc_type' => 'quotation',
            'job_ids' => [$sibling->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, JobDocument::count());
    }

    public function test_combining_with_a_job_from_a_different_customer_is_ignored(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $otherCustomer = Customer::create(['customer_id' => 'C002', 'name' => 'Other Co']);
        $unrelated = Job::create([
            'job_id' => 'KW-2026-002', 'customer_id' => $otherCustomer->id, 'department' => 'work',
            'job_type' => 'Design', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->post(route('jobs.documents.combine', $job), [
            'doc_type' => 'quotation',
            'job_ids' => [$unrelated->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, JobDocument::count());
    }
}
