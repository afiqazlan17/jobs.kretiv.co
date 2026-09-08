<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesJobIds;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobController extends Controller
{
    use GeneratesJobIds;

    /**
     * @var array<string, array{title: string, sub: string}>
     */
    public const VIEW_META = [
        'queue' => ['title' => 'Job Queue', 'sub' => 'All jobs, sorted by most recently changed'],
        'aging' => ['title' => 'Aging Job', 'sub' => 'Jobs untouched for the longest'],
        'mine' => ['title' => 'My Jobs', 'sub' => 'Jobs under your responsibility'],
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Job::class);

        $user = $request->user();
        $view = array_key_exists($request->query('view'), self::VIEW_META) ? $request->query('view') : 'queue';

        $query = Job::query()->with(['customer', 'activityLog'])->where('archived', false);

        if (! $user->isBod()) {
            $query->whereIn('department', $user->visibleDepartments());
        }

        if ($view === 'mine') {
            $query->where('pic', $user->name);
        } elseif ($view === 'aging') {
            $query->whereNotIn('status', [Job::STATUS_COMPLETED, Job::STATUS_CANCELLED]);
        }

        if ($dept = $request->query('department')) {
            $query->where('department', $dept);
        }

        $status = $request->query('status');
        if (in_array($status, array_keys(config('kretivco.hold_statuses')), true)) {
            $query->where('hold_status', $status);
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('job_id', 'like', "%{$search}%")
                    ->orWhere('job_type', 'like', "%{$search}%")
                    ->orWhere('pic', 'like', "%{$search}%")
                    ->orWhere('project_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $jobs = $query->get();

        // "Last touched" = the most recent of the job's own timestamps and
        // every activity-log entry against it (notes/comments included —
        // status/field changes don't always bump updated_at otherwise).
        $jobs->each(function (Job $job) {
            $job->last_touched = $job->activityLog->max('created_at') ?? $job->updated_at;
        });

        // Sibling jobs sharing a project_id (created together across
        // departments) — surfaced as a 🔗 badge next to the Job ID.
        $projectIds = $jobs->pluck('project_id')->filter()->unique()->values();
        $siblingsByProject = $projectIds->isEmpty()
            ? collect()
            : Job::whereIn('project_id', $projectIds)->get()->groupBy('project_id');

        $sortCol = in_array($request->query('sort'), ['id', 'customer', 'dept', 'status', 'value', 'deadline', 'touched'], true)
            ? $request->query('sort') : 'touched';
        $sortDir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $jobs = $jobs->sortBy(function (Job $job) use ($sortCol) {
            return match ($sortCol) {
                'id' => $job->job_id,
                'customer' => $job->customer?->name ?? '',
                'dept' => $job->department,
                'status' => $job->status,
                'value' => (float) ($job->estimation_value ?? 0),
                'deadline' => $job->deadline?->timestamp ?? PHP_INT_MAX,
                default => $job->last_touched?->timestamp ?? 0,
            };
        }, SORT_REGULAR, $sortDir === 'desc')->values();

        return view('jobs.index', [
            'jobs' => $jobs,
            'siblingsByProject' => $siblingsByProject,
            'department' => $dept ?? '',
            'status' => $status ?? '',
            'search' => $search ?? '',
            'view' => $view,
            'sortCol' => $sortCol,
            'sortDir' => $sortDir,
            'pipelineValue' => $jobs->whereIn('status', [Job::STATUS_POTENTIAL, Job::STATUS_IN_PROGRESS])->sum('estimation_value'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Job::class);

        return view('jobs.create', [
            'customers' => Customer::orderBy('name')->get(),
            'departments' => $this->availableDepartments($request),
        ]);
    }

    /**
     * A single submission can create one job per selected department, all
     * under the same customer — matches the old app's multi-department
     * create flow. More than one department shares a generated Project ID
     * so the siblings stay linked (see GeneratesJobIds::nextProjectId());
     * a single-department submission gets no project_id, same as before.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Job::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'departments' => ['required', 'array', 'min:1'],
            'departments.*' => ['in:'.implode(',', array_keys(self::DEPT_CODES))], // DEPT_CODES from GeneratesJobIds
            'per_dept' => ['required', 'array'],
            'per_dept.*.job_type' => ['required', 'string', 'max:255'],
            'per_dept.*.job_type_category' => ['required', 'in:client_project,product_sale'],
            'per_dept.*.bank' => ['nullable', 'in:mbb,affin'],
            'per_dept.*.pic' => ['nullable', 'string', 'max:255'],
            'per_dept.*.start_date' => ['nullable', 'date'],
            'per_dept.*.deadline' => ['nullable', 'date'],
            'per_dept.*.notes' => ['nullable', 'string'],
            'per_dept.*.estimation_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Defense in depth: the create form only offers departments the
        // user can see (availableDepartments()), but posted departments
        // outside that set must still be rejected server-side — BOD is
        // exempt (sees everything).
        if (! $request->user()->isBod()) {
            $allowed = $request->user()->visibleDepartments();
            if (array_diff($validated['departments'], $allowed) !== []) {
                abort(403);
            }
        }

        $departments = $validated['departments'];
        $isMulti = count($departments) > 1;
        $projectId = $isMulti ? $this->nextProjectId() : null;

        $createdJobs = collect($departments)->map(function (string $dept) use ($validated, $projectId, $request) {
            $fields = $validated['per_dept'][$dept];

            $job = Job::create([
                'job_id' => $this->nextJobId($dept),
                'customer_id' => $validated['customer_id'],
                'department' => $dept,
                'project_id' => $projectId,
                'job_type' => $fields['job_type'],
                'job_type_category' => $fields['job_type_category'],
                'bank' => $fields['bank'] ?? null,
                'pic' => $fields['pic'] ?? null,
                'start_date' => $fields['start_date'] ?? null,
                'deadline' => $fields['deadline'] ?? null,
                'notes' => $fields['notes'] ?? null,
                'estimation_value' => $fields['estimation_value'] ?? null,
                'status' => Job::STATUS_POTENTIAL,
                'created_by' => $request->user()->id,
            ]);

            ActivityLog::create([
                'job_id' => $job->id,
                'job_code' => $job->job_id,
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'action' => 'created',
                'note' => 'Job created.',
            ]);

            return $job;
        });

        if (! $isMulti) {
            return redirect()->route('jobs.show', $createdJobs->first())->with('success', "{$createdJobs->first()->job_id} created.");
        }

        return redirect()->route('jobs.index')->with(
            'success',
            "{$createdJobs->count()} jobs created under Project {$projectId} ({$createdJobs->pluck('job_id')->join(', ')})."
        );
    }

    public function show(Job $job): View
    {
        $this->authorize('view', $job);

        return view('jobs.show', [
            'job' => $job->load(['customer', 'activityLog' => fn ($q) => $q->orderByDesc('created_at')]),
        ]);
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'job_type' => ['required', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'estimation_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $job->update($validated);

        return back()->with('success', "{$job->job_id} updated.");
    }

    /** Claims the job — sets PIC and moves potential -> in_progress. */
    public function takeIn(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate(['pic' => ['required', 'string', 'max:255']]);

        $job->update(['pic' => $validated['pic'], 'status' => Job::STATUS_IN_PROGRESS]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'status_change',
            'field_changed' => 'status',
            'old_value' => Job::STATUS_POTENTIAL,
            'new_value' => Job::STATUS_IN_PROGRESS,
            'note' => "Taken in by {$validated['pic']}.",
        ]);

        return back()->with('success', "{$job->job_id} taken in.");
    }

    /** Close Ticket — from Potential or In Progress, mandatory reason, snapshots the stage it closed at. */
    public function closeTicket(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'cancel_reason' => ['required', 'in:'.implode(',', array_keys(config('kretivco.cancel_reasons')))],
            'cancel_reason_text' => ['nullable', 'string', 'max:255'],
        ]);

        $fromStatus = $job->status;

        $job->update([
            'status' => Job::STATUS_CANCELLED,
            'closed_from_status' => $fromStatus,
            'cancel_reason' => $validated['cancel_reason'],
            'cancel_reason_text' => $validated['cancel_reason_text'] ?? null,
        ]);

        $reasonLabel = config('kretivco.cancel_reasons')[$validated['cancel_reason']] ?? $validated['cancel_reason'];

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'cancelled',
            'note' => $validated['cancel_reason'] === 'other' && $validated['cancel_reason_text']
                ? $validated['cancel_reason_text']
                : $reasonLabel,
        ]);

        return redirect()->route('jobs.index')->with('success', "{$job->job_id} closed.");
    }

    /** Complete — from In Progress, records final value. */
    public function complete(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate(['final_value' => ['required', 'numeric', 'min:0']]);

        $job->update(['status' => Job::STATUS_COMPLETED, 'final_value' => $validated['final_value']]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'completed',
            'note' => 'Final value: RM '.number_format($validated['final_value'], 2),
        ]);

        return back()->with('success', "{$job->job_id} marked completed.");
    }

    /**
     * @return array<string, array{label: string, color: string}>
     */
    private function availableDepartments(Request $request): array
    {
        $user = $request->user();
        $all = config('kretivco.departments');

        if ($user->isBod()) {
            return $all;
        }

        return array_intersect_key($all, array_flip($user->visibleDepartments()));
    }
}
