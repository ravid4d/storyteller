<?php

namespace AmcLab\Storyteller\Jobs;

use AmcLab\Storyteller\Contracts\Storyteller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StorytellerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $when;
    protected $environment;
    protected $user;
    protected $payload;

    public function __construct(array $payload, $environment = null, $user = null)
    {
        $this->when = \Carbon\Carbon::now();
        $this->environment = $environment ?? app('environment');
        $this->user = $user ?? app('auth')->user();
        $this->payload = $payload;
    }

    public function __destruct() {
        //
    }

    public function handle(Storyteller $storyteller)
    {
        $storyteller->about($this->payload, $this->when, $this->environment, $this->user);
    }


}
