{{-- Results list for the itemCombo Alpine component (resources/js/app.js). Uses its scope: open, results, loading, pick(row, r). --}}
<div x-show="open" x-cloak class="absolute z-30 left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg text-left">
    <template x-for="r in results" :key="r.id">
        <div @click="pick(row, r)" class="px-3 py-2 cursor-pointer hover:bg-gray-50 border-b border-gray-50">
            <div class="flex justify-between gap-3">
                <span class="text-xs font-semibold text-gray-800" x-text="r.name"></span>
                <span class="text-xs text-gray-500 whitespace-nowrap" x-text="r.price !== null ? 'RM ' + Number(r.price).toFixed(2) : ''"></span>
            </div>
            <div class="text-[11px] text-gray-400 truncate" x-show="r.description" x-text="r.description"></div>
        </div>
    </template>
    <div x-show="!loading && results.length === 0" class="px-3 py-2 text-xs text-gray-400">No match in the library — keep typing to use it as a custom item.</div>
    <a href="{{ route('items.index') }}" target="_blank" class="block px-3 py-2 text-[11px] font-semibold text-indigo-600 hover:underline border-t border-gray-100">Manage library →</a>
</div>
