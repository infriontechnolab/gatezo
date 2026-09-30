<?php

return [
    // We deploy to our own servers (EC2 / Contabo), not Laravel Cloud.
    'guidelines' => [
        'exclude' => ['deployments'],
    ],
];
