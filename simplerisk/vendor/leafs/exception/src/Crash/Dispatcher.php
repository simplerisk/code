<?php

namespace Leaf\Crash;

/**
 * Delivers reports to reporters without blocking the app.
 *
 * Reports are buffered and flushed after the response is sent (via a
 * shutdown hook), so a slow log disk or a remote reporting endpoint can
 * never delay a user's request. A reporter that throws is isolated —
 * one broken reporter never takes down error handling or the other
 * reporters.
 */
class Dispatcher
{
    /** @var Reporter[] */
    protected array $reporters = [];

    /** @var Report[] */
    protected array $buffer = [];

    /** @var array<int, array{reporter: class-string, error: string}> */
    protected array $failures = [];

    protected bool $shutdownRegistered = false;

    public function reporter(Reporter $reporter): self
    {
        $this->reporters[] = $reporter;

        return $this;
    }

    /**
     * Queue a report for delivery after the response.
     * Pass $immediately = true to deliver right now instead.
     */
    public function dispatch(Report $report, bool $immediately = false): self
    {
        if ($immediately) {
            $this->deliver($report);

            return $this;
        }

        $this->buffer[] = $report;

        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;

            register_shutdown_function(function () {
                // hand the response to the client first when possible so
                // reporting time is never user-facing time
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }

                $this->flush();
            });
        }

        return $this;
    }

    /**
     * Deliver everything in the buffer now.
     */
    public function flush(): self
    {
        $queued = $this->buffer;
        $this->buffer = [];

        foreach ($queued as $report) {
            $this->deliver($report);
        }

        return $this;
    }

    /**
     * Delivery problems from reporters that threw — for diagnostics,
     * never rethrown into the app.
     *
     * @return array<int, array{reporter: class-string, error: string}>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    protected function deliver(Report $report): void
    {
        foreach ($this->reporters as $reporter) {
            try {
                $reporter->report($report);
            } catch (\Throwable $th) {
                $this->failures[] = [
                    'reporter' => get_class($reporter),
                    'error' => $th->getMessage(),
                ];
            }
        }
    }
}
