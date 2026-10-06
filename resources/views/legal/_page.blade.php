{{-- Shared frame for /privacy and /terms: readable column, plain headings, contact at the end. --}}
<article class="text-[15px] leading-relaxed text-neutral-700 [&_a]:font-medium [&_a]:text-neutral-900 [&_a]:underline [&_h2]:mt-10 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-neutral-900 [&_h3]:mt-5 [&_h3]:font-semibold [&_h3]:text-neutral-900 [&_li]:mt-1.5 [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5">
    <a href="{{ route('landing') }}" class="inline-flex items-center gap-2 !no-underline"><img src="/brand/mark.png" alt="" class="h-7 w-7"><span class="font-extrabold">Gatezo</span></a>
    <h1 class="mt-8 text-3xl font-extrabold tracking-tight text-neutral-900">{{ $heading }}</h1>
    <p class="!mt-2 text-sm text-neutral-500">Last updated {{ $updated }}</p>

    {{ $slot }}

    <h2>Contact</h2>
    <p>Questions, requests about your data, or complaints: write to <a href="mailto:{{ config('gatezo.contact_email') }}">{{ config('gatezo.contact_email') }}</a>. This is also our grievance contact under the Digital Personal Data Protection Act, 2023.</p>

    <p class="!mt-10 text-sm text-neutral-500"><a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('terms') }}">Terms of use</a></p>
</article>
