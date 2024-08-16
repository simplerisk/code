<?php

declare(strict_types=1);

namespace JobRunner\JobRunner;

use JobRunner\JobRunner\Event\JobEvent;
use JobRunner\JobRunner\Job\JobList;
use Symfony\Component\Lock\PersistingStoreInterface;

interface JobRunner
{
    public static function create(): self;

    public function withPersistingStore(PersistingStoreInterface $persistingStore): self;

    public function withEventListener(JobEvent $jobEvent): self;

    public function run(JobList $jobs): void;
}
