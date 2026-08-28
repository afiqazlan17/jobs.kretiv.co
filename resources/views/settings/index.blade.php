<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings — User Management</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Add User</h3>
                <form method="POST" action="{{ route('settings.users.store') }}"
                      x-data="{ role: '{{ old('role', 'staff') }}', visibleDepartments: {{ json_encode(old('visible_departments', [])) }} }"
                      class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Full Name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email *" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Role *" />
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (config('kretivco.roles') as $key => $role)
                                <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm cursor-pointer"
                                       :class="role === '{{ $key }}' ? 'border-2' : 'border-gray-200'"
                                       :style="role === '{{ $key }}' ? 'border-color: {{ $role['color'] }}' : ''">
                                    <input type="radio" name="role" value="{{ $key }}" x-model="role" class="text-xs">
                                    {{ $role['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                        <x-input-label value="Department *" />
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (config('kretivco.departments') as $key => $dept)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                    <input type="radio" name="department" value="{{ $key }}" required>
                                    {{ $dept['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                        <x-input-label value="Visible Departments" />
                        <p class="text-xs text-gray-400 mb-1">Leave empty for their own department only.</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (config('kretivco.departments') as $key => $dept)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                    <input type="checkbox" name="visible_departments[]" value="{{ $key }}">
                                    {{ $dept['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <x-input-label for="title" value="Title (optional)" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Add User</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ editingId: null }">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3 whitespace-nowrap">Staff ID</th>
                                <th class="px-4 py-3 whitespace-nowrap">Name</th>
                                <th class="px-4 py-3 whitespace-nowrap">Email</th>
                                <th class="px-4 py-3 whitespace-nowrap">Role</th>
                                <th class="px-4 py-3 whitespace-nowrap">Department</th>
                                <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $user)
                                @php $role = config('kretivco.roles')[$user->role] ?? null; @endphp
                                <tr :class="editingId === {{ $user->id }} ? 'bg-gray-50' : ''">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $user->staff_id ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $user->email }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                              style="background: {{ $role['color'] ?? '#eee' }}22; color: {{ $role['color'] ?? '#666' }}">
                                            {{ $role['label'] ?? $user->role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if (empty($user->visible_departments) || count($user->visible_departments) === count(config('kretivco.departments')))
                                            <span class="text-gray-500 text-xs">{{ $user->department ? config('kretivco.departments')[$user->department]['label'] ?? $user->department : 'All' }}</span>
                                        @else
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($user->visible_departments as $d)
                                                    <span class="text-xs rounded px-1.5 py-0.5 bg-gray-100 text-gray-600">{{ config('kretivco.departments')[$d]['label'] ?? $d }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $user->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $user->active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" @click="editingId = editingId === {{ $user->id }} ? null : {{ $user->id }}" class="text-indigo-600 hover:underline text-xs">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="editingId === {{ $user->id }}" x-cloak>
                                    <td colspan="7" class="px-4 py-4 bg-gray-50">
                                        <form method="POST" action="{{ route('settings.users.update', $user) }}"
                                              x-data="{ role: '{{ $user->role }}' }"
                                              class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <x-input-label value="Full Name *" />
                                                <x-text-input name="name" type="text" class="mt-1 block w-full" :value="$user->name" required />
                                            </div>
                                            <div>
                                                <x-input-label value="Email *" />
                                                <x-text-input name="email" type="email" class="mt-1 block w-full" :value="$user->email" required />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-input-label value="Role *" />
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach (config('kretivco.roles') as $key => $r)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                                            <input type="radio" name="role" value="{{ $key }}" x-model="role" {{ $user->role === $key ? 'checked' : '' }}>
                                                            {{ $r['label'] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                                                <x-input-label value="Department *" />
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach (config('kretivco.departments') as $key => $dept)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                                            <input type="radio" name="department" value="{{ $key }}" {{ $user->department === $key ? 'checked' : '' }}>
                                                            {{ $dept['label'] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                                                <x-input-label value="Visible Departments" />
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach (config('kretivco.departments') as $key => $dept)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                                            <input type="checkbox" name="visible_departments[]" value="{{ $key }}"
                                                                   {{ in_array($key, $user->visible_departments ?? []) ? 'checked' : '' }}>
                                                            {{ $dept['label'] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div>
                                                <x-input-label value="Title" />
                                                <x-text-input name="title" type="text" class="mt-1 block w-full" :value="$user->title" />
                                            </div>
                                            <div class="sm:col-span-2 flex items-center gap-3">
                                                <x-primary-button type="submit">Save</x-primary-button>
                                                <button type="button" @click="editingId = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('settings.users.toggle-active', $user) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="text-xs {{ $user->active ? 'text-red-600' : 'text-green-600' }} hover:underline">
                                                {{ $user->active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
