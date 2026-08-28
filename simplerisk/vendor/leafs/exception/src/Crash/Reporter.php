<?php

namespace Leaf\Crash;

/**
 * Something that takes a crash report somewhere: a log file, Alchemy
 * Cloud, a webhook, a Slack channel.
 *
 * Reporters are fire-and-forget — they are dispatched after the
 * response, must never throw into the application, and must never
 * assume an HTTP context.
 */
interface Reporter
{
    public function report(Report $report): void;
}
