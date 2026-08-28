<?php

namespace Leaf\Crash;

use Leaf\Crash\Renderer\HtmlRenderer;

/**
 * The global error/exception handler — the successor to the whoops
 * based Leaf\Exception\Run.
 *
 * Registers PHP's exception, error and shutdown hooks, captures every
 * failure through the Hub (so reports carry journey + context), and
 * renders through the crash page in debug mode or a minimal page in
 * production. Reporting happens either way: production hides details
 * from users, never from you.
 */
class Handler
{
    protected Hub $hub;
    protected bool $debug;
    protected bool $registered = false;

    /** @var array<string, mixed> */
    protected array $options = [];

    /** @var callable|null Custom renderer: fn (Report, bool): string */
    protected $renderer = null;

    /**
     * @param array{
     *     debug?: bool,
     *     editor?: string,
     *     projectContext?: string|null,
     * } $options
     */
    public function __construct(?Hub $hub = null, array $options = [])
    {
        $this->hub = $hub ?? new Hub();
        $this->debug = (bool) ($options['debug'] ?? true);
        $this->options = $options;
    }

    public function hub(): Hub
    {
        return $this->hub;
    }

    /**
     * Flip debug rendering after construction — frameworks register the
     * handler before their config loads, then set this once it has.
     */
    public function debug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Replace how reports are turned into output, eg for JSON APIs.
     * The callable receives the Report and the debug flag.
     */
    public function renderWith(callable $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function register(): self
    {
        if ($this->registered) {
            return $this;
        }

        $this->registered = true;

        set_exception_handler([$this, 'handle']);

        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () {
            $error = error_get_last();

            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->handle(new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });

        return $this;
    }

    public function unregister(): self
    {
        if ($this->registered) {
            restore_exception_handler();
            restore_error_handler();
            $this->registered = false;
        }

        return $this;
    }

    /**
     * Capture and render a throwable. Public so frameworks can route
     * their own caught exceptions through the same pipeline.
     */
    public function handle(\Throwable $throwable): void
    {
        $report = $this->hub->capture($throwable);

        if (!headers_sent()) {
            http_response_code(500);
        }

        echo $this->render($report);
    }

    public function render(Report $report): string
    {
        if ($this->renderer) {
            return ($this->renderer)($report, $this->debug);
        }

        if (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg') {
            return $report->toText() . "\n";
        }

        if ($this->debug) {
            return (new HtmlRenderer())->render($report, $this->options);
        }

        return static::productionPage();
    }

    /**
     * The production error page: clean, branded, and deliberately
     * empty of internals. The report still went to your reporters.
     */
    public static function productionPage(): string
    {
        ob_start();
        include __DIR__ . '/Renderer/views/production.html.php';

        return ob_get_clean();
    }
}
