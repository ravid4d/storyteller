<?php

namespace AmcLab\Storyteller\Abstracts;

use Carbon\Carbon;

abstract class AbstractDocument {

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
