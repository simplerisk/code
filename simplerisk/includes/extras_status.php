<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * SR-2380: which Extras are installed and enabled, and how to migrate each one's database.
 *
 * Everything here is side-effect free at include time (functions only, no requires), so
 * the pure parts can be unit tested and a caller can include the file without running
 * Core's bootstrap. The one function that touches the instance, extras_status_collect(),
 * loads what it needs when it is called.
 */

/*********************************************
 * FUNCTION: EXTRA DATABASE REGISTRY         *
 *********************************************/
/**
 * What the registry (available_extras() in extras.php, the one list of Extras) says about
 * each Extra's database: the POST route that migrates it, the function that does, and the
 * settings row that stamps how far it has been migrated. Every key is null for an Extra
 * that has none. Nothing here is a second copy: adding an Extra to the registry is the
 * only edit that makes it appear in the status, the plan and the upgrade-databases run.
 *
 * @return array<string, array{route: ?string, function: ?string, setting: ?string}>
 */
function extra_database_registry(): array
{
    // Declared here rather than relied on transitively, per the reachability rule.
    require_once(realpath(__DIR__ . '/extras.php'));

    $registry = [];

    foreach (available_extras() as $extra) {
        $registry[(string)$extra['short_name']] = [
            'route'    => $extra['database_upgrade_route'] ?? null,
            'function' => $extra['database_upgrade_function'] ?? null,
            'setting'  => $extra['database_version_setting'] ?? null,
        ];
    }

    return $registry;
}

/*********************************************
 * FUNCTION: EXTRA DATABASE UPGRADE ROUTES   *
 *********************************************/
/**
 * The POST route, relative to /api/v2, that migrates each Extra's database. Null for an
 * Extra that has none (the Upgrade Extra migrates nothing of its own this way).
 *
 * The route prefix is NOT derivable from the short name (incident_management is /im/,
 * artificial_intelligence is /ai/, complianceforgescf is /scf/upgrade), which is why the
 * status endpoint hands it to the caller instead of making the Upgrade Extra, which runs
 * against Cores older than itself, keep its own copy of the table.
 *
 * @return array<string, string|null>
 */
function extra_database_upgrade_routes(): array
{
    return array_map(function (array $entry) {
        return $entry['route'];
    }, extra_database_registry());
}

/*********************************************
 * FUNCTION: EXTRAS STATUS BUILD             *
 *********************************************/
/**
 * Shape the per-Extra facts into the status payload. Pure.
 *
 * An Extra that is not installed cannot be enabled, whatever its setting says: a
 * leftover enabled flag with no files behind it must not make a caller try to migrate
 * code that is not there.
 *
 * @param string[]                                                         $short_names Every available Extra, in display order.
 * @param array<string, array{installed?: bool, enabled?: bool, version?: ?string}> $facts Per-Extra facts; a missing Extra counts as absent.
 * @param array<string, string|null>                                       $routes      From extra_database_upgrade_routes().
 *
 * @return list<array{name: string, installed: bool, enabled: bool, version: ?string, database_upgrade_route: ?string}>
 */
function extras_status_build(array $short_names, array $facts, array $routes): array
{
    $out = [];

    foreach ($short_names as $name) {
        $installed = !empty($facts[$name]['installed']);
        $version   = $facts[$name]['version'] ?? null;

        $out[] = [
            'name'                   => (string)$name,
            'installed'              => $installed,
            'enabled'                => $installed && !empty($facts[$name]['enabled']),
            'version'                => ($installed && is_string($version) && $version !== '') ? $version : null,
            'database_upgrade_route' => $routes[$name] ?? null,
        ];
    }

    return $out;
}

/*********************************************
 * FUNCTION: EXTRA DATABASE UPGRADE ROUTE    *
 * IS VALID                                  *
 *********************************************/
/**
 * The shape a database-upgrade route must have to be requested: lowercase words, digits,
 * underscores and hyphens between slashes. The route is spliced into a URL requested with
 * the administrator's session, so anything else is skipped rather than requested.
 */
function extra_database_upgrade_route_is_valid($route): bool
{
    return is_string($route) && preg_match('#^/[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#', $route) === 1;
}

/*********************************************
 * FUNCTION: EXTRAS DATABASE UPGRADE PLAN    *
 *********************************************/
/**
 * Decide which of the Extras an upgrade just replaced get their database migrated.
 * Pure.
 *
 * An Extra is migrated only when it was upgraded in this run, is reported installed AND
 * enabled (a disabled Extra's tables may not exist, and migrating one the customer never
 * turned on creates them), and has a well-formed route. Everything else is reported with
 * a reason code, so the operator sees "skipped" rather than silence. The codes are
 * not_reported, not_installed, not_enabled, no_route and bad_route; the Upgrade Extra's
 * own planner uses the same ones (ExtraDatabaseStepTest keeps the two in agreement).
 *
 * @param string[]                 $upgraded      Short names the run replaced.
 * @param array<int, array<mixed>> $status_extras The "extras" list from extras_status_build().
 *
 * @return array{run: list<array{name: string, route: string}>, skipped: list<array{name: string, reason: string}>}
 */
function extras_database_upgrade_plan(array $upgraded, array $status_extras): array
{
    $by_name = [];
    foreach ($status_extras as $entry) {
        if (is_array($entry) && isset($entry['name']) && is_string($entry['name'])) {
            $by_name[$entry['name']] = $entry;
        }
    }

    $run     = [];
    $skipped = [];

    foreach ($upgraded as $name) {
        $entry = $by_name[$name] ?? null;

        if ($entry === null) {
            $skipped[] = ['name' => (string)$name, 'reason' => 'not_reported'];
        } elseif (empty($entry['installed'])) {
            $skipped[] = ['name' => (string)$name, 'reason' => 'not_installed'];
        } elseif (empty($entry['enabled'])) {
            $skipped[] = ['name' => (string)$name, 'reason' => 'not_enabled'];
        } elseif (!isset($entry['database_upgrade_route']) || $entry['database_upgrade_route'] === '') {
            $skipped[] = ['name' => (string)$name, 'reason' => 'no_route'];
        } elseif (!extra_database_upgrade_route_is_valid($entry['database_upgrade_route'])) {
            $skipped[] = ['name' => (string)$name, 'reason' => 'bad_route'];
        } else {
            $run[] = ['name' => (string)$name, 'route' => $entry['database_upgrade_route']];
        }
    }

    return ['run' => $run, 'skipped' => $skipped];
}

/*********************************************
 * FUNCTION: EXTRAS RUN DATABASE STEP        *
 *********************************************/
/**
 * Migrate the databases of the Extras an upgrade just replaced. The Core-driven upgrade's
 * counterpart of the Upgrade Extra's own step (extras/upgrade/includes/extra_database_step.php,
 * a separate copy because that Extra runs against Cores older than this one). Never throws
 * and never reports a migration it did not see succeed.
 *
 *   status 'skipped' - the status endpoint answered 404, so there is nothing to ask;
 *                      nothing was attempted. Extras still migrate on first use.
 *   status 'ok'      - every planned Extra answered 200, or none needed migrating.
 *   status 'failed'  - the status could not be read, or an Extra did not answer 200.
 *
 * Narration goes through $say(languageKey, params) so the caller renders it in the
 * user's language and escapes it once at its own sink; this function has no text.
 *
 * @param string[]                                              $upgraded  Short names the run replaced.
 * @param callable(string, string): array{code: int, body: array<mixed>|null} $request   (method, path) => response
 * @param callable(string, array<string, mixed>): void          $say       Progress line.
 * @param callable(string, array<string, mixed>): void          $say_error Problem line.
 *
 * @return array{status: string, migrated: list<string>, failed: list<string>, skipped: list<array{name: string, reason: string}>}
 */
function extras_run_database_step(array $upgraded, callable $request, callable $say, callable $say_error): array
{
    $outcome = ['status' => 'ok', 'migrated' => [], 'failed' => [], 'skipped' => []];

    if ($upgraded === []) {
        return $outcome;
    }

    $status = $request('GET', '/admin/extras/status');

    if ($status['code'] === 404) {
        $outcome['status'] = 'skipped';
        $say('UpgradeExtraDatabaseStatusUnavailable', []);
        return $outcome;
    }

    $extras = $status['body']['data']['extras'] ?? null;

    if ($status['code'] !== 200 || !is_array($extras)) {
        $outcome['status'] = 'failed';
        $outcome['failed'] = array_values($upgraded);
        $say_error('UpgradeExtraDatabaseStatusFailed', ['code' => $status['code']]);
        return $outcome;
    }

    $plan = extras_database_upgrade_plan($upgraded, $extras);
    $outcome['skipped'] = $plan['skipped'];

    $reason_keys = [
        'not_reported'  => 'UpgradeExtraDatabaseSkipNotReported',
        'not_installed' => 'UpgradeExtraDatabaseSkipNotInstalled',
        'not_enabled'   => 'UpgradeExtraDatabaseSkipNotEnabled',
        'no_route'      => 'UpgradeExtraDatabaseSkipNoRoute',
        'bad_route'     => 'UpgradeExtraDatabaseSkipBadRoute',
    ];

    foreach ($plan['skipped'] as $skip) {
        $say('UpgradeExtraDatabaseSkipped', [
            'extra'      => $skip['name'],
            'reason_key' => $reason_keys[$skip['reason']] ?? $reason_keys['no_route'],
        ]);
    }

    foreach ($plan['run'] as $item) {
        $say('UpgradeExtraDatabaseStarted', ['extra' => $item['name']]);

        $answer = $request('POST', $item['route']);

        if ($answer['code'] === 200) {
            $outcome['migrated'][] = $item['name'];
            $say('UpgradeExtraDatabaseDone', ['extra' => $item['name']]);
        } else {
            $outcome['failed'][] = $item['name'];
            $say_error('UpgradeExtraDatabaseFailedLine', ['extra' => $item['name'], 'code' => $answer['code']]);
        }
    }

    if ($outcome['failed'] !== []) {
        $outcome['status'] = 'failed';
    }

    return $outcome;
}

/*********************************************
 * FUNCTION: EXTRAS DATABASE UPGRADE         *
 *           REQUESTED                       *
 *********************************************/
/**
 * Is this request the one that migrates every Extra's database? The API router asks before
 * it loads any Extra, because loading an Extra is what migrates it: the endpoint has to
 * snapshot the version stamps first, so that it can say what the run changed.
 *
 * Pure. POST only, so a GET still answers 404 like every other POST-only route.
 */
function extras_database_upgrade_requested($method, $uri): bool
{
    if (!is_string($method) || strtoupper($method) !== 'POST' || !is_string($uri)) {
        return false;
    }

    $path = parse_url($uri, PHP_URL_PATH);

    if (!is_string($path)) {
        return false;
    }

    // Optional install subpath, then the API base, then the route: a path that merely ends in
    // the route (/api/v2/risks/admin/extras/upgrade-databases) is not this endpoint
    return preg_match('#^(/[^/]+)*?/api/v2/admin/extras/upgrade-databases/?$#', $path) === 1;
}

/*********************************************
 * FUNCTION: EXTRAS UPGRADE DATABASES        *
 *********************************************/
/**
 * Migrate the database of every installed, enabled Extra and report what each run did.
 * The orchestration only: the two callables do the work against the live instance, so the
 * decisions here are testable without a database.
 *
 * Per Extra, `result` is one of
 *   upgraded       the version stamp moved
 *   current        the migration ran and the stamp did not move: already up to date
 *   queued         the migration is asynchronous (SCF queues its import); no stamp to compare
 *   not_installed  the Extra's files are not on this instance
 *   not_enabled    installed but switched off; a disabled Extra's tables may not exist, and
 *                  migrating one the customer never turned on creates them
 *   no_database    the registry lists no migration for it (the Upgrade Extra)
 *   failed         the migration threw or reported failure; `error` is a stable code. The
 *                  run carries on with the other Extras, so one failure does not hide the rest.
 *
 * `database_version_before` / `_after` are the stamp read around the migration, or null when
 * the Extra has none. Neither callable's message is ever put in the result: an exception
 * message can carry SQL and paths, so the caller logs it and this reports only the code.
 *
 * @param list<array{name: string, installed: bool, enabled: bool, version: ?string}> $status_rows From extras_status_collect().
 * @param array<string, array{route: ?string, function: ?string, setting: ?string}>   $registry    From extra_database_registry().
 * @param callable(string): ?string                                                    $read_stamp  Reads a settings row.
 * @param callable(string, string): bool                                               $migrate     (short name, function) => success; may throw.
 *
 * @return array{ok: bool, extras: list<array<string, mixed>>, summary: array<string, list<string>>}
 */
function extras_upgrade_databases(array $status_rows, array $registry, callable $read_stamp, callable $migrate): array
{
    $results = [];
    $summary = ['upgraded' => [], 'current' => [], 'queued' => [], 'skipped' => [], 'failed' => []];

    foreach ($status_rows as $row) {
        $name     = (string)$row['name'];
        $entry    = $registry[$name] ?? ['route' => null, 'function' => null, 'setting' => null];
        $function = $entry['function'];
        $setting  = $entry['setting'];

        $result = [
            'name'                    => $name,
            'installed'               => (bool)$row['installed'],
            'enabled'                 => (bool)$row['enabled'],
            'version'                 => $row['version'] ?? null,
            'result'                  => '',
            'database_version_before' => null,
            'database_version_after'  => null,
            'error'                   => null,
        ];

        if (empty($row['installed'])) {
            $result['result'] = 'not_installed';
        } elseif (empty($row['enabled'])) {
            $result['result'] = 'not_enabled';
        } elseif ($function === null || $function === '') {
            $result['result'] = 'no_database';
        } else {
            $before = $setting !== null ? $read_stamp($setting) : null;
            $result['database_version_before'] = $before;

            try {
                $ok = $migrate($name, $function) === true;
            } catch (Throwable $e) {
                $ok = false;
            }

            if (!$ok) {
                $result['result'] = 'failed';
                $result['error']  = 'migration_failed';
            } elseif ($setting === null) {
                $result['result'] = 'queued';
            } else {
                $after = $read_stamp($setting);

                $result['database_version_after']  = $after;
                $result['result']                  = $before !== $after ? 'upgraded' : 'current';
            }
        }

        $bucket = in_array($result['result'], ['upgraded', 'current', 'queued', 'failed'], true) ? $result['result'] : 'skipped';
        $summary[$bucket][] = $name;
        $results[] = $result;
    }

    return ['ok' => $summary['failed'] === [], 'extras' => $results, 'summary' => $summary];
}

/*********************************************
 * FUNCTION: EXTRAS UPGRADE DATABASES        *
 *           RESPONSE                        *
 *********************************************/
/**
 * Turn the outcome of extras_upgrade_databases() into the HTTP answer. Pure.
 *
 * A null outcome means the upgrade lock was held by another upgrade: 409, nothing was changed.
 * Any failed Extra makes it a 500 that still carries the full per-Extra body. The workers are
 * asked to restart when something was upgraded or queued, because only then could their loaded
 * Extra job code be out of date.
 *
 * @param array<string, mixed>|null $outcome
 *
 * @return array{code: int, message: string, data: ?array<string, mixed>, workers_restart_requested: bool}
 */
function extras_upgrade_databases_response(?array $outcome): array
{
    if ($outcome === null) {
        return ['code' => 409, 'message' => 'An upgrade is already running on this instance.', 'data' => null, 'workers_restart_requested' => false];
    }

    $restart = ($outcome['summary']['upgraded'] ?? []) !== [] || ($outcome['summary']['queued'] ?? []) !== [];

    return [
        'code'                      => !empty($outcome['ok']) ? 200 : 500,
        'message'                   => !empty($outcome['ok']) ? 'Extra databases are up to date.' : 'One or more Extra databases could not be upgraded.',
        'data'                      => [
            'extras'                    => $outcome['extras'] ?? [],
            'summary'                   => $outcome['summary'] ?? [],
            'workers_restart_requested' => $restart,
        ],
        'workers_restart_requested' => $restart,
    ];
}

/*********************************************
 * FUNCTION: EXTRAS STATUS COLLECT           *
 *********************************************/
/**
 * Read the live instance. Never includes an Extra's index.php: including one runs its
 * lazy database migration and redeclares functions, which a status read must not do. The
 * version is read from the file's source instead, as the install-time gate does.
 *
 * @return list<array{name: string, installed: bool, enabled: bool, version: ?string, database_upgrade_route: ?string}>
 */
function extras_status_collect(): array
{
    // Declared here rather than relied on transitively, per the reachability rule.
    require_once(realpath(__DIR__ . '/extras.php'));
    require_once(realpath(__DIR__ . '/licensing.php'));

    $names = available_extra_short_names();
    $facts = [];

    foreach ($names as $name) {
        $index     = realpath(__DIR__ . '/../extras/' . $name . '/index.php');
        $installed = $index !== false && is_file($index);

        $enabled = false;
        $version = null;

        if ($installed) {
            // The Upgrade Extra has no activation switch: installed is enabled.
            if ($name === 'upgrade') {
                $enabled = true;
            } else {
                $state_fn = extra_state_check_function($name);
                $enabled  = function_exists($state_fn) ? (bool)$state_fn() : false;
            }

            $source  = @file_get_contents((string)$index);
            $version = parse_extra_version_from_source($source === false ? '' : $source);
        }

        $facts[$name] = ['installed' => $installed, 'enabled' => $enabled, 'version' => $version];
    }

    return extras_status_build($names, $facts, extra_database_upgrade_routes());
}
