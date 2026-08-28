<?php

namespace Leaf\Crash;

/**
 * The runtime entry point for crash capture.
 *
 * The hub owns the journey (breadcrumbs), the context that gets stamped
 * onto every report (user, app, request), and the reporters that
 * reports are delivered to. Both automatic exception handling and
 * manual checkpoints go through capture(), so everything downstream
 * sees one kind of thing: a Report.
 */
class Hub
{
    protected Breadcrumbs $breadcrumbs;
    protected Dispatcher $dispatcher;
    protected Timing $timing;
    protected ?OccurrenceStore $occurrences = null;

    /** @var array<string, mixed> */
    protected array $context = [
        'appRoot' => null,
        'request' => [],
        'app' => [],
        'user' => [],
        'redact' => [],
    ];

    public function __construct()
    {
        $this->breadcrumbs = new Breadcrumbs();
        $this->dispatcher = new Dispatcher();
        $this->timing = new Timing();
    }

    /**
     * Record a step of the user journey.
     *
     * @param array<string, mixed> $meta
     */
    public function leaveCrumb(string $message, string $type = Breadcrumbs::TYPE_ACTION, array $meta = [], bool $withOrigin = true): self
    {
        $this->breadcrumbs->add($message, $type, $meta, $withOrigin);

        return $this;
    }

    /**
     * Set context stamped onto every report: appRoot, request, app,
     * user, redact. Arrays merge; scalars replace.
     *
     * @param array<string, mixed> $context
     */
    public function context(array $context): self
    {
        foreach ($context as $key => $value) {
            $this->context[$key] = is_array($value) && is_array($this->context[$key] ?? null)
                ? array_merge($this->context[$key], $value)
                : $value;
        }

        return $this;
    }

    /**
     * Attach an occurrence store so reports carry "seen N times" counts.
     * A failing store is ignored: error handling works without it.
     */
    public function countWith(OccurrenceStore $store): self
    {
        $this->occurrences = $store;

        return $this;
    }

    /**
     * Time a span of work. Spans ride on any report captured later:
     * $hub->span('db', fn () => $query->run());
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function span(string $name, callable $callback)
    {
        return $this->timing->measure($name, $callback);
    }

    /**
     * Register a reporter (log file, Alchemy Cloud, webhook, ...)
     */
    public function reportTo(Reporter $reporter): self
    {
        $this->dispatcher->reporter($reporter);

        return $this;
    }

    /**
     * Capture a crash or a manual checkpoint.
     *
     * Pass a Throwable for a real crash, or a string to snapshot a flow
     * that isn't behaving without anything being thrown. The report is
     * built immediately (so context is accurate) but delivered to
     * reporters after the response — capturing never blocks the app.
     *
     * @param \Throwable|string $subject
     * @param array<string, mixed> $options Extra per-capture options, merged over hub context
     */
    public function capture($subject, array $options = []): Report
    {
        $options = array_merge($this->context, $options, [
            'breadcrumbs' => $this->breadcrumbs->trail(),
            'timings' => $this->timing->spans(),
        ]);

        // cheap runtime facts, gathered only when a report is being built
        $options['app'] = array_merge([
            'php' => PHP_VERSION,
            'memory' => round(memory_get_peak_usage(true) / 1048576, 1) . 'MB',
        ], $options['app'] ?? []);

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $options['app']['elapsed'] = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . 'ms';
        }

        $report = $subject instanceof \Throwable
            ? Report::from($subject, $options)
            : Report::message($subject, $options);

        if ($this->occurrences) {
            try {
                $report->occurrences = $this->occurrences->record($report);
            } catch (\Throwable $th) {
                // a broken store must never break error handling
            }
        }

        $this->dispatcher->dispatch($report, $options['immediately'] ?? false);

        return $report;
    }

    public function breadcrumbs(): Breadcrumbs
    {
        return $this->breadcrumbs;
    }

    public function dispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    public function timing(): Timing
    {
        return $this->timing;
    }
}
