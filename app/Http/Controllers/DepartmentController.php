<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $jobs = Job::where('archived', false)->get()->groupBy('department');

        $departments = collect(config('kretivco.departments'))
            ->filter(fn ($d, $key) => $user->isBod() || in_array($key, $user->visibleDepartments(), true))
            ->map(function ($dept, $key) use ($jobs) {
                $mine = $jobs->get($key, collect());
                $active = $mine->whereIn('status', [Job::STATUS_POTENTIAL, Job::STATUS_IN_PROGRESS]);

                return $dept + (config("kretivco.department_profiles.{$key}") ?? []) + [
                    'active' => $active->count(),
                    'completed' => $mine->where('status', Job::STATUS_COMPLETED)->count(),
                    'pipeline' => (float) $active->sum('estimation_value'),
                ];
            });

        return view('departments.index', ['departments' => $departments]);
    }
}
