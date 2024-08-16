<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Job;

use JobRunner\JobRunner\Exceptions\DuplicateJob;

use function array_key_exists;
use function array_map;
use function array_values;

class JobList
{
    /** @var array<string, Job> */
    private array $jobs;

    public function __construct(Job ...$jobs)
    {
        $this->jobs = [];

        foreach ($jobs as $job) {
            $this->push($job);
        }
    }

    public function push(Job $job): void
    {
        if (array_key_exists($job->getName(), $this->jobs)) {
            throw DuplicateJob::fromJob($job);
        }

        $this->jobs[$job->getName()] = $job;
    }

    /** @return array<array-key, Job> */
    public function getList(): array
    {
        return array_values($this->jobs);
    }

    /** @param array<array-key, array{command : string, cronExpression : string, name? : string, ttl? : int, autoRelease? : bool}> $jobs */
    public static function fromArray(array $jobs): self
    {
        return new self(...array_map(static fn (array $job) => CliJob::fromArray($job), $jobs));
    }
}
