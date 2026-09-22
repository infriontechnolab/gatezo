<?php

return [
    /*
    | Our WhatsApp number. Sign-up is self-serve (/admin/register); this is where the
    | "Upgrade" and "Talk to us" links go, and gatezo:organizer still hand-onboards.
    */
    'whatsapp' => env('GATEZO_WHATSAPP', '919328964742'),

    /*
    | Where to mail a one-line "new organizer signed up" note so every sign-up is a lead
    | we see. Empty = no mail.
    */
    'signup_notify' => env('GATEZO_SIGNUP_NOTIFY'),

    /*
    | Plans. Self-serve sign-ups start on `free`; we flip them to `pro` by hand
    | (php artisan gatezo:plan someone@example.com pro) after the WhatsApp chat.
    | Limits are caps, not clocks: an organizer signs up weeks before the event and a
    | 14-day trial would expire before their first gate scan. null = unlimited.
    |   events     events this user has created
    |   attendees  registrations per event (online form, CSV import, manual add)
    |   team       organizers per event, including the creator
    */
    'plans' => [
        'free' => ['label' => 'Free', 'events' => 1, 'attendees' => 200, 'team' => 1],
        'pro' => ['label' => 'Pro', 'events' => null, 'attendees' => null, 'team' => null],
    ],

    /*
    | What the Upgrade page says Pro costs. Free text so it can be "₹2,999 per event" or
    | "₹9,999 a year"; empty = "priced per event, we quote in the chat". No payments yet:
    | the page records an upgrade_request and opens WhatsApp, Ops flips the plan by hand.
    */
    'pro_price' => env('GATEZO_PRO_PRICE'),

    /*
    | Public demo. /demo signs the visitor straight into the seeded "Sharad Utsav" event
    | (see DemoSeeder) so prospects can click around without registering. The account is
    | shared, so the event is rebuilt from the seeder every night (gatezo:demo-reset).
    */
    'demo' => [
        'enabled' => (bool) env('GATEZO_DEMO', false),
        'email' => 'demo@gatezo.local',
        'event' => 'sharad-utsav',
        'reset_at' => env('GATEZO_DEMO_RESET_AT', '04:00'),
    ],
];
