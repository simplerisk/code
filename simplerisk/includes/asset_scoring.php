<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Asset Scoring (docs/superpowers/specs/2026-09-30-asset-scoring-design.md):
 * an asset's FIPS 199 potential impact per security objective, and the two
 * results derived from those three selections -- the FIPS categorization
 * (high-water mark) and a weighted score with its Low/Moderate/High band.
 *
 * Only the selections are stored (assets.confidentiality/integrity/
 * availability). Every result is computed on read by asset_scoring_compute(),
 * the single source of truth: the Manage assets list filters and sorts with
 * it in PHP (assets_list.php), the record modal shows it, Import-Export
 * exports it. Every quantity is an integer number of hundredths (1.67 => 167)
 * so the arithmetic is exact and matches the record modal's JS mirror
 * (asset-card-profile.js) case for case -- both run the shared table
 * tests/web-e2e/src/data/asset-management/asset-scoring-cases.json.
 */

require_once(realpath(__DIR__ . '/functions.php'));

const ASSET_SCORING_OBJECTIVES = ['confidentiality', 'integrity', 'availability'];
const ASSET_SCORING_LEVELS = ['low', 'moderate', 'high'];
const ASSET_SCORING_NOT_APPLICABLE = 'not_applicable';
/** Largest weight / level value / threshold, in hundredths (100.00). */
const ASSET_SCORING_MAX_HUNDREDTHS = 10000;
/** Manage assets columns computed from the selections. */
const ASSET_SCORING_RESULT_COLUMNS = ['fips_categorization', 'weighted_score', 'weighted_band'];
/** Every Manage assets column Asset Scoring adds, in picker order. */
const ASSET_SCORING_COLUMNS = ['confidentiality', 'integrity', 'availability', 'fips_categorization', 'weighted_score', 'weighted_band'];

/** low 1, moderate 2, high 3; anything else (not applicable included) null. */
function asset_scoring_level_rank($level): ?int
{
    $index = array_search($level, ASSET_SCORING_LEVELS, true);
    return $index === false ? null : $index + 1;
}

function asset_scoring_level_for_rank(int $rank): ?string
{
    return ASSET_SCORING_LEVELS[$rank - 1] ?? null;
}

/**
 * Filter id of the confidentiality-only "not applicable" rating on Manage
 * assets (the Confidentiality filter, GET /assets?confidentiality=). The
 * levels keep their rank as id (1 Low, 2 Moderate, 3 High); not applicable
 * is 0, the rank it sorts at (asset_scoring_sort_key()).
 */
const ASSET_SCORING_NOT_APPLICABLE_ID = 0;

/** Filter id of a stored rating code (see ASSET_SCORING_NOT_APPLICABLE_ID), or null (unset / unknown). */
function asset_scoring_filter_id($code): ?int
{
    return $code === ASSET_SCORING_NOT_APPLICABLE ? ASSET_SCORING_NOT_APPLICABLE_ID : asset_scoring_level_rank($code);
}

/**
 * The filter ids one objective offers, in option order: Low, Moderate, High,
 * then Not applicable for confidentiality only.
 *
 * @return int[]
 */
function asset_scoring_filter_ids(string $objective): array
{
    return array_values(array_map('asset_scoring_filter_id', asset_scoring_allowed_values($objective)));
}

/**
 * Whether an assets row passes the confidentiality / integrity / availability
 * filters ($filter[objective] = filter ids, any match; an empty list does not
 * filter). The filters combine with AND, and an objective that is not set (or
 * holds a value it does not accept) never matches a filter on it. Pure.
 *
 * @param array<string,mixed> $row
 * @param array<string,int[]> $filter
 */
function asset_scoring_row_matches_objective_filters(array $row, array $filter): bool
{
    $selections = asset_scoring_normalize_selections($row);
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $wanted = $filter[$objective] ?? [];
        if (!$wanted) {
            continue;
        }
        $id = asset_scoring_filter_id($selections[$objective]);
        if ($id === null || !in_array($id, $wanted, true)) {
            return false;
        }
    }
    return true;
}

/**
 * The options of one objective's Manage assets filter: {id, key, name} in
 * asset_scoring_filter_ids() order. name is RAW text (the page inserts it
 * with text setters).
 *
 * @return array<int,array{id:int,key:string,name:string}>
 */
function asset_scoring_filter_levels(string $objective): array
{
    $labels = asset_scoring_level_labels();
    $out = [];
    foreach (asset_scoring_allowed_values($objective) as $code) {
        $out[] = ['id' => (int)asset_scoring_filter_id($code), 'key' => $code, 'name' => $labels[$code]];
    }
    return $out;
}

/** The codes one objective accepts: only confidentiality may be not applicable (FIPS 199 fn. 4). */
function asset_scoring_allowed_values(string $objective): array
{
    return $objective === 'confidentiality'
        ? array_merge(ASSET_SCORING_LEVELS, [ASSET_SCORING_NOT_APPLICABLE])
        : ASSET_SCORING_LEVELS;
}

/** Each objective of $row as a valid code, or null (not set / unreadable). */
function asset_scoring_normalize_selections(array $row): array
{
    $out = [];
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $value = $row[$objective] ?? null;
        $out[$objective] = (is_string($value) && in_array($value, asset_scoring_allowed_values($objective), true)) ? $value : null;
    }
    return $out;
}

/**
 * "1", "1.5", "1.50" => 100, 150, 150. Up to three integer digits and two
 * decimals, at most ASSET_SCORING_MAX_HUNDREDTHS; anything else is null.
 * Floats are refused on purpose: settings and form posts arrive as strings.
 */
function asset_scoring_parse_hundredths($raw): ?int
{
    if (is_int($raw)) {
        $raw = (string)$raw;
    }
    if (!is_string($raw) || !preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', trim($raw), $m)) {
        return null;
    }
    $hundredths = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
    return $hundredths <= ASSET_SCORING_MAX_HUNDREDTHS ? $hundredths : null;
}

function asset_scoring_format_hundredths(int $hundredths): string
{
    return intdiv($hundredths, 100) . '.' . str_pad((string)($hundredths % 100), 2, '0', STR_PAD_LEFT);
}

/** Spec defaults: High 3 / Moderate 2 / Low 1, weights 1/1/1, bands at 1.67 and 2.34, no default scoring. */
function asset_scoring_default_settings(): array
{
    return [
        'weights' => ['confidentiality' => 100, 'integrity' => 100, 'availability' => 100],
        'values' => ['low' => 100, 'moderate' => 200, 'high' => 300],
        'thresholds' => ['moderate' => 167, 'high' => 234],
        'defaults' => ['confidentiality' => null, 'integrity' => null, 'availability' => null],
    ];
}

/** The `settings` rows, in the order asset_scoring_settings_to_rows() writes them. */
function asset_scoring_setting_names(): array
{
    $names = [];
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $names[] = "asset_scoring_weight_{$objective}";
    }
    foreach (ASSET_SCORING_LEVELS as $level) {
        $names[] = "asset_scoring_value_{$level}";
    }
    $names[] = 'asset_scoring_threshold_moderate';
    $names[] = 'asset_scoring_threshold_high';
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $names[] = "asset_scoring_default_{$objective}";
    }
    return $names;
}

/**
 * Null when $s is usable, else the lang key of what is wrong. Rulings G3/G4:
 * weights 0-100 with Integrity + Availability > 0 (a not-applicable
 * Confidentiality must not zero the denominator); values 0.01-100 with
 * Low < Moderate < High; V(Low) < Moderate threshold < High threshold <= V(High).
 */
function asset_scoring_validate_settings(array $s): ?string
{
    $in_range = static fn($v, int $min): bool => is_int($v) && $v >= $min && $v <= ASSET_SCORING_MAX_HUNDREDTHS;

    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        if (!$in_range($s['weights'][$objective] ?? null, 0)) {
            return 'AssetScoringWeightsInvalid';
        }
    }
    if ($s['weights']['integrity'] + $s['weights']['availability'] <= 0) {
        return 'AssetScoringWeightsInvalid';
    }

    foreach (ASSET_SCORING_LEVELS as $level) {
        if (!$in_range($s['values'][$level] ?? null, 1)) {
            return 'AssetScoringValuesInvalid';
        }
    }
    $v = $s['values'];
    if (!($v['low'] < $v['moderate'] && $v['moderate'] < $v['high'])) {
        return 'AssetScoringValuesInvalid';
    }

    $moderate = $s['thresholds']['moderate'] ?? null;
    $high = $s['thresholds']['high'] ?? null;
    if (!is_int($moderate) || !is_int($high) || !($v['low'] < $moderate && $moderate < $high && $high <= $v['high'])) {
        return 'AssetScoringThresholdsInvalid';
    }

    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $default = $s['defaults'][$objective] ?? null;
        if ($default !== null && !in_array($default, asset_scoring_allowed_values($objective), true)) {
            return 'AssetScoringDefaultsInvalid';
        }
    }
    return null;
}

/**
 * Settings from `settings` rows (name => value). An absent or unreadable row
 * keeps its default; a default-for-new that is not a valid code is null.
 * When the combination does not validate, the whole default set is returned
 * and $fell_back is set (the caller logs it).
 */
function asset_scoring_settings_from_rows(array $rows, ?bool &$fell_back = null): array
{
    $fell_back = false;
    $s = asset_scoring_default_settings();
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $h = asset_scoring_parse_hundredths($rows["asset_scoring_weight_{$objective}"] ?? null);
        if ($h !== null) {
            $s['weights'][$objective] = $h;
        }
        $default = $rows["asset_scoring_default_{$objective}"] ?? null;
        $s['defaults'][$objective] = (is_string($default) && in_array($default, asset_scoring_allowed_values($objective), true)) ? $default : null;
    }
    foreach (ASSET_SCORING_LEVELS as $level) {
        $h = asset_scoring_parse_hundredths($rows["asset_scoring_value_{$level}"] ?? null);
        if ($h !== null) {
            $s['values'][$level] = $h;
        }
    }
    foreach (['moderate', 'high'] as $band) {
        $h = asset_scoring_parse_hundredths($rows["asset_scoring_threshold_{$band}"] ?? null);
        if ($h !== null) {
            $s['thresholds'][$band] = $h;
        }
    }
    if (asset_scoring_validate_settings($s) !== null) {
        $fell_back = true;
        return asset_scoring_default_settings();
    }
    return $s;
}

/**
 * The Preferences post ($_POST['asset_scoring']: weight[], value[],
 * threshold[], default[]) as settings, or the lang key of the first problem.
 * A post without the section (not an array) is "not submitted".
 *
 * @return array{settings:?array, error:?string}
 */
function asset_scoring_settings_from_post($post): array
{
    if (!is_array($post)) {
        return ['settings' => null, 'error' => null];
    }
    $number = static fn($group, $key) => asset_scoring_parse_hundredths(is_array($post[$group] ?? null) ? ($post[$group][$key] ?? null) : null);
    $s = asset_scoring_default_settings();

    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $h = $number('weight', $objective);
        if ($h === null) {
            return ['settings' => null, 'error' => 'AssetScoringWeightsInvalid'];
        }
        $s['weights'][$objective] = $h;
    }
    foreach (ASSET_SCORING_LEVELS as $level) {
        $h = $number('value', $level);
        if ($h === null) {
            return ['settings' => null, 'error' => 'AssetScoringValuesInvalid'];
        }
        $s['values'][$level] = $h;
    }
    foreach (['moderate', 'high'] as $band) {
        $h = $number('threshold', $band);
        if ($h === null) {
            return ['settings' => null, 'error' => 'AssetScoringThresholdsInvalid'];
        }
        $s['thresholds'][$band] = $h;
    }
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $raw = is_array($post['default'] ?? null) ? ($post['default'][$objective] ?? '') : '';
        if (!is_string($raw)) {
            return ['settings' => null, 'error' => 'AssetScoringDefaultsInvalid'];
        }
        $s['defaults'][$objective] = trim($raw) === '' ? null : trim($raw);
    }

    $error = asset_scoring_validate_settings($s);
    return $error === null ? ['settings' => $s, 'error' => null] : ['settings' => null, 'error' => $error];
}

/** name => stored string for every setting ('' for a default-for-new that is off). */
function asset_scoring_settings_to_rows(array $s): array
{
    $rows = [];
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $rows["asset_scoring_weight_{$objective}"] = asset_scoring_format_hundredths($s['weights'][$objective]);
    }
    foreach (ASSET_SCORING_LEVELS as $level) {
        $rows["asset_scoring_value_{$level}"] = asset_scoring_format_hundredths($s['values'][$level]);
    }
    $rows['asset_scoring_threshold_moderate'] = asset_scoring_format_hundredths($s['thresholds']['moderate']);
    $rows['asset_scoring_threshold_high'] = asset_scoring_format_hundredths($s['thresholds']['high']);
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $rows["asset_scoring_default_{$objective}"] = (string)($s['defaults'][$objective] ?? '');
    }
    return $rows;
}

/**
 * Structural check only (every weight, level value and threshold present as an
 * int), so compute never reads a missing key or turns null into a confident 0.
 * Value rules stay in asset_scoring_validate_settings(); compute deliberately
 * still copes with a zero denominator.
 */
function asset_scoring_settings_usable(array $settings): bool
{
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        if (!is_int($settings['weights'][$objective] ?? null)) {
            return false;
        }
    }
    foreach (ASSET_SCORING_LEVELS as $level) {
        if (!is_int($settings['values'][$level] ?? null)) {
            return false;
        }
    }
    return is_int($settings['thresholds']['moderate'] ?? null) && is_int($settings['thresholds']['high'] ?? null);
}

/**
 * The FIPS 199 categorization (high-water mark over the answered objectives,
 * not applicable ignored) and the weighted score and band. Nothing is scored
 * unless all three objectives are answered. Score rounding is half up on
 * exact integers (ruling G2); a zero denominator (only possible with settings
 * that fail validation) yields no score and no band (ruling G3).
 *
 * @return array{scored:bool, categorization:?string, score_hundredths:?int, score:?string, band:?string}
 */
function asset_scoring_compute(array $selections, array $settings): array
{
    $sel = asset_scoring_normalize_selections($selections);
    if (!asset_scoring_settings_usable($settings)) {
        return ['scored' => false, 'categorization' => null, 'score_hundredths' => null, 'score' => null, 'band' => null];
    }
    $none = ['scored' => false, 'categorization' => null, 'score_hundredths' => null, 'score' => null, 'band' => null];
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        if ($sel[$objective] === null) {
            return $none;
        }
    }

    $top = 0;
    $numerator = 0;
    $denominator = 0;
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        $rank = asset_scoring_level_rank($sel[$objective]);
        if ($rank === null) {
            continue; // not applicable: out of the high-water mark, and its weight drops out
        }
        $top = max($top, $rank);
        $weight = (int)$settings['weights'][$objective];
        $numerator += $weight * (int)$settings['values'][$sel[$objective]];
        $denominator += $weight;
    }

    $result = ['scored' => true, 'categorization' => asset_scoring_level_for_rank($top), 'score_hundredths' => null, 'score' => null, 'band' => null];
    if ($denominator > 0) {
        $hundredths = intdiv(2 * $numerator + $denominator, 2 * $denominator);
        $result['score_hundredths'] = $hundredths;
        $result['score'] = asset_scoring_format_hundredths($hundredths);
        $result['band'] = $hundredths >= (int)$settings['thresholds']['high'] ? 'high'
            : ($hundredths >= (int)$settings['thresholds']['moderate'] ? 'moderate' : 'low');
    }
    return $result;
}

/**
 * Integer sort key of one Manage assets scoring column for an assets row, or
 * null when the row has no value there (the caller sorts nulls last in both
 * directions). Not applicable sorts below Low.
 */
function asset_scoring_sort_key(string $column, array $row, array $settings): ?int
{
    if (in_array($column, ASSET_SCORING_OBJECTIVES, true)) {
        $value = asset_scoring_normalize_selections($row)[$column];
        if ($value === null) {
            return null;
        }
        return $value === ASSET_SCORING_NOT_APPLICABLE ? 0 : asset_scoring_level_rank($value);
    }
    if (!in_array($column, ASSET_SCORING_RESULT_COLUMNS, true)) {
        return null;
    }
    $r = asset_scoring_compute($row, $settings);
    switch ($column) {
        case 'fips_categorization':
            return asset_scoring_level_rank($r['categorization']);
        case 'weighted_score':
            return $r['score_hundredths'];
        default:
            return asset_scoring_level_rank($r['band']);
    }
}

/**
 * The scoring part of a POST/PATCH /assets body: only the objectives the body
 * names; null (or '' after trimming) clears; a non-string or a code the
 * objective does not accept is 'AssetScoringValueInvalid' (the handler
 * answers 400 before anything is written).
 *
 * @return array{error:?string, selections:array<string,?string>}
 */
function asset_scoring_parse_request(array $body): array
{
    $selections = [];
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        if (!array_key_exists($objective, $body)) {
            continue;
        }
        $raw = $body[$objective];
        if ($raw === null) {
            $selections[$objective] = null;
            continue;
        }
        if (!is_string($raw)) {
            return ['error' => 'AssetScoringValueInvalid', 'selections' => []];
        }
        $value = trim($raw);
        if ($value === '') {
            $selections[$objective] = null;
        } elseif (in_array($value, asset_scoring_allowed_values($objective), true)) {
            $selections[$objective] = $value;
        } else {
            return ['error' => 'AssetScoringValueInvalid', 'selections' => []];
        }
    }
    return ['error' => null, 'selections' => $selections];
}

/** [objective => {from, to}] for every objective $incoming names whose value differs from $current. */
function asset_scoring_diff(array $current, array $incoming): array
{
    $current = asset_scoring_normalize_selections($current);
    $changes = [];
    foreach (ASSET_SCORING_OBJECTIVES as $objective) {
        if (!array_key_exists($objective, $incoming)) {
            continue;
        }
        $to = $incoming[$objective];
        if ($to !== null && !in_array($to, asset_scoring_allowed_values($objective), true)) {
            continue; // asset_scoring_parse_request() refuses these; never write one
        }
        if ($to !== $current[$objective]) {
            $changes[$objective] = ['from' => $current[$objective], 'to' => $to];
        }
    }
    return $changes;
}

/**
 * One imported cell: a code ('low', 'not_applicable', ...) or its label in
 * $labels (code => label), case-insensitive; blank is null (not set); anything
 * else is false (the importer reports it and leaves the value alone).
 *
 * @return string|null|false
 */
function asset_scoring_parse_import_value($raw, string $objective, array $labels)
{
    if ($raw === null || !is_scalar($raw)) {
        return null;
    }
    $text = mb_strtolower(trim((string)$raw));
    if ($text === '') {
        return null;
    }
    foreach (asset_scoring_allowed_values($objective) as $code) {
        if ($text === $code || (isset($labels[$code]) && is_scalar($labels[$code]) && $text === mb_strtolower(trim((string)$labels[$code])))) {
            return $code;
        }
    }
    return false;
}

/**
 * Whether assets has the three scoring columns (the upgrade has run). Code
 * that is deployed before the database upgrade reads no scoring column and
 * writes none. Cached per request; $refresh re-checks (tests).
 */
function asset_scoring_schema_ready(bool $refresh = false): bool
{
    $ready = &asset_scoring_schema_ready_slot();
    if ($ready === null || $refresh) {
        $ready = true;
        foreach (ASSET_SCORING_OBJECTIVES as $objective) {
            if (!field_exists_in_table($objective, 'assets')) {
                $ready = false;
                break;
            }
        }
    }
    return $ready;
}

/**
 * The request-level cache slot of asset_scoring_schema_ready() (by
 * reference): null = not checked yet. Tests set it to false to stand in for
 * code deployed before the database upgrade, and back to null afterwards.
 */
function &asset_scoring_schema_ready_slot(): ?bool
{
    static $slot = null;
    return $slot;
}

/**
 * Whether a POST/PATCH body's scoring selections may be written: a body that
 * names any objective (a clear included) while the columns are missing is
 * refused with this lang key BEFORE anything is written, so the caller never
 * gets a success for a write that did not happen. A body without scoring is
 * never refused. Pure.
 *
 * @param array<string,?string> $selections asset_scoring_parse_request()'s selections
 */
function asset_scoring_write_refusal(array $selections, bool $schema_ready): ?string
{
    return $selections && !$schema_ready ? 'AssetScoringUpgradePending' : null;
}

/**
 * Logs $message at $level the first time $key is seen in this process, and
 * returns whether it logged. For persistent conditions that are re-checked on
 * every request or write (a pending upgrade, an inconsistent stored settings
 * set): one line per process, not one per call.
 */
function asset_scoring_log_once(string $key, string $message, string $level): bool
{
    static $logged = [];
    if (isset($logged[$key])) {
        return false;
    }
    $logged[$key] = true;
    write_debug_log($message, $level);
    return true;
}

/**
 * The live settings (one query per request). An inconsistent stored set logs
 * a notice (once per process) and uses the defaults. Cached for the request,
 * so list rendering may call this per row; $refresh re-reads.
 * A writer (the Preferences save) calls asset_scoring_settings_reset_cache().
 */
function asset_scoring_settings(bool $refresh = false): array
{
    $cache = &asset_scoring_settings_cache_slot();
    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $names = asset_scoring_setting_names();
    $db = db_open();
    $stmt = $db->prepare('SELECT `name`, `value` FROM `settings` WHERE `name` IN (' . implode(',', array_fill(0, count($names), '?')) . ')');
    $stmt->execute($names);
    $rows = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'value', 'name');
    db_close($db);

    $fell_back = false;
    $settings = asset_scoring_settings_from_rows($rows, $fell_back);
    if ($fell_back) {
        // A persistent administrative configuration gap, re-checked on every
        // request: notice, once per process (choosing-log-levels).
        asset_scoring_log_once('settings_inconsistent', 'Asset scoring: the stored weights, level values or band thresholds are inconsistent; the defaults are used until they are saved again on the Preferences page.', 'notice');
    }
    $cache = $settings;
    return $settings;
}

/** The request-level cache slot of asset_scoring_settings() (by reference). */
function &asset_scoring_settings_cache_slot(): ?array
{
    static $slot = null;
    return $slot;
}

/** Forgets the cached settings: after saving them, and between tests. */
function asset_scoring_settings_reset_cache(): void
{
    $slot = &asset_scoring_settings_cache_slot();
    $slot = null;
}

/** code => label (RAW text; the consumer escapes once) for every selectable code. */
function asset_scoring_level_labels(): array
{
    global $lang;
    return [
        'low' => (string)$lang['AssetScoringLevelLow'],
        'moderate' => (string)$lang['AssetScoringLevelModerate'],
        'high' => (string)$lang['AssetScoringLevelHigh'],
        ASSET_SCORING_NOT_APPLICABLE => (string)$lang['ApplicabilityNotApplicable'],
    ];
}

/** objective => label (RAW text). */
function asset_scoring_objective_labels(): array
{
    global $lang;
    return [
        'confidentiality' => (string)$lang['Confidentiality'],
        'integrity' => (string)$lang['Integrity'],
        'availability' => (string)$lang['Availability'],
    ];
}

/**
 * Writes the named selections of one asset (only columns that change) and,
 * with $log, one audit line per change. The caller has already validated the
 * values (asset_scoring_parse_request()) and the access (check_access_for_asset()),
 * and refused the write while the columns are missing
 * (asset_scoring_write_refusal()). Returns the changes applied, or null when
 * the columns do not exist yet -- callers treat that as a failed write. A
 * database error propagates.
 *
 * @param array<string,?string> $selections
 * @return array<string,array{from:?string,to:?string}>|null
 */
function asset_scoring_apply(int $asset_id, array $selections, bool $log = true): ?array
{
    if (!asset_scoring_schema_ready()) {
        asset_scoring_log_once('schema_pending', 'Asset scoring: the assets table has no scoring columns yet, so scoring selections are refused and not saved. Run the SimpleRisk database upgrade.', 'notice');
        return null;
    }
    if (!array_intersect_key($selections, array_flip(ASSET_SCORING_OBJECTIVES))) {
        return [];
    }

    $db = db_open();
    $stmt = $db->prepare('SELECT `name`, `confidentiality`, `integrity`, `availability` FROM `assets` WHERE `id` = :id');
    $stmt->bindValue(':id', $asset_id, PDO::PARAM_INT);
    $stmt->execute();
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        db_close($db);
        return [];
    }

    $changes = asset_scoring_diff($current, $selections);
    if ($changes) {
        // Column names come from ASSET_SCORING_OBJECTIVES via asset_scoring_diff() only.
        $sets = [];
        foreach (array_keys($changes) as $objective) {
            $sets[] = "`{$objective}` = :{$objective}";
        }
        $update = $db->prepare('UPDATE `assets` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
        foreach ($changes as $objective => $change) {
            $update->bindValue(":{$objective}", $change['to'], $change['to'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $update->bindValue(':id', $asset_id, PDO::PARAM_INT);
        $update->execute();
    }
    db_close($db);

    if ($log && $changes) {
        global $lang;
        $levels = asset_scoring_level_labels();
        $objectives = asset_scoring_objective_labels();
        $name = (string)try_decrypt((string)$current['name']);
        foreach ($changes as $objective => $change) {
            // _lang() escapes every parameter once; do not pre-escape (CLAUDE.md).
            $message = _lang('AssetScoringChangedLog', [
                'name' => $name,
                'objective' => $objectives[$objective],
                'from' => $change['from'] === null ? $lang['AssetScoringNotSet'] : $levels[$change['from']],
                'to' => $change['to'] === null ? $lang['AssetScoringNotSet'] : $levels[$change['to']],
                'user' => $_SESSION['user'] ?? '',
            ]);
            write_log($asset_id, $_SESSION['uid'] ?? 0, $message, 'asset');
        }
    }
    return $changes;
}

/**
 * Saves the Preferences "Asset Scoring" section ($_POST['asset_scoring']).
 *
 * Validated as a whole: an invalid set writes nothing and returns the lang key
 * of the problem.
 *
 * "Changed" means the submitted set differs from the EFFECTIVE stored set --
 * asset_scoring_settings_from_rows() over the raw rows, so an absent row is its
 * default. Re-submitting what is in effect (including the spec defaults on an
 * instance whose rows were never written) writes nothing, adds no audit line
 * and returns changed=false, so the page says "No changes were made". A stored
 * set that is inconsistent (the loader fell back to the defaults) always counts
 * as changed: saving it is a repair.
 *
 * When changed, every row whose raw stored value differs from the target is
 * written (an absent row always is, so after a real change the whole set is
 * stored), in one transaction: a database failure part-way leaves every row as
 * it was and returns 'AssetScoringSettingsNotSaved'. The rows go through
 * update_or_insert_setting(), not update_setting(), whose own audit line per
 * setting would make it up to eleven; this writes ONE line (log type
 * asset_settings, never 'asset': it must not appear in an asset's own trail).
 * The per-request settings cache is reset either way. The default scoring for
 * new assets is only stored here; nothing on the server applies it (ruling G5:
 * it pre-fills the Add form). Admin-only: settings_preferences.php is
 * check_admin.
 *
 * @return array{changed:bool, error:?string}
 */
function asset_scoring_save_settings($post): array
{
    $parsed = asset_scoring_settings_from_post($post);
    if ($parsed['settings'] === null) {
        return ['changed' => false, 'error' => $parsed['error']];
    }
    $submitted = $parsed['settings'];

    $names = asset_scoring_setting_names();
    $db = db_open();
    // Uncached raw rows: the comparison must see what is stored, not this request's caches.
    $stmt = $db->prepare('SELECT `name`, `value` FROM `settings` WHERE `name` IN (' . implode(',', array_fill(0, count($names), '?')) . ')');
    $stmt->execute($names);
    $stored = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'value', 'name');

    $fell_back = false;
    $effective = asset_scoring_settings_from_rows($stored, $fell_back);
    if (!$fell_back && $effective === $submitted) {
        db_close($db);
        return ['changed' => false, 'error' => null];
    }

    $pending = [];
    foreach (asset_scoring_settings_to_rows($submitted) as $name => $value) {
        if (!array_key_exists($name, $stored) || (string)$stored[$name] !== $value) {
            $pending[$name] = $value;
        }
    }

    // Only a transaction this function started is committed or rolled back:
    // a caller's own transaction on the shared connection is never touched.
    $started = false;
    $saved = false;
    try {
        $db->beginTransaction();
        $started = true;
        $saved = true;
        foreach ($pending as $name => $value) {
            if (!update_or_insert_setting($name, $value, $db)) {
                $saved = false;
                break;
            }
        }
        if ($saved) {
            $db->commit();
        } else {
            $db->rollBack();
        }
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) {
            $db->rollBack();
        }
        $saved = false;
        write_debug_log('Asset scoring: saving the Preferences settings failed: ' . $e->getMessage(), 'error');
    }
    db_close($db);

    // update_or_insert_setting() primes the per-setting cache even when the
    // transaction is rolled back, so drop those entries whatever happened.
    foreach (array_keys($pending) as $name) {
        unset($GLOBALS['setting_' . $name]);
    }
    asset_scoring_settings_reset_cache();

    if (!$saved) {
        write_debug_log('Asset scoring: the Preferences settings were not saved; no row was changed.', 'error');
        return ['changed' => false, 'error' => 'AssetScoringSettingsNotSaved'];
    }

    // _lang() escapes the user name once; the audit view purifies (CLAUDE.md).
    write_log(1000, $_SESSION['uid'] ?? 0, _lang('AssetScoringSettingsChangedLog', ['user' => $_SESSION['user'] ?? '']), 'asset_settings');
    return ['changed' => true, 'error' => null];
}
