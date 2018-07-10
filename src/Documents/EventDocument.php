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
        if (is_array($this->event)) {
            [$name, $payload] = $this->event;
        }

        else if (is_object($this->event)) {
            $name = class_basename($this->event);
            $payload = json_decode(json_encode($this->event), true);
        }

        else {
            $name = $this->event;
            $payload = null;
        }

        return [
            'name' => $name,
            'payload' => $payload,
        ];
    }

}
