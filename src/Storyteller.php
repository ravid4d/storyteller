<?php

namespace AmcLab\Storyteller;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Contracts\Receiver;
use AmcLab\Storyteller\Contracts\Storyteller as Contract;
use AmcLab\Storyteller\Documents\EntityDocument;
use AmcLab\Storyteller\Documents\EventDocument;
use AmcLab\Storyteller\Documents\HappenedDocument;
use AmcLab\Storyteller\Documents\ResponsibilityDocument;
use AmcLab\Storyteller\Jobs\Tell;
use AmcLab\Storyteller\Jobs\WriteLog;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Traversable;

class Storyteller implements Contract {

    use DispatchesJobs;

    protected $receiver;
    protected $environment;
    protected $user;

    public function __construct(Receiver $receiver, Environment $environment, ?Authenticatable $user) {
        $this->receiver = $receiver;
        $this->environment = $environment;
        $this->user = $user;
    }

    /**
     * Richiede il log asincrono di un evento arbitrario che coinvolge uno o più entità, passate
     * come arguments (a numero variabile) del metodo.
     * Ciascuna entità può essere un'istanza di Model o una sua rappresentazione sotto forma di
     * array con la struttura [className, id].
     *
     * @param mixed $event
     * @param array|Model ...$entities
     * @return void
     */
    public function happened($event, ...$entities) {

        if (!count($entities)) {
            throw new BadMethodCallException('Wrong arguments count for input models (0 passed).');
        }

        if (!$this->environment->getIdentity()) {
            throw new StorytellerException('Environment Identity must be set before logging', 1000);
        }

        // costruisco il contenitore delle responsabilità (utente e scope)
        $responsibility = new ResponsibilityDocument($this->user, $this->environment);

        // setto qui il datetime, così gli N eventi avranno tutti lo stesso orario
        $datetime = Carbon::now();

        $eventRepresentation = new EventDocument($event);

        foreach ($entities as $entityKey => $model) {

            if (!is_array($model)) {
                if ($model instanceof Model) {
                    $model = [$model];
                }
                else {
                    throw new StorytellerException('Entity #' . ($entityKey+1) . ' must be an instance of Model or [$className, $id]', 1500);
                }
            }

            // costruisco l'identificatore dell'entità attualmente coinvolta
            // NOTE: $model potrebbe essere un array con un'istanza di Model o un array nella forma [className, id]
            $currentEntity = new EntityDocument(...$model);

            // richiedo l'inoltro asincrono del Document
            $documents[] = (new HappenedDocument($eventRepresentation, $currentEntity, $responsibility))->at($datetime);

            $this->queuedLog(...$documents);

        }
    }

    /**
     * Richiede di mettere in coda il log di un Document (o una sua esportazione in array)
     *
     * @param Document|array ...$inputDocuments
     * @return void
     */
    public function queuedLog(...$inputDocuments) {
        if (!$this->environment->getIdentity()) {
            throw new StorytellerException('Environment Identity must be set before logging', 1000);
        }
        foreach($inputDocuments as $inputDocument) {
            $job = new WriteLog($this->environment->getSpecs(), $inputDocument instanceof Document ? $inputDocument->export() : $inputDocument);
            $this->dispatch($job->onQueue('storyteller'));
        }
    }

    /**
     * Esegue immediatamente il log di un Document (o una sua esportazione in array)
     *
     * @param Document|array ...$inputDocuments
     * @return void
     */
    public function immediateLog(...$inputDocuments) {
        if (!$this->environment->getIdentity()) {
            throw new StorytellerException('Environment Identity must be set before logging', 1000);
        }
        foreach($inputDocuments as $inputDocument) {
            $this->receiver->push($inputDocument instanceof Document ? $inputDocument->export() : $inputDocument);
        }
    }

    /**
     * Restituisce l'istanza del Receiver in uso
     *
     * @return Receiver
     */
    public function getReceiver() : Receiver {
        return $this->receiver;
    }

    /**
     * Restituisce il log delle operazioni loggate per uno specifico Model
     *
     * @param Model $model
     * @return Traversable
     */
    public function getByModel(Model $model) : Traversable {
        return $this->receiver->getByModel($model);
    }

    /**
     * Restituisce il log delle azioni loggate per uno specifico Authenticatable (user)
     *
     * @param Authenticatable $user
     * @return Traversable
     */
    public function getByAuth(?Authenticatable $user) : Traversable {
        return $this->receiver->getByAuth($user);
    }

    /**
     * Elimina il log di una specifica $affectedEntity (TODO: da fare...)
     *
     * @param [type] ...$args
     * @return void
     */
    public function purge(...$args) {
        // TODO:
    }


}
