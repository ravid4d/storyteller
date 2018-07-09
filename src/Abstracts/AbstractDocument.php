<?php

namespace AmcLab\Storyteller\Abstracts;

use AmcLab\Storyteller\Contracts\Document;
use Carbon\Carbon;

abstract class AbstractDocument implements Document {

    protected $datetime;

    public function __construct() {
        $this->datetime = Carbon::now();
    }

    public function at(Carbon $datetime) {
        $this->datetime = $datetime;
        return $this;
    }

    abstract public function export();

}
