<x-os-layout>
    <div class="space-y-4">
        @if (session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div>
            <h1 class="text-lg font-semibold">Users &amp; Access</h1>
            <p class="text-sm text-gray-500">Choose which modules each person can open. BOD always has every module. New accounts start with their role's defaults until you save here. Create accounts in Jobs → Settings for now.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Role</th>
                            @foreach (config('kretivco.modules') as $module)
                                <th class="px-4 py-3 text-center">{{ $module['label'] }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-center">Active</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $u)
                            @php $self = $u->is(auth()->user()); @endphp
                            <tr class="{{ $u->active ? '' : 'opacity-50' }}">
                                <form method="POST" action="{{ route('os.access.update', $u) }}" id="f{{ $u->id }}">@csrf @method('PUT')</form>
                                <td class="px-4 py-3"><div class="font-medium">{{ $u->name }}</div><div class="text-xs text-gray-400">{{ $u->email }}</div></td>
                                <td class="px-4 py-3">
                                    <select name="role" form="f{{ $u->id }}" @disabled($self) class="rounded-md border-gray-300 text-xs">
                                        @foreach (config('kretivco.roles') as $key => $role)
                                            <option value="{{ $key }}" @selected($u->role === $key)>{{ $role['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                @foreach (config('kretivco.modules') as $key => $module)
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="modules[]" value="{{ $key }}" form="f{{ $u->id }}" class="rounded border-gray-300"
                                               @checked($u->canAccess($key)) @disabled($u->isBod())>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="active" value="1" form="f{{ $u->id }}" class="rounded border-gray-300" @checked($u->active) @disabled($self)>
                                </td>
                                <td class="px-4 py-3 text-right"><button type="submit" form="f{{ $u->id }}" class="text-xs font-semibold px-3 py-1.5 rounded-md bg-gray-800 text-white hover:bg-gray-900">Save</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-os-layout>
