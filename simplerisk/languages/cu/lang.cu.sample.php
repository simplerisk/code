<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * SimpleRisk custom language template.
 *
 * Copy this file to "lang.cu.php" in the same directory to activate a
 * custom language, then add a row to the `languages` database table:
 *   value = (next id), name = 'cu', full = 'Custom'
 * and pick "Custom" from My Account -> Profile -> Language.
 *
 * Maintenance is minimal: list ONLY the strings you want to change in the
 * $lang array below. Every key you do not override is filled automatically
 * from the base language set in $lang_base, so you never have to copy the
 * full key list and you do not have to re-sync it on every SimpleRisk
 * upgrade.
 *
 * This file is shipped as ".sample" so a SimpleRisk upgrade never overwrites
 * your live "lang.cu.php".
 */

ini_set('default_charset', 'utf-8');

// Base language to fall back to for any key you do not override below.
// Use any installed locale code (e.g. 'en', 'de', 'fr'). Defaults to 'en'.
$lang_base = 'en';

// ---------------------------------------------------------------------------
// Your overrides: list ONLY the strings you want to change.
// ---------------------------------------------------------------------------
$lang = [
    'Logout' => 'Sign out',
    // 'Home' => 'Dashboard',
];

// ---------------------------------------------------------------------------
// Fallback boilerplate -- do not edit.
// Loads the base language, then layers your overrides on top so that every
// key you did not override is filled from the base language.
// ---------------------------------------------------------------------------
$__base_code = basename($lang_base ?: 'en');
$__base = __DIR__ . '/../' . $__base_code . '/lang.' . $__base_code . '.php';
// Skip the merge if the base is missing, or is 'cu' itself (a self-reference
// would re-include this file recursively). In either case the custom file
// simply renders with only its overrides.
if ($__base_code !== 'cu' && is_file($__base)) {
    $__overrides = $lang;          // hold your overrides
    require($__base);              // $lang = base language array
    $lang = $__overrides + $lang;  // overrides win, base fills the gaps
    unset($__overrides);
}
unset($__base, $__base_code);
