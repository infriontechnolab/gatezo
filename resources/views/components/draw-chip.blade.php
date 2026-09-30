@props(['w'])
@php
    $cls = match ($w->status) {
        \App\Enums\WinnerStatus::Pending => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300',
        \App\Enums\WinnerStatus::Announced => 'bg-primary-100 text-primary-800',
        \App\Enums\WinnerStatus::Claimed => 'bg-green-100 text-green-800',
        \App\Enums\WinnerStatus::Forfeited => 'bg-gray-100 text-gray-400 line-through',
    };
@endphp
<span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-sm {{ $cls }}">{{ $w->pass->attendee->name }} <span class="text-[10px] uppercase opacity-70">{{ $w->status->getLabel() }}</span></span>
