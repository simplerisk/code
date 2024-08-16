<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Process\Dto;

use JobRunner\JobRunner\Exceptions\DuplicateJob;
use JobRunner\JobRunner\Exceptions\UnknownProcess;

use function array_key_exists;
use function array_values;
use function count;

/** @internal */
class ProcessAndLockList
{
    /** @var array<string, ProcessAndLock> */
    private array $jobs;

    public function __construct()
    {
        $this->jobs = [];
    }

    public function push(ProcessAndLock $process): void
    {
        if (array_key_exists($process->getJob()->getName(), $this->jobs)) {
            throw DuplicateJob::fromJob($process->getJob());
        }

        $this->jobs[$process->getJob()->getName()] = $process;
    }

    /** @return array<array-key, ProcessAndLock> */
    public function getList(): array
    {
        return array_values($this->jobs);
    }

    public function count(): int
    {
        return count($this->jobs);
    }

    public function remove(ProcessAndLock $process): void
    {
        if (array_key_exists($process->getJob()->getName(), $this->jobs)) {
            unset($this->jobs[$process->getJob()->getName()]);

            return;
        }

        throw UnknownProcess::fromProcess($process);
    }
}
