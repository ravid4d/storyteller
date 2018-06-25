<?php

namespace AmcLab\Storyteller\Clients;

use AmcLab\Storyteller\Contracts\Clients\StorytellerClient;
use Traversable;

class MongoClient implements StorytellerClient {

    protected $destinationName;
    protected $instance;

    public function __construct(string $providerName, string $destinationName) {
        $this->instance = app()->make($providerName);
        $this->destinationName = $destinationName;
    }

    public function getInstance() {
        return $this->instance;
    }

    public function getDestinationName() : string {
        return $this->destinationName;
    }

    public function push(string $entity, $key, array $action) : bool {

        $this->instance->db()->{$this->destinationName}->insertOne([
            'entity' => $entity,
            'key' => $key,
            'action' => $action,
        ]);

        return true;
    }

    public function list(string $entity, $key) : Traversable {

        $filter = []; // TODO:
        $sort = []; // TODO:

        $filters = [
            'entity' => $entity,
            'key' => $key,
        ] + $filter;

        return $this->instance->db()->{$this->destinationName}->find($filters, $sort);
    }

    public function purge(string $entity, $key) : bool {

        $this->instance->db()->{$this->destinationName}->deleteMany([
            'entity' => $entity,
            'key' => $key,
        ]);

        return true;

    }


}
