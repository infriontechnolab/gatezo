<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Join code</div>
            <div class="mt-1 font-mono text-2xl font-bold">{{ $event->volunteer_code }}</div>
            <div class="text-xs text-gray-500">Rotate it from the dashboard if it leaks. Everyone rejoins with the new one.</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Mode</div>
            <div class="mt-1 text-sm">
                {{ $event->roster_only ? 'Roster only: names must be on the Shifts list.' : 'Anyone with the code can join.' }}<br>
                {{ $event->require_volunteer_approval ? 'Approval required before scanning.' : 'No approval step.' }}
            </div>
            <div class="mt-1 text-xs text-gray-500">Change in Event settings.</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Last hour</div>
            <div class="mt-1 text-2xl font-bold {{ $wrongLastHour >= 10 ? 'text-danger-600' : '' }}">{{ $wrongLastHour }}</div>
            <div class="text-xs text-gray-500">failed join attempts{{ $waiting ? " · {$waiting} waiting for approval" : '' }}</div>
        </x-filament::section>
    </div>

    {{ $this->table }}

    <x-filament::section heading="Recent join attempts" description="Every attempt, successful or not. 10 wrong codes from one IP blocks it for 15 minutes.">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase tracking-wider text-gray-500"><th class="py-1">When</th><th>Name</th><th>Code</th><th>Result</th><th>Device</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($recentJoins as $j)
                <tr class="border-t border-gray-100 dark:border-white/5">
                    <td class="py-1.5 whitespace-nowrap">{{ $j->created_at->format('D g:i A') }}</td>
                    <td>{{ $j->name }}</td>
                    <td class="font-mono text-xs">{{ $j->result === 'ok' ? '✓' : $j->code }}</td>
                    <td><span class="rounded-md px-1.5 py-0.5 text-xs {{ $j->result === 'ok' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ str_replace('_', ' ', $j->result) }}</span></td>
                    <td class="font-mono text-xs">{{ $j->device }}</td>
                    <td class="font-mono text-xs">{{ $j->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-3 text-gray-400">No attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
