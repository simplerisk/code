<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use Leaf\Crash\Report;

/**
 * Renders an uncaught exception raised inside a Leaf route as SimpleRisk's JSON
 * error envelope, so an API caller gets a machine-readable 500 rather than
 * Leaf's HTML crash page.
 *
 * Leaf v5 replaced the whoops-derived error engine with Leaf Crash. Where v4
 * pushed a Handler subclass onto a stack and called handle() on it, v5 stores a
 * single callable and invokes it as $handler($report) with one Leaf\Crash\Report
 * — hence __invoke() rather than an inherited handle(). Leaf\Exception\Handler\
 * Handler and Leaf\Exceptions\Formatter no longer exist.
 *
 * One ordering constraint the call sites have to honor: App::setErrorHandler()
 * is a silent no-op while `debug` is true, so every entry point must call
 * app()->config('debug', false) BEFORE installing this handler. Otherwise the
 * API answers exceptions with Leaf's crash page — stack frames, request context
 * and all — instead of the envelope below. ApiExceptionHandlerTest pins that.
 *
 * The two rendering decisions live in static helpers so they can be tested
 * without a log destination or a live HTTP response.
 */
class SimpleriskApiExceptionHandler
{

    protected $handler = null;

    /**
     * Constructor.
     *
     * @param callable|null $handler Optional replacement renderer. Under Leaf v5
     *                               it receives a Leaf\Crash\Report, not a Throwable.
     *
     * @return void
     */
    public function __construct($handler = null)
    {
        $this->handler = is_callable($handler) ? $handler : function (Report $report) {
            write_debug_log(self::log_message($report), 'error');

            response()->json(self::response_body($report), 500, false);
        };
    }

    /**
     * The function that's run by Leaf's exception handling logic.
     *
     * @param Report $report The crash report Leaf built from the throwable
     *
     * @return void
     */
    public function __invoke(Report $report)
    {
        call_user_func($this->handler, $report);
    }

    /**
     * Build the line written to the debug log. Replaces v4's
     * Formatter::formatExceptionPlain() and, like it, carries the exception,
     * its message and the stack — and nothing else.
     *
     * Deliberately not $report->toJson() or ->toArray(): those carry the
     * request context, which includes the caller's headers and body.
     *
     * @param Report $report
     *
     * @return string
     */
    public static function log_message(Report $report): string
    {
        return rtrim($report->toText(), "\n");
    }

    /**
     * Build the JSON body returned to the caller.
     *
     * @param Report $report
     *
     * @return array
     */
    public static function response_body(Report $report): array
    {
        global $escaper;

        // Although in certain cases this message gets escaped again,
        // I'd prefer to have a message that's double-escaped than one that gets through without escaping
        return create_json_response_array(500, $escaper->escapeHtml($report->message));
    }
}

?>
