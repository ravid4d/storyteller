<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Documents\EntityDocument;

class EventDocument extends AbstractDocument implements Document {

    protected $event;

    public function __construct($event) {
        parent::__construct();
        $this->event = $event;
    }

    public function export() {
        return [
            'name' => is_object($this->event) ? class_basename($this->event) : $this->event,
            'payload' => is_object($this->event) ? json_decode(json_encode($this->event), true) : null,
        ];
    }

}
