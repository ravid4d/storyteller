<?php

return [

    'clients' => [
        'mongo' => MongoDB\Client::class,
    ],

    'client' => env('STORYTELLER_CLIENT'),
    'connection' => env('STORYTELLER_CONNECTION'),

];
