@if ($admin = \App\Support\Impersonation::admin())
    <div class="demo-banner">
        <span class="demo-banner-dot"></span>
        <span>You're seeing the panel as <b>{{ auth()->user()->name }}</b> ({{ auth()->user()->email }}). Changes are real.</span>
        <form method="post" action="{{ route('ops.stop-impersonating') }}">@csrf<button type="submit" class="font-semibold underline underline-offset-2">Back to Ops as {{ $admin->name }}</button></form>
    </div>
@endif
