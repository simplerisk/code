<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Event;

use JobRunner\JobRunner\Job\Job;

interface JobStartEvent extends JobEvent
{
    public function start(Job $job): void;
}
