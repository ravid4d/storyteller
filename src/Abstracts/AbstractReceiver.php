<?php

namespace AmcLab\Storyteller\Abstracts;

use AmcLab\Environment\Contracts\Environment;
use AmcLab\Storyteller\Contracts\Receiver;
use Illuminate\Contracts\Config\Repository;

abstract class AbstractReceiver implements Receiver {

    protected $config;
    protected $environment;

    public function __construct(Repository $configRepository, Environment $environment) {
        $this->config = $configRepository->get('storyteller.receivers.'.$this->name.'.parameters');
        $this->environment = $environment;
    }

    abstract public function push($pushed);

}
