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
use AmcLab\Storyteller\Exceptions\StorytellerException;
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

    protected $ignored = [];
    protected $deferrable = [];
    protected $deferred = [];

    public function __construct(Receiver $receiver, Environment $environment, ?Authenticatable $user) {
        $this->receiver = $receiver;
        $this->environment = $environment;
        $this->user = $user;
    }

    /**
     * PATCH: Permette di settare un utente dal vecchio sistema di autenticazione
     * eTesseramenti come Authenticatable attuale.
     *
     * !! IMPORTANT: È SOLTANTO TEMPORANEO!!!!!
     *
     * @param User $user
     * @return self
     */
    public function setAuthFromETUser(User $user) {
        $this->user = $user;
        return $this;
    }

    public function tell($job) {
        $connection = config('storyteller.connection');

        if ($connection === '`null`') {
            $connection = 'null';
        }

        $this->dispatch(
            $job->onConnection($connection)
            ->onQueue(config('storyteller.queue'))
        );
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

        }

        return $this->queuedLog(...$documents);
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

            // trasforma il documento in un array, se necessario
            $document = $inputDocument instanceof Document ? $inputDocument->export() : $inputDocument;

            // verifica che il document adesso sia un array
            if (!is_array($document)) {
                throw new StorytellerException('Document "'.json_encode($document).'" cannot be reduced to array', 1500);
            }

            // se il model in oggetto è ignorato, salta...
            if (in_array($document['affectedEntity']['name'], $this->ignored)) {
                continue;
            }

            $job = new WriteLog($this->environment->getSpecs(), $document);

            // se il model corrente è deferrable, non fare il dispatch ma inseriscilo nella coda
            if (in_array($document['affectedEntity']['name'], $this->deferrable)) {
                $this->deferred[] = [$document['affectedEntity']['name'], $job];
            }

            // altrimenti fai il dispatch normalmente
            else {
                $this->tell($job);
            }
        }

        return $this;
    }

    /**
     * Esegue immediatamente il log di un Document (o una sua esportazione in array)
     *
     * IMPORTANT: l'operazione viene lanciata a prescindere da $ignored, $deferrable e $deferred.
     *
     * @param Document|array $document
     * @return void
     */
    public function immediateLog($document) {

        if (!$this->environment->getIdentity()) {
            throw new StorytellerException('Environment Identity must be set before logging', 1000);
        }

        // trasforma il documento in un array, se necessario
        $document = $document instanceof Document ? $document->export() : $document;

        // verifica che il document adesso sia un array
        if (!is_array($document)) {
            throw new StorytellerException('Document "'.json_encode($document).'" cannot be reduced to array', 1500);
        }

        // mandalo al receiver per la scrittura immediata
        $this->receiver->push($document);

        return $this;
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

    /**
     * Aggiunge una classe Model all'elenco dei nomi di classi da ignorare
     *
     * @param string $modelClass
     * @return self
     */
    public function pushIgnored(string $modelClass) {
        $this->ignored[] = $modelClass;
        return $this;
    }

    /**
     * Toglie la classe Model dall'elenco dei nomi di classi da ignorare
     *
     * @param string $modelClass
     * @return self
     */
    public function pullIgnored(string $modelClass) {
        $this->ignored = array_filter($this->ignored, function($v) use ($modelClass) {
            return $v !== $modelClass;
        });
        return $this;
    }

    /**
     * Setta un elenco di nomi di classi da ignorare
     *
     * @param string $modelClass
     * @return self
     */
    public function setIgnored(array $modelClasses) {
        $this->ignored = $modelClasses;
        return $this;
    }

    /**
     * Restituisce l'elenco di nomi di classi da ignorare
     *
     * @param string $modelClass
     * @return array
     */
    public function getIgnored() {
        return $this->ignored;
    }

    /**
     * Indica di posticipare il dispatch per una classe Model
     *
     * @param string $modelClass
     * @return self
     */
    public function pushDeferrable(string $modelClass) {
        $this->deferrable[] = $modelClass;
        return $this;
    }

    /**
     * Toglie il nome della classe Model dall'elenco di dispatch posticipati
     *
     * @param string $modelClass
     * @return self
     */
    public function pullDeferrable(string $modelClass) {
        $this->deferrable = array_filter($this->deferrable, function($v) use ($modelClass) {
            return $v !== $modelClass;
        });
        return $this;
    }

    /**
     * Setta un elenco di nomi di classi con dispatch posticipato
     *
     * @param string $modelClass
     * @return self
     */
    public function setDeferrable(array $modelClasses) {
        $this->deferrable = $modelClasses;
        return $this;
    }

    /**
     * Restituisce l'elenco di classi Model con dispatch posticipato
     *
     * @param string $modelClass
     * @return array
     */
    public function getDeferrable() {
        return $this->deferrable;
    }

    /**
     * Effettua il dispatch di tutti i job posticipati o di quelli relativi ad una specifica
     * classe Model, eliminandoli dalla coda
     *
     * @param string|null $modelClass
     * @return self
     */
    public function dispatchDeferred(?string $modelClass = null) {
        return $this->deferredCallback($modelClass, function($className, $job, $deferredId) {
            $this->tell($job);
            unset($this->deferred[$deferredId]);
        });
    }

    /**
     * Elimina tutti i job posticipati o quelli di una specifica classe Model
     *
     * @param string|null $modelClass
     * @return self
     */
    public function flushDeferred(?string $modelClass = null) {
        return $this->deferredCallback($modelClass, function($className, $job, $deferredId) {
            unset($this->deferred[$deferredId]);
        });
    }

    /**
     * Scorre tutti i job posticipati o quelli di una specifica classe Model ed esegue una callback
     *
     * @param string|null $modelClass
     * @param callable $callback
     * @return self
     */
    public function deferredCallback(?string $modelClass = null, callable $callback) {
        foreach ($this->deferred as $deferredId => $single) {
            [$className, $job] = $single;
            if (($modelClass && $modelClass === $className) || !$modelClass)  {
                $callback($className, $job, $deferredId);
            }
        }

        return $this;
    }

    /**
     * Rimuove tutti i job posticipati e tutti i model dall'elenco di quelli da posticipare
     *
     * @return self
     */
    public function resetAllDefers() {
        return $this->setDeferrable([])->flushDeferred();
    }

}
