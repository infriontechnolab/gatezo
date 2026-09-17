<div class="eq-auth">
    {{-- Brand panel --}}
    <aside class="eq-auth-brand">
        <div class="eq-auth-brand-inner">
            <a href="{{ url('/') }}" class="eq-auth-logo">
                <img src="{{ asset('brand/logo-dark.svg') }}" alt="EventQR" height="36">
            </a>

            <div class="eq-auth-copy">
                <h2>Print QR codes. Stick them on things.<br>Your event runs itself.</h2>
                <p>Registration, gate check-in, live headcount, stall menus, vendor leads and exit feedback, all from a few sheets of A4.</p>
            </div>

            <ul class="eq-auth-points">
                <li><span class="eq-dot"></span>Works when the venue Wi-Fi dies</li>
                <li><span class="eq-dot"></span>No app, no account for attendees</li>
                <li><span class="eq-dot"></span>One report to forward the next morning</li>
            </ul>

            <div class="eq-auth-qr" aria-hidden="true">
                @for ($i = 0; $i < 49; $i++)
                    <i style="--d: {{ ($i * 37) % 11 }}"></i>
                @endfor
            </div>
        </div>
    </aside>

    {{-- Form panel --}}
    <main id="fi-main-content" class="eq-auth-main">
        <div class="fi-simple-page eq-auth-card">
            <div class="fi-simple-page-content">
                <x-filament-panels::header.simple
                    :heading="$this->getHeading()"
                    :logo="false"
                    :subheading="$this->getSubheading()"
                />

                {{ $this->content }}
            </div>

            <x-filament-actions::modals />
        </div>

        <p class="eq-auth-foot">
            Volunteer? You don't need an account: <a href="{{ route('scan.join') }}">open the scanner</a> with the event code.
        </p>
    </main>
</div>
