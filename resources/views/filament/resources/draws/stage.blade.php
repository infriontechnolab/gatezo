<x-filament-panels::page>
    @if (! $draw->isRun())
        <x-filament::section heading="Before you run it">
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Prizes: {{ $draw->prizes->map(fn ($p) => $p->quantity.' × '.$p->name)->join(', ') ?: 'none yet' }}</li>
                <li>Pool: {{ \App\Models\Draw::POOLS[$draw->pool_source] }} · backups per prize: {{ $draw->alternates_per_prize }} · {{ $draw->claim_minutes }} min to claim</li>
                <li>Seed committed: <span class="font-mono text-xs">{{ $draw->seed_hash }}</span></li>
                <li>Open the presenter screen on the projector first, then press <b>Run draw</b>.</li>
            </ul>
        </x-filament::section>
    @else
        @if ($current)
            <x-filament::section :heading="'On stage: '.$current->prize->name.($current->prize->quantity > 1 ? ' #'.$current->slot : '')" wire:poll.5s>
                <div class="text-3xl font-bold">{{ $current->pass->attendee->name }}</div>
                <div class="text-gray-500">{{ $current->pass->attendee->phone }} · pass {{ $current->pass->code }}{{ $current->rank > 1 ? ' · backup #'.($current->rank - 1) : '' }}</div>
                <div class="mt-2 text-sm {{ $current->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                    {{ $current->isOverdue() ? 'Time is up, you can forfeit' : 'Must claim by '.$current->claim_deadline->format('g:i:s A') }}
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="Slots" description="One row per prize slot: winner, then backups in order.">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wider text-gray-500"><th class="py-2">Prize</th><th>Winner</th><th>Backups</th></tr></thead>
                <tbody>
                @foreach ($slotRows as $group)
                    @php $first = $group->first(); $winner = $group->firstWhere('rank', 1); $backups = $group->where('rank', '>', 1); @endphp
                    <tr class="border-t border-gray-100 dark:border-white/5 align-top">
                        <td class="py-2 pr-4 font-medium">{{ $first->prize->name }}{{ $first->prize->quantity > 1 ? ' #'.$first->slot : '' }}</td>
                        <td class="py-2 pr-4">@if ($winner)<x-draw-chip :w="$winner" />@else<span class="text-gray-400">—</span>@endif</td>
                        <td class="py-2 space-x-1">@forelse ($backups as $b)<x-draw-chip :w="$b" />@empty<span class="text-gray-400">—</span>@endforelse</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Proof">
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Seed hash (committed at create)</dt><dd class="break-all font-mono text-xs">{{ $draw->seed_hash }}</dd></div>
                <div><dt class="text-gray-500">Seed (revealed at finish)</dt><dd class="break-all font-mono text-xs">{{ $draw->status === 'finished' ? $draw->getAttribute('seed') : 'hidden until all prizes are done' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-gray-500">Public results</dt><dd><a href="{{ $publicUrl }}" target="_blank" class="underline">{{ $publicUrl }}</a></dd></div>
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
