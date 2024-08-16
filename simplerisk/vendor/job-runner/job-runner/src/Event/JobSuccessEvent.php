<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Event;

use JobRunner\JobRunner\Job\Job;

interface JobSuccessEvent extends JobEvent
{
    public function success(Job $job, string $output): void;
}
