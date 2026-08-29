<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// One-time cutover script: migrates the live Supabase data (pulled via the
// Supabase MCP tool and embedded below — a handful of rows, not worth a
// live Postgres connection on a host with no pgsql driver guarantee) into
// this MySQL schema. Old Postgres UUID PKs are mapped to this schema's
// auto-increment IDs via the $customerMap/$jobMap/$userMap lookups built
// as rows are inserted. Run once, then this command (and its data) should
// be deleted — it has no reason to exist after cutover.
class MigrateFromSupabase extends Command
{
    protected $signature = 'migrate:from-supabase {--fresh : Truncate customers/jobs/activity_log/ledger_entries before migrating}';

    protected $description = 'One-time data migration from the old Supabase/Postgres schema';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Truncating customers, jobs, activity_log, ledger_entries...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            ActivityLog::truncate();
            LedgerEntry::truncate();
            Job::truncate();
            Customer::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Old users are matched by email — this deployment's BOD accounts
        // were already bootstrapped by hand at deploy time (see DEPLOYMENT
        // notes), and dept_head/staff accounts are created via Settings.
        // Emails below must already exist as Users before running this.
        $userMap = User::pluck('id', 'email')->all();

        $customerMap = $this->migrateCustomers();
        $jobMap = $this->migrateJobs($customerMap);
        $this->migrateActivityLog($jobMap, $userMap);
        $this->migrateLedgerEntries();

        $this->info('Done.');
        $this->table(['Table', 'Rows'], [
            ['customers', Customer::count()],
            ['jobs', Job::count()],
            ['activity_log', ActivityLog::count()],
            ['ledger_entries', LedgerEntry::count()],
        ]);

        return self::SUCCESS;
    }

    /** @return array<string, int> old UUID => new id */
    private function migrateCustomers(): array
    {
        $rows = [
            ['uuid' => '65ff43e7-c548-49f9-b8cf-c95c5f60eeb0', 'customer_id' => 'KCO-001', 'name' => 'Ammar I H Alali', 'company' => 'Chef Ammar Sdn. Bhd.', 'phone' => '01115134573', 'email' => 'chefammar1506@gmail.com', 'source' => 'referral', 'customer_type' => 'company', 'ssm_number' => '202001025005 (1381325-P)', 'address_line_1' => 'C6-1, Jalan Reef 1/2, Pusat Perniagaan Reef,', 'address_line_2' => null, 'postcode' => '48000', 'city' => 'Rawang', 'state' => 'Selangor', 'notes' => '', 'created_at' => '2026-08-07 07:26:18'],
            ['uuid' => '515cabc1-8a9b-475b-b970-320a8c930c83', 'customer_id' => 'KCO-002', 'name' => 'Ariff Rahim', 'company' => 'GLAMBOOTH SDN. BHD', 'phone' => '0195567791', 'email' => 'glambooth.my@gmail.com', 'source' => 'referral', 'customer_type' => 'company', 'ssm_number' => '202401000266 (1546117-X)', 'address_line_1' => 'Suite 9.01, Level 9, Menara Summit,', 'address_line_2' => 'Persiaran Kewajipan, USJ 1', 'postcode' => '47600', 'city' => 'Subang Jaya', 'state' => 'Selangor', 'notes' => '', 'created_at' => '2026-08-07 08:46:00'],
        ];

        $map = [];
        foreach ($rows as $r) {
            $uuid = $r['uuid'];
            unset($r['uuid']);
            $customer = Customer::create($r);
            $map[$uuid] = $customer->id;
        }

        return $map;
    }

    /** @return array<string, array{id: int, job_id: string}> old UUID => {id, job_id} */
    private function migrateJobs(array $customerMap): array
    {
        $rows = [
            ['uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'job_id' => 'KP-2026-001', 'customer_uuid' => '65ff43e7-c548-49f9-b8cf-c95c5f60eeb0', 'department' => 'print', 'job_type' => 'Print poster', 'job_type_category' => 'client_project', 'status' => 'in_progress', 'estimation_value' => 100.00, 'final_value' => null, 'pic' => 'Afiq Azlan', 'start_date' => '2026-08-12', 'deadline' => '2026-08-16', 'notes' => 'N/A', 'priority' => 'normal', 'source' => 'other', 'line_items' => [['qty' => 1, 'desc' => 'MATTE', 'item' => 'PRINT POSTER', 'size' => '5X4', 'price' => 100, 'total' => 100]], 'bank' => 'mbb', 'project_id' => null, 'archived' => false, 'created_at' => '2026-08-10 15:45:18', 'updated_at' => '2026-08-11 08:52:10'],
            ['uuid' => '079cf641-7b14-462f-bf44-78ad858ee247', 'job_id' => 'KP-2026-002', 'customer_uuid' => '65ff43e7-c548-49f9-b8cf-c95c5f60eeb0', 'department' => 'print', 'job_type' => 'Print label', 'job_type_category' => 'client_project', 'status' => 'potential', 'estimation_value' => null, 'final_value' => null, 'pic' => 'Nurfadilah Rahmat', 'start_date' => '2026-08-11', 'deadline' => '2026-08-13', 'notes' => null, 'priority' => 'normal', 'source' => 'other', 'line_items' => [], 'bank' => 'mbb', 'project_id' => 'PRJ-2026-001', 'archived' => false, 'created_at' => '2026-08-10 15:46:21', 'updated_at' => '2026-08-10 16:22:33'],
            ['uuid' => 'f133b1c8-671c-4181-9160-7bced09202e8', 'job_id' => 'KW-2026-001', 'customer_uuid' => '65ff43e7-c548-49f9-b8cf-c95c5f60eeb0', 'department' => 'work', 'job_type' => 'Barnd Identity', 'job_type_category' => 'client_project', 'status' => 'in_progress', 'estimation_value' => null, 'final_value' => null, 'pic' => 'Amirul Hafiz', 'start_date' => '2026-08-11', 'deadline' => '2026-08-13', 'notes' => null, 'priority' => 'normal', 'source' => 'other', 'line_items' => [], 'bank' => 'mbb', 'project_id' => 'PRJ-2026-001', 'archived' => false, 'created_at' => '2026-08-10 15:46:21', 'updated_at' => '2026-08-11 11:14:30'],
            ['uuid' => 'e52d276f-983e-4427-a35c-4aedc970ea56', 'job_id' => 'KP-2026-003', 'customer_uuid' => '515cabc1-8a9b-475b-b970-320a8c930c83', 'department' => 'print', 'job_type' => 'Business Card', 'job_type_category' => 'client_project', 'status' => 'potential', 'estimation_value' => 100.00, 'final_value' => null, 'pic' => '', 'start_date' => '2026-08-12', 'deadline' => '2026-08-16', 'notes' => 'xxxx', 'priority' => 'normal', 'source' => 'other', 'line_items' => [['qty' => 1, 'desc' => 'dsadfsfafdsffsdfdsfdffd', 'item' => 'business', 'size' => '', 'price' => 100, 'total' => 100]], 'bank' => 'mbb', 'project_id' => null, 'archived' => false, 'created_at' => '2026-08-10 16:41:53', 'updated_at' => '2026-08-10 16:43:58'],
            ['uuid' => '92069cf4-9c6c-4f79-91d7-691b04c220bb', 'job_id' => 'KP-2026-004', 'customer_uuid' => '65ff43e7-c548-49f9-b8cf-c95c5f60eeb0', 'department' => 'print', 'job_type' => 'Business Card', 'job_type_category' => 'client_project', 'status' => 'completed', 'estimation_value' => null, 'final_value' => 0.00, 'pic' => 'Afiq Azlan', 'start_date' => '2026-08-11', 'deadline' => '2026-08-13', 'notes' => null, 'priority' => 'normal', 'source' => 'other', 'line_items' => [], 'bank' => 'mbb', 'project_id' => null, 'archived' => false, 'created_at' => '2026-08-11 05:26:33', 'updated_at' => '2026-08-11 05:26:53'],
        ];

        $map = [];
        foreach ($rows as $r) {
            $uuid = $r['uuid'];
            $customerUuid = $r['customer_uuid'];
            unset($r['uuid'], $r['customer_uuid']);
            $r['customer_id'] = $customerMap[$customerUuid];

            $job = Job::create($r);
            $map[$uuid] = ['id' => $job->id, 'job_id' => $job->job_id];
        }

        return $map;
    }

    private function migrateActivityLog(array $jobMap, array $userMap): void
    {
        // note fields with pasted base64 images (test-data artifacts, one
        // over 5MB) are dropped rather than migrated — see migration notes.
        $rows = [
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'created', 'note' => 'Job baru dicipta.', 'created_at' => '2026-08-10 15:45:18'],
            ['job_uuid' => 'f133b1c8-671c-4181-9160-7bced09202e8', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'created', 'note' => 'Job baru dicipta.', 'created_at' => '2026-08-10 15:46:22'],
            ['job_uuid' => '079cf641-7b14-462f-bf44-78ad858ee247', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'created', 'note' => 'Job baru dicipta.', 'created_at' => '2026-08-10 15:46:22'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'edited', 'field_changed' => 'pic', 'old_value' => '', 'new_value' => 'Nurfadilah Rahmat', 'created_at' => '2026-08-10 15:47:43'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'edited', 'field_changed' => 'estimation_value', 'old_value' => null, 'new_value' => '100.00', 'created_at' => '2026-08-10 16:03:52'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => null, 'user_name' => 'Nurfadilah Rahmat', 'action' => 'document_generated', 'detail' => 'Quotation (QT-2026-001)', 'created_at' => '2026-08-10 16:04:49'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => null, 'user_name' => 'Nurfadilah Rahmat', 'action' => 'note', 'note' => '[Rich note — a pasted image; dropped during migration]', 'created_at' => '2026-08-10 16:11:46'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => null, 'user_name' => 'Nurfadilah Rahmat', 'action' => 'document_generated', 'detail' => 'Receipt (RC-2026-001)', 'created_at' => '2026-08-10 16:16:00'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'edited', 'field_changed' => 'pic', 'old_value' => 'Nurfadilah Rahmat', 'new_value' => 'Afiq Azlan', 'created_at' => '2026-08-10 16:20:34'],
            ['job_uuid' => '079cf641-7b14-462f-bf44-78ad858ee247', 'user_email' => 'nurfadilah@kretiv.co', 'user_name' => 'Nurfadilah Rahmat', 'action' => 'edited', 'field_changed' => 'pic', 'old_value' => '', 'new_value' => 'Nurfadilah Rahmat', 'created_at' => '2026-08-10 16:22:33'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => null, 'user_name' => 'Nurfadilah Rahmat', 'action' => 'document_generated', 'detail' => 'Invoice (INV-2026-001)', 'created_at' => '2026-08-10 16:27:08'],
            ['job_uuid' => 'e52d276f-983e-4427-a35c-4aedc970ea56', 'user_email' => 'amirul@kretiv.co', 'user_name' => 'Amirul Hafiz', 'action' => 'created', 'note' => 'Job baru dicipta.', 'created_at' => '2026-08-10 16:41:54'],
            ['job_uuid' => 'e52d276f-983e-4427-a35c-4aedc970ea56', 'user_email' => 'amirul@kretiv.co', 'user_name' => 'Amirul Hafiz', 'action' => 'edited', 'field_changed' => 'estimation_value', 'old_value' => null, 'new_value' => '100.00', 'created_at' => '2026-08-10 16:43:55'],
            ['job_uuid' => 'e52d276f-983e-4427-a35c-4aedc970ea56', 'user_email' => null, 'user_name' => 'Amirul Hafiz', 'action' => 'document_generated', 'detail' => 'Quotation (QT-2026-003)', 'created_at' => '2026-08-10 16:43:57'],
            ['job_uuid' => 'e52d276f-983e-4427-a35c-4aedc970ea56', 'user_email' => null, 'user_name' => 'Amirul Hafiz', 'action' => 'note', 'note' => '[Rich note — a pasted image; dropped during migration]', 'created_at' => '2026-08-10 16:45:14'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'afiq@kretiv.co', 'user_name' => 'Afiq Azlan', 'action' => 'status_change', 'field_changed' => 'status', 'old_value' => 'potential', 'new_value' => 'in_progress', 'created_at' => '2026-08-11 04:45:06'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'afiq@kretiv.co', 'user_name' => 'Afiq Azlan', 'action' => 'status_change', 'field_changed' => 'status', 'old_value' => 'in_progress', 'new_value' => 'potential', 'created_at' => '2026-08-11 04:45:10'],
            ['job_uuid' => '92069cf4-9c6c-4f79-91d7-691b04c220bb', 'user_email' => 'afiq@kretiv.co', 'user_name' => 'Afiq Azlan', 'action' => 'created', 'note' => 'Job baru dicipta.', 'created_at' => '2026-08-11 05:26:35'],
            ['job_uuid' => '92069cf4-9c6c-4f79-91d7-691b04c220bb', 'user_email' => 'afiq@kretiv.co', 'user_name' => 'Afiq Azlan', 'action' => 'status_change', 'field_changed' => 'status', 'old_value' => 'potential', 'new_value' => 'in_progress', 'created_at' => '2026-08-11 05:26:44'],
            ['job_uuid' => '92069cf4-9c6c-4f79-91d7-691b04c220bb', 'user_email' => 'afiq@kretiv.co', 'user_name' => 'Afiq Azlan', 'action' => 'completed', 'field_changed' => 'status', 'old_value' => 'in_progress', 'new_value' => 'completed', 'note' => 'Final value: RM 0.00', 'created_at' => '2026-08-11 05:26:53'],
            ['job_uuid' => 'c1f57ec0-e4d2-4211-af8b-cd943ab18b19', 'user_email' => 'amirul@kretiv.co', 'user_name' => 'Amirul Hafiz', 'action' => 'status_change', 'field_changed' => 'status', 'old_value' => 'potential', 'new_value' => 'in_progress', 'created_at' => '2026-08-11 08:52:10'],
            ['job_uuid' => 'f133b1c8-671c-4181-9160-7bced09202e8', 'user_email' => 'amirul@kretiv.co', 'user_name' => 'Amirul Hafiz', 'action' => 'status_change', 'field_changed' => 'status', 'old_value' => 'potential', 'new_value' => 'in_progress', 'created_at' => '2026-08-11 11:13:12'],
            ['job_uuid' => 'f133b1c8-671c-4181-9160-7bced09202e8', 'user_email' => null, 'user_name' => 'Amirul Hafiz', 'action' => 'document_generated', 'detail' => 'Quotation (QT-2026-001)', 'created_at' => '2026-08-11 11:14:30'],
        ];

        foreach ($rows as $r) {
            $jobUuid = $r['job_uuid'];
            $userEmail = $r['user_email'];
            unset($r['job_uuid'], $r['user_email']);

            $job = $jobMap[$jobUuid];
            ActivityLog::create(array_merge($r, [
                'job_id' => $job['id'],
                'job_code' => $job['job_id'],
                'user_id' => $userEmail ? ($userMap[$userEmail] ?? null) : null,
            ]));
        }
    }

    private function migrateLedgerEntries(): void
    {
        // job_id here is the business code (jobs.job_id), not the PK —
        // matches this schema's convention already, no mapping needed.
        $rows = [
            ['date' => '2026-08-10 16:16:00', 'type' => 'receipt', 'description' => 'Resit RC-2026-001 — Chef Ammar Sdn. Bhd.', 'department' => 'print', 'job_id' => 'KP-2026-001', 'doc_number' => 'RC-2026-001', 'debit_account' => 'bank_mbb', 'credit_account' => 'ar', 'amount' => 100, 'bank' => 'mbb', 'created_by' => 'Nurfadilah Rahmat', 'reversed' => false, 'created_at' => '2026-08-10 16:16:00'],
            ['date' => '2026-08-10 16:27:08', 'type' => 'invoice', 'description' => 'Invois INV-2026-001 — Chef Ammar Sdn. Bhd.', 'department' => 'print', 'job_id' => 'KP-2026-001', 'doc_number' => 'INV-2026-001', 'debit_account' => 'ar', 'credit_account' => 'revenue_print', 'amount' => 100, 'bank' => null, 'created_by' => 'Nurfadilah Rahmat', 'reversed' => false, 'created_at' => '2026-08-10 16:27:08'],
        ];

        foreach ($rows as $r) {
            LedgerEntry::create($r);
        }
    }
}
