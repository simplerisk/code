<?php

namespace Leaf\Crash;

/**
 * Lightweight span timing — the base for performance monitoring.
 *
 * Recording a span is two microtime() calls and an array write, so
 * always-on instrumentation costs effectively nothing. Spans ride
 * along on crash reports; a future Alchemy Cloud reporter can lift
 * the same data for standalone performance monitoring.
 */
class Timing
{
    /** @var array<string, float> Open spans keyed by name */
    protected array $open = [];

    /** @var array<int, array{name: string, ms: float}> */
    protected array $spans = [];

    public function start(string $name): self
    {
        $this->open[$name] = microtime(true);

        return $this;
    }

    public function stop(string $name): self
    {
        if (isset($this->open[$name])) {
            $this->spans[] = [
                'name' => $name,
                'ms' => round((microtime(true) - $this->open[$name]) * 1000, 2),
            ];

            unset($this->open[$name]);
        }

        return $this;
    }

    /**
     * Time a callable and return its result.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function measure(string $name, callable $callback)
    {
        $this->start($name);

        try {
            return $callback();
        } finally {
            $this->stop($name);
        }
    }

    /**
     * @return array<int, array{name: string, ms: float}>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    public function clear(): self
    {
        $this->open = [];
        $this->spans = [];

        return $this;
    }
}
