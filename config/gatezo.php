<?php

return [
    /*
    | Organizer sign-up is invite-only for now: every "Start your event" button opens a
    | WhatsApp chat with us and we create the event and the first login together.
    */
    'whatsapp' => env('GATEZO_WHATSAPP', '919328964742'),

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
