<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobCreationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $departments, array $overrides = []): array
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme Sdn Bhd']);

        $perDept = [];
        foreach ($departments as $dept) {
            $perDept[$dept] = [
                'job_type' => "Job for {$dept}",
                'job_type_category' => 'client_project',
            ];
        }

        return array_merge([
            'customer_id' => $customer->id,
            'departments' => $departments,
            'per_dept' => $perDept,
        ], $overrides);
    }

    public function test_a_single_department_submission_creates_one_job_with_no_project_id(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('jobs.store'), $this->payload(['print']));

        $job = Job::first();
        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame(1, Job::count());
        $this->assertNull($job->project_id);
        $this->assertSame('print', $job->department);
        $this->assertSame(Job::STATUS_POTENTIAL, $job->status);
    }

    public function test_a_multi_department_submission_creates_one_job_per_department_sharing_a_project_id(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('jobs.store'), $this->payload(['print', 'work']));

        $response->assertRedirect(route('jobs.index'));
        $this->assertSame(2, Job::count());

        $jobs = Job::all();
        $this->assertNotNull($jobs->first()->project_id);
        $this->assertSame($jobs->first()->project_id, $jobs->last()->project_id);
        $this->assertSame(['print', 'work'], $jobs->pluck('department')->sort()->values()->toArray());
    }

    public function test_department_scoped_user_cannot_create_a_job_outside_their_visible_departments(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($staff)->post(route('jobs.store'), $this->payload(['work']));

        $response->assertForbidden();
        $this->assertSame(0, Job::count());
    }

    public function test_customer_store_returns_json_for_the_inline_create_form(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->postJson(route('customers.store'), [
            'name' => 'Inline Customer',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Inline Customer']);
        $this->assertDatabaseHas('customers', [
            'name' => 'Inline Customer',
            'source' => 'referral',
            'customer_type' => 'individual',
        ]);
    }
}
