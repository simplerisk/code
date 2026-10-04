<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Asset discovery: how a host is probed for liveness.
 *
 * Four methods, best first. The discovery job detects which ones work in its
 * own process at the start of a run (assets_discovery_detect_capabilities())
 * and records the one it picked on the run, so a resumed run never switches
 * method half way through.
 *
 *   icmp_dgram   ICMP echo over an unprivileged "ping socket"
 *                (socket(AF_INET, SOCK_DGRAM, IPPROTO_ICMP)). Linux allows it
 *                when one of the process's groups is inside
 *                /proc/sys/net/ipv4/ping_group_range (Docker sets
 *                "0 2147483647"); no capability needed.
 *   icmp_raw     ICMP echo over a raw socket. Needs CAP_NET_RAW in the
 *                effective set (root alone is not enough when a container
 *                runtime dropped the capability).
 *   ping_binary  The `ping` command through ping_check() (the only method
 *                that starts a process; the address is IPv4-validated and
 *                escapeshellarg()'d there).
 *   tcp          TCP connects to a short port list. A host is alive when a
 *                connect succeeds or is actively refused (a RST proves a live
 *                host); a timeout means "not seen". Needs nothing, but misses
 *                hosts that silently drop every probed port.
 *
 * Sockets methods probe a batch of hosts at once (one echo request each, one
 * wait window) and never start a process. Addresses arrive as integers from
 * the range parser (reserved ranges are rejected there, on user input) and
 * become strings only at the socket call, IPv4 only.
 *
 * Everything here except the transports is pure: detection takes an
 * injectable environment, so the tests need neither privileges nor network.
 */


/** Probe methods, best first (also the whitelist for the forcing setting). */
const ASSETS_DISCOVERY_PROBE_METHODS = ['icmp_dgram', 'icmp_raw', 'ping_binary', 'tcp'];
/** Default of the asset_discovery_tcp_ports setting. */
const ASSETS_DISCOVERY_DEFAULT_TCP_PORTS = '22,80,135,139,443,445,3389,8080';
/** Most TCP ports one run may probe. */
const ASSETS_DISCOVERY_MAX_TCP_PORTS = 20;
/** Hosts per batch (and per progress checkpoint) for the batch methods. */
const ASSETS_DISCOVERY_SOCKET_CHUNK = 64;
/** How long the ICMP methods wait for replies to one batch. */
const ASSETS_DISCOVERY_ICMP_TIMEOUT_MS = 1000;
/** How long one TCP connect may take before the port counts as silent. */
const ASSETS_DISCOVERY_TCP_TIMEOUT_MS = 700;
/** Most TCP connects in flight at once (stays well below FD_SETSIZE). */
const ASSETS_DISCOVERY_TCP_MAX_IN_FLIGHT = 128;
/** Echo request payload: small and constant (the classic 32-byte pattern). */
const ASSETS_DISCOVERY_ICMP_PAYLOAD = 'abcdefghijklmnopqrstuvwabcdefghi';
/** A worker's recorded detection is trusted for this long. */
const ASSETS_DISCOVERY_WORKER_CAPS_MAX_AGE = 7 * 86400;
/**
 * The TCP probe's canary: 192.0.2.1 (RFC 5737 TEST-NET-1) is never a real
 * host, so when it "answers" a TCP connect the network in between (a
 * transparent proxy, a firewall that resets everything) makes every address
 * look alive and the run's results would be junk.
 */
const ASSETS_DISCOVERY_TCP_CANARY = 0xC0000201;
/** Smallest TCP batch the "almost everything answered" breaker judges. */
const ASSETS_DISCOVERY_TCP_BREAKER_MIN_BATCH = 32;
/** Socket-open errors that are about a missing privilege (EPERM, EACCES); anything else is transient. */
const ASSETS_DISCOVERY_SOCKET_REFUSED_ERRNOS = [1, 13];

/* =========================================================================
 * ICMP packets (pure)
 * ========================================================================= */

/** The RFC 1071 Internet checksum of $data (one's complement of the one's-complement sum). */
function assets_discovery_icmp_checksum(string $data): int
{
    if (strlen($data) % 2 === 1) {
        $data .= "\x00";
    }
    $sum = 0;
    foreach (unpack('n*', $data) ?: [] as $word) {
        $sum += $word;
    }
    while ($sum >> 16) {
        $sum = ($sum & 0xFFFF) + ($sum >> 16);
    }
    return ~$sum & 0xFFFF;
}

/** An ICMP echo request (type 8) with the constant payload and a valid checksum. */
function assets_discovery_icmp_build_echo(int $id, int $seq): string
{
    $body = pack('nn', $id & 0xFFFF, $seq & 0xFFFF) . ASSETS_DISCOVERY_ICMP_PAYLOAD;
    $checksum = assets_discovery_icmp_checksum("\x08\x00\x00\x00" . $body);
    return "\x08\x00" . pack('n', $checksum) . $body;
}

/**
 * Parses a received ICMP message: from a raw socket it starts with the IPv4
 * header ($has_ip_header), from a ping socket it does not. Returns null for
 * anything truncated, malformed or with a bad checksum.
 *
 * @return array{type:int,code:int,id:int,seq:int,source:?string}|null
 */
function assets_discovery_icmp_parse_reply(string $packet, bool $has_ip_header): ?array
{
    $source = null;
    if ($has_ip_header) {
        if (strlen($packet) < 20) {
            return null;
        }
        $first = ord($packet[0]);
        $ihl = ($first & 0x0F) * 4;
        if (($first >> 4) !== 4 || $ihl < 20 || strlen($packet) < $ihl + 8) {
            return null;
        }
        $source = inet_ntop(substr($packet, 12, 4));
        $packet = substr($packet, $ihl);
    }
    if (strlen($packet) < 8 || assets_discovery_icmp_checksum($packet) !== 0) {
        return null;
    }
    $h = unpack('Ctype/Ccode/nchecksum/nid/nseq', $packet);
    return ['type' => $h['type'], 'code' => $h['code'], 'id' => $h['id'], 'seq' => $h['seq'], 'source' => $source === false ? null : $source];
}

/* =========================================================================
 * Environment parsing (pure)
 * ========================================================================= */

/** CAP_NET_RAW is capability number 13 (linux/capability.h). */
const ASSETS_DISCOVERY_CAP_NET_RAW = 13;

/** Whether /proc/self/status says the effective set holds CAP_NET_RAW. Unknown reads as no. */
function assets_discovery_status_has_cap_net_raw(string $status): bool
{
    if (!preg_match('/^CapEff:\s*([0-9a-fA-F]{1,16})\s*$/m', $status, $m)) {
        return false;
    }
    // Only the low 32 bits matter for bit 13, so no 64-bit overflow concerns.
    $low = hexdec(substr(str_pad($m[1], 16, '0', STR_PAD_LEFT), 8));
    return ((int)$low & (1 << ASSETS_DISCOVERY_CAP_NET_RAW)) !== 0;
}

/**
 * The effective gid (the second `Gid:` field) and the supplementary groups
 * from /proc/self/status -- the groups the kernel checks against
 * ping_group_range. Effective gid first; no duplicates.
 *
 * @return int[]
 */
function assets_discovery_status_gids(string $status): array
{
    $gids = [];
    if (preg_match('/^Gid:\s*\d+\s+(\d+)/m', $status, $m)) {
        $gids[] = (int)$m[1];
    }
    if (preg_match('/^Groups:[ \t]*([0-9 \t]*)$/m', $status, $m)) {
        foreach (preg_split('/\s+/', trim($m[1])) as $g) {
            if ($g !== '') {
                $gids[] = (int)$g;
            }
        }
    }
    return array_values(array_unique($gids));
}

/**
 * Whether any of $gids lies in the ping_group_range "low high" (inclusive).
 * "1 0" (the kernel default) disables ping sockets; anything malformed reads
 * as disabled.
 *
 * @param int[] $gids
 */
function assets_discovery_ping_group_allows(?string $range, array $gids): bool
{
    if ($range === null || !preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $range, $m)) {
        return false;
    }
    [$low, $high] = [(int)$m[1], (int)$m[2]];
    foreach ($gids as $gid) {
        if ($gid >= $low && $gid <= $high) {
            return true;
        }
    }
    return false;
}

/**
 * The first absolute PATH entry holding an executable $binary, or null. A
 * pure filesystem lookup (no shell): relative or empty entries (the current
 * directory is no place to trust a binary from), entries with `..` and
 * entries with control characters are skipped, and $binary must be a plain
 * file name.
 *
 * @param callable(string):bool $is_executable
 */
function assets_discovery_find_in_path(string $path, string $binary, callable $is_executable): ?string
{
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $binary) || $binary === '.' || $binary === '..') {
        return null;
    }
    if (strpbrk($path, "\0\r\n") !== false) {
        return null;
    }
    $windows = DIRECTORY_SEPARATOR === '\\';
    foreach (explode(PATH_SEPARATOR, $path) as $dir) {
        $absolute = $windows ? (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $dir) : str_starts_with($dir, '/');
        if (!$absolute || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $dir)) {
            continue;
        }
        $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $binary;
        if ($is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/* =========================================================================
 * Capability detection and method choice
 * ========================================================================= */

/**
 * The real environment of this process, in the shape
 * assets_discovery_detect_capabilities() takes.
 *
 * @return array<string,mixed>
 */
function assets_discovery_default_env(): array
{
    $read = static function (string $file): ?string {
        if (!is_readable($file)) {
            return null;
        }
        $data = @file_get_contents($file);
        return is_string($data) ? $data : null;
    };
    return [
        'os' => PHP_OS_FAMILY,
        'sockets' => function_exists('socket_create'),
        'status' => $read('/proc/self/status'),
        'ping_group_range' => $read('/proc/sys/net/ipv4/ping_group_range'),
        'egid' => function_exists('posix_getegid') ? posix_getegid() : null,
        'path' => (string)(getenv('PATH') ?: ''),
        'is_executable' => static fn(string $p): bool => @is_file($p) && @is_executable($p),
        'open_socket' => 'assets_discovery_try_open_socket',
        'ping_works' => 'assets_discovery_ping_binary_works',
    ];
}

/**
 * Opens (and closes) one socket of $kind to confirm the kernel allows it.
 * Returns 0 on success, else the socket error number (EPERM 1, EACCES 13, ...).
 */
function assets_discovery_try_open_socket(string $kind): int
{
    if (!function_exists('socket_create')) {
        return -1;
    }
    $socket = assets_discovery_icmp_socket($kind === 'icmp_raw');
    if ($socket === false) {
        $err = socket_last_error();
        socket_clear_error();
        return $err ?: -1;
    }
    socket_close($socket);
    return 0;
}

/**
 * Whether the ping command can actually ping (the loopback address, through
 * the hardened ping_check()). A ping binary on PATH is not enough: without
 * NET_RAW and without ping sockets every ping fails "Operation not
 * permitted".
 */
function assets_discovery_ping_binary_works(): bool
{
    require_once(realpath(__DIR__ . '/assets.php'));
    return ping_check('127.0.0.1');
}

/** The reason code for a failed socket open: a missing privilege, or a transient error. */
function assets_discovery_socket_open_reason(int $errno): string
{
    return in_array($errno, ASSETS_DISCOVERY_SOCKET_REFUSED_ERRNOS, true) ? 'socket_refused' : 'socket_error';
}

/**
 * Whether $method being unavailable is transient (a socket could not be
 * opened for a reason other than a missing privilege, e.g. EMFILE or
 * ENOBUFS), so the attempt should be retried rather than the run failed.
 * Structural reasons (no capability, group outside ping_group_range, no
 * binary, no extension, unknown method) are not.
 *
 * @param string[] $reasons from assets_discovery_detect_capabilities()
 */
function assets_discovery_probe_failure_is_transient(string $method, array $reasons): bool
{
    return in_array($method, ['icmp_dgram', 'icmp_raw'], true) && in_array("{$method}:socket_error", $reasons, true);
}

/**
 * The TCP breaker: a batch of at least ASSETS_DISCOVERY_TCP_BREAKER_MIN_BATCH
 * addresses where more than 90% "answered" is almost certainly a network
 * that answers for everyone, not a network that full.
 */
function assets_discovery_tcp_batch_unreliable(int $alive, int $size): bool
{
    return $size >= ASSETS_DISCOVERY_TCP_BREAKER_MIN_BATCH && $alive * 10 > $size * 9;
}

/** A new ICMP socket (raw or ping socket), or false. */
function assets_discovery_icmp_socket(bool $raw)
{
    if ($raw) {
        $proto = getprotobyname('icmp');
        return @socket_create(AF_INET, SOCK_RAW, $proto === false ? 1 : $proto);
    }
    return @socket_create(AF_INET, SOCK_DGRAM, 1 /* IPPROTO_ICMP */);
}

/**
 * Which probe methods work in this process. Pure over $env (defaults to the
 * real one): `status` (/proc/self/status), `ping_group_range`, `egid`,
 * `path`, `os`, `sockets` (ext-sockets loaded), and the callables
 * `is_executable(path)`, `open_socket(kind)` (0 = opened, else errno) and
 * `ping_works()`. A method that looks available is confirmed for real: a
 * socket is opened, and a ping binary found on PATH must ping the loopback
 * (tested only when no socket method works).
 *
 * `reasons` lists why a method is unavailable, as "<method>:<code>".
 *
 * @param array<string,mixed>|null $env
 * @return array{icmp_dgram:bool,icmp_raw:bool,ping_binary:bool,tcp:true,method:string,reasons:string[]}
 */
function assets_discovery_detect_capabilities(?array $env = null): array
{
    $env = array_merge(assets_discovery_default_env(), $env ?? []);
    $status = is_string($env['status']) ? $env['status'] : '';
    $reasons = [];

    // 1. Ping socket.
    $dgram = false;
    if (!$env['sockets']) {
        $reasons[] = 'icmp_dgram:no_sockets_extension';
    } elseif ($env['os'] !== 'Linux' || $env['ping_group_range'] === null) {
        $reasons[] = 'icmp_dgram:not_supported';
    } else {
        $gids = assets_discovery_status_gids($status);
        if ($env['egid'] !== null && !in_array((int)$env['egid'], $gids, true)) {
            array_unshift($gids, (int)$env['egid']);
        }
        if (!assets_discovery_ping_group_allows((string)$env['ping_group_range'], $gids)) {
            $reasons[] = 'icmp_dgram:group_not_allowed';
        } elseif (($errno = (int)($env['open_socket'])('icmp_dgram')) !== 0) {
            $reasons[] = 'icmp_dgram:' . assets_discovery_socket_open_reason($errno);
        } else {
            $dgram = true;
        }
    }

    // 2. Raw socket.
    $raw = false;
    if (!$env['sockets']) {
        $reasons[] = 'icmp_raw:no_sockets_extension';
    } elseif (!assets_discovery_status_has_cap_net_raw($status)) {
        $reasons[] = 'icmp_raw:no_cap_net_raw';
    } elseif (($errno = (int)($env['open_socket'])('icmp_raw')) !== 0) {
        $reasons[] = 'icmp_raw:' . assets_discovery_socket_open_reason($errno);
    } else {
        $raw = true;
    }

    // 3. The ping command.
    $binary = assets_discovery_find_in_path((string)$env['path'], $env['os'] === 'Windows' ? 'ping.exe' : 'ping', $env['is_executable']) !== null;
    if (!$binary) {
        $reasons[] = 'ping_binary:not_found';
    } elseif (!$dgram && !$raw && !($env['ping_works'])()) {
        // Installed but not allowed to ping (no NET_RAW, no ping sockets).
        // Only tested when no socket method works: then the binary is the
        // candidate, and otherwise a ping would be forked for nothing (the
        // web-side capability check runs on every Discover dialog).
        $binary = false;
        $reasons[] = 'ping_binary:not_permitted';
    }

    $caps = ['icmp_dgram' => $dgram, 'icmp_raw' => $raw, 'ping_binary' => $binary, 'tcp' => true];
    $caps['method'] = assets_discovery_pick_method($caps);
    $caps['reasons'] = $reasons;
    return $caps;
}

/**
 * The probe method to use: $force when it is a known method that is
 * available, else the best available one (tcp always is). Pure.
 *
 * @param array<string,mixed> $caps
 */
function assets_discovery_pick_method(array $caps, ?string $force = null): string
{
    if ($force !== null && $force !== 'auto' && in_array($force, ASSETS_DISCOVERY_PROBE_METHODS, true)
        && ($force === 'tcp' || !empty($caps[$force]))) {
        return $force;
    }
    foreach (ASSETS_DISCOVERY_PROBE_METHODS as $method) {
        if ($method === 'tcp' || !empty($caps[$method])) {
            return $method;
        }
    }
    return 'tcp';
}

/** Whether $method is known and available in $caps. */
function assets_discovery_method_available(array $caps, string $method): bool
{
    return in_array($method, ASSETS_DISCOVERY_PROBE_METHODS, true) && ($method === 'tcp' || !empty($caps[$method]));
}

/** The language key of a method's human label ('' for an unknown method). */
function assets_discovery_probe_method_lang_key(string $method): string
{
    static $map = [
        'icmp_dgram' => 'DiscoveryProbeIcmpUnprivileged',
        'icmp_raw' => 'DiscoveryProbeIcmpRaw',
        'ping_binary' => 'DiscoveryProbePingCommand',
        'tcp' => 'DiscoveryProbeTcpConnect',
    ];
    return $map[$method] ?? '';
}

/* =========================================================================
 * TCP ports (pure)
 * ========================================================================= */

/**
 * Validates a comma-separated TCP port list: digits only (at most five per
 * port), 1-65535, spaces around commas allowed, no empty entries, at most
 * ASSETS_DISCOVERY_MAX_TCP_PORTS distinct ports. Duplicates are dropped and
 * the order is kept.
 *
 * @return array{ok:bool,ports:int[],error:?string}
 */
function assets_discovery_validate_tcp_ports(string $csv): array
{
    $fail = ['ok' => false, 'ports' => [], 'error' => 'DiscoveryPortsInvalid'];
    if (strlen($csv) > 200 || trim($csv) === '') {
        return $fail;
    }
    $ports = [];
    foreach (explode(',', $csv) as $part) {
        $part = trim($part, " \t");
        if (!preg_match('/^[0-9]{1,5}$/', $part)) {
            return $fail;
        }
        $port = (int)$part;
        if ($port < 1 || $port > 65535) {
            return $fail;
        }
        $ports[$port] = $port;
    }
    if (count($ports) > ASSETS_DISCOVERY_MAX_TCP_PORTS) {
        return $fail;
    }
    return ['ok' => true, 'ports' => array_values($ports), 'error' => null];
}

/** @param int[] $ports */
function assets_discovery_tcp_ports_csv(array $ports): string
{
    return implode(',', array_map('intval', $ports));
}

/* =========================================================================
 * Probing (transports)
 * ========================================================================= */

/**
 * Probes $ips (integer IPv4 addresses) with $method. Returns [int ip => bool
 * alive] for every address, in input order.
 *
 * $opts: `ports` (int[], tcp), `timeout_ms`, and injectable transports for
 * tests -- `pinger` (callable(string):bool, the ping_binary method; defaults
 * to ping_check()).
 *
 * @param int[] $ips
 * @param array<string,mixed> $opts
 * @return array<int,bool>
 */
function assets_discovery_probe_hosts(array $ips, string $method, array $opts = []): array
{
    if (!in_array($method, ASSETS_DISCOVERY_PROBE_METHODS, true)) {
        throw new \InvalidArgumentException('Unknown probe method.');
    }
    $list = [];
    foreach ($ips as $n) {
        if (!is_int($n) || $n < 0 || $n > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('Probe addresses must be IPv4 integers.');
        }
        $list[$n] = false;
    }
    if (!$list) {
        return [];
    }

    switch ($method) {
        case 'icmp_dgram':
        case 'icmp_raw':
            return assets_discovery_probe_icmp($list, $method === 'icmp_raw', (int)($opts['timeout_ms'] ?? ASSETS_DISCOVERY_ICMP_TIMEOUT_MS));
        case 'tcp':
            $ports = array_values(array_filter(array_map('intval', (array)($opts['ports'] ?? [])), fn($p) => $p >= 1 && $p <= 65535));
            if (!$ports) {
                $ports = assets_discovery_validate_tcp_ports(ASSETS_DISCOVERY_DEFAULT_TCP_PORTS)['ports'];
            }
            return assets_discovery_probe_tcp($list, array_slice($ports, 0, ASSETS_DISCOVERY_MAX_TCP_PORTS), (int)($opts['timeout_ms'] ?? ASSETS_DISCOVERY_TCP_TIMEOUT_MS));
        default: // ping_binary
            $pinger = $opts['pinger'] ?? null;
            if (!is_callable($pinger)) {
                require_once(realpath(__DIR__ . '/assets.php'));
                $pinger = 'ping_check';
            }
            foreach ($list as $n => $_) {
                $list[$n] = (bool)$pinger(long2ip($n));
            }
            return $list;
    }
}

/**
 * One echo request per address over one socket, then a single wait window
 * for the replies. A reply counts only when it is an echo reply (type 0)
 * whose sequence number maps to the address it came from; on a raw socket
 * (which receives every inbound ICMP message) the identifier must match too.
 * On a ping socket the kernel rewrites the identifier and only delivers the
 * socket's own replies.
 *
 * @param array<int,bool> $list
 * @return array<int,bool>
 */
function assets_discovery_probe_icmp(array $list, bool $raw, int $timeout_ms): array
{
    $socket = assets_discovery_icmp_socket($raw);
    if ($socket === false) {
        throw new \RuntimeException('Could not open the ICMP socket.');
    }
    try {
        socket_set_nonblock($socket);
        $id = random_int(1, 0xFFFF);
        $base = random_int(0, 0xFFFF);
        $bySeq = [];
        $i = 0;
        foreach ($list as $n => $_) {
            $seq = ($base + $i++) & 0xFFFF;
            $bySeq[$seq] = $n;
            $packet = assets_discovery_icmp_build_echo($id, $seq);
            // An unreachable network fails the send; the host simply reads as down.
            @socket_sendto($socket, $packet, strlen($packet), 0, long2ip($n), 0);
        }

        $pending = count($list);
        $deadline = microtime(true) + max(50, $timeout_ms) / 1000;
        while ($pending > 0) {
            $left = $deadline - microtime(true);
            if ($left <= 0) {
                break;
            }
            $read = [$socket];
            $write = $except = null;
            $ready = @socket_select($read, $write, $except, (int)floor($left), (int)(($left - floor($left)) * 1000000));
            if ($ready === false) {
                break;
            }
            if ($ready === 0) {
                continue;
            }
            // Drain everything that arrived.
            while (true) {
                $buf = '';
                $from = '';
                $port = 0;
                $len = @socket_recvfrom($socket, $buf, 2048, 0, $from, $port);
                if ($len === false || $len === 0) {
                    break;
                }
                $reply = assets_discovery_icmp_parse_reply($buf, $raw);
                if ($reply === null || $reply['type'] !== 0 || ($raw && $reply['id'] !== $id) || !isset($bySeq[$reply['seq']])) {
                    continue;
                }
                $n = $bySeq[$reply['seq']];
                if (long2ip($n) === $from && !$list[$n]) {
                    $list[$n] = true;
                    $pending--;
                }
            }
        }
        return $list;
    } finally {
        socket_close($socket);
    }
}

/** The errno values that mean "connection refused" (Linux, BSD/macOS, Windows). */
function assets_discovery_refused_errnos(): array
{
    return array_values(array_unique(array_filter([defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111, 111, 61, 10061])));
}

/**
 * TCP connect probe. Every (address, port) pair is a non-blocking connect;
 * at most ASSETS_DISCOVERY_TCP_MAX_IN_FLIGHT are open at once and each gets
 * $timeout_ms. A completed connect or an active refusal marks the host alive
 * (and cancels its remaining ports); any other error or a timeout does not.
 *
 * @param array<int,bool> $list
 * @param int[] $ports
 * @return array<int,bool>
 */
function assets_discovery_probe_tcp(array $list, array $ports, int $timeout_ms): array
{
    $queue = [];
    foreach ($list as $n => $_) {
        foreach ($ports as $port) {
            $queue[] = [$n, $port];
        }
    }
    $refused = assets_discovery_refused_errnos();
    $timeout = max(50, $timeout_ms) / 1000;
    $flight = [];   // key => [stream, n, started]
    $key = 0;
    $close = static function ($stream): void {
        if (is_resource($stream)) {
            @fclose($stream);
        }
    };

    try {
        while ($queue || $flight) {
            // Fill the window.
            while ($queue && count($flight) < ASSETS_DISCOVERY_TCP_MAX_IN_FLIGHT) {
                [$n, $port] = array_shift($queue);
                if ($list[$n]) {
                    continue;   // already known alive
                }
                $errno = 0;
                $errstr = '';
                $stream = @stream_socket_client('tcp://' . long2ip($n) . ':' . (int)$port, $errno, $errstr, $timeout,
                    STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
                if ($stream === false) {
                    // Loopback refusals come back synchronously.
                    if (in_array((int)$errno, $refused, true)) {
                        $list[$n] = true;
                    }
                    continue;
                }
                stream_set_blocking($stream, false);
                $flight[$key++] = [$stream, $n, microtime(true)];
            }
            if (!$flight) {
                break;
            }

            $write = [];
            $except = [];
            foreach ($flight as $k => [$stream]) {
                $write[$k] = $stream;
                $except[$k] = $stream;
            }
            $read = null;
            $oldest = min(array_column($flight, 2));
            $left = max(0.0, $oldest + $timeout - microtime(true));
            $ready = @stream_select($read, $write, $except, (int)floor($left), (int)(($left - floor($left)) * 1000000));
            if ($ready === false) {
                break;
            }
            foreach (array_unique(array_merge(array_keys($write), array_keys($except))) as $k) {
                if (!isset($flight[$k])) {
                    continue;
                }
                [$stream, $n] = $flight[$k];
                if (assets_discovery_tcp_connect_proves_host($stream, $refused)) {
                    $list[$n] = true;
                }
                $close($stream);
                unset($flight[$k]);
            }
            // Expire connects that ran out of time, and drop the ones for
            // hosts already proven alive.
            $now = microtime(true);
            foreach ($flight as $k => [$stream, $n, $started]) {
                if ($list[$n] || $now - $started >= $timeout) {
                    $close($stream);
                    unset($flight[$k]);
                }
            }
        }
    } finally {
        foreach ($flight as [$stream]) {
            $close($stream);
        }
    }
    return $list;
}

/**
 * A connect that finished (the socket turned writable): alive when it
 * connected or was refused. With ext-sockets the pending error (SO_ERROR)
 * tells refused (alive) from unreachable (not alive); without it only a
 * completed connection counts.
 *
 * @param resource $stream
 * @param int[] $refused
 */
function assets_discovery_tcp_connect_proves_host($stream, array $refused): bool
{
    if (function_exists('socket_import_stream')) {
        $socket = @socket_import_stream($stream);
        if ($socket !== false && $socket !== null) {
            $err = @socket_get_option($socket, SOL_SOCKET, SO_ERROR);
            if (is_int($err)) {
                return $err === 0 || in_array($err, $refused, true);
            }
        }
    }
    return @stream_socket_get_name($stream, true) !== false;
}
