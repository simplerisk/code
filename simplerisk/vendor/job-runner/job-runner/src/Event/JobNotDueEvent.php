<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Event;

use JobRunner\JobRunner\Job\Job;

interface JobNotDueEvent extends JobEvent
{
    public function notDue(Job $job): void;
}
