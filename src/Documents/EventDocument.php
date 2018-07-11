<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use AmcLab\Storyteller\Documents\EntityDocument;

class EventDocument extends AbstractDocument implements Document {

    protected $event;

    // proprietà da escludere perché non più utili...
    protected $excluded = ['model', 'environmentSpecs', 'user'];

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

            // escludo le proprietà presenti in $this->excluded
            foreach (array_keys($payload) as $key) {
                if (in_array($key, $this->excluded)) {
                    unset($payload[$key]);
                }
            }
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
