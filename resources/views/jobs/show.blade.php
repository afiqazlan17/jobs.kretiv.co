<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight font-mono">{{ $job->job_id }}</h2>
            @php $st = config('kretivco.job_statuses')[$job->status] ?? null; @endphp
            <span class="text-xs rounded-full px-2 py-1" style="background: {{ ($st['color'] ?? '#eee') }}22; color: {{ $st['color'] ?? '#666' }}">{{ $st['label'] ?? $job->status }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-400">Customer</span><br>{{ $job->customer?->name ?? '—' }}</div>
                <div><span class="text-gray-400">Department</span><br>{{ config('kretivco.departments')[$job->department]['label'] ?? $job->department }}</div>
                <div><span class="text-gray-400">Job Name</span><br>{{ $job->job_type }}</div>
                <div><span class="text-gray-400">PIC</span><br>{{ $job->pic ?? '— queue —' }}</div>
                <div><span class="text-gray-400">Start Date</span><br>{{ $job->start_date?->format('d M Y') ?? '—' }}</div>
                <div><span class="text-gray-400">Deadline</span><br>{{ $job->deadline?->format('d M Y') ?? '—' }}</div>
                <div><span class="text-gray-400">Estimation Value</span><br>RM {{ number_format($job->estimation_value ?? 0, 2) }}</div>
                <div><span class="text-gray-400">Final Value</span><br>{{ $job->final_value !== null ? 'RM '.number_format($job->final_value, 2) : '—' }}</div>
                @if ($job->notes)
                    <div class="sm:col-span-2"><span class="text-gray-400">Notes</span><br>{{ $job->notes }}</div>
                @endif
                @if ($job->status === 'cancelled')
                    <div class="sm:col-span-2 text-red-600">
                        Closed from <strong>{{ config('kretivco.job_statuses')[$job->closed_from_status]['label'] ?? $job->closed_from_status }}</strong> —
                        {{ config('kretivco.cancel_reasons')[$job->cancel_reason] ?? $job->cancel_reason }}
                        @if ($job->cancel_reason_text) : {{ $job->cancel_reason_text }} @endif
                    </div>
                @endif
            </div>

            @can('update', $job)
            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ closing: false }">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Actions</h3>
                <div class="flex flex-wrap gap-2">
                    @if ($job->status === 'potential' && ! $job->pic)
                        <form method="POST" action="{{ route('jobs.take-in', $job) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="text" name="pic" placeholder="Your name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                            <x-primary-button type="submit">Take In Job</x-primary-button>
                        </form>
                    @endif
                    @if ($job->status === 'in_progress')
                        <form method="POST" action="{{ route('jobs.complete', $job) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="number" step="0.01" min="0" name="final_value" placeholder="Final value (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                            <x-primary-button type="submit">Mark Completed</x-primary-button>
                        </form>
                    @endif
                    @if (in_array($job->status, ['potential', 'in_progress']))
                        <button type="button" @click="closing = !closing" class="text-xs text-red-600 hover:underline">Close Ticket</button>
                    @endif
                    <a href="{{ route('jobs.invoice', $job) }}" class="text-xs font-semibold px-3 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Generate Invoice</a>
                    <a href="{{ route('jobs.receipt', $job) }}" class="text-xs font-semibold px-3 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Generate Receipt</a>
                </div>
                <form method="POST" action="{{ route('jobs.close-ticket', $job) }}" x-show="closing" x-cloak class="mt-4 flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Reason for Closing *</label>
                        <select name="cancel_reason" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('kretivco.cancel_reasons') as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="cancel_reason_text" placeholder="Details (if Other)" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Yes, Close Ticket</button>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ showVendorForm: false, payingId: null }">
                @php
                    $vendorCosts = collect($job->vendor_costs ?? []);
                    $totalEstimated = $vendorCosts->sum(fn ($v) => (float) ($v['estimated_cost'] ?? 0));
                    $totalActual = $vendorCosts->sum(fn ($v) => (float) ($v['actual_cost'] ?? 0));
                    $customerPrice = (float) ($job->final_value ?? $job->estimation_value ?? 0);
                @endphp
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase">Vendor Cost</h3>
                    @can('update', $job)
                    <button type="button" @click="showVendorForm = !showVendorForm" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 text-pink-600 hover:bg-pink-50">+ Add Vendor Cost</button>
                    @endcan
                </div>

                @can('update', $job)
                <form method="POST" action="{{ route('jobs.vendor-costs.store', $job) }}" x-show="showVendorForm" x-cloak class="mb-4 p-3 rounded-md bg-gray-50 border border-gray-200 flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Vendor *</label>
                        <select name="vendor_id" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— Select —</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->vendor_id }} · {{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Estimated Cost (RM)</label>
                        <input type="number" step="0.01" min="0" name="estimated_cost" class="block rounded-md border-gray-300 shadow-sm text-sm w-32">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Actual Cost (RM)</label>
                        <input type="number" step="0.01" min="0" name="actual_cost" class="block rounded-md border-gray-300 shadow-sm text-sm w-32">
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <label class="text-xs text-gray-500">Notes</label>
                        <input type="text" name="notes" placeholder="e.g. includes delivery" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Save</button>
                </form>
                @endcan

                @if ($vendorCosts->isEmpty())
                    <p class="text-sm text-gray-400 italic">No vendor cost recorded yet — leave blank if this job is done in-house.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($vendorCosts as $item)
                            @php $vendor = $vendors->firstWhere('id', $item['vendor_id']); @endphp
                            <div class="border border-gray-100 rounded-md p-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-semibold">{{ $vendor?->name ?? 'Unknown vendor' }}</span>
                                        @if ($vendor)<span class="ml-1.5 text-xs text-gray-400 font-mono">{{ $vendor->vendor_id }}</span>@endif
                                    </div>
                                    <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ ($item['status'] ?? 'unpaid') === 'paid' ? 'text-green-600 bg-green-50' : 'text-amber-600 bg-amber-50' }}">
                                        {{ ($item['status'] ?? 'unpaid') === 'paid' ? '✓ Paid' : '⏸ Unpaid' }}
                                    </span>
                                </div>
                                <div class="flex gap-4 mt-1.5 text-xs text-gray-500">
                                    <span>Estimated: <strong class="text-gray-800">{{ $item['estimated_cost'] ? 'RM '.number_format($item['estimated_cost'], 2) : '—' }}</strong></span>
                                    <span>Actual: <strong class="text-gray-800">{{ $item['actual_cost'] ? 'RM '.number_format($item['actual_cost'], 2) : '—' }}</strong></span>
                                </div>
                                @if (!empty($item['notes']))
                                    <p class="text-xs text-gray-400 italic mt-1">{{ $item['notes'] }}</p>
                                @endif
                                @can('update', $job)
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    @if (($item['status'] ?? 'unpaid') === 'unpaid' && (float) ($item['actual_cost'] ?? 0) > 0)
                                        <button type="button" @click="payingId = (payingId === '{{ $item['id'] }}' ? null : '{{ $item['id'] }}')" class="text-xs font-semibold px-2.5 py-1 rounded-md border border-green-200 text-green-600 hover:bg-green-50">Mark as Paid</button>
                                    @endif
                                    <form method="POST" action="{{ route('jobs.vendor-costs.destroy', [$job, $item['id']]) }}" onsubmit="return confirm('Remove this vendor cost entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded-md border border-red-200 text-red-600 hover:bg-red-50">Remove</button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('jobs.vendor-costs.mark-paid', [$job, $item['id']]) }}" x-show="payingId === '{{ $item['id'] }}'" x-cloak class="flex flex-wrap items-end gap-2 mt-2 p-2.5 rounded-md bg-gray-50">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-gray-500">Bank *</label>
                                        <select name="bank" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                                            @foreach (config('kretivco.banks') as $key => $bank)
                                                <option value="{{ $key }}">{{ $bank['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500">Date Paid</label>
                                        <input type="date" name="date" value="{{ now()->toDateString() }}" class="block rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Confirm Paid</button>
                                </form>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-600 space-y-1">
                        <div class="flex justify-between"><span>Total Estimated</span><strong>RM {{ number_format($totalEstimated, 2) }}</strong></div>
                        <div class="flex justify-between"><span>Estimated Margin</span><strong class="{{ ($customerPrice - $totalEstimated) >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($customerPrice - $totalEstimated, 2) }}</strong></div>
                        @if ($totalActual > 0)
                            <div class="flex justify-between"><span>Total Actual</span><strong>RM {{ number_format($totalActual, 2) }}</strong></div>
                            <div class="flex justify-between"><span>Actual Margin</span><strong class="{{ ($customerPrice - $totalActual) >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($customerPrice - $totalActual, 2) }}</strong></div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Attachments</h3>
                <div class="space-y-2 text-sm mb-4">
                    @forelse ($job->attachments ?? [] as $att)
                        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                            <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="text-indigo-600 hover:underline">{{ $att['name'] }}</a>
                            <div class="flex items-center gap-3 text-xs text-gray-400">
                                <span>{{ $att['kind'] }} · {{ $att['uploaded_by'] }}</span>
                                @can('update', $job)
                                <form method="POST" action="{{ route('jobs.attachments.destroy', [$job, $att['id']]) }}" onsubmit="return confirm('Delete this attachment?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400">No attachments.</p>
                    @endforelse
                </div>
                @can('update', $job)
                <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="kind" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="artwork">Artwork</option>
                        <option value="approval">Customer Approval</option>
                        <option value="document">Document</option>
                    </select>
                    <input type="file" name="file" required class="text-sm">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Upload</button>
                </form>
                @endcan
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Activity Log</h3>
                <div class="space-y-3 text-sm">
                    @forelse ($job->activityLog as $log)
                        <div class="border-b border-gray-100 pb-2">
                            <div class="text-gray-800">{{ $log->note ?? ucfirst(str_replace('_', ' ', $log->action)) }}</div>
                            <div class="text-xs text-gray-400">{{ $log->user_name ?? 'System' }} · {{ $log->created_at->format('d M Y, g:ia') }}</div>
                        </div>
                    @empty
                        <p class="text-gray-400">No activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
