<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        return view('customers.index', [
            'customers' => $query->get(),
            'search' => $search ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $this->validated($request);

        $customer = Customer::create([
            ...$validated,
            'customer_id' => $this->nextCustomerId(),
            'created_by' => $request->user()->id,
        ]);

        // The Job create form's inline "+ New Customer" panel posts here via
        // fetch() so it can select the new customer without a page reload —
        // same endpoint the full Customers page form uses.
        if ($request->wantsJson()) {
            return response()->json($customer);
        }

        return back()->with('success', "{$customer->customer_id} · {$customer->name} ditambah.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update($this->validated($request));

        return back()->with('success', "{$customer->customer_id} dikemaskini.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'in:tender,referral,walk-in,social_media,website,other'],
            'customer_type' => ['nullable', 'in:individual,company'],
            'ssm_number' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        // The Job create form's inline "+ New Customer" mini-form only
        // collects name/company/phone/email/source — customer_type isn't
        // asked there, so it needs a sensible default rather than a
        // validation failure.
        $validated['source'] ??= 'referral';
        $validated['customer_type'] ??= 'individual';

        return $validated;
    }

    /** KCO-001, KCO-002, ... — matches the old app's genCustId(). */
    private function nextCustomerId(): string
    {
        $count = Customer::count();

        return 'KCO-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
