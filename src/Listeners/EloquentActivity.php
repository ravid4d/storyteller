<?php

namespace AmcLab\Storyteller\Listeners;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Storyteller;
use AmcLab\Storyteller\Documents\DataChangeDocument;
use AmcLab\Storyteller\Documents\EloquentDocument;
use AmcLab\Storyteller\Documents\EntityDocument;
use AmcLab\Storyteller\Documents\EventDocument;
use AmcLab\Storyteller\Documents\RelatedChangeDocument;
use AmcLab\Storyteller\Documents\ResponsibilityDocument;
use AmcLab\Storyteller\Events\Happening;
use AmcLab\Storyteller\Jobs\WriteLog;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Queue\InteractsWithQueue;

class EloquentActivity implements ShouldQueue
{
    use DispatchesJobs, InteractsWithQueue;

    public $queue = 'storyteller';

    protected $storyteller;
    protected $environment;

    public function __construct(Storyteller $storyteller, Environment $environment)
    {
        $this->storyteller = $storyteller;
        $this->environment = $environment;
    }

    public function failed(Happening $event, \Exception $e) {
        // TODO: allertare il mondo... qui non dovrebbe MAI fallire!
        dump($e);
    }

    public function handle(Happening $event)
    {

        try {
            $this->environment->unsetIdentity()
            ->setWithSpecs($event->environmentSpecs);

            $changes = [];
            $attributes = $event->model->getAttributes();
            $softDeletable = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($event->model));
            $dirtyFields = array_keys($event->model->getDirty());
            $idColumn = $event->model->getKeyName();
            $createdAtColumn = $event->model->getCreatedAtColumn();
            $updatedAtColumn = $event->model->getUpdatedAtColumn();
            $deletedAtColumn = $softDeletable ? $event->model->getDeletedAtColumn() : null;

            // costruisco l'elenco dei campi da escludere dal log
            $excluded = array_filter(array_merge([
                $idColumn,
                $createdAtColumn,
                $updatedAtColumn,
                $deletedAtColumn,
            ], $event->model->getExcludeFromLog()));

            // se il model è fisicamente esistente, considero come data di riferimento quella
            // della colonna updated_at, tranne se l'evento è una cancellazione che avviene su
            // un model che usa SoftDeletes: in tal caso, usa deleted_at come data di riferimento.
            if ($event->model->exists) {
                $datetime = ($softDeletable && $event->name === 'deleted') ? $attributes[$deletedAtColumn] : $attributes[$updatedAtColumn];

                // FIXME: perché non arriva già sotto forma di Carbon? (inizialmente funzionava)
                $datetime = $datetime instanceof Carbon ? $datetime : new Carbon($datetime);
            }

            // se il model non esiste più, considera buona la data corrente
            else {
                $datetime = Carbon::now();
            }

            // salta l'elaborazione se l'evento è un update del campo "deleted_at" = null, perché
            // in realtà devi loggare il "restored", che segue questo evento.
            if (
                $event->name === 'updated'
                && isset($deletedAtColumn)
                && in_array($deletedAtColumn, $dirtyFields)
                && $attributes[$deletedAtColumn] === null
            ) {
                return;
            }

            // adesso scorro i campi contrassegnati come "dirty" e, per ciascuno, costruisco la differenza
            foreach ($dirtyFields as $fieldName) {

                // tranne se il campo in oggetto è tra quelli esclusi dal log
                if (in_array($fieldName, $excluded)) {
                    continue;
                }

                $changes[] = [
                    'attribute' => $fieldName,
                    'changes' => $this->getAttributeVersions($event->model, $fieldName),
                ];

            }

            // salta l'elaborazione se è un update ma $changes è vuoto
            if ($event->name === 'updated' && !$changes) {
                return;
            }

            // costruisco il contenitore delle responsabilità (utente e scope)
            $responsibility = new ResponsibilityDocument($event->user, $this->environment);

            // costruisco l'identificatore dell'entità attualmente coinvolta
            $currentEntity = new EntityDocument($event->model);

            $eventDocument = new EventDocument($event);

            // richiedo l'inoltro asincrono del Document
            $document = new EloquentDocument($eventDocument, $currentEntity, $responsibility, $changes);
            $job = new WriteLog($this->environment->getSpecs(), $document->at($datetime)->export());
            $this->dispatch($job->onQueue($this->queue));

            // se il model corrente ha delle relazioni con altri models, devo
            // loggare anche loro...
            foreach (($event->model->getBubbles() ?? []) as $column => $bubblingData) {

                [$relatedModel, $loggedRelatedEvents] = $bubblingData;

                // ...ma solo se la colonna id che lega all'altro model è popolata
                if (!$relatedId = $event->model->$column ?? null) {
                    continue;
                }

                // ...e solo se l'evento è contrassegnato come evento da loggare sul model relazionato
                if (!in_array($event->name, $loggedRelatedEvents)) {
                    continue;
                }

                // costruisco l'identificatore dell'entità a cui segnalare l'operazione
                $relatedEntity = new EntityDocument($relatedModel, $relatedId);

                // richiedo l'inoltro asincrono del Document
                $document = new RelatedChangeDocument('related@'.$event->name, $relatedEntity, $currentEntity, $responsibility);
                $job = new WriteLog($this->environment->getSpecs(), $document->at($datetime)->export());
                $this->dispatch($job->onQueue($this->queue));

            }

        }
        catch(\Exception $e) {
            $this->environment->unsetIdentity();
            throw $e;
        }
        $this->environment->unsetIdentity();

    }

    /**
     * Restituisce il vecchio ed il nuovo valore di uno specifico attributo di un model
     *
     * @param Model $model
     * @param string $attributeName
     * @return array
     */
    protected function getAttributeVersions(Model $model, string $attributeName) : array {

        $old = $model->getOriginal()[$attributeName] ?? null;
        $new = $model->getAttributes()[$attributeName] ?? null;

        $casts = $model->getCasts();
        $current = $casts[$attributeName] ?? null;

        // se hanno il cast in array o json, effettua un controllo profondo dei contenuti
        if ($current === 'array' || $current === 'json') {
            return arrayVersionCompare((array) json_decode($old, true), (array) json_decode($new, true));
        }

        // se sono datetime o date, trasformali in Carbon
        if ($current === 'datetime' || $current === 'date') {
            return [
                'oldValue' => $old ? ($old instanceof Carbon ? $old : new Carbon($old)) : null,
                'newValue' => $new ? ($new instanceof Carbon ? $new : new Carbon($new)) : null,
            ];
        }

        // ...TODO: valutare anche cast di altri tipi

        return [
            'oldValue' => $old,
            'newValue' => $new,
        ];

    }


}
