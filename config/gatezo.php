<?php

return [
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
