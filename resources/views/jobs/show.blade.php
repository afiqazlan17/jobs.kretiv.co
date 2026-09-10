<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1 text-white/80 hover:text-white text-sm">&larr; Back</a>

                @can('update', $job)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold rounded-md">
                        Action <span class="text-[10px]">&#9662;</span>
                    </button>
                    <div x-show="open" x-cloak x-transition @click="open = false" class="absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg py-1 z-20 text-sm text-gray-700">
                        @if ($job->status === 'potential' && ! $job->pic)
                            <button type="button" @click="$store.jobActions.panel = 'takein'" class="w-full text-left px-4 py-2 hover:bg-gray-50">🙋 Take In Job</button>
                        @endif
                        <button type="button" @click="$store.jobActions.panel = 'reassign'" class="w-full text-left px-4 py-2 hover:bg-gray-50">🔁 Change Current Responsible</button>
                        @if (! $job->hold_status)
                            <button type="button" @click="$store.jobActions.panel = 'hold-pending'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-amber-600">⏸ Pending Job</button>
                            <button type="button" @click="$store.jobActions.panel = 'hold-suspended'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600">⛔ Suspend Job</button>
                        @else
                            <form method="POST" action="{{ route('jobs.resume', $job) }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-green-600">▶️ Resume Job</button>
                            </form>
                        @endif
                        @if (! in_array($job->status, ['completed', 'cancelled']))
                            <button type="button" @click="$store.jobActions.panel = 'complete'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-green-600">✅ Close Job</button>
                        @endif
                        @if (! in_array($job->status, ['completed', 'cancelled']))
                            <div class="border-t border-gray-100 my-1"></div>
                            <button type="button" @click="$store.jobActions.panel = 'cancel'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600">&times; Cancel Job</button>
                        @endif
                        @if (! $job->archived && $job->status !== 'cancelled')
                            <form method="POST" action="{{ route('jobs.archive', $job) }}" onsubmit="return confirm('Archive {{ $job->job_id }}? It will be hidden from the Job Queue.')">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-gray-500">🗄️ Archive</button>
                            </form>
                        @endif
                    </div>
                </div>
                @endcan
            </div>

            <div class="flex items-start justify-between flex-wrap gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-white leading-tight font-mono">{{ $job->job_id }} | {{ $job->job_type }}</h2>
                    @if ($job->customer)
                        <a href="{{ route('customers.index', ['q' => $job->customer->customer_id]) }}" class="block text-sm text-white/90 hover:underline mt-1">{{ $job->customer->customer_id }} | {{ $job->customer->company ?: $job->customer->name }}</a>
                        <div class="text-sm text-white/70">{{ $job->customer->name }}@if ($job->customer->phone) | {{ $job->customer->phone }} @endif</div>
                        @if ($job->customer->email)
                            <div class="text-sm text-white/70">{{ $job->customer->email }}</div>
                        @endif
                    @else
                        <div class="text-sm text-white/70 mt-1">No customer linked</div>
                    @endif
                    @if ($job->project_id)
                        <div class="text-sm text-white/70 mt-1">Project ID: {{ $job->project_id }}</div>
                    @endif
                </div>
                <div class="text-right">
                    @php $st = config('kretivco.job_statuses.'.$job->status); @endphp
                    <div class="text-white font-semibold">Status: {{ $st['label'] ?? $job->status }}</div>
                    <div class="text-sm text-white/70">Current Responsible: {{ $job->pic ?? 'Not yet assigned' }}</div>
                    <div class="text-sm text-white/70">Current Department: {{ config('kretivco.departments.'.$job->department.'.label') }}</div>
                    @if ($job->hold_status)
                        @php $hs = config('kretivco.hold_statuses.'.$job->hold_status); @endphp
                        <span class="inline-block mt-1.5 text-xs font-semibold rounded-full px-2.5 py-1 bg-white/20 text-white">{{ $hs['icon'] }} {{ $hs['label'] }}@if ($job->hold_reason): {{ $job->hold_reason }}@endif</span>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="p-6 space-y-4" x-data>

        @if (session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Job Progress --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Job Progress</h3>
            @if ($job->status === 'cancelled')
                <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 font-medium">
                    &times; Job Cancelled — {{ config('kretivco.cancel_reasons.'.$job->cancel_reason, $job->cancel_reason) }}@if ($job->cancel_reason_text): {{ $job->cancel_reason_text }}@endif
                </div>
            @else
                @php
                    $stages = [
                        'potential' => 'Potential',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ];
                    $stageKeys = array_keys($stages);
                    $currentIdx = array_search($job->status, $stageKeys, true);
                    $canForwardTo = match ($job->status) {
                        'potential' => 'in_progress',
                        'in_progress' => 'completed',
                        default => null,
                    };
                    $canRollbackTo = \App\Http\Controllers\JobController::ROLLBACK_MAP[$job->status] ?? null;
                @endphp
                <div class="flex items-center">
                    @foreach ($stageKeys as $i => $key)
                        @php
                            $state = $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : 'upcoming');
                            $clickableForward = $key === $canForwardTo;
                            $clickableBack = $key === $canRollbackTo;
                        @endphp
                        <div class="flex-1 flex flex-col items-center relative">
                            @if ($i > 0)
                                <div class="absolute top-4 h-0.5 {{ $i <= $currentIdx ? 'bg-green-400' : 'bg-gray-200' }}" style="right: 50%; width: 100%;"></div>
                            @endif
                            @if ($clickableForward)
                                <button type="button" @click="$store.jobActions.panel = '{{ $key === 'in_progress' ? 'takein' : 'complete' }}'"
                                        class="relative z-10 w-8 h-8 rounded-full border-2 border-blue-400 bg-white text-blue-600 text-xs font-bold flex items-center justify-center hover:bg-blue-50" title="Advance to {{ $stages[$key] }}">{{ $i + 1 }}</button>
                            @elseif ($clickableBack)
                                <button type="button" @click="$store.jobActions.panel = 'rollback'"
                                        class="relative z-10 w-8 h-8 rounded-full border-2 border-gray-300 bg-white text-gray-500 text-xs font-bold flex items-center justify-center hover:bg-gray-50" title="Roll back to {{ $stages[$key] }}">{{ $i + 1 }}</button>
                            @elseif ($state === 'done')
                                <div class="relative z-10 w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center text-sm">&check;</div>
                            @elseif ($state === 'current')
                                <div class="relative z-10 w-8 h-8 rounded-full text-white flex items-center justify-center text-xs font-bold shadow" style="background: {{ config('kretivco.job_statuses.'.$key.'.color') }}">{{ $i + 1 }}</div>
                            @else
                                <div class="relative z-10 w-8 h-8 rounded-full border-2 border-gray-200 bg-white text-gray-400 flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</div>
                            @endif
                            <span class="mt-2 text-xs font-medium {{ $state === 'upcoming' ? 'text-gray-400' : 'text-gray-700' }}">{{ $stages[$key] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sibling / project banner --}}
        @if ($job->project_id && $siblings->isNotEmpty())
            <div class="rounded-md bg-pink-50 border border-pink-100 text-sm px-4 py-3">
                🔗 Project <strong>{{ $job->project_id }}</strong> — with
                @foreach ($siblings as $sibling)
                    <a href="{{ route('jobs.show', $sibling) }}" class="font-semibold text-pink-600 hover:underline">{{ $sibling->job_id }} · {{ config('kretivco.departments.'.$sibling->department.'.label') }}</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        @endif

        {{-- Action panels — toggled by the header's Action dropdown or the stepper --}}
        @can('update', $job)
        <div x-show="$store.jobActions.panel" x-cloak class="bg-white shadow-sm sm:rounded-lg p-6 border-2 border-pink-100">
            <div x-show="$store.jobActions.panel === 'takein'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Take In Job</h3>
                <form method="POST" action="{{ route('jobs.take-in', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="text" name="pic" placeholder="Your name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <x-primary-button type="submit">Take In Job</x-primary-button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'reassign'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Change Current Responsible</h3>
                <form method="POST" action="{{ route('jobs.reassign', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    @method('PUT')
                    <input type="text" name="pic" value="{{ $job->pic }}" placeholder="New staff name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <x-primary-button type="submit">Save</x-primary-button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'hold-pending'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Mark Pending</h3>
                <form method="POST" action="{{ route('jobs.hold', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="hold_status" value="pending">
                    <input type="text" name="hold_reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-amber-500 text-white hover:bg-amber-600">Mark Pending</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'hold-suspended'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Suspend Job</h3>
                <form method="POST" action="{{ route('jobs.hold', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="hold_status" value="suspended">
                    <input type="text" name="hold_reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-500 text-white hover:bg-red-600">Suspend</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'complete'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Close Job</h3>
                <form method="POST" action="{{ route('jobs.complete', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="number" step="0.01" min="0" name="final_value" placeholder="Final value (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                    <x-primary-button type="submit">Mark Completed</x-primary-button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'cancel'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Cancel Job</h3>
                <form method="POST" action="{{ route('jobs.close-ticket', $job) }}" class="flex flex-wrap items-end gap-2">
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
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Yes, Cancel Job</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'rollback'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Roll Back Status</h3>
                <form method="POST" action="{{ route('jobs.rollback', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="text" name="reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-700 text-white hover:bg-gray-800">Confirm Rollback</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
        </div>
        @endcan

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            {{-- Left column --}}
            <div class="space-y-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-400">PIC</span><br>{{ $job->pic ?? '— queue —' }}</div>
                    <div><span class="text-gray-400">Bank</span><br>{{ config('kretivco.banks.'.$job->bank.'.label', '—') }}</div>
                    <div><span class="text-gray-400">Start Date</span><br>{{ $job->start_date?->format('d M Y') ?? '—' }}</div>
                    <div><span class="text-gray-400">Deadline</span><br>{{ $job->deadline?->format('d M Y') ?? '—' }}</div>
                    <div><span class="text-gray-400">Estimation Value</span><br>RM {{ number_format($job->estimation_value ?? 0, 2) }}</div>
                    <div><span class="text-gray-400">Final Value</span><br>{{ $job->final_value !== null ? 'RM '.number_format($job->final_value, 2) : '—' }}</div>
                    @if ($job->notes)
                        <div class="sm:col-span-2"><span class="text-gray-400">Notes</span><br>{{ $job->notes }}</div>
                    @endif
                </div>

                @can('update', $job)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">New Note</h3>
                    <form method="POST" action="{{ route('jobs.notes.store', $job) }}" enctype="multipart/form-data"
                          x-data="noteComposer()" x-init="mount($refs.editor)" @submit="sync()">
                        @csrf
                        <div x-ref="editor"></div>
                        <input type="hidden" name="note" :value="note">
                        <div class="mt-2">
                            <input type="file" name="attachments[]" multiple class="text-xs">
                        </div>
                        <div class="mt-2 text-right">
                            <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Add Note</button>
                        </div>
                    </form>
                </div>
                @endcan

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Activity Log</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($job->activityLog as $log)
                            @php
                                $icon = ['created' => '📝', 'status_change' => '🔄', 'rollback' => '⏪', 'cancelled' => '✕', 'edited' => '✏️', 'completed' => '✅', 'note' => '💬', 'document_generated' => '🧾'][$log->action] ?? '•';
                                $label = ['created' => 'created this job', 'note' => 'added a note', 'rollback' => 'rolled back the status', 'cancelled' => 'cancelled the job', 'completed' => 'completed the job', 'edited' => 'made a change'][$log->action] ?? str_replace('_', ' ', $log->action);
                            @endphp
                            <div class="border-b border-gray-100 pb-2">
                                <div class="text-gray-800">
                                    <span class="mr-1">{{ $icon }}</span>
                                    <strong>{{ $log->user_name ?? 'System' }}</strong>
                                    {{ $log->detail ?? $label }}
                                </div>
                                @if ($log->action === 'note' && $log->note)
                                    <div class="mt-1 text-gray-600 bg-gray-50 rounded-md px-3 py-2 prose-sm max-w-none">{!! $log->note !!}</div>
                                @elseif ($log->note)
                                    <div class="mt-1 text-gray-500 italic">{{ $log->note }}</div>
                                @endif
                                @if (! empty($log->attachments))
                                    <div class="mt-1.5 flex flex-wrap gap-2">
                                        @foreach ($log->attachments as $att)
                                            <a href="{{ route('jobs.notes.attachments.show', [$job, $log, $att['id']]) }}" class="text-xs text-indigo-600 hover:underline">📎 {{ $att['name'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-xs text-gray-400 mt-1">{{ $log->created_at->format('d M Y, g:ia') }}</div>
                            </div>
                        @empty
                            <p class="text-gray-400">No activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Documents</h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('jobs.invoice', $job) }}" class="text-xs font-semibold px-3 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Generate Invoice</a>
                        <a href="{{ route('jobs.receipt', $job) }}" class="text-xs font-semibold px-3 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Generate Receipt</a>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">Quotation &amp; Proforma Invoice, and a saved document history, are coming in a follow-up update.</p>
                </div>

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
            </div>
        </div>
    </div>
</x-app-layout>
