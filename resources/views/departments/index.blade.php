<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-white leading-tight">Departments</h2>
            <p class="text-xs text-white/60 mt-0.5">Overview of services, products, and PIC for each department.</p>
        </div>
    </x-slot>

    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($departments as $key => $dept)
            <div class="bg-white shadow-sm sm:rounded-lg p-6 border-t-4" style="border-color: {{ $dept['color'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="text-[11px] font-bold px-2 py-1 rounded" style="color: {{ $dept['color'] }}; background: {{ $dept['color'] }}18">{{ \App\Http\Controllers\JobController::DEPT_CODES[$key] ?? strtoupper($key) }}</span>
                        <h3 class="text-base font-bold text-gray-900">{{ $dept['label'] }}</h3>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <div><span class="font-semibold text-gray-800">{{ $dept['active'] }}</span> active</div>
                        <div><span class="font-semibold text-gray-800">{{ $dept['completed'] }}</span> completed</div>
                    </div>
                </div>
                @isset($dept['lead'])
                    <div class="mt-3 text-sm text-gray-600">Lead: <span class="font-semibold text-gray-800">{{ $dept['lead'] }}</span></div>
                @endisset

                @if (! empty($dept['services']))
                    <div class="mt-4 text-[11px] font-semibold text-gray-400 uppercase">Services</div>
                    <ul class="mt-1 space-y-1 text-sm text-gray-700 list-disc list-inside">
                        @foreach ($dept['services'] as $service)<li>{{ $service }}</li>@endforeach
                    </ul>
                @endif
                @if (! empty($dept['products']))
                    <div class="mt-4 text-[11px] font-semibold text-gray-400 uppercase">Kretivco Products</div>
                    <ul class="mt-1 space-y-1 text-sm text-gray-700 list-disc list-inside">
                        @foreach ($dept['products'] as $product)<li>{{ $product }}</li>@endforeach
                    </ul>
                @endif
                @if (! empty($dept['note']))
                    <p class="mt-4 text-xs italic text-gray-500">{{ $dept['note'] }}</p>
                @endif
                @if ($dept['pipeline'] > 0)
                    <div class="mt-4 pt-3 border-t border-gray-100 text-sm"><span class="text-gray-500">Pipeline Value</span> <span class="font-bold ml-1">RM {{ number_format($dept['pipeline'], 0) }}</span></div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
