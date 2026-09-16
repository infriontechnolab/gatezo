<div class="flex flex-col items-center gap-3 text-center">
    <div class="w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $svg !!}</div>
    <div class="font-mono text-xl tracking-[0.3em]">{{ $code }}</div>
    <div class="text-sm text-gray-500 break-all">{{ $url }}</div>
    <div class="text-xs text-gray-400">{{ $hint }}</div>
</div>
