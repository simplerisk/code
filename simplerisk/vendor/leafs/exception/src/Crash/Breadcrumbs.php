<?php

namespace Leaf\Crash;

/**
 * The user journey that led to a crash.
 *
 * A ring buffer of typed events — requests, queries, log lines, custom
 * markers — recorded as the app runs. When a Report is built, the trail
 * is attached so every renderer and reporter can show not just where
 * the app broke, but what the user was doing on the way there.
 */
class Breadcrumbs
{
    public const TYPE_LOG = 'log';
    public const TYPE_QUERY = 'query';
    public const TYPE_REQUEST = 'request';
    public const TYPE_NAVIGATION = 'navigation';
    public const TYPE_ACTION = 'action';
    public const TYPE_HTTP = 'http';
    public const TYPE_CACHE = 'cache';
    public const TYPE_VIEW = 'view';
    public const TYPE_JOB = 'job';

    /** @var array<int, array<string, mixed>> */
    protected array $trail = [];

    protected int $limit;

    public function __construct(int $limit = 30)
    {
        $this->limit = $limit;
    }

    /**
     * Record a step in the journey.
     *
     * @param string $message What happened, eg "SELECT * FROM orders" or "clicked checkout"
     * @param string $type One of the TYPE_* constants (or your own)
     * @param array<string, mixed> $meta Extra structured data for this step
     */
    public function add(string $message, string $type = self::TYPE_ACTION, array $meta = []): self
    {
        $this->trail[] = [
            'type' => $type,
            'message' => $message,
            'meta' => $meta === [] ? [] : Peek::export($meta),
            'at' => date('c'),
            'origin' => $this->origin(),
        ];

        if (count($this->trail) > $this->limit) {
            array_shift($this->trail);
        }

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function trail(): array
    {
        return $this->trail;
    }

    public function clear(): self
    {
        $this->trail = [];

        return $this;
    }

    /**
     * Where in the app this crumb was recorded — the first backtrace
     * frame outside this module. Lets renderers jump from a journey
     * step straight to the code that logged it.
     *
     * @return array{file: string, line: int}|null
     */
    protected function origin(): ?array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? null;

            if ($file && strpos($file, __DIR__) !== 0) {
                $enclosing = $trace[$index + 1] ?? null;
                $callable = $enclosing
                    ? (isset($enclosing['class']) ? $enclosing['class'] . '::' : '') . $enclosing['function']
                    : null;

                return [
                    'file' => $file,
                    'line' => $frame['line'] ?? 0,
                    'callable' => $callable,
                ];
            }
        }

        return null;
    }
}
