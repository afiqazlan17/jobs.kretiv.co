@php
    $user = auth()->user();
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => '📊'],
        ['key' => 'jobs', 'label' => 'Job', 'route' => 'jobs.index', 'pattern' => 'jobs.*', 'icon' => '📋'],
        ['key' => 'leads', 'label' => 'Leads', 'route' => 'leads.index', 'pattern' => 'leads.*', 'icon' => '🧲'],
        ['key' => 'customers', 'label' => 'Customers', 'route' => 'customers.index', 'icon' => '👥'],
        ['key' => 'vendors', 'label' => 'Vendors', 'route' => 'vendors.index', 'icon' => '🏭'],
        ['key' => 'finance', 'label' => 'Finance', 'route' => 'finance.index', 'pattern' => 'finance.*', 'icon' => '💰', 'roles' => ['bod', 'dept_head']],
        ['key' => 'reports', 'label' => 'Reports', 'route' => 'reports.index', 'icon' => '📈', 'roles' => ['bod', 'dept_head']],
        ['key' => 'settings', 'label' => 'Settings', 'route' => 'settings.index', 'icon' => '⚙️', 'roles' => ['bod']],
    ];
@endphp

{{-- Logo --}}
<div class="border-b border-white/[.06] flex justify-between items-center px-5 py-5">
    <div>
        <div class="text-xl font-extrabold tracking-tight">Kretivco</div>
        <div class="mt-1 inline-block text-[11px] font-medium text-[#E91E63] bg-[#E91E6318] rounded-full px-2.5 py-0.5">
            Job Dashboard
        </div>
    </div>
    <button @click="mobileOpen = false" class="md:hidden text-white/50 text-2xl leading-none">×</button>
</div>

{{-- Navigation --}}
<nav class="flex-1 py-1 overflow-y-auto">
    @php
        $jobSubmenu = [
            ['key' => 'queue', 'label' => 'Job Queue', 'icon' => '📋'],
            ['key' => 'aging', 'label' => 'Aging Job', 'icon' => '⏳'],
            ['key' => 'mine', 'label' => 'My Jobs', 'icon' => '🙋'],
        ];
        $activeJobView = request()->routeIs('jobs.index') ? (request()->query('view', 'queue')) : null;
    @endphp
    @foreach ($navItems as $item)
        @continue(isset($item['roles']) && ! in_array($user->role, $item['roles'], true))
        @php $active = request()->routeIs($item['pattern'] ?? $item['route']); @endphp
        <a href="{{ $item['key'] === 'jobs' ? route('jobs.index') : route($item['route']) }}"
           class="relative flex items-center gap-3 h-11 px-5 text-[13px] whitespace-nowrap {{ $active ? 'bg-white/[.06] text-white font-semibold' : 'text-white/50 font-normal hover:text-white/80' }}">
            @if ($active)
                <span class="absolute left-0 top-[7px] bottom-[7px] w-[3px] bg-[#E91E63] rounded-r"></span>
            @endif
            <span class="text-[15px] w-6 text-center">{{ $item['icon'] }}</span>
            <span>{{ $item['label'] }}</span>
        </a>
        @if ($item['key'] === 'jobs' && $active)
            <div class="py-0.5 pb-1.5">
                @foreach ($jobSubmenu as $sub)
                    <a href="{{ route('jobs.index', ['view' => $sub['key']]) }}"
                       class="flex items-start gap-2 min-h-[34px] py-[7px] pl-11 pr-2.5 text-xs leading-tight {{ $activeJobView === $sub['key'] ? 'bg-white/[.08] text-white font-semibold' : 'text-white/45 font-normal' }}">
                        <span class="text-[13px] shrink-0 mt-px">{{ $sub['icon'] }}</span>
                        <span>{{ $sub['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</nav>

{{-- User profile --}}
@if ($user)
    <div class="px-5 py-3.5 border-t border-white/[.06]">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-[#E91E6330] text-[#E91E63] flex items-center justify-center text-[13px] font-bold shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold truncate">{{ $user->name }}</div>
                <div class="text-[10px] font-medium mt-0.5" style="color: {{ config('kretivco.roles.'.$user->role.'.color', '#3A86FF') }}">
                    {{ config('kretivco.roles.'.$user->role.'.label', $user->role) }}{{ $user->title ? ' | '.$user->title : '' }}
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-2.5 w-full py-1.5 text-[11px] font-medium text-white/40 bg-transparent border border-white/10 rounded-md">
                Log Out
            </button>
        </form>
    </div>
@endif
