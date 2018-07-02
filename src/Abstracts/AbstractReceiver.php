<?php

namespace AmcLab\Storyteller\Abstracts;

use AmcLab\Storyteller\Contracts\Receivers\ReceiverInterface;
use Illuminate\Contracts\Config\Repository;

abstract class AbstractReceiver implements ReceiverInterface {

    protected $config;

    public function __construct(Repository $configRepository) {
        $this->config = $configRepository->get('storyteller.receivers.'.$this->name.'.parameters');
    }

    abstract public function push($pushed, $destination);

}
