<?php

namespace Leaf\Crash;

/**
 * The seam for occurrence memory: "seen 12 times since Tuesday".
 *
 * Counting occurrences needs persistence, which is a job for a real
 * store — Alchemy Cloud, redis, a database. The error page itself
 * never writes anywhere; the Hub asks a store (when one is attached)
 * and stamps the count onto the report. Store failures are swallowed:
 * error handling must work when the store doesn't.
 */
interface OccurrenceStore
{
    /**
     * Record this report's occurrence and return the total number of
     * times its fingerprint has been seen, including this one.
     */
    public function record(Report $report): int;
}
