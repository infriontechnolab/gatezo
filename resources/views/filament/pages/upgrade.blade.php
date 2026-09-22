<x-filament-panels::page>
    @php
        $free = $plans['free'];
        $pro = $plans['pro'];
        $row = fn (string $label, $freeVal, $usedVal = null) => [$label, $freeVal, $usedVal];
        $fmt = fn ($v) => $v === null ? 'Unlimited' : number_format($v);
    @endphp

    @if ($pending)
        <x-filament::section icon="heroicon-o-clock" icon-color="warning" heading="Request received {{ $pending->created_at->diffForHumans() }}"
            description="We'll switch you over as soon as we've talked. Haven't heard from us? Ping us on WhatsApp.">
            <x-filament::button tag="a" :href="$whatsapp" target="_blank" icon="heroicon-o-chat-bubble-left-right">Open WhatsApp</x-filament::button>
        </x-filament::section>
    @elseif (! $user->onFreePlan())
        <x-filament::section icon="heroicon-o-check-badge" icon-color="success" heading="You're on Pro" description="No caps on this account. Thanks for running your events with us." />
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section heading="Free" description="Everything, for one small event.">
            <dl class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                @foreach ([['Events', $free['events'], $used['events']], ['Registrations per event', $free['attendees'], $used['attendees']], ['Organizers per event', $free['team'], $used['team']]] as [$label, $cap, $now])
                    <div class="flex items-center justify-between py-2">
                        <dt class="text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                        <dd class="font-medium">{{ $fmt($cap) }} <span class="text-xs font-normal text-gray-400">· using {{ number_format($now) }}</span></dd>
                    </div>
                @endforeach
                <div class="flex items-center justify-between py-2"><dt class="text-gray-600 dark:text-gray-300">Scanner, stalls, feedback, lucky draw, reports</dt><dd class="font-medium">Included</dd></div>
                <div class="flex items-center justify-between py-2"><dt class="text-gray-600 dark:text-gray-300">Price</dt><dd class="font-medium">₹0</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Pro" description="No caps. For the event that outgrew the free plan, or the organizer who runs several.">
            <dl class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                @foreach (['Events', 'Registrations per event', 'Organizers per event'] as $label)
                    <div class="flex items-center justify-between py-2"><dt class="text-gray-600 dark:text-gray-300">{{ $label }}</dt><dd class="font-medium">Unlimited</dd></div>
                @endforeach
                <div class="flex items-center justify-between py-2"><dt class="text-gray-600 dark:text-gray-300">Scanner, stalls, feedback, lucky draw, reports</dt><dd class="font-medium">Included</dd></div>
                <div class="flex items-center justify-between py-2"><dt class="text-gray-600 dark:text-gray-300">Priority help on event day</dt><dd class="font-medium">Included</dd></div>
                <div class="flex items-center justify-between py-2"><dt class="text-gray-600 dark:text-gray-300">Price</dt><dd class="font-medium">{{ $price ?: 'Per event · we quote in the chat' }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-gray-500">Paid by UPI or bank transfer after a quick WhatsApp chat. We switch the plan the same day; nothing on your side changes except the caps disappearing.</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
