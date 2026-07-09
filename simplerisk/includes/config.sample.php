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
