<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Documents\EntityDocument;
use AmcLab\Storyteller\Documents\EventDocument;
use AmcLab\Storyteller\Documents\ResponsibilityDocument;
use Carbon\Carbon;

class EloquentDocument extends AbstractDocument implements Document {

    protected $event;
    protected $affectedEntity;
    protected $responsibility;
    protected $changes;

    public function __construct(EventDocument $event, EntityDocument $affectedEntity, ResponsibilityDocument $responsibility, array $changes) {
        parent::__construct();
        $this->event = $event;
        $this->affectedEntity = $affectedEntity;
        $this->responsibility = $responsibility;
        $this->changes = $changes;
    }

    public function export() {
        return [
            'documentType' => class_basename($this),
            'event' => $this->event->export(),
            'datetime' => $this->datetime,
            'affectedEntity' => $this->affectedEntity->export(),
            'responsibility' => $this->responsibility->export(),
        ] + (!$this->changes ? [] : [
            'changes' => $this->changes,
        ]);
    }

}
