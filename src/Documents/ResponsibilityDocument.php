<?php

namespace AmcLab\Storyteller\Documents;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Abstracts\AbstractDocument;
use AmcLab\Storyteller\Contracts\Document;
use Illuminate\Contracts\Auth\Authenticatable;

class ResponsibilityDocument extends AbstractDocument implements Document {

    protected $user;
    protected $environment;

    function __construct(?Authenticatable $user, Environment $environment) {
        parent::__construct();
        $this->user = $user;
        $this->environment = $environment;
    }

    function export() {
        return [
            'userId' => $this->user->id ?? null,
            'scope' => [
                'name' => get_class($this->environment->getScope()),
                'data' => $this->environment->getScope()->getData(),
            ],
        ];
    }
}
