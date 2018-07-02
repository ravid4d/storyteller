<?php

namespace AmcLab\Storyteller\Subscribers;

use AmcLab\Storyteller\Jobs\StorytellerJob;

class StorytellerEventSubscriber {

    protected $listenerName = __CLASS__ . '@on';

    public function on($eventName, $data) {
        StorytellerJob::dispatch([$eventName, $data])->onQueue('storyteller');
        // $queueName = $this->config['worker']['queueName'];
        // dump("eccomi.. faccio il dispatch verso ", $queueName);
        // return StorytellerJob::dispatch($payload)->onQueue($queueName);
    }

    public function subscribe($events) {
        //$events->listen('eloquent.retrieved: *', $this->listenerName);
        $events->listen('eloquent.created: *', $this->listenerName);
        $events->listen('eloquent.updated: *', $this->listenerName);
        $events->listen('eloquent.deleted: *', $this->listenerName);
        $events->listen('eloquent.restored: *', $this->listenerName);
    }
}
