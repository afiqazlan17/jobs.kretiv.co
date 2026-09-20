<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">Items</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
            @endif

            <p class="text-sm text-gray-500 px-1">Items added here show up in the search dropdown when adding a line item on a new job or on a document (quotation, invoice…). Picking one fills in the name, description and price — you can still change them for that job.</p>

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="text-sm font-semibold text-indigo-600 hover:underline">
                    <span x-show="!open">+ New Item</span>
                    <span x-show="open" x-cloak>− Close Form</span>
                </button>
                <form method="POST" action="{{ route('items.store') }}" x-show="open" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label value="Department *" />
                        <select name="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('kretivco.departments') as $key => $dept)
                                <option value="{{ $key }}" @selected(old('department') === $key)>{{ $dept['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Default Price (RM)" />
                        <x-text-input name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Item Name *" />
                        <x-text-input name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Description" />
                        <textarea name="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('description') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mb-2" />
                        <x-primary-button type="submit">Save Item</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <form method="GET" action="{{ route('items.index') }}" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search item name or description..." class="flex-1 rounded-md border-gray-300 shadow-sm text-sm">
                    <select name="department" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All departments</option>
                        @foreach (config('kretivco.departments') as $key => $dept)
                            <option value="{{ $key }}" @selected($department === $key)>{{ $dept['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ editingId: null }">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3 whitespace-nowrap">Department</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 whitespace-nowrap text-right">Price</th>
                                <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr class="{{ $item->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ config("kretivco.departments.{$item->department}.label", $item->department) }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $item->item_name }}</td>
                                    <td class="px-4 py-3 text-gray-500 max-w-xs truncate">{{ $item->description ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $item->price !== null ? 'RM '.number_format($item->price, 2) : '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $item->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $item->active ? 'Active' : 'Hidden' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @can('update', $item)
                                            <button type="button" @click="editingId = editingId === {{ $item->id }} ? null : {{ $item->id }}" class="text-indigo-600 hover:underline text-xs">Edit</button>
                                        @endcan
                                    </td>
                                </tr>
                                @can('update', $item)
                                <tr x-show="editingId === {{ $item->id }}" x-cloak>
                                    <td colspan="6" class="px-4 py-4 bg-gray-50">
                                        <form method="POST" action="{{ route('items.update', $item) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-start">
                                            @csrf
                                            @method('PUT')
                                            <select name="department" class="rounded-md border-gray-300 shadow-sm text-sm">
                                                @foreach (config('kretivco.departments') as $key => $dept)
                                                    <option value="{{ $key }}" @selected($item->department === $key)>{{ $dept['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <x-text-input name="item_name" type="text" class="block w-full sm:col-span-2" :value="$item->item_name" required placeholder="Item name" />
                                            <x-text-input name="price" type="number" step="0.01" min="0" class="block w-full" :value="$item->price" placeholder="Price" />
                                            <textarea name="description" rows="2" class="sm:col-span-4 rounded-md border-gray-300 shadow-sm text-sm" placeholder="Description">{{ $item->description }}</textarea>
                                            <label class="sm:col-span-4 flex items-center gap-2 text-sm text-gray-600">
                                                <input type="checkbox" name="active" value="1" @checked($item->active) class="rounded border-gray-300"> Show in dropdown (untick to hide)
                                            </label>
                                            <div class="sm:col-span-4">
                                                <x-primary-button type="submit">Save</x-primary-button>
                                                <button type="button" @click="editingId = null" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</button>
                                            </div>
                                        </form>
                                        @can('delete', $item)
                                            <form method="POST" action="{{ route('items.destroy', $item) }}" class="mt-2" onsubmit="return confirm('Delete this item permanently? Hiding it is usually enough.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-500 hover:underline">Delete item</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                                @endcan
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No items yet — add the first one above.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
