<?php

namespace AmcLab\Storyteller\Traits;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Storyteller;
use Illuminate\Contracts\Auth\Authenticatable;

trait Tellable {

    // protected static function boot() {
    //     if ($bubblesTo = static::$bubblesTo ?? []) {
    //         // foreach ($bubblesTo as $bubble) {
    //         //     app('storyteller')->aboutRelated($bubble);
    //         // }
    //         // static::fire???
    //         // dump(get_class_methods(self::class));
    //     }
    // }

    public function history() : iterable {
        return app('storyteller')->getByModel($this, app('environment')->pathway()['linkableResourceId']);
    }

    public function actions() : iterable {
        return ($this instanceof Authenticatable) ? app('storyteller')->getByAuth($this, app('environment')->pathway()['linkableResourceId']) : [];
    }

    public function createdBy() {
    }

    public function updatedBy() {
    }

    public function deletedBy() {
    }

    public function restoredBy() {
    }

    public function getBubbles() {
        return $this->bubbles ?? [];
    }



}
