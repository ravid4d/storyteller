<?php

namespace AmcLab\Storyteller\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    protected $subscribe = [
        'AmcLab\Storyteller\Subscribers\StorytellerEventSubscriber',
    ];

    public function boot() {
        parent::boot();
    }

}
