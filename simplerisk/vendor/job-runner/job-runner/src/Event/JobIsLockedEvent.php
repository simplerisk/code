<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Event;

use JobRunner\JobRunner\Job\Job;

interface JobIsLockedEvent extends JobEvent
{
    public function isLocked(Job $job): void;
}
