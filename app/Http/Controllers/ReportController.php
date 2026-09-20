<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\User;
use App\Support\SimpleXlsx;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Ports the aggregations from the old app's reports/page.jsx: conversion
// funnel, closed-tickets breakdown, monthly breakdown, department
// breakdown, top customers, PIC performance, plus an .xlsx export of the
// same filtered jobs.
class ReportController extends Controller
{
    /** @return array{0: User, 1: string, 2: string, 3: string, 4: Builder<Job>} */
    private function filteredQuery(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->isBod() || $user->isDeptHead(), 403);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $department = $request->query('department', '');

        $query = Job::query()->with('customer')
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        return [$user, $from, $to, $department, $query];
    }

    public function export(Request $request): Response
    {
        [, $from, $to, , $query] = $this->filteredQuery($request);
        $jobs = $query->orderBy('job_id')->get();
        $live = $jobs->where('status', '!=', Job::STATUS_CANCELLED);
        $completed = $jobs->where('status', Job::STATUS_COMPLETED);
        $label = fn (string $status) => config("kretivco.job_statuses.{$status}.label", $status);

        $summary = [
            ['Kretivco Job Report', "{$from} to {$to}"],
            [],
            ['Total Jobs', $live->count()],
            ['Completed', $completed->count()],
            ['Estimate (RM)', (float) $live->sum('estimation_value')],
            ['Final Value — Completed (RM)', (float) $completed->sum('final_value')],
            ['Variance (RM)', (float) $completed->sum('final_value') - (float) $completed->sum('estimation_value')],
            [],
            ['Department', 'Jobs', 'Estimate (RM)'],
            ...$live->groupBy('department')->map(fn ($g, $d) => [config("kretivco.departments.{$d}.label", $d), $g->count(), (float) $g->sum('estimation_value')])->values()->all(),
        ];

        $rows = [['Job ID', 'Customer', 'Department', 'Job Name', 'Status', 'Est. Value (RM)', 'Final Value (RM)', 'PIC', 'Start', 'Deadline', 'Created']];
        foreach ($jobs as $job) {
            $rows[] = [
                $job->job_id, $job->customer?->name, config("kretivco.departments.{$job->department}.label", $job->department), $job->job_type,
                $label($job->status), (float) $job->estimation_value, $job->final_value !== null ? (float) $job->final_value : null, $job->pic,
                $job->start_date?->toDateString(), $job->deadline?->toDateString(), $job->created_at?->toDateString(),
            ];
        }

        return response(SimpleXlsx::build(['Summary' => $summary, 'Jobs' => $rows]), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"Kretivco_Report_{$from}_{$to}.xlsx\"",
        ]);
    }

    public function index(Request $request): View
    {
        [$user, $from, $to, $department, $query] = $this->filteredQuery($request);

        $jobs = $query->get();
        $notCancelled = $jobs->where('status', '!=', Job::STATUS_CANCELLED);
        $completed = $jobs->where('status', Job::STATUS_COMPLETED);

        $totalEst = $notCancelled->sum('estimation_value');
        $totalFinal = $completed->sum('final_value');
        $variance = $totalFinal - $completed->sum('estimation_value');

        $deptKeys = collect(config('kretivco.departments'))->keys()
            ->filter(fn ($k) => $user->isBod() || in_array($k, $user->visibleDepartments(), true));

        $monthly = collect(range(1, 12))->map(function ($month) use ($notCancelled, $deptKeys) {
            $inMonth = $notCancelled->filter(fn (Job $j) => $j->created_at->month === $month);

            return [
                'label' => Carbon::create(null, $month, 1)->format('M'),
                'total' => $inMonth->count(),
                'est' => $inMonth->sum('estimation_value'),
                'final' => $inMonth->sum('final_value'),
                'by_dept' => $deptKeys->mapWithKeys(fn ($d) => [$d => $inMonth->where('department', $d)->count()]),
            ];
        });

        $deptBreakdown = $deptKeys->mapWithKeys(function ($d) use ($notCancelled) {
            $inDept = $notCancelled->where('department', $d);

            return [$d => ['count' => $inDept->count(), 'est' => $inDept->sum('estimation_value')]];
        });
        $maxDeptEst = max($deptBreakdown->pluck('est')->max(), 1);

        $topCustomers = $notCancelled->groupBy('customer_id')
            ->map(fn ($group) => [
                'name' => $group->first()->customer?->name ?? '—',
                'count' => $group->count(),
                'est' => $group->sum('estimation_value'),
                'final' => $group->sum('final_value'),
            ])
            ->sortByDesc('est')
            ->take(5);

        $picBreakdown = $notCancelled->groupBy(fn (Job $j) => $j->pic ?: '— queue —')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'est' => $group->sum('estimation_value'),
                'completed' => $group->where('status', Job::STATUS_COMPLETED)->count(),
                'final' => $group->where('status', Job::STATUS_COMPLETED)->sum('final_value'),
            ])
            ->sortByDesc('count');

        $funnel = [
            'potential' => $jobs->where('status', Job::STATUS_POTENTIAL)->count(),
            'in_progress' => $jobs->where('status', Job::STATUS_IN_PROGRESS)->count(),
            'completed' => $completed->count(),
        ];
        $funnelTotal = array_sum($funnel);
        $conversionPct = $funnelTotal > 0 ? round($funnel['completed'] / $funnelTotal * 100) : 0;

        // A job that never reached Completed (customer didn't proceed, or
        // work stopped mid-way) still needs accounting for, distinct from
        // one that finished normally — closed_from_status (snapshotted at
        // Close Ticket time) is what tells them apart once status flips to
        // "cancelled".
        $closedPotential = $jobs->where('status', Job::STATUS_CANCELLED)->where('closed_from_status', Job::STATUS_POTENTIAL)->count();
        $closedInProgress = $jobs->where('status', Job::STATUS_CANCELLED)->where('closed_from_status', Job::STATUS_IN_PROGRESS)->count();
        $closedTotal = $closedPotential + $closedInProgress + $completed->count();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'department' => $department,
            'totalJobs' => $notCancelled->count(),
            'completedCount' => $completed->count(),
            'totalEst' => $totalEst,
            'totalFinal' => $totalFinal,
            'variance' => $variance,
            'deptKeys' => $deptKeys,
            'monthly' => $monthly,
            'deptBreakdown' => $deptBreakdown,
            'maxDeptEst' => $maxDeptEst,
            'topCustomers' => $topCustomers,
            'picBreakdown' => $picBreakdown,
            'funnel' => $funnel,
            'conversionPct' => $conversionPct,
            'closedPotential' => $closedPotential,
            'closedInProgress' => $closedInProgress,
            'closedTotal' => $closedTotal,
            'jobsCount' => $jobs->count(),
        ]);
    }
}
