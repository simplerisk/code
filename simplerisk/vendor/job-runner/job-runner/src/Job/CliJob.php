<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Job;

use Symfony\Component\Process\Process;

use function array_key_exists;

class CliJob implements Job
{
    private const int TTL_DEFAULT_VALUE           = 300;
    private const true AUTO_RELEASE_DEFAULT_VALUE = true;

    public function __construct(
        private readonly string $command,
        private readonly string $cronExpression,
        private readonly string $name,
        private readonly int $ttl = self::TTL_DEFAULT_VALUE,
        private readonly bool $autoRelease = self::AUTO_RELEASE_DEFAULT_VALUE,
    ) {
    }

    public function getProcess(): Process
    {
        return Process::fromShellCommandline($this->command);
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function isAutoRelease(): bool
    {
        return $this->autoRelease;
    }

    /** @inheritDoc */
    public static function fromArray(array $job): self
    {
        $name        = array_key_exists('name', $job) ? $job['name'] : $job['command'];
        $ttl         = array_key_exists('ttl', $job) ? $job['ttl'] : self::TTL_DEFAULT_VALUE;
        $autoRelease = array_key_exists('autoRelease', $job) ? $job['autoRelease'] : self::AUTO_RELEASE_DEFAULT_VALUE;

        return new self($job['command'], $job['cronExpression'], $name, $ttl, $autoRelease);
    }
}
