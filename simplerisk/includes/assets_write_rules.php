<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/** Dependency-free rules for writing assets and asset groups: verification, name uniqueness, the permission each endpoint needs, and request-body parsing. Keep it that way: unit tests load this file alone. */

function assets_verification_for_new(bool $can_verify, bool $auto_verify_setting, bool $is_import): int
{
    return ($can_verify || $auto_verify_setting || $is_import) ? 1 : 0;
}

function assets_verification_after_edit(int $was_verified, bool $can_verify, bool $identity_changed): int
{
    if (!$was_verified) return 0;
    return ($identity_changed && !$can_verify) ? 0 : 1;
}

/**
 * The one reading of a "verified" filter value, shared by the asset list
 * normaliser and the bulk validator so they cannot disagree: true, 1, '1'
 * and 'true' mean verified (1); false, 0, '0' and 'false' mean unverified (0);
 * strings are trimmed and case-insensitive. Anything else (null, '', 2,
 * 'no', arrays) is null: "not a verified value".
 *
 * @param mixed $value
 */
function assets_parse_verified($value): ?int
{
    if ($value === true || $value === 1) {
        return 1;
    }
    if ($value === false || $value === 0) {
        return 0;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if ($v === '1' || $v === 'true') {
            return 1;
        }
        if ($v === '0' || $v === 'false') {
            return 0;
        }
    }
    return null;
}

/**
 * Whether an edit changes an asset's identity (its name or IP address).
 * Compares plaintext to plaintext (the caller decrypts the stored values),
 * ignoring surrounding whitespace. A null new value means "this field was not
 * supplied, leave it alone", so it can never count as a change.
 */
function assets_identity_changed(string $stored_name, string $stored_ip, ?string $new_name, ?string $new_ip): bool
{
    $name_changed = $new_name !== null && trim($stored_name) !== trim($new_name);
    $ip_changed = $new_ip !== null && trim($stored_ip) !== trim($new_ip);
    return $name_changed || $ip_changed;
}

/**
 * A client-supplied "verified" value is honored only for a user who may verify
 * assets (spec section 10: verify/unverify is asset_verify only). Anyone else
 * gets null, meaning "not supplied": create falls back to the default rules
 * (unverified unless auto-verify) and update leaves the decision to the
 * edit-demotion logic. Internal callers (import, discovery, forced
 * verification) call add_asset() directly with explicit values and do not use this.
 */
function assets_effective_client_verified(?bool $client_value, bool $can_verify): ?bool
{
    return $can_verify ? $client_value : null;
}

/**
 * A client's `verified` value as true / false, or null when it was omitted or
 * cannot be read as a boolean (treated as omitted). '0', 'false', 'off' and ''
 * are an explicit "unverified".
 *
 * @param mixed $raw
 */
function assets_parse_client_verified($raw): ?bool
{
    if ($raw === null || is_array($raw) || is_object($raw)) {
        return null;
    }
    if (is_bool($raw)) {
        return $raw;
    }
    return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
}

/**
 * Verified state (0/1) of an asset created through POST /assets (R11).
 * A verifier's explicit choice is kept as sent -- including "unverified",
 * even with auto-verify on. With no choice, or from a user without
 * asset_verify (whose value is ignored), the usual new-asset rule applies:
 * assets_verification_for_new().
 */
function assets_verified_for_new_from_client(?bool $client_value, bool $can_verify, bool $auto_verify_setting): int
{
    if ($can_verify && $client_value !== null) {
        return $client_value ? 1 : 0;
    }
    return assets_verification_for_new($can_verify, $auto_verify_setting, false);
}

/**
 * Narrow permission a single-asset mutation endpoint requires on top of the
 * base `asset` permission and the per-asset scope check, or null when the
 * endpoint needs nothing extra (unknown keys, and create: anyone holding
 * `asset` may add an asset, verified-ness is decided by asset_verify).
 * Keeps the endpoint -> permission mapping in one testable place.
 */
function assets_endpoint_required_permission(string $endpoint_key): ?string
{
    static $map = [
        'patch_asset' => 'asset_edit',
        'update_asset_legacy' => 'asset_edit',
        'write_associations' => 'asset_edit',
        'delete_asset' => 'asset_delete',
        'delete_asset_legacy' => 'asset_delete',
        // Asset groups: one narrow permission per verb (spec section 9), used
        // by both the /asset-groups CRUD routes and the legacy /asset-group/* RPC.
        // Changing membership (add, remove, replace) is editing the group.
        'group_create' => 'asset_group_create',
        'group_update' => 'asset_group_edit',
        'group_add_asset' => 'asset_group_edit',
        'group_remove_asset' => 'asset_group_edit',
        'group_delete' => 'asset_group_delete',
        // Background discovery runs (/assets/discovery-runs): starting,
        // viewing and cancelling runs all need asset_discovery.
        'discovery_create' => 'asset_discovery',
        'discovery_list' => 'asset_discovery',
        'discovery_cancel' => 'asset_discovery',
        // Which probe method discovery would use (GET .../capabilities).
        'discovery_capabilities' => 'asset_discovery',
    ];
    return $map[$endpoint_key] ?? null;
}

/**
 * Narrow permission PATCH /assets/{id} requires for a given request body
 * (spec section 10: verify/unverify is asset_verify only). A body whose only
 * editable field is `verified` is a verify/unverify and needs asset_verify
 * instead of asset_edit; any other editable field is an edit and needs
 * asset_edit (a `verified` value alongside it is still honoured only for
 * verifiers, see assets_effective_client_verified()). Keys that are not
 * editable fields (id, CSRF token) are ignored.
 *
 * @param array<int|string> $body_keys the request body's top-level keys
 */
function assets_patch_required_permission(array $body_keys): string
{
    // Every key updateAssetById() acts on. A key missing here would let a
    // verify-only caller slip an edit in beside `verified`.
    static $editable = [
        'ip', 'name', 'value', 'location', 'team', 'teams', 'details', 'tags', 'verified',
        'associated_risks', 'control_maturity', 'control_id', 'mapped_controls', 'custom_field',
        'confidentiality', 'integrity', 'availability',
    ];
    $present = array_values(array_intersect($editable, array_map('strval', $body_keys)));
    return $present === ['verified'] ? 'asset_verify' : 'asset_edit';
}

/**
 * Membership a group edit may write when the caller replaces the member list.
 * Team Separation: the caller only chooses among assets they can see, and a
 * current member they cannot see is kept, never dropped by omission.
 *
 * @param int[] $current_ids            the group's members now
 * @param int[] $accessible_current_ids those of them the caller can see
 * @param int[] $posted_accessible_ids  the caller's list, already filtered to what they can see
 * @return int[]
 */
function assets_group_members_after_replace(array $current_ids, array $accessible_current_ids, array $posted_accessible_ids): array
{
    $hidden = array_diff(array_map('intval', $current_ids), array_map('intval', $accessible_current_ids));
    return array_values(array_unique(array_merge(array_map('intval', $posted_accessible_ids), $hidden)));
}

/**
 * Canonical form of an asset group name: surrounding whitespace removed and
 * every internal run of whitespace collapsed to one space, so "Web  Servers "
 * and "Web Servers" are the same name for the duplicate check and the insert.
 * A non-scalar value (an array or object in the request body) has no name.
 *
 * @param mixed $name
 * @return string '' when nothing is left
 */
function assets_normalize_group_name($name): string
{
    if (!is_scalar($name)) return '';
    $collapsed = preg_replace('/\s+/u', ' ', (string)$name);
    return trim($collapsed ?? '');
}

/**
 * The key two asset names are compared by (SR-37): trimmed and case-folded,
 * which is what the unencrypted `name` column's case-insensitive collation
 * and UNIQUE key amount to. The Encryption Extra orders and searches its
 * ciphertext buckets by the same rule (encrypted_asset_name_compare()).
 *
 * @param mixed $name
 */
function assets_name_key($name): string
{
    return mb_strtolower(trim((string)$name));
}

/**
 * Whether $name is held by an asset OTHER than $self_id (null on create).
 * The asset being edited is never compared with itself: when its own current
 * name already has the requested key it may keep it, even if a case twin
 * exists (possible with the Encryption Extra, which has no UNIQUE key on the
 * ciphertext). Mirrors asset_group_name_check().
 *
 * @param array<int,string> $names asset id => plaintext name; must include
 *        every asset whose key could equal $name's, and $self_id's own row
 */
function assets_name_conflict(array $names, string $name, ?int $self_id): bool
{
    $wanted = assets_name_key($name);
    if ($self_id !== null && isset($names[$self_id]) && assets_name_key($names[$self_id]) === $wanted) {
        return false;
    }
    foreach ($names as $id => $other) {
        if ((int)$id !== $self_id && assets_name_key($other) === $wanted) {
            return true;
        }
    }
    return false;
}

/**
 * The name an edit stores, once assets_name_conflict() has passed: the
 * requested (trimmed) $name -- unless the column has a UNIQUE key
 * ($unique_key: the unencrypted install) and another row already holds the
 * same value under the column's collation (case and trailing space
 * insignificant, LEADING space significant). That only happens for a legacy
 * row such as " web01" saved under its own name while "web01" also exists;
 * writing "web01" would hit the key, so the stored spelling is kept. Pure.
 *
 * @param array<int,string> $names see assets_name_conflict()
 */
function assets_name_for_storage(array $names, string $name, int $self_id, bool $unique_key): string
{
    if (!$unique_key || !isset($names[$self_id])) {
        return $name;
    }
    $collation = static fn(string $value): string => mb_strtolower(rtrim($value));
    foreach ($names as $id => $other) {
        if ((int)$id !== $self_id && $collation((string)$other) === $collation($name)) {
            return $names[$self_id];
        }
    }
    return $name;
}

/**
 * The asset group name rule (SR-1881), shared by every path that creates or
 * renames a group: two names are the same name when they match after
 * assets_normalize_group_name() and case folding. Another group holding the
 * same name is a conflict; the group being edited is never compared with
 * itself, so it can always keep its own name.
 *
 * Stored rows are normalised here too rather than compared raw: the column's
 * UNIQUE key treats a leading space or a tab as significant, so rows such as
 * "Devices" and " Devices" can both exist (typically from an import or an
 * install that predates the key). When the edited group shares its name with
 * such a row, its stored spelling is kept -- writing the normalised name would
 * collide with the other row's key.
 *
 * @param array<int,array{id:int|string,name:string}> $groups every asset group
 * @param string   $name    the requested name, already normalised
 * @param int|null $self_id the group being edited; null on create
 * @return array{conflict:bool,name:string} name = what to store when no conflict
 */
function asset_group_name_check(array $groups, string $name, ?int $self_id): array
{
    $key = static fn($n): string => mb_strtolower(assets_normalize_group_name($n));
    $wanted = $key($name);

    $own_name = null;
    $others_match = false;
    foreach ($groups as $group) {
        if ($self_id !== null && (int)$group['id'] === $self_id) {
            $own_name = (string)$group['name'];
        } elseif ($key($group['name']) === $wanted) {
            $others_match = true;
        }
    }

    if ($own_name !== null && $key($own_name) === $wanted) {
        return ['conflict' => false, 'name' => $others_match ? $own_name : $name];
    }
    return ['conflict' => $others_match, 'name' => $name];
}

/** The most distinct controls one asset may be mapped to (POST/PATCH /assets). */
const ASSETS_MAX_MAPPED_CONTROLS = 500;

/**
 * The mapped-controls part of a POST/PATCH /assets body, parsed into the
 * [maturity => [control ids]] shape save_asset_to_controls() reads (asset
 * record modal, spec section 5a). The old builders produced [[maturity,
 * control]] rows, which that function read as maturity 0 => [maturity,
 * control] -- wrong rows, and no way to clear.
 *
 * Accepted forms (mapped_controls wins when both are present):
 *  - mapped_controls[]: JSON rows {"control_maturity": m, "control_id": [ids]}
 *    (the legacy Edit modal's shape) or the same rows as arrays;
 *  - control_maturity[] + control_id[] paired by index, where control_id[i]
 *    is one id, a comma-separated list, or an array of ids.
 *
 * status:
 *  - 'absent'   the body names neither key: leave the mapping alone;
 *  - 'clear'    ONLY an explicit empty marker (mapped_controls= /
 *               mapped_controls[]= / control_maturity[]= with no control):
 *               remove every mapping;
 *  - 'ok'       mapping holds at least one row;
 *  - 'invalid'  a malformed row (bad JSON, missing or non-numeric maturity,
 *               a control id that is not a positive integer), or rows were
 *               sent but none carries a control -- the caller answers 400 and
 *               writes nothing, so a broken client can never wipe a mapping;
 *  - 'too_many' more than $max_controls distinct controls.
 * A row with a valid maturity and no control at all is skipped (an unused
 * row in the form) as long as another row is usable. 0 is a real maturity.
 * Whether the ids exist is the caller's check (asset_control_mapping_
 * references_valid(), which needs the database).
 *
 * @param array<string,mixed> $body
 * @return array{status:string, mapping:array<int,int[]>}
 */
function assets_parse_mapped_controls_body(array $body, int $max_controls = ASSETS_MAX_MAPPED_CONTROLS): array
{
    $result = fn(string $status, array $mapping = []) => ['status' => $status, 'mapping' => $mapping];
    $is_blank = fn($v) => $v === null || (is_scalar($v) && trim((string)$v) === '')
        || (is_array($v) && array_filter($v, fn($x) => !(is_scalar($x) && trim((string)$x) === '')) === []);

    if (array_key_exists('mapped_controls', $body)) {
        if ($is_blank($body['mapped_controls'])) {
            return $result('clear');
        }
        $rows = [];
        foreach ((array)$body['mapped_controls'] as $row) {
            if (is_string($row)) {
                $row = json_decode($row, true);
            }
            if (!is_array($row) || !array_key_exists('control_maturity', $row)) {
                return $result('invalid');
            }
            $rows[] = [$row['control_maturity'], $row['control_id'] ?? []];
        }
    } elseif (array_key_exists('control_maturity', $body)) {
        if ($is_blank($body['control_maturity']) && $is_blank($body['control_id'] ?? null)) {
            return $result('clear');
        }
        $rows = [];
        $control_ids = (array)($body['control_id'] ?? []);
        foreach ((array)$body['control_maturity'] as $index => $maturity) {
            $rows[] = [$maturity, $control_ids[$index] ?? []];
        }
    } else {
        return $result('absent');
    }

    $mapping = [];
    foreach ($rows as [$maturity, $ids]) {
        if (!is_scalar($maturity) || !preg_match('/^\d+$/', trim((string)$maturity))) {
            return $result('invalid');
        }
        if (is_string($ids) || is_int($ids)) {
            $ids = explode(',', (string)$ids);
        }
        if (!is_array($ids)) {
            return $result('invalid');
        }
        $ids = array_values(array_filter($ids, fn($id) => !(is_scalar($id) && trim((string)$id) === '')));
        if ($ids === []) {
            continue;
        }
        foreach ($ids as $id) {
            if (!is_scalar($id) || !preg_match('/^\d+$/', trim((string)$id)) || (int)$id <= 0) {
                return $result('invalid');
            }
        }
        $clean = assets_positive_ids($ids);
        $maturity = (int)$maturity;
        $mapping[$maturity] = array_values(array_unique(array_merge($mapping[$maturity] ?? [], $clean)));
    }

    if ($mapping === []) {
        return $result('invalid');
    }
    if (count(array_unique(array_merge(...array_values($mapping)))) > $max_controls) {
        return $result('too_many');
    }
    return $result('ok', $mapping);
}

/**
 * Whether a POST/PATCH /assets body carries any mapped-controls input at all
 * -- rows, index pairs, or the explicit clear marker. Such a body needs the
 * Governance permission (control mappings are Governance data).
 *
 * @param array<string,mixed> $body
 */
function assets_body_has_mapping_input(array $body): bool
{
    return array_key_exists('mapped_controls', $body)
        || array_key_exists('control_maturity', $body)
        || array_key_exists('control_id', $body);
}

/**
 * Whether an asset write body names `associated_risks` at all, including an
 * explicit empty clear marker. Such a body needs the Risk Management
 * permission (SR-2313): a caller who may not see an asset's risks may not
 * change them either. A body that does not name the key leaves every link
 * alone.
 *
 * @param array<string,mixed> $body
 */
function assets_body_has_associated_risks_input(array $body): bool
{
    return array_key_exists('associated_risks', $body);
}

/**
 * Whether the caller may see (and choose) the risks associated with an asset:
 * the Risk Management permission (SR-2313). Which of those risks it sees is
 * still narrowed per risk by Team Separation (filter_accessible_risk_ids()).
 */
function assets_caller_can_see_associated_risks(): bool
{
    return (bool)check_permission('riskmanagement');
}

/**
 * Positive integer ids from an array, a comma-separated string or a single
 * scalar, in first-seen order, de-duplicated. Anything else is dropped.
 *
 * @param mixed $value
 * @return int[]
 */
function assets_positive_ids($value): array
{
    if (is_string($value) || is_int($value)) {
        $value = explode(',', (string)$value);
    }
    if (!is_array($value)) {
        return [];
    }
    $ids = [];
    foreach ($value as $item) {
        if (is_scalar($item) && preg_match('/^\d+$/', trim((string)$item)) && (int)$item > 0) {
            $ids[] = (int)$item;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * The CSV the assets.location / assets.teams columns store, from an array or
 * a CSV string (spec section 5b: update_asset() used to bind the raw array).
 * null means "not supplied" and stays null; an empty marker gives ''.
 *
 * @param mixed $value
 */
function assets_id_csv($value): ?string
{
    if ($value === null) {
        return null;
    }
    return implode(',', assets_positive_ids($value));
}

/**
 * Splits a custom_field[<id>] => value body against the asset custom fields
 * the caller may write ($fields_by_id: non-basic asset fields of the asset's
 * template group, keyed by id, each with `required`). Returns the values for
 * known fields, the ids that are NOT in that set (the caller refuses the
 * write rather than dropping them silently), and whether a required field was
 * sent empty. "Empty" is save_custom_field_values()'s own rule -- empty() or
 * blank after trim, so a required "0" is empty too -- so a value this accepts
 * is never refused by the save afterwards. An omitted field is simply absent:
 * PATCH leaves it alone. A multi-value empty marker ([''] ) becomes [].
 *
 * @param mixed $input
 * @param array<int,array<string,mixed>> $fields_by_id
 * @return array{values: array<int,mixed>, missing_required: bool, unknown: int[]}
 */
function assets_split_custom_field_input($input, array $fields_by_id): array
{
    $values = [];
    $unknown = [];
    $missing_required = false;
    if (!is_array($input)) {
        return ['values' => $values, 'missing_required' => $missing_required, 'unknown' => $unknown];
    }

    foreach ($input as $field_id => $value) {
        $field_id = (int)$field_id;
        if (!isset($fields_by_id[$field_id])) {
            $unknown[] = $field_id;
            continue;
        }
        if (is_array($value)) {
            $value = array_values(array_filter($value, fn($v) => is_scalar($v) && trim((string)$v) !== ''));
            $empty = $value === [];
        } elseif (is_scalar($value) || $value === null) {
            $value = (string)$value;
            $empty = empty($value) || trim($value) === '';
        } else {
            $unknown[] = $field_id;
            continue;
        }
        if ($empty && (int)($fields_by_id[$field_id]['required'] ?? 0) === 1) {
            $missing_required = true;
        }
        $values[$field_id] = $value;
    }

    return ['values' => $values, 'missing_required' => $missing_required, 'unknown' => $unknown];
}

/**
 * The lang key of the 400 an assets_split_custom_field_input() result earns,
 * or null to proceed. An id outside the asset's template group is refused
 * only while the Customization Extra is active: without it there are no
 * custom fields at all, and Core-only installs keep ignoring custom_field
 * the way they always have.
 *
 * @param array{values: array<int,mixed>, missing_required: bool, unknown: int[]} $split
 */
function assets_custom_field_rejection(array $split, bool $customization_active): ?string
{
    if (!$customization_active) {
        return null;
    }
    if (!empty($split['unknown'])) {
        return 'AssetCustomFieldNotInTemplate';
    }
    if (!empty($split['missing_required'])) {
        return 'ThereAreRequiredFields';
    }
    return null;
}
