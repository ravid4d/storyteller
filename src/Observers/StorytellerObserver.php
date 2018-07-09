<?php

namespace AmcLab\Storyteller\Observers;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Events\Happening;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

class StorytellerObserver
{
    protected $environment;
    protected $user;
    protected $events;

    public function __construct(Environment $environment, ?Authenticatable $user, Dispatcher $events) {
        $this->environment = $environment;
        $this->user = $user;
        $this->events = $events;
    }

    protected function invoke(string $event, Model $model) {
        $this->events->fire(new Happening(
            $model,
            $event,
            $this->environment->getSpecs(),
            $this->user
        ));
    }

    public function created(Model $model)
    {
        $this->invoke(__FUNCTION__, $model);
    }

    public function updated(Model $model)
    {
        $this->invoke(__FUNCTION__, $model);
    }

    public function deleted(Model $model)
    {
        $this->invoke(__FUNCTION__, $model);
    }

    public function restored(Model $model)
    {
        $this->invoke(__FUNCTION__, $model);
    }

    public function forceDeleted(Model $model)
    {
        $this->invoke(__FUNCTION__, $model);
    }

}
