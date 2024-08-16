<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Event;

use JobRunner\JobRunner\Job\Job;

use function array_filter;

class JobEventRunner implements JobStartEvent, JobSuccessEvent, JobFailEvent, JobNotDueEvent, JobIsLockedEvent
{
    /** @var array<array-key, JobEvent> */
    private readonly array $jobEvent;

    public function __construct(JobEvent ...$jobEvent)
    {
        $this->jobEvent = $jobEvent;
    }

    public function start(Job $job): void
    {
        $this->apply(JobStartEvent::class, static function (JobStartEvent $event) use ($job): void {
            $event->start($job);
        });
    }

    public function fail(Job $job, string $output): void
    {
        $this->apply(JobFailEvent::class, static function (JobFailEvent $event) use ($job, $output): void {
            $event->fail($job, $output);
        });
    }

    public function success(Job $job, string $output): void
    {
        $this->apply(JobSuccessEvent::class, static function (JobSuccessEvent $event) use ($job, $output): void {
            $event->success($job, $output);
        });
    }

    public function notDue(Job $job): void
    {
        $this->apply(JobNotDueEvent::class, static function (JobNotDueEvent $event) use ($job): void {
            $event->notDue($job);
        });
    }

    public function isLocked(Job $job): void
    {
        $this->apply(JobIsLockedEvent::class, static function (JobIsLockedEvent $event) use ($job): void {
            $event->isLocked($job);
        });
    }

    /** @param class-string $className */
    private function apply(string $className, callable $callable): void
    {
        foreach (array_filter($this->jobEvent, static fn (JobEvent $event) => $event instanceof $className) as $event) {
            $callable($event);
        }
    }
}
