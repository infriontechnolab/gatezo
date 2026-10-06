<?php

return [
    /*
    | Our WhatsApp number, for the public landing page's "Talk to us". Sign-up is self-serve
    | (/admin/register); inside the panel, upgrades go through the Request Pro form instead.
    */
    'whatsapp' => env('GATEZO_WHATSAPP', '919328964742'),

    /*
    | Where people write about their data or the terms: the grievance contact on /privacy
    | and /terms. Needs a real mailbox (Resend only sends).
    */
    'contact_email' => env('GATEZO_CONTACT_EMAIL', 'hello@gatezo.in'),

    /*
    | Where to mail a one-line "new organizer signed up" note so every sign-up is a lead
    | we see. Empty = no mail.
    */
    'signup_notify' => env('GATEZO_SIGNUP_NOTIFY'),

    /*
    | Plans (names, caps, prices) live in the subscription_plans table, edited in Ops → Plans.
    */

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
