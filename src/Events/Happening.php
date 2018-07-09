<?php

namespace AmcLab\Storyteller\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Happening
{

    /*
        IMPORTANT: qui non uso "SerializesModels" perché così viene serializzata l'intera istanza
        del Model, e non un suo riferimento (che poi verrebbe ripreso dal db attraverso il
        listener::handler).
        # Questo permette di cristallizzare l'istanza realmente coinvolta nell'operazione loggata!
    */

    public $model;
    public $name;
    public $environmentSpecs;
    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $model, string $name, array $environmentSpecs, ?Authenticatable $user)
    {
        $this->model = $model;
        $this->name = $name;
        $this->environmentSpecs = $environmentSpecs;
        $this->user = $user;
    }

}
