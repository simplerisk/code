<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Retired: Automated Discovery now lives in the Discover assets modal on Manage assets.
    // Kept so bookmarks and old links still land somewhere useful. The
    // authentication and asset permission checks run first, so a caller
    // without the permission is refused exactly as on the retired page
    // rather than being redirected into the new one. Nothing runs here.
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    add_security_headers();

    // is_action: a redirect is not a page the user "visited", so it must not
    // become the remembered landing location.
    add_session_check([
        'check_access' => true,
        'check_assets' => true,
        'is_action' => true,
    ]);

    // Relative, so it works on installs served from a subpath.
    header('Location: manage_assets.php', true, 302);
    exit;
