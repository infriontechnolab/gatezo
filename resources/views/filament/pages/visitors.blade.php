<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Visitors</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($total) }}</div>
            <div class="text-xs text-gray-500">One per phone number, across every event you created.</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Said yes to WhatsApp</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($optedIn) }}</div>
            <div class="text-xs text-gray-500">Only these are in the export. Turn the question on in Event settings.</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Came back</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($returning) }}</div>
            <div class="text-xs text-gray-500">Registered for more than one of your events.</div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
