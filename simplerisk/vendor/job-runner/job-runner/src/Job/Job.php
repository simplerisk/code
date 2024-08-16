<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Job;

use Symfony\Component\Process\Process;

interface Job
{
    public function getProcess(): Process;

    public function getCronExpression(): string;

    public function getName(): string;

    public function getTtl(): int;

    public function isAutoRelease(): bool;

    /** @param array{command : string, cronExpression : string, name? : string, ttl? : int, autoRelease? : bool} $job */
    public static function fromArray(array $job): self;
}
