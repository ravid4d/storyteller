<?php

namespace AmcLab\Storyteller;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Receivers\ReceiverInterface;
use AmcLab\Storyteller\Contracts\Storyteller as Contract;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

class Storyteller implements Contract {

    protected $app;
    protected $receiver;

    public function __construct(Application $app, ReceiverInterface $receiver) {
        $this->app = $app;
        $this->receiver = $receiver;
    }

    public function about(array $payload, Carbon $when = null, Environment $environment = null, ?Authenticatable $user = null) {

        [ $eventNameRaw, $data ] = $payload;

        $when = $when ?? Carbon::now();
        $environment = $environment ?? $this->app->make('environment');

        $destination = $environment->getIdentity()
        ? 'storyteller_' . $environment->pathway()['linkableResourceId']
        : 'storyteller_anonymous';

        $eventName = last(explode('.', head(explode(':', $eventNameRaw))));

        foreach($data as $model) {

            $exclude = [
                $model->getKeyName(),
                $model->getCreatedAtColumn(),
                $model->getUpdatedAtColumn(),
            ];

            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model))){
                $exclude[] = $model->getDeletedAtColumn();
            }

            if ($model->exists) {

                $originals = $model->getOriginal();
                $attributes = $model->getAttributes();
                $casts = $model->getCasts();

                foreach ($model->getDirty() as $name => $value) {

                    if (!in_array($name, $exclude)) {

                        $old = $originals[$name] ?? null;
                        $new = $attributes[$name] ?? null;

                        $localChanges = [
                            'oldValue' => $old,
                            'newValue' => $new,
                        ];

                        // se i dati hanno un casting...
                        if (array_key_exists($name, $casts)) {

                            // se sono trattati come array (es json)
                            if ($casts[$name] === 'array') {
                                $localChanges = $this->arrayVersionCompare((array) json_decode($old, true), (array) json_decode($new, true));
                            }

                            // se sono datetime -> Carbon
                            else if ($casts[$name] === 'datetime') {
                                $localChanges = [
                                    'oldValue' => new Carbon($old),
                                    'newValue' => new Carbon($new),
                                ];
                            }

                            // ...TODO: valutare se gestire cast verso altri tipi (es. date -> Carbon)
                            // altrimenti vengono scritti come stringhe
                            else {
                                // $localChanges = ...
                            }

                        }

                        $changes[] = [
                            'key' => $name,
                            'changes' => $localChanges,
                        ];
                    }
                }

                // se è richiesto un update ma $changes è vuoto/inesistente, allora salta.
                // NOTE: capita nel caso in cui si fa un update di soli campi esclusi dal log...
                // es.: il restore fa un update della data e poi lancia l'evento "restored"
                if ($eventName === 'updated' && (!$changes ?? [])) {
                    return;
                }

            }

            else {
                // ELIMINATO FISICAMENTE
                $eventName = 'hard-deleted';
            }

            $responsibility = $this->composeResponsibility($user, $environment);

            $this->push([
                'event' => $eventName,
                'datetime' => $when,
                'entity' => $this->composeEntity($model, $changes ?? null),
                'responsibility' => $responsibility,
            ], $destination);

            // se il model eredita il trait Tellable, allora logga l'operazione anche presso i parent model dichiarati
            if (in_array(\AmcLab\Storyteller\Traits\Tellable::class, class_uses_recursive($model))){
                foreach ($model->getBubbles() ?? [] as $key => $info) {
                    [$bubbledModel, $awaited] = $info;

                    // procedi se la colonna ha un valore e se l'evento è tra quelli da loggare
                    if (($model->$key) && (in_array($eventName, $awaited))) {
                        $this->push([
                            'event' => 'related@' . $eventName,
                            'datetime' => $when,
                            'entity' => $this->composeEntity($bubbledModel, null, $model->$key),
                            'responsibility' => $responsibility,
                            'eventBubbledFrom' => $this->composeEntity($model),
                        ], $destination);
                    }
                    //echo "dispatch";
                    //dd($model);
                    //dispatch(new \AmcLab\Storyteller\Jobs\StorytellerJob(['prova',[$model]], $environment, $user))->onQueue('storyteller');
                    // TODO: continua da qui, eliminando il push precedente e sostituendolo con il dispatch!!
                    //echo "...dispatched";
                }
            }


        }
    }

    public function push($pushed, $destination) {
        $this->receiver->push($pushed, $destination);
    }

    public function getByModel(Model $model, $destination) {
        return $this->receiver->getByModel($model, $destination);
    }

    public function getByAuth(?Authenticatable $user, $destination) {
        return $this->receiver->getByAuth($user, $destination);
    }

    public function purge(...$args) {
        // TODO:
    }

    protected function composeEntity($model, $changes = null, $key = null) {
        return [
            'name' => is_object($model) ? get_class($model) : $model,
            'key' => $key ?? (is_object($model) ? $model->getKey() : $key),
        ] + (is_null($changes) ? [] : [
            'changes' => $changes,
        ]);
    }

    protected function composeResponsibility($user, $environment) {
        return [
            'userId' => $user->id ?? null,
            'scope' => [
                'name' => get_class($environment->getScope()),
                'data' => $environment->getScope()->getData(),
            ],
        ];
    }

    protected function arrayVersionCompare(?array $old, ?array $new) : array {
        $diffs = [];
        $checked = [];

        // confronta il vecchio con il nuovo
        $current = $old;
        $compared = $new;
        if (!is_null($current)) {
            foreach($current as $key => $currentValue) {
                $exists = array_key_exists($key, $compared ?? []);
                $comparedValue = $exists ? $compared[$key] : null;
                if (is_array($currentValue) && count($currentValue)) {
                    $diffs[] = [
                        'key' => $key,
                        'subEvent' => $exists ? 'updated' : 'deleted',
                        'changes' => $exists ? $this->{__FUNCTION__}($currentValue, $comparedValue) : [
                            'oldValue' => $currentValue,
                            'newValue' => null,
                        ]
                    ];
                }
                else {
                    if ($currentValue !== $comparedValue) {
                        $diffs[] = [
                            'key' => $key,
                            'subEvent' => $exists ? 'updated' : 'deleted',
                            'changes' => [
                                'oldValue' => $currentValue,
                                'newValue' => $comparedValue,
                            ]
                        ];
                    }

                    $checked[] = $key;
                }
            }
        }

        // confronta il nuovo con il vecchio
        $current = $new;
        $compared = $old;
        if (!is_null($current)) {

            foreach($current as $key => $currentValue) {
                $exists = array_key_exists($key, $compared ?? []);
                $comparedValue = $exists ? $compared[$key] : null;
                if (is_array($currentValue) && count($currentValue)) {
                    $diffs[] = [
                        'key' => $key,
                        'subEvent' => $exists ? 'updated' : 'created',
                        'changes' => $this->{__FUNCTION__}($comparedValue, $currentValue)
                    ];
                }
                else {

                    // ...e verifica se esistono nuove proprietà che nel vecchio non esistevano
                    if (!in_array($key, $checked)){
                        $diffs[] = [
                            'key' => $key,
                            'subEvent' => 'created',
                            'changes' => [
                                'oldValue' => null,
                                'newValue' => $currentValue,
                            ]
                        ];
                    }
                }
            }
        }

        // elimina indici duplicati
        $diffs = array_intersect_key($diffs, array_unique(array_map(function($v) {
            return json_encode($v);
        }, $diffs)));

        // elimina update vuoti
        $diffs = array_filter($diffs, function($v) {
            return !($v['subEvent']==='updated' && (($v['changes'] ?? true)===[]) );
        });

        // restituisce il risultato
        return array_values($diffs);

    }


}
