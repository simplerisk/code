<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// MySQL Database Host Name
define('DB_HOSTNAME', '127.0.0.1');

// MySQL Database Port Number
define('DB_PORT', '3306');

// MySQL Database User Name
define('DB_USERNAME', 'simplerisk');

// MySQL Database Password
define('DB_PASSWORD', 'simplerisk');

// MySQL Database Name
define('DB_DATABASE', 'simplerisk');

// Session last activity timeout (Default: 3600 = 1h)
define('LAST_ACTIVITY_TIMEOUT', '3600');

// Session renegotiation timeout (Default: 600 = 10m)
define('SESSION_RENEG_TIMEOUT', '600');

// Use database for sessions
define('USE_DATABASE_FOR_SESSIONS', 'true');

// Enable Content Security Policy (This has broken Chrome in the past)
define('CSP_ENABLED', 'false');

// Set the default language (Can be overridden per user)
// Options: bp, en, es
define('LANG_DEFAULT', 'en');

// Set the default Timezone
// List of supported timezones here: http://www.php.net/manual/en/timezones.php
date_default_timezone_set('America/Chicago');

?>
