<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use Illuminate\Database\Eloquent\Model;

class EntityDocument extends AbstractDocument implements Document {

    protected $model;
    protected $id;

    public function __construct($model, $id = null) {
        parent::__construct();
        $this->model = $model;
        $this->id = $id;
    }

    public function export() {
        return [
            'name' => $this->model instanceof Model ? get_class($this->model) : $this->model,
            'key' => $this->model instanceof Model ? $this->model->getKey() : $this->id,
        ];
    }
}
