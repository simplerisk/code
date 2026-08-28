<?php

namespace Leaf\Crash;

/**
 * The canonical crash report.
 *
 * One exception becomes one Report: a plain, serializable value object
 * carrying everything a renderer (dev page, JSON, CLI) or a reporter
 * (log file, Alchemy Cloud, webhook) could need. Renderers and reporters
 * only ever consume Reports — they never touch the raw Throwable.
 */
class Report
{
    public string $id;
    public string $occurredAt;

    public string $exception;
    public string $message;
    public int|string $code;

    /** error | warning | info | debug — manual captures default to info */
    public string $level = 'error';

    /**
     * Stable hash identifying "this kind of crash" across occurrences.
     * Built from the exception class and the application frame's file and
     * callable — not the line number, so editing unrelated code doesn't
     * split one issue into many.
     */
    public string $fingerprint;

    /** @var Frame[] */
    public array $frames = [];

    /** @var array<string, mixed> Request context (method, url, headers, body, ...) */
    public array $request = [];

    /** @var array<string, mixed> App context (leaf version, php version, environment, ...) */
    public array $app = [];

    /** @var array<string, mixed> The signed-in user, if any */
    public array $user = [];

    /** @var array<int, mixed> The last log entries before the crash */
    public array $breadcrumbs = [];

    /** @var array<string, mixed>|null The previous exception in the chain */
    public ?array $previous = null;

    /** @var array<int, array<string, mixed>> The full caused-by chain, nearest first */
    public array $chain = [];

    /** @var array<string, mixed> dump()-style variable peeks attached at capture */
    public array $peeks = [];

    /** @var array<int, array{name: string, ms: float}> Performance spans recorded before the crash */
    public array $timings = [];

    /** How many times this fingerprint has been seen, when an occurrence store is attached */
    public ?int $occurrences = null;

    /**
     * Build a report from a throwable.
     *
     * @param array{
     *     appRoot?: string,
     *     request?: array<string, mixed>,
     *     app?: array<string, mixed>,
     *     user?: array<string, mixed>,
     *     breadcrumbs?: array<int, mixed>,
     *     redact?: string[],
     * } $options
     */
    public static function from(\Throwable $throwable, array $options = []): self
    {
        $appRoot = $options['appRoot'] ?? getcwd();
        $redactor = new Redactor($options['redact'] ?? []);
        $inspector = new Inspector($throwable, $appRoot);

        $report = new self();
        $report->id = bin2hex(random_bytes(8));
        $report->occurredAt = date('c');
        $report->exception = get_class($throwable);
        $report->message = $throwable->getMessage();
        $report->code = $throwable->getCode();
        $report->level = $options['level'] ?? 'error';
        $report->frames = $inspector->frames();
        $report->fingerprint = static::fingerprint($throwable, $inspector, $appRoot);

        $report->request = $redactor->redact($options['request'] ?? []);
        $report->app = $redactor->redact($options['app'] ?? []);
        $report->user = $redactor->redact($options['user'] ?? []);
        $report->breadcrumbs = $redactor->redact($options['breadcrumbs'] ?? []);

        $report->chain = static::chain($throwable);
        $report->previous = $report->chain[0] ?? null;
        $report->peeks = static::exportPeeks($options['peeks'] ?? [], $redactor);
        $report->timings = $options['timings'] ?? [];

        return $report;
    }

    /**
     * Walk the caused-by chain (nearest cause first, capped at 5), each
     * entry carrying a code excerpt of its own throw site.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function chain(\Throwable $throwable): array
    {
        $chain = [];
        $current = $throwable->getPrevious();

        while ($current && count($chain) < 5) {
            $frame = new Frame($current->getFile(), $current->getLine());
            $frame->loadExcerpt();

            $chain[] = [
                'exception' => get_class($current),
                'message' => $current->getMessage(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
                'excerpt' => $frame->excerpt,
            ];

            $current = $current->getPrevious();
        }

        return $chain;
    }

    /**
     * @param array<string, mixed> $peeks
     * @return array<string, mixed>
     */
    protected static function exportPeeks(array $peeks, Redactor $redactor): array
    {
        $exported = [];

        foreach ($peeks as $name => $value) {
            $value = Peek::export($value);
            $exported[$name] = is_array($value) ? $redactor->redact($value) : $value;
        }

        return $exported;
    }

    /**
     * Build a report from a plain message — no exception required.
     *
     * This is the manual trigger: drop checkpoints into a flow that
     * "works" but returns the wrong thing, and every renderer/reporter
     * treats them exactly like crash reports.
     *
     * @param array<string, mixed> $options Same options as from(), plus level (defaults to info)
     */
    public static function message(string $message, array $options = []): self
    {
        $appRoot = $options['appRoot'] ?? getcwd();
        $redactor = new Redactor($options['redact'] ?? []);

        $report = new self();
        $report->id = bin2hex(random_bytes(8));
        $report->occurredAt = date('c');
        $report->exception = 'message';
        $report->message = $message;
        $report->code = 0;
        $report->level = $options['level'] ?? 'info';

        // the capture site gives manual reports a stack + fingerprint too
        $trigger = new \Exception($message);
        $inspector = new Inspector($trigger, $appRoot);
        $frames = $inspector->frames();

        // drop the frames inside this module so the trail starts at the caller
        while (isset($frames[0]) && $frames[0]->file && strpos($frames[0]->file, __DIR__) === 0) {
            array_shift($frames);
        }

        $report->frames = $frames;

        $frame = $report->applicationFrame();
        $file = $frame->file ?? '';

        if ($file && strpos($file, $appRoot . DIRECTORY_SEPARATOR) === 0) {
            $file = substr($file, strlen($appRoot) + 1);
        }

        $report->fingerprint = sha1(implode('|', ['message', $file, $message]));

        $report->request = $redactor->redact($options['request'] ?? []);
        $report->app = $redactor->redact($options['app'] ?? []);
        $report->user = $redactor->redact($options['user'] ?? []);
        $report->breadcrumbs = $redactor->redact($options['breadcrumbs'] ?? []);
        $report->peeks = static::exportPeeks($options['peeks'] ?? [], $redactor);
        $report->timings = $options['timings'] ?? [];

        return $report;
    }

    /**
     * The frame where your code broke — the first application frame,
     * falling back to the very first frame for pure-vendor crashes.
     */
    public function applicationFrame(): ?Frame
    {
        foreach ($this->frames as $frame) {
            if ($frame->isApplication) {
                return $frame;
            }
        }

        return $this->frames[0] ?? null;
    }

    protected static function fingerprint(\Throwable $throwable, Inspector $inspector, string $appRoot): string
    {
        $frame = $inspector->applicationFrame();

        $file = $frame->file ?? $throwable->getFile();

        // relative paths keep fingerprints stable across servers and deploys
        if (strpos($file, $appRoot . DIRECTORY_SEPARATOR) === 0) {
            $file = substr($file, strlen($appRoot) + 1);
        }

        return sha1(implode('|', [
            get_class($throwable),
            $file,
            $frame ? ($frame->callable() ?? '') : '',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Render the report as plain text — for consoles, logs, and anywhere
     * HTML has no business being.
     */
    public function toText(): string
    {
        $text = "{$this->exception}: {$this->message}\n";

        foreach ($this->frames as $index => $frame) {
            $location = $frame->file ? "{$frame->file}:{$frame->line}" : '[internal]';
            $callable = $frame->callable();
            $text .= sprintf("  #%d %s%s\n", $index, $location, $callable ? " — {$callable}()" : '');
        }

        foreach ($this->chain as $caused) {
            $text .= "Caused by {$caused['exception']}: {$caused['message']}\n";
        }

        return $text;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurredAt' => $this->occurredAt,
            'exception' => $this->exception,
            'message' => $this->message,
            'code' => $this->code,
            'level' => $this->level,
            'fingerprint' => $this->fingerprint,
            'frames' => array_map(fn (Frame $frame) => $frame->toArray(), $this->frames),
            'request' => $this->request,
            'app' => $this->app,
            'user' => $this->user,
            'breadcrumbs' => $this->breadcrumbs,
            'previous' => $this->previous,
            'chain' => $this->chain,
            'peeks' => $this->peeks,
            'timings' => $this->timings,
            'occurrences' => $this->occurrences,
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * Serialize the report as markdown — the same shape used by the
     * dev page's "copy for AI" button and (eventually) cloud reporting.
     */
    public function toMarkdown(): string
    {
        $lines = [
            "# {$this->exception}",
            '',
            "> {$this->message}",
            '',
            "- **When:** {$this->occurredAt}",
            "- **Fingerprint:** `{$this->fingerprint}`",
        ];

        if ($frame = $this->applicationFrame()) {
            $lines[] = "- **Where:** `{$frame->file}:{$frame->line}`" . ($frame->callable() ? " in `{$frame->callable()}`" : '');
        }

        if (!empty($this->request['method']) || !empty($this->request['url'])) {
            $lines[] = '- **Request:** ' . trim(($this->request['method'] ?? '') . ' ' . ($this->request['url'] ?? ''));
        }

        if ($frame && $frame->excerpt) {
            $lines[] = '';
            $lines[] = '```php';

            foreach ($frame->excerpt as $number => $code) {
                $marker = $number === $frame->line ? '>' : ' ';
                $lines[] = sprintf('%s %4d| %s', $marker, $number, $code);
            }

            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '## Stack trace';
        $lines[] = '';

        foreach ($this->frames as $index => $frame) {
            $location = $frame->file ? "{$frame->file}:{$frame->line}" : '[internal]';
            $callable = $frame->callable() ? " — `{$frame->callable()}`" : '';
            $badge = $frame->isApplication ? ' (app)' : '';
            $lines[] = "{$index}. `{$location}`{$callable}{$badge}";
        }

        foreach ($this->chain as $cause) {
            $lines[] = '';
            $lines[] = "## Caused by: {$cause['exception']}";
            $lines[] = '';
            $lines[] = "> {$cause['message']}";
            $lines[] = "`{$cause['file']}:{$cause['line']}`";
        }

        if ($this->peeks) {
            $lines[] = '';
            $lines[] = '## Peeked values';
            $lines[] = '';

            foreach ($this->peeks as $name => $value) {
                $lines[] = "- **{$name}:** `" . (is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)) . '`';
            }
        }

        if ($this->timings) {
            $lines[] = '';
            $lines[] = '## Timings';
            $lines[] = '';

            foreach ($this->timings as $span) {
                $lines[] = "- {$span['name']}: {$span['ms']}ms";
            }
        }

        if ($this->breadcrumbs) {
            $lines[] = '';
            $lines[] = '## Breadcrumbs';
            $lines[] = '';

            foreach ($this->breadcrumbs as $crumb) {
                if (is_array($crumb) && isset($crumb['message'])) {
                    $meta = !empty($crumb['meta']) ? ' — ' . json_encode($crumb['meta']) : '';
                    $lines[] = '- [' . ($crumb['type'] ?? 'action') . '] ' . $crumb['message'] . $meta;
                } else {
                    $lines[] = '- ' . (is_string($crumb) ? $crumb : json_encode($crumb));
                }
            }
        }

        return implode("\n", $lines);
    }
}
