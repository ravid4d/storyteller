<?php

namespace AmcLab\Storyteller\Traits;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Storyteller;
use AmcLab\Storyteller\Events\Happening;
use AmcLab\Storyteller\Observers\StorytellerObserver;
use Illuminate\Contracts\Auth\Authenticatable;

trait TellableTrait {

    public static function bootTellableTrait() {
        static::observe(StorytellerObserver::class);
    }

    public function getRecordHistory() : iterable {
        return app('storyteller')->getByModel($this, app('environment')->pathway('linkableResourceId'));
    }

    public function getUserHistory() : iterable {
        return !$this instanceof Authenticatable ? []
        : app('storyteller')->getByAuth($this, app('environment')->pathway('linkableResourceId'));
    }

    // public function createdBy() {
    // }

    // public function updatedBy() {
    // }

    // public function deletedBy() {
    // }

    // public function restoredBy() {
    // }

    public function getBubbles() {
        return $this->bubbles ?? [];
    }

    public function getExcludeFromLog() {
        return $this->excludeFromLog ?? [];
    }



}
