<?php

return [

    'receivers' => [
        'mongo' => [
            'class' => AmcLab\Storyteller\Receivers\MongoReceiver::class,
            'parameters' => [
                'uri' => env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
                'uriOptions' => env('MONGODB_URI_OPTIONS', []),
                'driverOptions' => env('MONGODB_DRIVER_OPTIONS', []),
            ],
        ],
    ],

    'receiver' => 'mongo',

    'connection' => env('STORYTELLER_CONNECTION', config('queue.default')),
    'queue' => 'storyteller',

];
