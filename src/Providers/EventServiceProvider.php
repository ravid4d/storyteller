<?php

namespace AmcLab\Storyteller\Providers;

use AmcLab\Storyteller\Events\Happening;
use AmcLab\Storyteller\Listeners\AuthActivity;
use AmcLab\Storyteller\Listeners\CatchEloquentEvents;
use AmcLab\Storyteller\Listeners\EloquentActivity;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Happening::class => [EloquentActivity::class],

        // \Illuminate\Auth\Events\Registered::class => [AuthActivity::class],
        // \Illuminate\Auth\Events\Attempting::class => [AuthActivity::class],
        // \Illuminate\Auth\Events\Authenticated::class => [AuthActivity::class],
        \Illuminate\Auth\Events\Login::class => [AuthActivity::class],
        // \Illuminate\Auth\Events\Failed::class => [AuthActivity::class],
        \Illuminate\Auth\Events\Logout::class => [AuthActivity::class],
        // \Illuminate\Auth\Events\Lockout::class => [AuthActivity::class],
        \Illuminate\Auth\Events\PasswordReset::class => [AuthActivity::class],
    ];

    public function boot() {
        parent::boot();
    }

}
