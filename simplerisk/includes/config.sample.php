<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Template for simplerisk/includes/config.php. The installer copies this
// file to config.php on first boot and substitutes every __PLACEHOLDER__
// with the value supplied in the installer form. SimpleRisk treats the
// existence of config.php as the install marker; deleting it re-triggers
// the installer.
//
// For a manual install, copy this file to config.php and replace each
// __PLACEHOLDER__ with the appropriate value before loading the
// application.

// --- Database ---
// Use 127.0.0.1 instead of "localhost" for DB_HOSTNAME so MySQL connects
// via TCP. On Linux, "localhost" forces a Unix socket connection that
// fails in Docker.
define('DB_HOSTNAME', '__DB_HOSTNAME__');
define('DB_PORT', '__DB_PORT__');
define('DB_USERNAME', '__DB_USERNAME__');
define('DB_PASSWORD', '__DB_PASSWORD__');
define('DB_DATABASE', '__DB_DATABASE__');

// To use SSL for database connections, uncomment the line below and set
// the path to the CA certificate file. Leaving this commented out is the
// safe default — it means SimpleRisk does not request an SSL connection,
// rather than requesting one with an invalid certificate path (which
// fails with a generic "Cannot connect to MySQL using SSL" error).
// The installer rewrites this entire line with an active define when a
// cert path is supplied at install time.
// define('DB_SSL_CERTIFICATE_PATH', '/path/to/ca-cert.pem');

// --- Database connection limits ---
// When the database is at its max_connections limit (MySQL error 1040), a
// request retries the connection this many more times, with a short
// exponential backoff starting at the delay below, before it gives up and
// returns a 503 with a Retry-After header. These are the defaults; 0 attempts
// turns the retry off:
// define('DB_CONNECT_RETRY_ATTEMPTS', 3);
// define('DB_CONNECT_RETRY_BASE_DELAY_MS', 250);
// Optional cap on how many web requests on THIS server may hold a database
// connection at once. 0 (the default) means no cap. A request that cannot get
// a slot within DB_CONNECTION_WAIT_SECONDS gets a 503 with a Retry-After
// header. The cap is per server and cannot see other servers: with several
// servers behind a load balancer on one database, give each server its share,
// (the database's max_connections minus the queue/cron worker connections,
// minus some headroom) divided by the number of servers. Keep the web
// server's own worker limit at or below the cap plus some headroom: waiting
// requests hold a web worker, so a flood can otherwise tie them all up. A load
// balancer health check that needs the database competes for the same slots.
// The queue and cron workers are not counted against it, and neither is the
// extra connection used for sessions when they are stored in the database, so
// leave headroom for both:
// define('DB_MAX_CONCURRENT_CONNECTIONS', 0);
// define('DB_CONNECTION_WAIT_SECONDS', 3);

// --- Sessions ---
// Accepts the strings 'true' or 'false' (not booleans).
define('USE_DATABASE_FOR_SESSIONS', '__USE_DATABASE_FOR_SESSIONS__');

// --- Outbound request allow-lists (SSRF) ---
// SimpleRisk makes server-side HTTP requests from two admin-configurable surfaces:
// the AI provider "Test Connection" / background calls, and the Workflows "HTTP
// Request" action. Both are fail-closed against Server-Side Request Forgery — by
// default they cannot reach internal/reserved addresses (loopback, RFC1918,
// 169.254.169.254 cloud metadata, link-local, ...). To reach a specific internal
// or custom destination, a *system* administrator must name it in one of the lists
// below — an explicit, auditable authorization on top of the in-app admin's
// configuration (two different people, in two different places). Each list accepts
// hostnames, bare IPs, and/or CIDR blocks; leave a list absent or empty to keep the
// fail-closed default.
//
// AI provider: hosts permitted IN ADDITION to the built-in providers
// (api.anthropic.com, api.openai.com, ...) and a loopback self-host
// (127.0.0.1 / ::1 / localhost, e.g. Ollama or LM Studio). Name a custom cloud
// endpoint or a LAN-hosted model here:
// $ai_allowed_provider_hosts = ['llm.corp.example.com', '192.168.50.10'];
//
// Workflows HTTP Request action: public URLs are always allowed; name any INTERNAL
// target the action is permitted to reach (e.g. an on-prem webhook / ticketing
// system). Cloud metadata (169.254.169.254) is reachable only if you list it
// explicitly — which you should not:
// $workflow_http_allowed_internal_targets = ['jira.corp.example.com', '10.0.0.0/8'];

// Asset discovery (Manage Assets > Discover assets) probes the addresses a user
// enters from this server. It is fail-closed: with this list absent or empty,
// no discovery run can start. Name the networks discovery may scan, as bare IPv4
// addresses and/or IPv4 CIDR blocks; a requested range must lie entirely inside
// them. Hostnames are accepted but never match an address (no DNS lookups).
// Loopback, link-local (including 169.254.169.254 cloud metadata), multicast and
// 0.0.0.0/8 stay refused even if listed here:
// $asset_discovery_allowed_ranges = ['10.20.0.0/16', '192.168.50.0/24', '10.1.2.3'];

// --- Background task / queue worker tuning (optional) ---
// The background workers (cron_queue_worker, cron_promise_worker and
// cron_queue_loader) use the built-in defaults below. To change one, remove the
// leading "// " from its line and edit the number. Values are whole numbers; a
// value outside the range shown is clamped to the nearest bound, and a value
// that is not a whole number is ignored (the default is used). The workers
// read this file once when they start, so an edit takes effect within a few
// minutes without a manual restart: the worker finishes its current task and
// exits, and the next cron run starts it with the new values. Raising the
// memory limits raises what each worker process may use; every worker runs as
// its own PHP process.
//
// Memory (MB) a worker may use before it exits cleanly between tasks and is
// restarted (64-8192):
// define('QUEUE_WORKER_MEMORY_SOFT_LIMIT_MB', 400);
// PHP memory_limit (MB) for the workers; a task that exceeds it fails. Always
// at least the soft limit above + 64 (128-8192):
// define('QUEUE_WORKER_PHP_MEMORY_LIMIT_MB', 512);
// Minutes a worker runs before it is recycled (5-1440):
// define('QUEUE_WORKER_MAX_RUNTIME_MINUTES', 60);
// Minutes a worker may sit idle before it is recycled (5-1440):
// define('QUEUE_WORKER_MAX_IDLE_MINUTES', 60);
// Seconds the queue worker waits between tasks (1-60):
// define('QUEUE_WORKER_LOOP_DELAY_SECONDS', 3);
// Seconds the step worker waits between batches of steps (1-60):
// define('PROMISE_WORKER_LOOP_DELAY_SECONDS', 5);
// The stuck-task window, in minutes. Work a running worker still holds is never
// requeued; work whose worker is gone is requeued once it has shown as running
// this long. A worker's database connection idle this long (at least 15
// minutes) is closed by the database, so a task or step that makes no database
// request for longer may run again. Also the shortest time (never less than
// 30) after a cancel that the Queue Monitor's delete waits (5-1440):
// define('QUEUE_STUCK_TASK_MINUTES', 30);
// Times a task or step that stopped part-way (its worker crashed, ran out of
// memory, was killed or lost its database connection) is put back in the queue
// before it is marked failed instead, which notifies the administrators
// (1-20):
// define('QUEUE_RECOVERY_MAX_ATTEMPTS', 5);
// Retries after a failure before a task is marked failed; a step gets this
// many attempts in total (0-20):
// define('QUEUE_RETRY_MAX_ATTEMPTS', 5);
// Seconds before the first retry; doubles with each further retry (1-3600):
// define('QUEUE_RETRY_BASE_DELAY_SECONDS', 5);
// Longest wait in seconds between two retries; never below the base delay
// (1-86400):
// define('QUEUE_RETRY_MAX_DELAY_SECONDS', 3600);
// Steps the step worker picks up per batch (1-200):
// define('PROMISE_WORKER_BATCH_SIZE', 20);
// Seconds a running worker may go without a heartbeat before a new worker on
// the same host treats it as hung, stops it and takes over. Keep it well above
// the longest time a single task can run
// (300-604800):
// define('WORKER_STALE_LOCK_SECONDS', 86400);
// How many queue workers may run at the same time, across the whole
// installation (every server that runs the cron jobs shares this number, so
// keep config.php the same on all of them). Each run of a worker's cron entry
// starts at most one more: cron.php has one entry per configured worker, so
// they all start on its next run; with a separate cron line every five
// minutes, three workers take up to fifteen minutes. Every worker is its own
// PHP process with its own memory limit and database connection. The workers
// rely on MySQL named locks at any count (if the database refuses them, the
// workers do not start); on a multi-writer cluster such as Galera all servers
// must use the same node. Queue workers mainly speed up email and other
// single-step tasks (1-10):
// define('QUEUE_WORKER_COUNT', 1);
// How many step workers may run at the same time, started and shared the same
// way. Step workers do the work of documents, controls and AI analysis
// (1-10):
// define('PROMISE_WORKER_COUNT', 1);
