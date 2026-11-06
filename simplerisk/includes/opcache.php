<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*********************************************************************
 * FUNCTION: INVALIDATE OPCACHE FOR DIRECTORY                        *
 * Drops every .php file under $dir from opcache. Writing new files  *
 * over an Extra is not enough on its own: with opcache timestamp    *
 * validation off (common in production) or inside revalidate_freq,  *
 * the web server keeps running its cached compilation of the old    *
 * files until it is restarted. That cache is shared by every worker *
 * of a PHP-FPM pool or an Apache mod_php server, so invalidating it *
 * from any one request reaches them all. Returns how many files     *
 * were handed to opcache, 0 when opcache is unavailable or $dir is *
 * not a directory. Keep in sync with the Upgrade Extra's copy,      *
 * upgrade_invalidate_opcache_for_directory(), which exists because  *
 * the Extra runs against Cores that predate this file.              *
 * $invalidate is a test seam for opcache_invalidate().              *
 *********************************************************************/
function invalidate_opcache_for_directory(string $dir, ?callable $invalidate = null): int
{
    if ($invalidate === null) {
        if (!function_exists('opcache_invalidate') || !filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }
        $invalidate = fn(string $file) => @opcache_invalidate($file, true);
    }

    if ($dir === '' || !is_dir($dir)) {
        return 0;
    }

    $count = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $invalidate($file->getPathname());
            $count++;
        }
    }

    return $count;
}
