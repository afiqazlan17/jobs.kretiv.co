<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_bod_cannot_view_the_settings_page(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($staff)->get(route('settings.index'));

        $response->assertForbidden();
    }

    public function test_settings_page_shows_active_user_counts_by_role(): void
    {
        $bod = User::create(['name' => 'BOD', 'email' => 'bod@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_BOD]);
        User::create(['name' => 'Staff One', 'email' => 'staff1@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print']);
        User::create(['name' => 'Inactive Staff', 'email' => 'staff2@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print', 'active' => false]);

        $response = $this->actingAs($bod)->get(route('settings.index'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total_active'] === 2 && $stats['staff'] === 1);
    }

    public function test_search_filters_the_user_list_by_name_or_email(): void
    {
        $bod = User::create(['name' => 'BOD', 'email' => 'bod@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_BOD]);
        User::create(['name' => 'Ahmad Ali', 'email' => 'ahmad@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print']);
        User::create(['name' => 'Siti Aminah', 'email' => 'siti@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($bod)->get(route('settings.index', ['search' => 'ahmad']));

        $response->assertOk();
        $response->assertSee('Ahmad Ali');
        $response->assertDontSee('Siti Aminah');
    }

    public function test_inactive_users_are_hidden_unless_show_inactive_is_set(): void
    {
        $bod = User::create(['name' => 'BOD', 'email' => 'bod@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_BOD]);
        User::create(['name' => 'Gone Staff', 'email' => 'gone@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print', 'active' => false]);

        $this->actingAs($bod)->get(route('settings.index'))->assertDontSee('Gone Staff');
        $this->actingAs($bod)->get(route('settings.index', ['show_inactive' => 1]))->assertSee('Gone Staff');
    }

    public function test_reset_jobs_deletes_jobs_and_activity_logs_but_keeps_customers(): void
    {
        $bod = User::create(['name' => 'BOD', 'email' => 'bod@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        $job = Job::create(['job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project']);
        ActivityLog::create(['job_id' => $job->id, 'job_code' => $job->job_id, 'action' => 'created']);

        $response = $this->actingAs($bod)->post(route('settings.reset-jobs'), ['confirm' => 'RESET']);

        $response->assertRedirect();
        $this->assertSame(0, Job::count());
        $this->assertSame(0, ActivityLog::count());
        $this->assertSame(1, Customer::count());
    }

    public function test_reset_jobs_without_typing_reset_is_rejected(): void
    {
        $bod = User::create(['name' => 'BOD', 'email' => 'bod@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        Job::create(['job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project']);

        $response = $this->actingAs($bod)->post(route('settings.reset-jobs'), ['confirm' => 'reset']);

        $response->assertSessionHasErrors('confirm');
        $this->assertSame(1, Job::count());
    }

    public function test_reset_all_data_deletes_jobs_customers_and_leads_but_keeps_users(): void
    {
        $bod = User::create(['name' => 'BOD', 'email' => 'bod@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        Job::create(['job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project']);
        Lead::create(['customer_id' => $customer->id, 'department' => 'print']);

        $response = $this->actingAs($bod)->post(route('settings.reset-all-data'), ['confirm' => 'RESET']);

        $response->assertRedirect();
        $this->assertSame(0, Job::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Lead::count());
        $this->assertSame(1, User::count());
    }

    public function test_non_bod_cannot_reset_jobs(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => bcrypt('x'), 'role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($staff)->post(route('settings.reset-jobs'), ['confirm' => 'RESET']);

        $response->assertForbidden();
    }
}
