@props(['w'])
@php $cls = ['pending' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300', 'announced' => 'bg-primary-100 text-primary-800', 'claimed' => 'bg-green-100 text-green-800', 'forfeited' => 'bg-gray-100 text-gray-400 line-through'][$w->status]; @endphp
<span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-sm {{ $cls }}">{{ $w->pass->attendee->name }} <span class="text-[10px] uppercase opacity-70">{{ $w->status }}</span></span>
