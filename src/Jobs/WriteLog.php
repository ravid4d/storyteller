<?php

namespace AmcLab\Storyteller\Jobs;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Contracts\Storyteller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class WriteLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $exportedDocument;
    protected $environmentSpecs;

    public function __construct(array $environmentSpecs, $exportedDocument) {
        // TODO: interfaccia event comuni che posso loggare!!!
        $this->exportedDocument = $exportedDocument;
        $this->environmentSpecs = $environmentSpecs;
    }

    public function handle(Storyteller $storyteller, Environment $environment) {

        try {
            $environment->unsetIdentity()->setWithSpecs($this->environmentSpecs);
            $storyteller->immediateLog($this->exportedDocument);
        }
        catch(\Exception $e) {
            $environment->unsetIdentity();
            throw $e;
        }
        $environment->unsetIdentity();
    }

    public function failed(\Exception $e) {
        // TODO: notificare IL MONDO da qui!!!
        dump($e);
    }

}
