<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_an_invoice_downloads_a_pdf_and_posts_a_ledger_entry(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        $job = Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print',
            'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 1000,
        ]);

        $response = $this->actingAs($bod)->get(route('jobs.invoice', $job));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('ledger_entries', ['job_id' => 'KP-2026-001', 'type' => 'invoice', 'amount' => 1000]);
        $this->assertSame(1, LedgerEntry::count());
    }
}
