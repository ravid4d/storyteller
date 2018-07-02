<?php

namespace AmcLab\Storyteller\Providers;

use AmcLab\Storyteller\Contracts\Receivers\ReceiverInterface;
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
        $this->app->bind(ReceiverInterface::class, \AmcLab\Storyteller\Receivers\MongoReceiver::class);
        $this->app->singleton(Storyteller::class, \AmcLab\Storyteller\Storyteller::class);
        $this->app->alias(Storyteller::class, 'storyteller');
    }

}
