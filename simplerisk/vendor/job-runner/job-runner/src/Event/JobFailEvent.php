<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Event;

use JobRunner\JobRunner\Job\Job;

interface JobFailEvent extends JobEvent
{
    public function fail(Job $job, string $output): void;
}
