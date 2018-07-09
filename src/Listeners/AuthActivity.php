<?php

namespace AmcLab\Storyteller\Listeners;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Storyteller;
use AmcLab\Storyteller\Documents\AuthDocument;
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

class AuthActivity
{
    use DispatchesJobs, InteractsWithQueue;

    protected $storyteller;
    protected $environment;

    public function __construct(Storyteller $storyteller, Environment $environment)
    {
        $this->storyteller = $storyteller;
        $this->environment = $environment;
    }

    public function failed($event, \Exception $e) {
        // TODO: allertare il mondo... qui non dovrebbe MAI fallire!
        dump($e);
    }

    public function handle($event)
    {
        // costruisco l'identificatore dell'entità (utente) attualmente coinvolta
        $currentUser = new EntityDocument($event->user);

        $eventDocument = new EventDocument($event);

        // richiedo l'inoltro asincrono del Document
        $document = new AuthDocument($eventDocument, $currentUser);
        $job = new WriteLog($this->environment->getSpecs(), $document->export());
        $this->dispatch($job->onQueue('storyteller'));
    }
}
