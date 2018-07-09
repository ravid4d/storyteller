<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Documents\EntityDocument;
use AmcLab\Storyteller\Documents\EventDocument;
use AmcLab\Storyteller\Documents\ResponsibilityDocument;
use Carbon\Carbon;

class AuthDocument extends AbstractDocument implements Document {

    protected $event;
    protected $affectedEntity;

    public function __construct(EventDocument $event, EntityDocument $affectedEntity) {
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
