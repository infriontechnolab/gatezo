<x-filament-panels::page>
    @php
        $fmt = fn ($v) => $v === null ? 'Unlimited' : number_format($v);
    @endphp

    @if ($pending)
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" icon-color="warning" heading="{{ $pending->planModel()?->name ?? 'Plan' }} requested {{ $pending->created_at->diffForHumans() }}"
            description="We'll call you on {{ $pending->contactPhone() ?: 'your number' }} within a working day. Once payment is in, we switch you over the same day.">
            {{ $this->cancelRequestAction }}
        </x-filament::section>
    @elseif ($paidUntil)
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckBadge" icon-color="success" heading="You're on {{ $current->name }} until {{ $paidUntil->format('j M Y') }}"
            description="After that the Free caps come back; your events and data stay as they are. Renew any time below." />
    @elseif (! $current->isFree())
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckBadge" icon-color="success" heading="You're on {{ $current->name }}" description="No end date on this account. Thanks for running your events with us." />
    @elseif ($lastEnded)
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowPath" icon-color="gray" heading="Your paid plan ended on {{ \Illuminate\Support\Carbon::parse($lastEnded)->format('j M Y') }}"
            description="Everything you made is still here. Pick a plan to lift the caps again." />
    @endif

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($plans as $plan)
            @php $mine = $plan->slug === $current->slug; @endphp
            <x-filament::section :heading="$plan->name" :description="$plan->description"
                @class(['ring-2 ring-primary-500' => $plan->is_featured && ! $mine])>
                <x-slot name="afterHeader">
                    @if ($mine)
                        <x-filament::badge color="success">Your plan</x-filament::badge>
                    @endif
                </x-slot>

                <div class="mb-4">
                    @if ($plan->isFree())
                        <span class="text-2xl font-semibold">₹0</span>
                    @else
                        @foreach ($plan->billingOptions() as $b)
                            <div class="{{ $loop->first ? 'text-2xl font-semibold' : 'mt-1 text-sm text-gray-500 dark:text-gray-400' }}">{{ $loop->first ? '' : 'or ' }}{{ $plan->priceLabel($b) }}</div>
                        @endforeach
                    @endif
                </div>

                <dl class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                    @foreach ([['Events', $plan->max_events, $used['events']], ['Registrations per event', $plan->max_attendees, $used['attendees']], ['Organizers per event', $plan->max_team, $used['team']]] as [$label, $cap, $now])
                        <div class="flex items-center justify-between gap-4 py-2">
                            <dt class="text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                            <dd class="text-right font-medium">{{ $fmt($cap) }}
                                @if ($mine)<span class="block text-xs font-normal text-gray-400">using {{ number_format($now) }}</span>@endif
                            </dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-4 py-2"><dt class="text-gray-600 dark:text-gray-300">Scanner, stalls, feedback, lucky draw, reports</dt><dd class="font-medium">Included</dd></div>
                </dl>

                @if ($plan->isForSale() && ! $pending)
                    <div class="mt-5">{{ ($this->requestAction)(['plan' => $plan->slug]) }}</div>
                @endif
            </x-filament::section>
        @endforeach
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">No card needed. Choose a plan and we call you back; you pay by UPI or bank transfer and the plan runs for the period you paid for. Nothing on your side changes except the caps.</p>
</x-filament-panels::page>
