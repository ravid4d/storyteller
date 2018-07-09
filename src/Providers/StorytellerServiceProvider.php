<?php

namespace AmcLab\Storyteller\Providers;

use AmcLab\Storyteller\Contracts\Receiver;
use AmcLab\Storyteller\Contracts\Storyteller;
use Illuminate\Support\ServiceProvider;

class StorytellerServiceProvider extends ServiceProvider
{

    public function boot()
    {

        $this->publishes(array(
            __DIR__.'../../config/storyteller.php' => config_path('storyteller.php'),
        ), 'config');

    }

    public function register()
    {
        $config = $this->app['config']['storyteller'];
        $this->app->bind(Receiver::class, $config['receivers'][$config['receiver']]['class']);
        $this->app->singleton(Storyteller::class, \AmcLab\Storyteller\Storyteller::class);
        $this->app->alias(Storyteller::class, 'storyteller');
    }

}
