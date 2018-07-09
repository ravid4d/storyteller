<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;

class RelatedChangeDocument extends AbstractDocument implements Document {

    protected $eventName;
    protected $eventOriginEntity;
    protected $responsibility;
    protected $changes;

    public function __construct(string $eventName, EntityDocument $affectedEntity, EntityDocument $eventOriginEntity, ResponsibilityDocument $responsibility) {
        parent::__construct();
        $this->eventName = $eventName;
        $this->affectedEntity = $affectedEntity;
        $this->eventOriginEntity = $eventOriginEntity;
        $this->responsibility = $responsibility;
    }

    public function export() {
        return [
            'event' => $this->eventName,
            'datetime' => $this->datetime,
            'affectedEntity' => $this->affectedEntity->export(),
            'responsibility' => $this->responsibility->export(),
            'eventOriginEntity' => $this->eventOriginEntity->export(),
        ];
    }

}
