<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        // The Settings *page* is BOD-only in the UI, even though the
        // underlying users_select_all RLS policy let anyone read the
        // table — matches the old app's Access Reference ("Settings":
        // BOD-only), a page-level restriction on top of the record-level
        // UserPolicy::viewAny (which stays true for everyone).
        abort_unless($request->user()->isBod(), 403);

        $users = User::orderByRaw("FIELD(role, 'bod', 'dept_head', 'staff', 'intern')")
            ->orderBy('name')
            ->get();

        return view('settings.index', ['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $this->validated($request);

        $password = Str::password(16);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'active' => true,
            'staff_id' => $this->nextStaffId(),
        ]);

        return back()->with('success', "{$user->name} ditambah sebagai {$validated['role']}. Password sementara: {$password} (salin sekarang — tidak dipaparkan lagi).");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $this->validated($request, $user);

        $user->update($validated);

        return back()->with('success', "{$user->name} dikemaskini.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->update(['active' => ! $user->active]);

        return back()->with('success', $user->active ? "{$user->name} diaktifkan semula." : "{$user->name} dinyahaktifkan.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($user ? ",{$user->id}" : '')],
            'role' => ['required', 'in:'.User::ROLE_BOD.','.User::ROLE_DEPT_HEAD.','.User::ROLE_STAFF.','.User::ROLE_INTERN],
            'department' => ['nullable', 'string'],
            'visible_departments' => ['nullable', 'array'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        // BOD has no department — matches the old app's needsDept logic.
        if ($validated['role'] === User::ROLE_BOD) {
            $validated['department'] = null;
            $validated['visible_departments'] = [];
        } else {
            $validated['visible_departments'] = $validated['visible_departments'] ?? [];
        }

        return $validated;
    }

    /** KCM001, KCM002, ... — matches the old app's staff_id sequence. */
    private function nextStaffId(): string
    {
        $lastNumber = User::whereNotNull('staff_id')
            ->get()
            ->map(fn (User $u) => (int) substr($u->staff_id, 3))
            ->max() ?? 0;

        return 'KCM'.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
