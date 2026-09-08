<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">New Job</h2>
    </x-slot>

    <div class="py-8"
         x-data="jobCreateForm(
             {{ $customers->map(fn ($c) => ['id' => $c->id, 'customer_id' => $c->customer_id, 'label' => $c->customer_type === 'company' ? ($c->company ?: $c->name) : $c->name])->values()->toJson() }},
             {{ json_encode(array_keys($departments)) }},
             {{ json_encode(config('kretivco.package_catalog')) }}
         )">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('jobs.store') }}">
                    @csrf

                    {{-- Customer picker + inline create --}}
                    <div class="mb-5">
                        <x-input-label value="Customer *" />
                        <div class="relative">
                            <input type="text" x-model="customerQuery" @focus="customerOpen = true" @click.outside="customerOpen = false"
                                   x-bind:placeholder="selectedCustomer ? selectedCustomer.customer_id + ' · ' + selectedCustomer.label : 'Search or click to browse customers...'"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <input type="hidden" name="customer_id" x-model="customerId" required>
                            <div x-show="customerOpen" x-cloak class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg">
                                <template x-for="c in filteredCustomers" :key="c.id">
                                    <div @click="selectCustomer(c)" class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 border-b border-gray-50">
                                        <span class="font-semibold" x-text="c.customer_id + ' · ' + c.label"></span>
                                    </div>
                                </template>
                                <div x-show="filteredCustomers.length === 0" class="px-3 py-2 text-sm text-gray-400">No customers found.</div>
                            </div>
                        </div>
                        <button type="button" @click="showInlineCustomer = !showInlineCustomer" class="mt-1.5 text-xs font-semibold text-indigo-600 hover:underline" x-show="!showInlineCustomer">+ New Customer</button>

                        <div x-show="showInlineCustomer" x-cloak class="mt-2 p-3.5 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-gray-600">New Customer</span>
                                <button type="button" @click="showInlineCustomer = false" class="text-gray-400 text-sm">×</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <input type="text" x-model="inlineCustomer.name" placeholder="Name *" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.company" placeholder="Company" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.phone" placeholder="Phone" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="email" x-model="inlineCustomer.email" placeholder="Email" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                            </div>
                            <select x-model="inlineCustomer.source" class="w-full rounded-md border-gray-300 shadow-sm text-xs h-9 mb-2">
                                @foreach (config('kretivco.sources') as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div x-show="inlineError" x-cloak class="text-xs text-red-600 mb-2" x-text="inlineError"></div>
                            <button type="button" @click="saveInlineCustomer()" :disabled="inlineSaving || !inlineCustomer.name.trim()"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-md bg-indigo-600 text-white disabled:opacity-40">
                                <span x-text="inlineSaving ? 'Saving...' : 'Save Customer'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Department multi-select --}}
                    <div class="mb-5">
                        <x-input-label value="Department * — you can select more than one" />
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                            @foreach ($departments as $key => $dept)
                                <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs font-semibold cursor-pointer"
                                       :class="depts.includes('{{ $key }}') ? 'border-2' : 'border-gray-200'"
                                       :style="depts.includes('{{ $key }}') ? 'border-color: {{ $dept['color'] }}; color: {{ $dept['color'] }}; background: {{ $dept['color'] }}10' : ''">
                                    <input type="checkbox" name="departments[]" value="{{ $key }}" x-model="depts" class="hidden">
                                    {{ $dept['label'] }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="depts.length > 1" x-cloak class="mt-2 px-3 py-2 rounded-md bg-pink-50 border border-dashed border-pink-300 text-xs text-gray-700">
                            <span x-text="depts.length"></span> departments selected — one Project ID will be generated to group these jobs together. Each department still gets its own Job ID &amp; status.
                        </div>
                    </div>

                    {{-- Per-department fields --}}
                    @foreach ($departments as $key => $dept)
                        <div x-show="depts.includes('{{ $key }}')" x-cloak class="mb-4 p-3.5 rounded-lg border" style="border-color: {{ $dept['color'] }}30; background: {{ $dept['color'] }}08">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-[10px] font-bold px-2 py-1 rounded" style="color: {{ $dept['color'] }}; background: {{ $dept['color'] }}18">{{ \App\Http\Controllers\JobController::DEPT_CODES[$key] ?? strtoupper($key) }}</span>
                                <span class="text-sm font-bold">{{ $dept['label'] }}</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <x-input-label value="Job Type *" />
                                    <select name="per_dept[{{ $key }}][job_type_category]" x-model="perDept.{{ $key }}.jobTypeCategory" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                        @foreach (config('kretivco.job_types') as $tKey => $t)
                                            <option value="{{ $tKey }}">{{ $t['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label value="Bank" />
                                    <select name="per_dept[{{ $key }}][bank]" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                        <option value="">—</option>
                                        @foreach (config('kretivco.banks') as $bKey => $b)
                                            <option value="{{ $bKey }}">{{ $b['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if (! empty(config('kretivco.package_catalog.'.$key)))
                                <div x-show="perDept.{{ $key }}.jobTypeCategory === 'product_sale'" x-cloak class="mb-3 p-3 rounded-md bg-white border border-dashed border-gray-300">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label value="Product" />
                                            <select name="per_dept[{{ $key }}][product_line]" x-model="perDept.{{ $key }}.productLine" @change="perDept.{{ $key }}.segment = ''; perDept.{{ $key }}.pkg = ''"
                                                    :disabled="!depts.includes('{{ $key }}') || perDept.{{ $key }}.jobTypeCategory !== 'product_sale'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                <option value="">— Custom job (not a package) —</option>
                                                <template x-for="line in productLinesFor('{{ $key }}')" :key="line.key">
                                                    <option :value="line.key" x-text="line.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div x-show="perDept.{{ $key }}.productLine">
                                            <x-input-label value="Customer Type" />
                                            <select name="per_dept[{{ $key }}][segment]" x-model="perDept.{{ $key }}.segment" @change="perDept.{{ $key }}.pkg = ''"
                                                    :disabled="!depts.includes('{{ $key }}') || perDept.{{ $key }}.jobTypeCategory !== 'product_sale'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                <option value="">— Select —</option>
                                                <template x-for="seg in segmentsFor('{{ $key }}', perDept.{{ $key }}.productLine)" :key="seg.key">
                                                    <option :value="seg.key" x-text="seg.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                    <div x-show="perDept.{{ $key }}.segment" class="mt-3">
                                        <x-input-label value="Package" />
                                        <select name="per_dept[{{ $key }}][package_value]" x-model="perDept.{{ $key }}.pkg" @change="onPackageChange('{{ $key }}')"
                                                :disabled="!depts.includes('{{ $key }}') || perDept.{{ $key }}.jobTypeCategory !== 'product_sale'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                            <option value="">— Select package —</option>
                                            <template x-for="opt in packageTierOptions('{{ $key }}', perDept.{{ $key }}.productLine, perDept.{{ $key }}.segment)" :key="opt.value">
                                                <option :value="opt.value" x-text="opt.label"></option>
                                            </template>
                                        </select>
                                        <template x-if="findPackageTier('{{ $key }}', perDept.{{ $key }}.productLine, perDept.{{ $key }}.segment, perDept.{{ $key }}.pkg)">
                                            <div class="mt-2 p-2.5 rounded-md bg-gray-50 text-xs text-gray-600 leading-relaxed">
                                                <template x-for="line in packageItemLines('{{ $key }}', perDept.{{ $key }}.productLine, perDept.{{ $key }}.segment, perDept.{{ $key }}.pkg)" :key="line">
                                                    <div x-text="'• ' + line"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <x-input-label value="Job Name *" />
                                <input type="text" name="per_dept[{{ $key }}][job_type]" x-model="perDept.{{ $key }}.jobType" :disabled="!depts.includes('{{ $key }}')" placeholder="e.g: Design &amp; Print Roti Bakar" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            </div>

                            <div class="mb-3">
                                <x-input-label value="PIC — optional, leave blank for department staff to self-assign" />
                                <input type="text" name="per_dept[{{ $key }}][pic]" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                                <div>
                                    <x-input-label value="Start Date" />
                                    <input type="date" name="per_dept[{{ $key }}][start_date]" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                                <div>
                                    <x-input-label value="Deadline" />
                                    <input type="date" name="per_dept[{{ $key }}][deadline]" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                                <div>
                                    <x-input-label value="Estimation Value (RM)" />
                                    <input type="number" step="0.01" min="0" name="per_dept[{{ $key }}][estimation_value]" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Notes" />
                                <textarea name="per_dept[{{ $key }}][notes]" :disabled="!depts.includes('{{ $key }}')" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs"></textarea>
                            </div>
                        </div>
                    @endforeach

                    <x-input-error :messages="$errors->all()" class="mt-1" />
                    <div class="mt-2">
                        <x-primary-button type="submit">Save Job</x-primary-button>
                        <a href="{{ route('jobs.index') }}" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function jobCreateForm(customers, departmentKeys, packageCatalog) {
            return {
                customers,
                packageCatalog,
                depts: {{ old('departments') ? json_encode(old('departments')) : '[]' }},
                perDept: Object.fromEntries(departmentKeys.map(k => [k, {
                    jobTypeCategory: 'client_project', productLine: '', segment: '', pkg: '', jobType: '',
                }])),
                customerId: '{{ old('customer_id') }}',
                customerQuery: '',
                customerOpen: false,
                showInlineCustomer: false,
                inlineCustomer: { name: '', company: '', phone: '', email: '', source: 'referral' },
                inlineSaving: false,
                inlineError: null,
                productLinesFor(dept) {
                    return this.packageCatalog[dept] || [];
                },
                segmentsFor(dept, lineKey) {
                    return this.productLinesFor(dept).find(l => l.key === lineKey)?.segments || [];
                },
                packageTierOptions(dept, lineKey, segmentKey) {
                    const seg = this.segmentsFor(dept, lineKey).find(s => s.key === segmentKey);
                    if (!seg) return [];
                    return seg.packages.flatMap(pkg => pkg.tiers.map(tier => ({
                        value: `${pkg.key}:${tier.pcs}`,
                        label: `${pkg.label} — ${tier.pcs}pcs (RM ${Number(tier.price).toFixed(2)})`,
                        pkg, tier,
                    })));
                },
                findPackageTier(dept, lineKey, segmentKey, value) {
                    if (!value) return null;
                    return this.packageTierOptions(dept, lineKey, segmentKey).find(o => o.value === value) || null;
                },
                packageItemLines(dept, lineKey, segmentKey, value) {
                    const found = this.findPackageTier(dept, lineKey, segmentKey, value);
                    if (!found) return [];
                    return found.pkg.items.map(i => i.replace('{pcs}', found.tier.pcs));
                },
                onPackageChange(dept) {
                    const pd = this.perDept[dept];
                    const tier = this.findPackageTier(dept, pd.productLine, pd.segment, pd.pkg);
                    if (tier) pd.jobType = `${tier.pkg.label} (${tier.tier.pcs}pcs)`;
                },
                get selectedCustomer() {
                    return this.customers.find(c => String(c.id) === String(this.customerId)) || null;
                },
                get filteredCustomers() {
                    const q = this.customerQuery.trim().toLowerCase();
                    if (!q) return this.customers;
                    return this.customers.filter(c =>
                        (c.customer_id || '').toLowerCase().includes(q) || (c.label || '').toLowerCase().includes(q)
                    );
                },
                selectCustomer(c) {
                    this.customerId = c.id;
                    this.customerQuery = '';
                    this.customerOpen = false;
                },
                async saveInlineCustomer() {
                    if (!this.inlineCustomer.name.trim()) return;
                    this.inlineSaving = true;
                    this.inlineError = null;
                    try {
                        const res = await fetch('{{ route('customers.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(this.inlineCustomer),
                        });
                        if (!res.ok) throw new Error('Failed to save customer.');
                        const customer = await res.json();
                        this.customers.push({
                            id: customer.id,
                            customer_id: customer.customer_id,
                            label: customer.customer_type === 'company' ? (customer.company || customer.name) : customer.name,
                        });
                        this.selectCustomer({ id: customer.id });
                        this.showInlineCustomer = false;
                        this.inlineCustomer = { name: '', company: '', phone: '', email: '', source: 'referral' };
                    } catch (e) {
                        this.inlineError = e.message;
                    }
                    this.inlineSaving = false;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
