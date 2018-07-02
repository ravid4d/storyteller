<?php

namespace AmcLab\Storyteller\Receivers;

use AmcLab\Storyteller\Abstracts\AbstractReceiver;
use AmcLab\Storyteller\Contracts\Receivers\ReceiverInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Client;
use Traversable;

class MongoReceiver extends AbstractReceiver implements ReceiverInterface {

    protected $name = 'mongo';
    protected $client;

    public function __construct(ConfigRepository $configRepository) {
        parent::__construct($configRepository);
        $this->client = new Client($this->config['uri'], $this->config['uriOptions'], $this->config['driverOptions']);
    }

    protected function transform($pushed) {
        // qui avvengono le eventuali trasformazioni necessarie...
        return array_map_recursive(function($entry) {

            // Carbon e DateTime devono essere convertiti in BSON
            if ($entry instanceof \Carbon\Carbon || $entry instanceof \DateTime) {
                $entry = new \MongoDB\BSON\UTCDateTime($entry);
            }
            return $entry;

        }, $pushed);
    }

    public function endpoint($destination) {
        [$database, $collection] = ((array) $destination + [null, 'storyteller']);
        if (!$database || !$destination) {
            throw new ReceiverException('Invalid endpoint: "'.$database.'.'.$collection.'"');
        }
        return $this->client->{$database}->{$collection};
    }

    public function push($pushed, $destination) {
        $a = $this->transform($pushed);
        $this->endpoint($destination)->insertOne($a);
    }

    public function retrieve($wheres = [], $orderBy = [], $destination) {
        $sort = ['sort' => $orderBy];
        $result = $this->endpoint($destination)->find($wheres, $sort);
        return $result;
    }

    public function retrieveNewest($wheres = [], $destination) {
        return $this->retrieve($wheres, ['datetime' => -1], $destination);
    }

    public function getByModel(Model $model, $destination) {
        return $this->retrieveNewest([
            'entity.name' => get_class($model),
            'entity.key' => $model->{$model->getKeyName()},
        ], $destination);
    }

    public function getByAuth(?Authenticatable $user, $destination) {
        return $this->retrieveNewest([
            'responsibility.userId' => $user->{$user->getKeyName()},
        ], $destination);
    }

    public function purge() {
        // TODO:
    }









    /*
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

*/
}
