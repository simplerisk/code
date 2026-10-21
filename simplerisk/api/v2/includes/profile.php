<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Every direct consumer of a function below declares its own require, even
// where it is also reachable transitively today (CLAUDE.md reachability rule).
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../../../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../../../includes/mfa.php'));
require_once(realpath(__DIR__ . '/../../../includes/extras.php'));

/**
 * The password-policy requirement list mirrors getPasswordReqeustMessages()
 * (includes/functions.php) exactly -- same settings, same $lang keys, same
 * order -- but additionally tags each entry with a machine-readable "type"
 * so the client can pair the server-resolved, already-localized message with
 * the right live-typing check. getPasswordReqeustMessages() itself returns
 * flat display strings only, which is enough for the old page's static list
 * but not for live validation, hence this sibling rather than a change to it.
 */
function api_v2_profile_password_policy_requirements()
{
    global $lang;

    $requirements = [];

    if (get_setting('pass_policy_enabled') != 1) {
        return ['enabled' => false, 'requirements' => []];
    }

    $min_chars = (int)get_setting('pass_policy_min_chars');
    $requirements[] = [
        'type' => 'min_chars',
        'message' => _lang('ConditionMessageForMinChar', ['min_chars' => $min_chars]),
        'min_chars' => $min_chars,
    ];

    if (get_setting('pass_policy_alpha_required') == 1) {
        $requirements[] = ['type' => 'alpha', 'message' => _lang('ConditionMessageForAlpha'), 'min_chars' => null];
    }
    if (get_setting('pass_policy_upper_required') == 1) {
        $requirements[] = ['type' => 'upper', 'message' => _lang('ConditionMessageForUppercase'), 'min_chars' => null];
    }
    if (get_setting('pass_policy_lower_required') == 1) {
        $requirements[] = ['type' => 'lower', 'message' => _lang('ConditionMessageForLowercase'), 'min_chars' => null];
    }
    if (get_setting('pass_policy_digits_required') == 1) {
        $requirements[] = ['type' => 'digit', 'message' => _lang('ConditionMessageForDigit'), 'min_chars' => null];
    }
    if (get_setting('pass_policy_special_required') == 1) {
        $requirements[] = ['type' => 'special', 'message' => _lang('ConditionMessageForSpecialchar'), 'min_chars' => null];
    }

    $min_password_age = get_setting('pass_policy_min_age');
    if ($min_password_age != 0) {
        $requirements[] = [
            'type' => 'min_age',
            'message' => _lang('ConditionMessageForMinPasswordAge', ['min_password_age' => $min_password_age]),
            'min_chars' => null,
        ];
    }

    return ['enabled' => true, 'requirements' => $requirements];
}

/**********************************
 * FUNCTION: API V2 PROFILE GET   *
 **********************************/
function api_v2_profile_get()
{
    // Self-scoped by construction: there is no id/user parameter anywhere in
    // this function. Every lookup below keys off $_SESSION['uid']/['user'].
    $user_id = $_SESSION['uid'];

    $user_info = get_user_by_id($user_id);
    $manager = $user_info['manager'] ? get_user_name($user_info['manager']) : null;
    $teams = get_names_by_multi_values('team', $user_info['teams'], true) ?: [];

    $role = '-';
    if ($user_info['role_id']) {
        $role_row = get_role($user_info['role_id']);
        if ($role_row) {
            $role = $role_row['name'];
        }
    }

    $db = db_open();
    $stmt = $db->prepare('SELECT `value` AS id, `full` AS name FROM `languages` ORDER BY `full`');
    $stmt->execute();
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $api_extra_active = api_extra();
    $api_key_exists = false;
    if ($api_extra_active) {
        $api_key_file = realpath(__DIR__ . '/../../../extras/api/index.php');
        if (file_exists($api_key_file)) {
            require_once($api_key_file);
            $api_key_exists = (get_user_api_key() !== '');
        }
    }

    $data = [
        'name' => $user_info['name'],
        'email' => $user_info['email'],
        'username' => $user_info['username'],
        'manager' => $manager,
        'teams' => array_values($teams),
        'role' => $role,
        'admin' => (bool)$user_info['admin'],
        // 'simplerisk', 'ldap', or 'saml' -- lets a future UI hide the
        // password-change form entirely for non-'simplerisk' accounts, the
        // same way account/profile.php's existing form already does via
        // $_SESSION['user_type']. Read from the freshly-queried $user_info
        // rather than the session copy, since this handler already has it.
        'user_type' => $user_info['type'],
        'mfa_enabled' => ((int)$user_info['multi_factor'] === 1),
        'mfa_required' => (get_setting('mfa_required') == 1),
        // user.lang stores the language CODE (e.g. "ar"), not the numeric
        // languages.value id -- get_value_by_name() is the inverse of
        // get_name_by_value() used by the PATCH handler below, so GET and
        // PATCH agree on the same id space as the 'languages' list.
        //
        // user.lang is empty for any account that never explicitly picked a
        // language: add_user() inserts it as '' (not NULL), and looking that
        // straight up would miss and leave language_id at 0 -- unmatched by
        // any <option>, so the browser preselects the first option
        // alphabetically (Arabic) instead of the language the user is
        // actually seeing.
        //
        // $_SESSION['lang'] is NOT a safe first fallback on its own: both
        // auth paths that can reach this handler (grant_access() in
        // authenticate.php, and authenticate_key() in extras/api/index.php)
        // check `!is_null(user.lang)` before copying it into the session --
        // an empty STRING passes that check, so $_SESSION['lang'] ends up
        // '' too for exactly the accounts this fallback exists for. Chained
        // with `?:` (not `??`) below, an empty $_SESSION['lang'] keeps
        // falling through to the same default_language-or-"en" resolution
        // login already applies once user.lang is empty, so this stays
        // consistent with what the rest of the session is actually
        // rendered in either way.
        'language_id' => (int)(get_value_by_name('languages', (string)(
            $user_info['lang'] ?: ($_SESSION['lang'] ?: (get_setting('default_language') ?: 'en'))
        )) ?? 0),
        'languages' => array_map(fn($row) => ['id' => (int)$row['id'], 'name' => $row['name']], $languages),
        'api_key_exists' => $api_key_exists,
        'api_extra_active' => $api_extra_active,
        'password_policy' => api_v2_profile_password_policy_requirements(),
        'permission_groups' => [],
    ];

    // Built separately (not as a single array_map expression) so
    // get_grouped_permissions() -- one query -- runs exactly once.
    $grouped_permissions = get_grouped_permissions($user_id);
    $data['permission_groups'] = array_map(
        function ($group_rows, $group_name) {
            return [
                'name' => $group_name,
                'description' => $group_rows[0]['permission_group_description'],
                'permissions' => array_map(function ($p) {
                    return [
                        'id' => (int)$p['permission_id'],
                        'key' => $p['key'],
                        'name' => $p['permission_name'],
                        'description' => $p['permission_description'],
                        'selected' => (bool)$p['selected'],
                    ];
                }, $group_rows),
            ];
        },
        $grouped_permissions,
        array_keys($grouped_permissions)
    );

    api_v2_json_result(200, 'SUCCESS', $data);
}

/************************************
 * FUNCTION: API V2 PROFILE PATCH   *
 ************************************/
function api_v2_profile_patch()
{
    global $lang;

    // PHP does not auto-populate $_POST for PATCH; parse the body first.
    parse_non_post_body_into_post();

    // The ONLY field this endpoint will ever read or act on. Every other
    // profile field (name, email, username, manager, role, admin, teams) is
    // admin-managed -- a client sending them here is silently ignored, never
    // merged, so a crafted body can't escalate into an admin-only field.
    $language_id = get_param('POST', 'language', null);

    if ($language_id === null || $language_id === '') {
        api_v2_json_result(400, 'BAD REQUEST: A language id is required.', null);
        return;
    }

    // get_name_by_value() returns its $default arg ("" here, since none was
    // passed) on a miss -- never false/null -- so the empty string IS the
    // failure sentinel to check for.
    $language_name = get_name_by_value('languages', (int)$language_id);
    if ($language_name === '' || $language_name === false || $language_name === null) {
        api_v2_json_result(400, 'BAD REQUEST: Unknown language id.', null);
        return;
    }

    // update_language() returns false only on a DEMO_MODE refusal, in which
    // case it has already surfaced the reason through its own channel.
    $updated = update_language($_SESSION['uid'], $language_name);

    if (!$updated) {
        api_v2_json_result(400, 'BAD REQUEST: The language could not be updated.', null);
        return;
    }

    api_v2_json_result(200, 'SUCCESS', null);
}

/****************************************************
 * FUNCTION: API V2 PROFILE RESET DISPLAY SETTINGS   *
 ****************************************************/
function api_v2_profile_reset_display_settings()
{
    // Self-scoped: reset_custom_display_settings() keys off $_SESSION['uid']
    // internally and takes no target parameter.
    reset_custom_display_settings();

    api_v2_json_result(200, 'SUCCESS', null);
}

/*******************************************
 * FUNCTION: API V2 PROFILE PASSWORD UPDATE *
 *******************************************/
function api_v2_profile_password_update()
{
    global $lang;

    // Self-scoped: the acting identity is whatever authenticated this
    // request. Any other identity-shaped field in the body (user_id, uid,
    // username, ...) is never read.
    $uid = $_SESSION['uid'];
    $username = $_SESSION['user'];

    // Guard (at the top, before anything else): SAML/LDAP-managed accounts
    // have no local password to change. is_valid_user() for a 'saml' user
    // never actually checks the submitted password -- is_valid_saml_user()
    // trusts the session type alone -- so without this guard a SAML user's
    // request would "succeed" while writing an inert local hash/history row
    // that does nothing. An 'ldap' user's current-password check DOES
    // validate against the directory, but the same inert-write problem
    // applies on success. Mirrors account/profile.php's existing
    // `$_SESSION['user_type'] != "ldap"` gate that hides the change-password
    // form, but is stricter/more correct: it excludes BOTH 'saml' and
    // 'ldap', not just 'ldap'.
    if (($_SESSION['user_type'] ?? 'simplerisk') !== 'simplerisk') {
        write_debug_log("[Profile] Password change denied for uid {$uid}: account type '{$_SESSION['user_type']}' has no local password.", "warning");
        api_v2_json_result(403, 'FORBIDDEN: Password changes are not available for single sign-on or directory-managed accounts.', null);
        return;
    }

    // Rate-limit EVERY request to this endpoint using the SAME bucket as MFA
    // attempt throttling (includes/mfa.php's check_mfa_attempts(), already
    // required into this file) -- 5 attempts/minute per uid. Sharing the
    // bucket with MFA verification elsewhere in the app is an accepted
    // tradeoff, not a bug. Without this, a stolen session/API key could
    // brute-force the account's real password against this endpoint with no
    // throttle at all (the login flow has lockout; this endpoint didn't).
    //
    // Called UNCONDITIONALLY, before Factor 1, in every request -- this is
    // deliberate and load-bearing. An earlier version of this handler skipped
    // this call whenever a non-empty mfa_code was submitted, reasoning that
    // does_mfa_token_match() (Factor 2, below) would throttle instead. But
    // Factor 1 returns immediately on a WRONG current_password, before
    // Factor 2 ever runs -- so a request with a wrong current_password AND
    // any non-empty mfa_code value (the attacker doesn't need a real code)
    // skipped the throttle entirely, fully defeating it for exactly the
    // current-password-guessing case it exists to stop. Calling it here,
    // unconditionally, closes that fail-open: the guess itself is always
    // throttled regardless of what Factor 1 or Factor 2 later decide.
    if (!check_mfa_attempts($uid)) {
        api_v2_json_result(429, 'TOO MANY ATTEMPTS: Please wait a minute before trying again.', null);
        return;
    }

    parse_non_post_body_into_post();

    $current_password = get_param('POST', 'current_password', '');
    $new_password = get_param('POST', 'new_password', '');
    $confirm_password = get_param('POST', 'confirm_password', '');
    $mfa_code = get_param('POST', 'mfa_code', '');

    // Factor 1: current password.
    if (!is_valid_user($username, $current_password)) {
        write_debug_log("[Profile] Password change denied for uid {$uid}: incorrect current password.", "warning");
        api_v2_json_result(401, 'UNAUTHORIZED: ' . $lang['PasswordIncorrect'], null);
        return;
    }

    // Factor 2 (only when the account has MFA enabled): a fresh, valid,
    // non-replayed TOTP code, via the same primitive process_mfa_disable()
    // uses to gate disabling MFA.
    $user_info = get_user_by_id($uid);
    if ((int)$user_info['multi_factor'] === 1) {
        // $skip_attempt_throttle=true: the unconditional gate above already
        // spent one check_mfa_attempts() bucket attempt for this request: a
        // second internal call here would double-count it for MFA-enabled
        // accounts without closing any additional gap (Factor 1 has already
        // passed by this point, so there is no fail-open risk in skipping it
        // here).
        if (empty($mfa_code) || !does_mfa_token_match($mfa_code, $uid, true)) {
            write_debug_log("[Profile] Password change denied for uid {$uid}: missing or invalid MFA code.", "warning");
            api_v2_json_result(401, 'UNAUTHORIZED: A valid MFA code is required to change your password.', null);
            return;
        }
    }

    // Check mismatch FIRST, before any policy evaluation.
    if ($new_password !== $confirm_password) {
        api_v2_json_result(400, 'BAD REQUEST: The new password and confirmation do not match.', null);
        return;
    }

    // Evaluate policy directly via the existing requirement list -- never call
    // valid_password()/check_valid_*()/check_current_password_age() here, since
    // those call set_alert() as a side effect, which (correctly, since Task 1's
    // fix) now persists through with_alert_session() even in this closed-session
    // context, producing a stale duplicate toast on the user's next page load.
    // Each check instead calls the matching side-effect-free password_has_*() /
    // password_meets_min_age() predicate that check_valid_*() /
    // check_current_password_age() themselves wrap, so the regex/policy source
    // of truth stays single.
    $policy = api_v2_profile_password_policy_requirements();
    if ($policy['enabled']) {
        foreach ($policy['requirements'] as $requirement) {
            $violates = match ($requirement['type']) {
                'min_chars' => strlen($new_password) < $requirement['min_chars'],
                'alpha' => !password_has_alpha($new_password),
                'upper' => !password_has_upper($new_password),
                'lower' => !password_has_lower($new_password),
                'digit' => !password_has_digit($new_password),
                'special' => !password_has_special($new_password),
                'min_age' => !password_meets_min_age($uid),
                default => false,
            };
            if ($violates) {
                api_v2_json_result(400, 'BAD REQUEST: ' . $requirement['message'], null);
                return;
            }
        }
    }

    if (!check_add_password_reuse_history($uid, $new_password)) {
        api_v2_json_result(400, 'BAD REQUEST: ' . $lang['PasswordNoLongerUse'], null);
        return;
    }

    $hash = hash_password($new_password);
    $old_data = get_salt_and_password_by_user_id($uid);
    add_last_password_history($uid, $old_data['salt'], $old_data['password']);

    if (!update_password($username, $hash)) {
        api_v2_json_result(400, 'BAD REQUEST: The password could not be updated.', null);
        return;
    }

    $sessions_cleared = kill_other_sessions_of_current_user();
    expire_reset_token_for_username($username);

    api_v2_json_result(200, 'SUCCESS', ['sessions_cleared' => $sessions_cleared]);
}
