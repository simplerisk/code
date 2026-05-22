<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Dispatcher for unversioned /api/<path> requests.
//
// SimpleRisk has two API versions:
//
//   - /api/v1/<path>  -> simplerisk/api/v1/index.php  (legacy, deprecated)
//   - /api/v2/<path>  -> simplerisk/api/v2/index.php  (current)
//
// This file handles the unversioned /api/<path> URL. Historically it was
// the v1 router itself; it now dispatches to v1 or v2 based on the
// "enable_api_v1" administrator setting:
//
//   - enable_api_v1 = 1  -> include the v1 router (preserves the legacy
//                           behavior for instances that still rely on it)
//   - enable_api_v1 = 0  -> include the v2 router (transparent fallback so
//                           customer integrations using unversioned URLs
//                           keep working after v1 is deprecated)
//
// The setting defaults to "1" on instances that have the API Extra
// installed and active at upgrade time (preserve backward compatibility);
// fresh installs and upgrades without the API Extra default to "0".
//
// Direct /api/v1/<path> requests bypass this dispatcher (handled by
// simplerisk/api/v1/.htaccess routing straight to v1/index.php). The v1
// router has its own gate at the top that returns a 410 deprecated
// response when enable_api_v1 is off, so /api/v1/<path> is also rejected
// in that mode.

require_once(realpath(__DIR__ . '/../includes/functions.php'));

if (get_setting('enable_api_v1'))
{
    require __DIR__ . '/v1/index.php';
}
else
{
    require __DIR__ . '/v2/index.php';
}
