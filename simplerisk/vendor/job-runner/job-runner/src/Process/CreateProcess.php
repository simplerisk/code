<?php

declare(strict_types=1);

namespace JobRunner\JobRunner\Process;

use Cron\CronExpression;
use JobRunner\JobRunner\Event\JobEventRunner;
use JobRunner\JobRunner\Exceptions\LockedJob;
use JobRunner\JobRunner\Exceptions\NotDueJob;
use JobRunner\JobRunner\Job\Job;
use JobRunner\JobRunner\Job\JobList;
use JobRunner\JobRunner\Process\Dto\ProcessAndLock;
use JobRunner\JobRunner\Process\Dto\ProcessAndLockList;
use Symfony\Component\Lock\LockFactory;

/** @internal */
class CreateProcess
{
    public function __construct(
        private readonly LockFactory $lock,
        private readonly JobEventRunner $jobEventRunner,
    ) {
    }

    public function __invoke(JobList $jobs): ProcessAndLockList
    {
        $jobsToRun = new ProcessAndLockList();

        foreach ($jobs->getList() as $job) {
            try {
                $jobsToRun->push($this->startJob($job));
                $this->jobEventRunner->start($job);
            } catch (LockedJob) {
                $this->jobEventRunner->isLocked($job);
            } catch (NotDueJob) {
                $this->jobEventRunner->notDue($job);
            }
        }

        return $jobsToRun;
    }

    private function startJob(Job $job): ProcessAndLock
    {
        if (! (new CronExpression($job->getCronExpression()))->isDue()) {
            throw NotDueJob::fromJob($job);
        }

        $lock = $this->lock->createLock($job->getName(), $job->getTtl(), $job->isAutoRelease());

        if (! $lock->acquire(false)) {
            throw LockedJob::fromJob($job);
        }

        $process = $job->getProcess();
        $process->start();

        return new ProcessAndLock($lock, $process, $job);
    }
}
