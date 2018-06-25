<?php

namespace AmcLab\Storyteller\Providers;

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

        foreach ($config['clients'] as $k=>$v) {
            $this->app->bind('storyteller.client.'.$k, $v);
        }

        $this->app->singleton('storyteller', function($app) {
            $config = $app['config']['storyteller'];
            //TODO:...continuare da qui...
        });


        // $this->app->singleton('storyteller', function($app) {

        //     $config = $app['config']['storyteller'];
        //     $configured = json_decode($config['used'], true);

        //     $clientName = $config['clients'][$configured['client']];
        //     dd($configured);
        //     return new $clientName($configured['']);


        //     dd($config, $clientName);

        //     echo "ciao!";
        //     // $providerName = $app->make('config')->get('services.remote-logger.provider');
        //     // $destinationName = $app->make('config')->get('services.remote-logger.destination');
        //     // return new RemoteLogService($providerName, $destinationName);
        // });

    }

}
