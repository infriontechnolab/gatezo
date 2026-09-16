<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section heading="Print kit" description="Every QR you stick on something. A4, black on white, any printer.">
            <ul class="mb-4 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>1 registration poster (scan → get pass)</li>
                <li>{{ $gateCount }} gate / zone signs (volunteers scan → on duty)</li>
                <li>{{ $stallCount }} stall cards, 4 per page (scan → menu &amp; offers)</li>
                <li>2 exit feedback cards</li>
            </ul>
            <x-filament::button tag="a" :href="$kitUrl" target="_blank" icon="heroicon-o-printer">Open print kit</x-filament::button>
        </x-filament::section>

        <x-filament::section heading="Post-event report" description="Attendance, peak time, per-gate split, feedback and stall numbers. Print or save as PDF and forward it.">
            <div class="flex flex-wrap gap-3">
                <x-filament::button tag="a" :href="$reportUrl" target="_blank" icon="heroicon-o-document-chart-bar">Open report</x-filament::button>
                <x-filament::button tag="a" :href="$csvUrl" color="gray" icon="heroicon-o-arrow-down-tray">Attendees CSV</x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Links to share" description="Same links that are inside the QR codes.">
            <dl class="space-y-2 text-sm">
                <div><dt class="font-medium">Registration (WhatsApp groups, poster)</dt><dd class="break-all text-gray-500"><a href="{{ $registerUrl }}" target="_blank" class="underline">{{ $registerUrl }}</a></dd></div>
                <div><dt class="font-medium">Feedback</dt><dd class="break-all text-gray-500"><a href="{{ $feedbackUrl }}" target="_blank" class="underline">{{ $feedbackUrl }}</a></dd></div>
                <div><dt class="font-medium">Volunteer scanner</dt><dd class="break-all text-gray-500"><a href="{{ $scanUrl }}" target="_blank" class="underline">{{ $scanUrl }}</a> · code <span class="font-mono">{{ $event->volunteer_code }}</span></dd></div>
                <div><dt class="font-medium">Vendor links</dt><dd class="text-gray-500">Per stall, from the Stalls table → "Vendor link".</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Night-before checklist">
            <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Print the kit. Tape gate signs where volunteers arrive, not where attendees queue.</li>
                <li>Open <span class="font-mono">/scan</span> on your own phone, join with the code, scan a gate sign and your own pass. Green flash = you're ready.</li>
                <li>Send each stall owner their vendor link.</li>
                <li>Forward the registration link to your groups once more.</li>
            </ol>
        </x-filament::section>
    </div>
</x-filament-panels::page>
