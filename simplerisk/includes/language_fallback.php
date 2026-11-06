<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Dependency-free helper that builds the $lang array for a request.
 *
 * Kept in its own file (no requires, no globals, no DB) so it can be unit
 * tested in isolation. includes/functions.php wraps it in load_language_file().
 *
 * Every key defined in the English language file is always present in the
 * result; the active locale's values win. A custom or partially translated
 * locale file that lacks a key therefore renders that string in English instead
 * of leaving $lang['Key'] null (which made Laminas Escaper throw a TypeError
 * and turned whole pages into HTTP 500s).
 *
 * array_replace() is used deliberately, not array_merge(): it keeps keys
 * exactly as written (including numeric-looking ones) and lets the right-hand
 * array win.
 *
 * @param string|null $locale_file  absolute path of the active locale's file, or
 *                                  null / the English file itself when English is active
 * @param string|null $english_file absolute path of languages/en/lang.en.php
 * @param array       $errors       (by reference) load problems, for the caller to log
 * @return array<string,string> the complete language array
 */
function language_load_with_english_fallback(?string $locale_file, ?string $english_file, array &$errors = []): array
{
    // Each file is included inside a closure so its `$lang = [...]` (or helper
    // variables such as $lang_base) stay local and cannot leak between the two
    // loads. A file that is unreadable, fails to parse, or throws is treated as
    // empty rather than fataling the request.
    $read = static function (string $file) use (&$errors): array {
        $label = basename(dirname($file)) . '/' . basename($file);
        if (!is_file($file) || !is_readable($file)) {
            $errors[] = 'Language file is not readable: ' . $label;
            return [];
        }
        try {
            $lang = [];
            // require_once on first load so that legacy callers (older Extras
            // shipped with their own require_once(language_file())) see the file
            // as already included and do not re-run it, which would overwrite the
            // merged $lang with the bare locale file. If the file was already
            // included earlier (require_once is then a no-op and $lang stays
            // empty), a plain include is the only way to read it again.
            require_once $file;
            if ($lang === []) {
                include $file;
            }
            return is_array($lang) ? $lang : [];
        } catch (\Throwable $e) {
            $errors[] = 'Language file ' . $label . ' failed to load: ' . $e->getMessage();
            return [];
        }
    };

    $english = ($english_file !== null && $english_file !== '') ? $read($english_file) : [];

    // English is active: nothing to layer, and English is not loaded twice.
    if ($locale_file === null || $locale_file === '' || $locale_file === $english_file) {
        return $english;
    }

    return array_replace($english, $read($locale_file));
}
