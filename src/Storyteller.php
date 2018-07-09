<?php

namespace AmcLab\Storyteller;

use AmcLab\Environment\Contracts\Environment;
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

    public function happened($event, ...$oneOrManyModels) {

        // costruisco il contenitore delle responsabilità (utente e scope)
        $responsibility = new ResponsibilityDocument($this->user, $this->environment);

        // setto qui il datetime, così gli N eventi avranno tutti lo stesso orario
        $datetime = Carbon::now();

        $eventRepresentation = new EventDocument($event);

        foreach ($oneOrManyModels as $model) {

            if (!is_array($model)) {
                $model = [$model];
            }

            // costruisco l'identificatore dell'entità attualmente coinvolta
            // NOTE: $model potrebbe essere un array con un'istanza di Model o un array nella forma [className, id]
            $currentEntity = new EntityDocument(...$model);

            // richiedo l'inoltro asincrono del Document
            $document = new HappenedDocument($eventRepresentation, $currentEntity, $responsibility);
            $job = new WriteLog($this->environment->getSpecs(), $document->at($datetime)->export());
            $this->dispatch($job->onQueue('storyteller'));

        }
    }

    public function push($contents) {
        $this->receiver->push($contents);
    }

    public function getByModel(Model $model) {
        return $this->receiver->getByModel($model);
    }

    public function getByAuth(?Authenticatable $user) {
        return $this->receiver->getByAuth($user);
    }

    public function purge(...$args) {
        // TODO:
    }


}
