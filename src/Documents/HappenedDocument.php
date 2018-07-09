<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Documents\EntityDocument;
use AmcLab\Storyteller\Documents\EventDocument;
use Carbon\Carbon;

class HappenedDocument extends AbstractDocument implements Document {

    protected $event;
    protected $affectedEntity;

    //$document = new HappenedDocument($event, $currentEntity, $responsibility);
    public function __construct(EventDocument $event, EntityDocument $affectedEntity, ResponsibilityDocument $responsibility) {
        parent::__construct();
        $this->event = $event;
        $this->affectedEntity = $affectedEntity;
    }

    public function export() {
        return [
            'documentType' => class_basename($this),
            'event' => $this->event->export(),
            'datetime' => $this->datetime,
            'affectedEntity' => $this->affectedEntity->export(),
        ];
    }

}
