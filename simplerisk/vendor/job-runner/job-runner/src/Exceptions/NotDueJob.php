<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Exceptions;

use JobRunner\JobRunner\Job\Job;
use RuntimeException;

use function sprintf;

class NotDueJob extends RuntimeException
{
    public static function fromJob(Job $job): self
    {
        return new self(sprintf('job "%s" is not due', $job->getName()));
    }
}
