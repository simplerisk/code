<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Exceptions;

use JobRunner\JobRunner\Process\Dto\ProcessAndLock;
use RuntimeException;

use function sprintf;

class UnknownProcess extends RuntimeException
{
    public static function fromProcess(ProcessAndLock $process): self
    {
        return new self(sprintf('process "%s" not found', $process->getJob()->getName()));
    }
}
