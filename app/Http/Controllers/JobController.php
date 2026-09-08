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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Job::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'department' => ['required', 'in:'.implode(',', array_keys(self::DEPT_CODES))], // DEPT_CODES from GeneratesJobIds
            'job_type' => ['required', 'string', 'max:255'],
            'job_type_category' => ['required', 'in:client_project,product_sale'],
            'bank' => ['nullable', 'in:mbb,affin'],
            'pic' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'estimation_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Defense in depth: the create form only offers departments the
        // user can see (availableDepartments()), but a posted department
        // outside that set must still be rejected server-side — BOD is
        // exempt (sees everything).
        if (! $request->user()->isBod() && ! in_array($validated['department'], $request->user()->visibleDepartments(), true)) {
            abort(403);
        }

        $job = Job::create([
            ...$validated,
            'job_id' => $this->nextJobId($validated['department']),
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

        return redirect()->route('jobs.show', $job)->with('success', "{$job->job_id} created.");
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
