<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Lookup value lists managed from Admin > Add and Remove Values (SR-2475).
 *
 * This file is the single home of the list configuration (which lists exist,
 * their group, length limit, custom hooks and where each list is referenced)
 * and of the operations the v2 API exposes: usage counting, add, rename and
 * delete with an optional reassignment of the records that use the value.
 *
 * Every table and column name interpolated into SQL below comes from the
 * configuration in lookup_value_lists(), never from a request. The request only
 * ever supplies a list key (checked against the configuration), an integer id
 * and a name (always bound).
 */

require_once(realpath(__DIR__ . '/functions.php'));

/**
 * The lists, keyed by table name.
 *
 *  group        language key of the group the list is shown under
 *  headerKey    language key of the list's name
 *  lengthLimit  maximum length of a value's name
 *  customAdd / customUpdate / customDelete  existing hooks that keep their
 *               list-specific behaviour (ordering, family bookkeeping, ...)
 *  usage        where a value is referenced. type: 'id' (a column that holds the
 *               value's id), 'name' (a column that holds the value's name),
 *               'junction' (a row per association, with a composite key; moving
 *               one must not create a duplicate pair) or 'csv' (a comma
 *               separated list of ids in a text column)
 *  nameLimit    a tighter limit imposed by a referencing column that stores the name
 *
 * @return array<string, array<string, mixed>>
 */
function lookup_value_lists(): array
{
    return [
        // Risk management
        'category' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'Category', 'lengthLimit' => 50,
            'usage' => [['entity' => 'Risks', 'table' => 'risks', 'column' => 'category', 'type' => 'id']],
        ],
        'source' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'RiskSource', 'lengthLimit' => 50,
            'usage' => [['entity' => 'Risks', 'table' => 'risks', 'column' => 'source', 'type' => 'id']],
        ],
        'status' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'Status', 'lengthLimit' => 50,
            // risks.status is a varchar(20) holding the status NAME, not its id
            'nameLimit' => 20,
            'usage' => [['entity' => 'Risks', 'table' => 'risks', 'column' => 'status', 'type' => 'name']],
        ],
        'review' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'Review', 'lengthLimit' => 50,
            'usage' => [['entity' => 'LookupEntityReviews', 'table' => 'mgmt_reviews', 'column' => 'review', 'type' => 'id']],
        ],
        'next_step' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'NextStep', 'lengthLimit' => 50,
            'usage' => [['entity' => 'LookupEntityReviews', 'table' => 'mgmt_reviews', 'column' => 'next_step', 'type' => 'id']],
        ],
        'planning_strategy' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'RiskPlanningStrategy', 'lengthLimit' => 20,
            'usage' => [['entity' => 'LookupEntityMitigations', 'table' => 'mitigations', 'column' => 'planning_strategy', 'type' => 'id']],
        ],
        'close_reason' => [
            'group' => 'LookupGroupRiskManagement', 'headerKey' => 'CloseReason', 'lengthLimit' => 50,
            'usage' => [['entity' => 'LookupEntityClosures', 'table' => 'closures', 'column' => 'close_reason', 'type' => 'id']],
        ],

        // Controls and documents
        'control_class' => [
            'group' => 'LookupGroupControlsDocuments', 'headerKey' => 'ControlClass', 'lengthLimit' => 200,
            'usage' => [['entity' => 'Controls', 'table' => 'framework_controls', 'column' => 'control_class', 'type' => 'id']],
        ],
        'control_phase' => [
            'group' => 'LookupGroupControlsDocuments', 'headerKey' => 'ControlPhase', 'lengthLimit' => 200,
            'usage' => [['entity' => 'Controls', 'table' => 'framework_controls', 'column' => 'control_phase', 'type' => 'id']],
        ],
        'control_priority' => [
            'group' => 'LookupGroupControlsDocuments', 'headerKey' => 'ControlPriority', 'lengthLimit' => 20,
            'usage' => [['entity' => 'Controls', 'table' => 'framework_controls', 'column' => 'control_priority', 'type' => 'id']],
        ],
        'family' => [
            'group' => 'LookupGroupControlsDocuments', 'headerKey' => 'ControlFamily', 'lengthLimit' => 100,
            'customAdd' => 'add_family', 'customUpdate' => 'update_family', 'customDelete' => 'delete_family',
            'usage' => [['entity' => 'Controls', 'table' => 'framework_controls', 'column' => 'family', 'type' => 'id']],
        ],
        'document_status' => [
            'group' => 'LookupGroupControlsDocuments', 'headerKey' => 'DocumentStatus', 'lengthLimit' => 100,
            'usage' => [['entity' => 'Documents', 'table' => 'documents', 'column' => 'document_status', 'type' => 'id']],
        ],
        'document_exceptions_status' => [
            'group' => 'LookupGroupControlsDocuments', 'headerKey' => 'ExceptionStatus', 'lengthLimit' => 100,
            'usage' => [['entity' => 'Exceptions', 'table' => 'document_exceptions', 'column' => 'status', 'type' => 'id']],
        ],

        // Compliance
        'test_status' => [
            'group' => 'Compliance', 'headerKey' => 'AuditStatus', 'lengthLimit' => 100,
            'usage' => [['entity' => 'Audits', 'table' => 'framework_control_test_audits', 'column' => 'status', 'type' => 'id']],
        ],

        // Risk and threat catalogs
        'risk_grouping' => [
            'group' => 'LookupGroupCatalogs', 'headerKey' => 'RiskGroupings', 'lengthLimit' => 50,
            'customAdd' => 'add_risk_grouping',
            'usage' => [['entity' => 'LookupEntityRiskCatalog', 'table' => 'risk_catalog', 'column' => 'grouping', 'type' => 'id']],
        ],
        'risk_function' => [
            'group' => 'LookupGroupCatalogs', 'headerKey' => 'RiskFunctions', 'lengthLimit' => 50,
            'usage' => [['entity' => 'LookupEntityRiskCatalog', 'table' => 'risk_catalog', 'column' => 'function', 'type' => 'id']],
        ],
        'threat_grouping' => [
            'group' => 'LookupGroupCatalogs', 'headerKey' => 'ThreatGroupings', 'lengthLimit' => 50,
            'customAdd' => 'lookup_values_add_threat_grouping',
            'usage' => [['entity' => 'LookupEntityThreatCatalog', 'table' => 'threat_catalog', 'column' => 'grouping', 'type' => 'id']],
        ],

        // Assets and environment
        'location' => [
            'group' => 'LookupGroupAssetsEnvironment', 'headerKey' => 'SiteLocation', 'lengthLimit' => 100,
            'usage' => [
                ['entity' => 'Risks', 'table' => 'risk_to_location', 'column' => 'location_id', 'type' => 'junction'],
                ['entity' => 'Assets', 'table' => 'assets', 'column' => 'location', 'type' => 'csv', 'key' => 'id'],
            ],
        ],
        'technology' => [
            'group' => 'LookupGroupAssetsEnvironment', 'headerKey' => 'Technology', 'lengthLimit' => 50,
            'usage' => [['entity' => 'Risks', 'table' => 'risk_to_technology', 'column' => 'technology_id', 'type' => 'junction']],
        ],
    ];
}

/**
 * Group keys in the order the page shows them.
 *
 * @return string[]
 */
function lookup_value_group_order(): array
{
    return [
        'LookupGroupRiskManagement',
        'LookupGroupControlsDocuments',
        'Compliance',
        'LookupGroupCatalogs',
        'LookupGroupAssetsEnvironment',
    ];
}

/**
 * Whether $list is one of the managed lists. The allow-list every other
 * function in this file relies on before it builds any SQL.
 */
function lookup_value_list_exists($list): bool
{
    return is_string($list) && array_key_exists($list, lookup_value_lists());
}

/**
 * Parses a request id. Digits only: no sign, no whitespace, no decimals.
 * Returns null for anything else so a caller can answer 400.
 */
function lookup_value_parse_id($id): ?int
{
    if (is_int($id)) {
        return $id > 0 ? $id : null;
    }
    if (!is_string($id) || !preg_match('/^[1-9]\d{0,9}$/', $id)) {
        return null;
    }
    return (int)$id;
}

/**
 * The longest name the list accepts, which is the tighter of its own limit and
 * the limit of a column that stores the name.
 */
function lookup_value_name_limit(string $list): int
{
    $config = lookup_value_lists()[$list];
    return min($config['lengthLimit'], $config['nameLimit'] ?? PHP_INT_MAX);
}

/**
 * Ids of the built-in workflow statuses. Core compares risks.status against
 * these names in dozens of places, so they can be neither renamed nor deleted.
 *
 * @return int[]
 */
function lookup_value_builtin_status_ids(): array
{
    // New, Mitigation Planned, Mgmt Reviewed, Closed, Reopened, Untreated, Treated
    return [1, 2, 3, 4, 5, 6, 7];
}

/**
 * The state the protection rules depend on, read once per request.
 *
 * @return array{closed_audit_status: int, default_risk_grouping: int, default_threat_grouping: int}
 */
function lookup_value_protection_context(): array
{
    $db = db_open();

    $stmt = $db->prepare("SELECT `value` FROM `risk_grouping` WHERE `default` = 1");
    $stmt->execute();
    $default_risk = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT `value` FROM `threat_grouping` WHERE `default` = 1");
    $stmt->execute();
    $default_threat = (int)$stmt->fetchColumn();

    db_close($db);

    return [
        'closed_audit_status' => (int)get_setting('closed_audit_status'),
        'default_risk_grouping' => $default_risk,
        'default_threat_grouping' => $default_threat,
    ];
}

/**
 * Why a value cannot be deleted, as a language key, or null when it can.
 * Pure: the caller supplies the state from lookup_value_protection_context().
 * $id must already be an int, so the strict comparisons below cannot be
 * defeated by a string id from the route.
 */
function lookup_value_delete_protection(string $list, int $id, array $context): ?string
{
    switch ($list) {
        case 'test_status':
            if ($id === $context['closed_audit_status']) {
                return 'TheClosedStatusCantBeDeleted';
            }
            break;
        case 'risk_grouping':
            return $id === $context['default_risk_grouping'] ? 'CantDeleteTheDefaultRiskGrouping' : null;
        case 'threat_grouping':
            return $id === $context['default_threat_grouping'] ? 'CantDeleteTheDefaultThreatGrouping' : null;
        case 'status':
            return in_array($id, lookup_value_builtin_status_ids(), true) ? 'LookupBuiltInStatusProtected' : null;
    }

    // The switch above holds the locks that depend on state (the closing audit status, the default
    // groupings) or on a set of ids; the table below holds the static ones. A list can have both
    // (test_status: the closing status above, the default audit status below).
    // Values the code depends on by id, or that the column default points new records at.
    $key = lookup_value_fixed_id_protection()[$list] ?? null;
    return ($key !== null && in_array($id, $key['ids'], true)) ? $key['reason'] : null;
}

/**
 * Values that cannot be deleted because core is keyed on their id or a new record starts on them.
 * Renaming them is fine: the logic compares ids, never the names.
 *
 *  next_step 2                   Plan Projects collects the risks whose latest review chose it
 *  review 1, 2                   the only two outcomes the management review form offers
 *  document_status 1, 2, 3       approve/unapprove write 3 and 2; 1 is the default and the grid's fallback
 *  document_exceptions_status 1  the column default for a new exception
 *  test_status 1                 the column default for a new audit (the closing status is guarded separately)
 *
 * @return array<string, array{ids: int[], reason: string}>
 */
function lookup_value_fixed_id_protection(): array
{
    return [
        'next_step' => ['ids' => [2], 'reason' => 'LookupPlanProjectsStepProtected'],
        'review' => ['ids' => [1, 2], 'reason' => 'LookupReviewOutcomeProtected'],
        'document_status' => ['ids' => [1, 2, 3], 'reason' => 'LookupDocumentStatusProtected'],
        'document_exceptions_status' => ['ids' => [1], 'reason' => 'LookupExceptionDefaultStatusProtected'],
        'test_status' => ['ids' => [1], 'reason' => 'LookupAuditDefaultStatusProtected'],
    ];
}

/**
 * Why a value cannot be renamed, as a language key, or null when it can.
 */
function lookup_value_rename_protection(string $list, int $id): ?string
{
    if ($list === 'status' && in_array($id, lookup_value_builtin_status_ids(), true)) {
        return 'LookupBuiltInStatusProtected';
    }
    return null;
}

/**
 * Whether $table exists. Core never touches an Extra's table unguarded.
 */
function lookup_value_table_ready(string $table): bool
{
    return table_exists($table);
}

/**
 * The values of a list, sorted by name.
 *
 * @return array<int, array{value: int, name: string}>
 */
function lookup_value_get_values(string $list): array
{
    $db = db_open();
    $stmt = $db->prepare("SELECT `value`, `name` FROM `{$list}` ORDER BY `name`");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    return array_map(fn($row) => ['value' => (int)$row['value'], 'name' => (string)$row['name']], $rows);
}

/**
 * One value, or null when the list has no value with that id.
 *
 * @return array{value: int, name: string}|null
 */
function lookup_value_get_value(string $list, int $id): ?array
{
    $db = db_open();
    $stmt = $db->prepare("SELECT `value`, `name` FROM `{$list}` WHERE `value` = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    return $row ? ['value' => (int)$row['value'], 'name' => (string)$row['name']] : null;
}

/**
 * How many records reference every value of a list, per referencing entity.
 *
 * @return array<int, array<string, int>> value id => entity => count. Values nothing uses are absent.
 */
function lookup_value_usage_counts(string $list): array
{
    $config = lookup_value_lists()[$list];
    $counts = [];
    $db = db_open();

    foreach ($config['usage'] as $usage) {
        if (!lookup_value_table_ready($usage['table'])) {
            continue;
        }
        $table = $usage['table'];
        $column = $usage['column'];

        switch ($usage['type']) {
            case 'id':
            case 'junction':
                $stmt = $db->prepare("SELECT `{$column}` AS `ref`, COUNT(*) AS `cnt` FROM `{$table}` WHERE `{$column}` IS NOT NULL GROUP BY `{$column}`");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $counts[(int)$row['ref']][$usage['entity']] = (int)$row['cnt'];
                }
                break;

            case 'name':
                $stmt = $db->prepare("SELECT l.`value` AS `ref`, COUNT(t.`{$column}`) AS `cnt` FROM `{$list}` l INNER JOIN `{$table}` t ON t.`{$column}` = l.`name` GROUP BY l.`value`");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $counts[(int)$row['ref']][$usage['entity']] = (int)$row['cnt'];
                }
                break;

            case 'csv':
                // One pass over the rows that hold any id; the lists are small.
                $stmt = $db->prepare("SELECT `{$column}` AS `ids` FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` <> ''");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $csv) {
                    foreach (array_unique(array_filter(array_map('trim', explode(',', (string)$csv)), 'ctype_digit')) as $ref) {
                        $counts[(int)$ref][$usage['entity']] = ($counts[(int)$ref][$usage['entity']] ?? 0) + 1;
                    }
                }
                break;
        }
    }

    db_close($db);
    return $counts;
}

/**
 * The entity language keys a list is referenced by, in the order they are configured.
 *
 * @return string[]
 */
function lookup_value_used_by_entities(string $list): array
{
    return array_values(array_unique(array_column(lookup_value_lists()[$list]['usage'], 'entity')));
}

/**
 * Turns an entity => count map into the API shape, with the labels resolved.
 *
 * @param array<string, int> $by_entity
 * @return array{total: int, by_entity: array<int, array{entity: string, label: string, count: int}>}
 */
function lookup_value_format_usage(array $by_entity): array
{
    global $lang;
    $total = 0;
    $rows = [];
    foreach ($by_entity as $entity => $count) {
        $total += $count;
        $rows[] = ['entity' => $entity, 'label' => $lang[$entity] ?? $entity, 'count' => $count];
    }
    return ['total' => $total, 'by_entity' => $rows];
}

/**
 * The lists with their group, size and the entities that use them. With
 * $include_values each list also carries its value ids and names (no usage),
 * which is what the page's live search matches against.
 *
 * @return array<int, array<string, mixed>>
 */
function lookup_value_lists_summary(bool $include_values = false): array
{
    global $lang;
    $db = db_open();
    $summary = [];

    foreach (lookup_value_lists() as $list => $config) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM `{$list}`");
        $stmt->execute();

        $summary[] = [
            'list' => $list,
            'name' => $lang[$config['headerKey']] ?? $list,
            'group' => $config['group'],
            'group_name' => $lang[$config['group']] ?? $config['group'],
            'count' => (int)$stmt->fetchColumn(),
            'length_limit' => lookup_value_name_limit($list),
            'used_by' => array_map(fn($entity) => $lang[$entity] ?? $entity, lookup_value_used_by_entities($list)),
        ];

        if ($include_values) {
            $summary[count($summary) - 1]['values'] = array_map(
                fn($row) => ['id' => $row['value'], 'name' => $row['name']],
                lookup_value_get_values($list)
            );
        }
    }

    db_close($db);
    return $summary;
}

/**
 * The values of a list with their usage and whether they are protected.
 *
 * @return array<int, array<string, mixed>>
 */
function lookup_value_values_with_usage(string $list): array
{
    global $lang;
    $usage = lookup_value_usage_counts($list);
    $context = lookup_value_protection_context();
    $values = [];

    foreach (lookup_value_get_values($list) as $row) {
        $id = $row['value'];
        $delete_protection = lookup_value_delete_protection($list, $id, $context);
        $rename_protection = lookup_value_rename_protection($list, $id);
        $protection = $delete_protection ?? $rename_protection;

        $values[] = [
            'id' => $id,
            'name' => $row['name'],
            'usage' => lookup_value_format_usage($usage[$id] ?? []),
            'protected' => $protection !== null,
            'protected_reason' => $protection !== null ? ($lang[$protection] ?? $protection) : null,
            'can_rename' => $rename_protection === null,
            'can_delete' => $delete_protection === null,
        ];
    }

    return $values;
}

/**
 * Whether the list already has a value with this name, ignoring case and the
 * value whose id is $except_id.
 */
function lookup_value_name_taken(string $list, string $name, int $except_id = 0): bool
{
    $db = db_open();
    $stmt = $db->prepare("SELECT COUNT(*) FROM `{$list}` WHERE LOWER(`name`) = LOWER(:name) AND `value` <> :id");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':id', $except_id, PDO::PARAM_INT);
    $stmt->execute();
    $taken = (int)$stmt->fetchColumn() > 0;
    db_close($db);

    return $taken;
}

/**
 * Adds a value, through the list's custom hook when it has one.
 *
 * @return int|false The new id, false when nothing was added
 */
function lookup_value_add(string $list, string $name)
{
    $config = lookup_value_lists()[$list];
    $size = lookup_value_name_limit($list);

    if (isset($config['customAdd'])) {
        $result = ($config['customAdd'])($name);
    } else {
        $result = add_name($list, $name, $size);
    }
    if (!$result) {
        return false;
    }

    $added = get_value_by_name($list, $name);
    return is_int($added) ? $added : false;
}

/**
 * Renames a value. Renaming a risk status also rewrites risks.status, which
 * stores the name, so no risk is orphaned.
 */
function lookup_value_rename(string $list, int $id, string $old_name, string $name): bool
{
    $config = lookup_value_lists()[$list];

    if ($list === 'status') {
        $db = db_open();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE `status` SET `name` = :name WHERE `value` = :id");
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt = $db->prepare("UPDATE `risks` SET `status` = :name WHERE `status` = :old_name");
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':old_name', $old_name, PDO::PARAM_STR);
            $stmt->execute();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            write_debug_log('Renaming the risk status failed: ' . $e->getMessage(), 'error');
            db_close($db);
            return false;
        }
        db_close($db);
        write_log(1000, $_SESSION['uid'] ?? 0, "The \"status\" naming convention was modified by the \"" . $GLOBALS['escaper']->escapeHtml($_SESSION['user'] ?? '') . "\" user.");
        return true;
    }

    if (isset($config['customUpdate'])) {
        return (bool)($config['customUpdate'])($id, $name);
    }
    update_table($list, $name, $id, lookup_value_name_limit($list));
    return true;
}

/**
 * Moves every reference to value $from onto value $to.
 *
 * @return int The number of records that were moved
 */
function lookup_value_reassign(string $list, int $from, int $to): int
{
    return lookup_value_reassign_with_usage($list, $from, $to, lookup_value_lists()[$list]['usage']);
}

/**
 * The reassignment itself, over an explicit usage list, in one transaction: either every entry
 * moves or none does. Split from lookup_value_reassign() so a test can feed it a failing entry.
 *
 * @param array<int, array<string, string>> $usage Entries in the shape of lookup_value_lists()[$list]['usage']
 * @return int The number of records that were moved
 */
function lookup_value_reassign_with_usage(string $list, int $from, int $to, array $usage_entries): int
{
    $from_name = lookup_value_get_value($list, $from)['name'] ?? '';
    $to_name = lookup_value_get_value($list, $to)['name'] ?? '';
    $moved = 0;

    $db = db_open();
    try {
        $db->beginTransaction();

        foreach ($usage_entries as $usage) {
            if (!lookup_value_table_ready($usage['table'])) {
                continue;
            }
            $table = $usage['table'];
            $column = $usage['column'];

            switch ($usage['type']) {
                case 'id':
                    $stmt = $db->prepare("UPDATE `{$table}` SET `{$column}` = :to WHERE `{$column}` = :from");
                    $stmt->bindValue(':to', $to, PDO::PARAM_INT);
                    $stmt->bindValue(':from', $from, PDO::PARAM_INT);
                    $stmt->execute();
                    $moved += $stmt->rowCount();
                    break;

                case 'name':
                    $stmt = $db->prepare("UPDATE `{$table}` SET `{$column}` = :to WHERE `{$column}` = :from");
                    $stmt->bindValue(':to', $to_name, PDO::PARAM_STR);
                    $stmt->bindValue(':from', $from_name, PDO::PARAM_STR);
                    $stmt->execute();
                    $moved += $stmt->rowCount();
                    break;

                case 'junction':
                    // IGNORE skips the rows whose owner already has the target;
                    // the rows left over are the duplicates and are removed.
                    $stmt = $db->prepare("UPDATE IGNORE `{$table}` SET `{$column}` = :to WHERE `{$column}` = :from");
                    $stmt->bindValue(':to', $to, PDO::PARAM_INT);
                    $stmt->bindValue(':from', $from, PDO::PARAM_INT);
                    $stmt->execute();
                    $moved += $stmt->rowCount();
                    $stmt = $db->prepare("DELETE FROM `{$table}` WHERE `{$column}` = :from");
                    $stmt->bindValue(':from', $from, PDO::PARAM_INT);
                    $stmt->execute();
                    break;

                case 'csv':
                    $key = $usage['key'];
                    $stmt = $db->prepare("SELECT `{$key}` AS `row_id`, `{$column}` AS `ids` FROM `{$table}` WHERE FIND_IN_SET(:from, `{$column}`)");
                    $stmt->bindValue(':from', (string)$from, PDO::PARAM_STR);
                    $stmt->execute();
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $ids = array_map('trim', explode(',', (string)$row['ids']));
                        $ids = array_map(fn($id) => $id === (string)$from ? (string)$to : $id, $ids);
                        $update = $db->prepare("UPDATE `{$table}` SET `{$column}` = :ids WHERE `{$key}` = :row_id");
                        $update->bindValue(':ids', implode(',', array_unique($ids)), PDO::PARAM_STR);
                        $update->bindValue(':row_id', (int)$row['row_id'], PDO::PARAM_INT);
                        $update->execute();
                        $moved++;
                    }
                    break;
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        db_close($db);
        write_debug_log('Reassigning the records of a lookup value failed: ' . $e->getMessage(), 'error');
        throw $e;
    }

    db_close($db);
    return $moved;
}

/**
 * Deletes a value through the list's existing delete behaviour, and tidies
 * what that behaviour has always tidied. The protection rules are checked by
 * the caller, so none of the functions below can raise a UI alert.
 */
function lookup_value_delete(string $list, int $id): bool
{
    $config = lookup_value_lists()[$list];

    if (isset($config['customDelete'])) {
        $result = ($config['customDelete'])($id);
    } else {
        $result = delete_value($list, $id);
    }

    if ($result !== false) {
        if (in_array($list, ['technology', 'location', 'risk_grouping', 'threat_grouping'], true)) {
            cleanup_after_delete($list);
        }
        if ($list === 'risk_grouping') {
            reassign_groupless_risk_catalogs();
        } elseif ($list === 'threat_grouping') {
            reassign_groupless_threat_catalogs();
        }
    }

    return (bool)$result;
}

/**
 * Adds a threat grouping at the end of the list. Moved here from the page.
 */
function lookup_values_add_threat_grouping($name)
{
    $db = db_open();

    $stmt = $db->prepare("SELECT MAX(`order`) FROM `threat_grouping`");
    $stmt->execute();
    $new_order = intval($stmt->fetchColumn()) + 1;

    $stmt = $db->prepare("INSERT INTO `threat_grouping` (`name`, `order`) VALUES (:name, :order)");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":order", $new_order, PDO::PARAM_INT);
    $stmt->execute();
    $id = $db->lastInsertId();

    db_close($db);

    return $id;
}
