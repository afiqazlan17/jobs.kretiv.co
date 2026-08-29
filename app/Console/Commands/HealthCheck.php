<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Replaces the old Postgres run_health_check() function — the same 8 data
// quality checks, run on demand or on a weekly schedule.
class HealthCheck extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Run data-quality checks across customers, jobs, ledger entries, and users';

    public function handle(): int
    {
        $findings = [
            ...$this->customerAddressAnomalies(),
            ...$this->customerMissingCompanyInfo(),
            ...$this->jobInProgressNoPic(),
            ...$this->jobCompletedNoValue(),
            ...$this->jobDeadlineBeforeStart(),
            ...$this->ledgerDuplicateUnreversed(),
            ...$this->ledgerSelfReferencingEntry(),
            ...$this->userDuplicateEmail(),
        ];

        if (empty($findings)) {
            $this->info('No issues found.');

            return self::SUCCESS;
        }

        $this->table(['Category', 'Severity', 'Detail'], $findings);

        return self::SUCCESS;
    }

    private function customerAddressAnomalies(): array
    {
        return Customer::query()
            ->where(fn ($q) => $q->whereRaw('LENGTH(address_line_1) > 100')->orWhereRaw('LENGTH(address_line_2) > 100'))
            ->get()
            ->flatMap(function (Customer $c) {
                $rows = [];
                if (mb_strlen((string) $c->address_line_2) > 100) {
                    $rows[] = ['customer_address_anomaly', 'warning', "Customer {$c->customer_id} ({$c->name}): address_line_2 unusually long (".mb_strlen($c->address_line_2).' chars) — possible duplication'];
                }
                if (mb_strlen((string) $c->address_line_1) > 100) {
                    $rows[] = ['customer_address_anomaly', 'warning', "Customer {$c->customer_id} ({$c->name}): address_line_1 unusually long (".mb_strlen($c->address_line_1).' chars) — possible duplication'];
                }

                return $rows;
            })->all();
    }

    private function customerMissingCompanyInfo(): array
    {
        return Customer::where('customer_type', 'company')
            ->where(fn ($q) => $q->whereNull('company')->orWhereNull('ssm_number'))
            ->get()
            ->map(function (Customer $c) {
                $missing = match (true) {
                    is_null($c->company) && is_null($c->ssm_number) => 'company name and SSM number',
                    is_null($c->company) => 'company name',
                    default => 'SSM number',
                };

                return ['customer_missing_company_info', 'info', "Customer {$c->customer_id} ({$c->name}): marked as company but missing {$missing}"];
            })->all();
    }

    private function jobInProgressNoPic(): array
    {
        return Job::where('status', Job::STATUS_IN_PROGRESS)
            ->where(fn ($q) => $q->whereNull('pic')->orWhere('pic', ''))
            ->where('archived', false)
            ->get()
            ->map(fn (Job $j) => ['job_in_progress_no_pic', 'critical', "Job {$j->job_id}: status is in_progress but has no PIC assigned"])
            ->all();
    }

    private function jobCompletedNoValue(): array
    {
        return Job::where('status', Job::STATUS_COMPLETED)
            ->where(fn ($q) => $q->whereNull('final_value')->orWhere('final_value', 0))
            ->where('estimation_value', '>', 0)
            ->get()
            ->map(fn (Job $j) => ['job_completed_no_value', 'warning', "Job {$j->job_id}: marked completed but final_value is empty (estimation was RM{$j->estimation_value})"])
            ->all();
    }

    private function jobDeadlineBeforeStart(): array
    {
        return Job::whereNotNull('deadline')->whereNotNull('start_date')
            ->whereColumn('deadline', '<', 'start_date')
            ->get()
            ->map(fn (Job $j) => ['job_deadline_before_start', 'warning', "Job {$j->job_id}: deadline ({$j->deadline->toDateString()}) is before its start date ({$j->start_date->toDateString()})"])
            ->all();
    }

    private function ledgerDuplicateUnreversed(): array
    {
        return DB::table('ledger_entries')
            ->select('job_id', 'type', DB::raw('count(*) as cnt'))
            ->where('reversed', false)
            ->whereIn('type', ['invoice', 'receipt'])
            ->groupBy('job_id', 'type')
            ->having('cnt', '>', 1)
            ->get()
            ->map(fn ($row) => ['ledger_duplicate_unreversed', 'critical', "Job {$row->job_id} has {$row->cnt} unreversed {$row->type} entries — should never be more than 1"])
            ->all();
    }

    private function ledgerSelfReferencingEntry(): array
    {
        return LedgerEntry::whereColumn('debit_account', 'credit_account')
            ->get()
            ->map(fn (LedgerEntry $e) => ['ledger_self_referencing_entry', 'critical', "Ledger entry {$e->id} (job {$e->job_id}): debit and credit account are both {$e->debit_account}"])
            ->all();
    }

    private function userDuplicateEmail(): array
    {
        return DB::table('users')
            ->select(DB::raw('LOWER(email) as email'), DB::raw('count(*) as cnt'))
            ->groupBy(DB::raw('LOWER(email)'))
            ->having('cnt', '>', 1)
            ->get()
            ->map(fn ($row) => ['user_duplicate_email', 'warning', "{$row->cnt} user accounts share the email {$row->email}"])
            ->all();
    }
}
