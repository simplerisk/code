<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Core module for SCF-based Self-Assessments. Reachable helpers:
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/assessments.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/permissions.php'));

// ---- Prerequisites (pure decision + environment probes) ----

function self_assessment_prerequisites(bool $registered, bool $scf_installed): array
{
    return [
        'registered'    => $registered,
        'scf_installed' => $scf_installed,
        'ready'         => $registered && $scf_installed,
    ];
}

function self_assessment_is_registered(): bool
{
    return get_setting('registration_registered') == 1;
}

function self_assessment_scf_installed(): bool
{
    return function_exists('complianceforge_scf_extra')
        && complianceforge_scf_extra()
        && table_exists('scf_controls')
        && table_exists('scf_risks')
        && table_exists('scf_authoritative_sources')
        && table_exists('scf_frameworks');
}

function self_assessment_default_name(string $framework_name, string $date): string
{
    return trim($framework_name) . ' — ' . $date;
}

// ---- Framework + control listing (guarded SCF reads) ----

/**
 * Strip the `enabled` flag from a framework listing for a user without the
 * governance permission. Which frameworks an org has enabled is governance-only
 * config, so it must not leak through the open (scope=all) listing. Pure over
 * its inputs (no DB/session) — unit-testable.
 *
 * @param array $frameworks rows from get_self_assessment_frameworks()
 * @param bool  $can_gov    whether the caller holds the governance permission
 * @return array
 */
function self_assessment_visible_frameworks(array $frameworks, bool $can_gov): array
{
    if ($can_gov) {
        return $frameworks;
    }
    foreach ($frameworks as &$f) {
        unset($f['enabled']);
    }
    unset($f);
    return $frameworks;
}

/**
 * @param string $scope 'enabled' | 'all'
 * @return array rows: scf_source_id, name, question_count, enabled(bool)
 */
function get_self_assessment_frameworks(string $scope): array
{
    if (!self_assessment_scf_installed()) { return []; }
    $db = db_open();
    $where = ($scope === 'enabled') ? "WHERE sas.enabled = 1" : "";
    $stmt = $db->prepare("
        SELECT sas.id AS scf_source_id,
               sas.authoritative_source AS name,
               sas.enabled AS enabled,
               COUNT(DISTINCT sc.simplerisk_control_id) AS question_count
        FROM scf_authoritative_sources sas
        LEFT JOIN scf_frameworks sf ON sf.mapping_column_header = sas.mapping_column_header
        LEFT JOIN scf_controls sc ON sc.number = sf.number AND sc.simplerisk_control_id IS NOT NULL
        {$where}
        GROUP BY sas.id, sas.authoritative_source, sas.enabled
        HAVING question_count > 0
        ORDER BY name
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return array_map(function ($r) {
        return [
            'scf_source_id'  => (int)$r['scf_source_id'],
            'name'           => (string)$r['name'],
            'question_count' => (int)$r['question_count'],
            'enabled'        => ((int)$r['enabled'] === 1),
        ];
    }, $rows ?: []);
}

/**
 * @return array rows: control_id, control_number, short_name, domain, question, weighting
 */
function get_self_assessment_framework_controls(int $scf_source_id): array
{
    if (!self_assessment_scf_installed()) { return []; }
    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT sc.simplerisk_control_id AS control_id,
               sc.number AS control_number,
               sc.control AS short_name,
               sc.domain AS domain,
               sc.question AS question,
               sc.weighting AS weighting
        FROM scf_authoritative_sources sas
        JOIN scf_frameworks sf ON sf.mapping_column_header = sas.mapping_column_header
        JOIN scf_controls sc ON sc.number = sf.number
        WHERE sas.id = :scf_source_id
          AND sc.simplerisk_control_id IS NOT NULL
        ORDER BY sc.domain, sc.number
    ");
    $stmt->execute([':scf_source_id' => $scf_source_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return array_map(function ($r) {
        return [
            'control_id'     => (int)$r['control_id'],
            'control_number' => (string)$r['control_number'],
            'short_name'     => (string)$r['short_name'],
            'domain'         => (string)($r['domain'] ?? ''),
            'question'       => (string)($r['question'] ?? ''),
            'weighting'      => (int)$r['weighting'],
        ];
    }, $rows ?: []);
}

// ---- Run CRUD ----

function create_self_assessment(int $scf_source_id, string $name, int $user_id): int|false
{
    // Resolve the framework name for the snapshot + default name.
    $frameworks = get_self_assessment_frameworks('all');
    $framework_name = '';
    foreach ($frameworks as $f) {
        if ($f['scf_source_id'] === $scf_source_id) { $framework_name = $f['name']; break; }
    }
    if ($framework_name === '') { return false; } // invalid / empty framework

    if (trim($name) === '') {
        $name = self_assessment_default_name($framework_name, date('Y-m-d'));
    }

    $db = db_open();
    $stmt = $db->prepare("
        INSERT INTO `self_assessments` (`name`, `scf_source_id`, `framework_name`, `status`, `started_by`)
        VALUES (:name, :scf, :fw, 'in_progress', :uid)
    ");
    $stmt->execute([
        ':name' => $name, ':scf' => $scf_source_id, ':fw' => $framework_name, ':uid' => $user_id,
    ]);
    $id = (int)$db->lastInsertId();
    db_close($db);
    return $id ?: false;
}

function get_self_assessment(int $id): array|false
{
    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM `self_assessments` WHERE `id` = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);
    return $row ?: false;
}

function get_self_assessments(): array
{
    $db = db_open();
    $stmt = $db->prepare("
        SELECT sa.*,
               (SELECT name FROM user u WHERE u.value = sa.started_by LIMIT 1) AS started_by_name,
               (SELECT COUNT(*) FROM self_assessment_responses r
                   WHERE r.self_assessment_id = sa.id AND r.response <> '') AS answered_count,
               (SELECT COUNT(*) FROM self_assessment_responses r
                   WHERE r.self_assessment_id = sa.id AND r.response = 'fail') AS failed_count
        FROM `self_assessments` sa
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    db_close($db);
    // total_count is the framework's control count (not stored per-run).
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['answered_count'] = (int)$r['answered_count'];
        $r['failed_count'] = (int)$r['failed_count'];
        $r['total_count'] = count(get_self_assessment_framework_controls((int)$r['scf_source_id']));
    }
    return $rows;
}

function delete_self_assessment(int $id): bool
{
    $db = db_open();
    // Clean child rows first (responses; generated pending risks are intentionally retained).
    $db->prepare("DELETE FROM `self_assessment_responses` WHERE `self_assessment_id` = :id")->execute([':id' => $id]);
    $stmt = $db->prepare("DELETE FROM `self_assessments` WHERE `id` = :id");
    $stmt->execute([':id' => $id]);
    $ok = $stmt->rowCount() > 0;
    db_close($db);
    return $ok;
}

// ---- Responses ----

function save_self_assessment_responses(int $id, array $responses): int
{
    $valid = ['', 'pass', 'fail', 'na'];
    // Restrict writes to controls that belong to this run's framework.
    $sa = get_self_assessment($id);
    if (!$sa) { return 0; }
    $allowed = [];
    foreach (get_self_assessment_framework_controls((int)$sa['scf_source_id']) as $c) {
        $allowed[(int)$c['control_id']] = true;
    }

    $db = db_open();
    // This is a PATCH sink: the route is PATCH /self-assessments/{id}/responses and the
    // API hands this function the caller's array verbatim, so an item naming only the
    // field it wants to change is the expected shape. Both writable columns therefore
    // follow the absent-means-preserve convention -- a NULL bind means "the caller did
    // not mention this field", so COALESCE falls back to the stored value. An explicit
    // '' is not NULL, so clearing a comment (or blanking an answer back to unanswered)
    // still works.
    //
    // Getting this wrong destroys respondent data silently in both directions. Writing
    // an absent `comment` through as NULL erased saved evidence; writing an absent
    // `response` through as '' un-answered the control, which also drops it from the
    // failed set complete_self_assessment() turns into pending risks. Neither shows up
    // in the browser flow, because the page's own JS always sends both keys.
    //
    // `response` needs two placeholders and its coercion in SQL rather than PHP: the
    // column is ENUM(...) NOT NULL, so the INSERT arm has to turn NULL into '', while
    // the UPDATE arm needs the NULL intact to detect absence. Coercing before the bind
    // would make absent and explicit-'' indistinguishable, which is the bug itself.
    //
    // isset(), not array_key_exists(): a JSON null is treated the same as an omitted
    // key. "Not mentioned" and "mentioned as nothing" are the same intent from a PATCH
    // client, and '' remains the explicit way to clear either column.
    $stmt = $db->prepare("
        INSERT INTO `self_assessment_responses` (`self_assessment_id`, `control_id`, `response`, `comment`)
        VALUES (:sa, :cid, COALESCE(:resp_ins, ''), :comment_ins)
        ON DUPLICATE KEY UPDATE
            `response` = COALESCE(:resp_upd, `response`),
            `comment`  = COALESCE(:comment_upd, `comment`)
    ");
    $count = 0;
    foreach ($responses as $r) {
        $cid = (int)($r['control_id'] ?? 0);
        $resp = isset($r['response']) ? (string)$r['response'] : null;
        $comment = isset($r['comment']) ? (string)$r['comment'] : null;
        // An absent response is legal (preserve); a present-but-invalid one is not.
        if (!isset($allowed[$cid]) || ($resp !== null && !in_array($resp, $valid, true))) { continue; }
        $stmt->execute([
            ':sa' => $id, ':cid' => $cid,
            ':resp_ins' => $resp, ':resp_upd' => $resp,
            ':comment_ins' => $comment, ':comment_upd' => $comment,
        ]);
        $count++;
    }
    db_close($db);
    return $count;
}

function get_self_assessment_with_controls(int $id): array|false
{
    $sa = get_self_assessment($id);
    if (!$sa) { return false; }
    $controls = get_self_assessment_framework_controls((int)$sa['scf_source_id']);

    $db = db_open();
    $stmt = $db->prepare("SELECT control_id, response, comment FROM `self_assessment_responses` WHERE `self_assessment_id` = :id");
    $stmt->execute([':id' => $id]);
    $responses = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $responses[(int)$row['control_id']] = [
            'response' => (string)$row['response'],
            'comment'  => (string)($row['comment'] ?? ''),
        ];
    }
    db_close($db);

    return ['assessment' => $sa, 'controls' => $controls, 'responses' => $responses];
}

// ---- Risk selection (pure) ----

function self_assessment_risk_score_from_weighting(int $max_weighting): int
{
    if ($max_weighting <= 0) { return 5; }       // default medium when unweighted (weighting can be 0)
    $score = $max_weighting * 2;                  // SCF weighting 1-5 -> 2-10
    return $score > 10 ? 10 : $score;             // clamp defensively
}

function self_assessment_select_pending_risks(array $failed_weight_by_control, array $mapping_rows): array
{
    $risks = [];
    foreach ($mapping_rows as $row) {
        $cid = (int)($row['control_id'] ?? 0);
        if (!array_key_exists($cid, $failed_weight_by_control)) { continue; } // only failed controls
        $rcid = (int)($row['risk_catalog_id'] ?? 0);
        $w = (int)$failed_weight_by_control[$cid];
        if (!isset($risks[$rcid])) {
            $risks[$rcid] = [
                'risk_catalog_id' => $rcid,
                'number'          => (string)($row['risk_number'] ?? ''),
                'name'            => (string)($row['risk_name'] ?? ''),
                'max_weighting'   => $w,
                'control_ids'     => [],
            ];
        }
        if ($w > $risks[$rcid]['max_weighting']) { $risks[$rcid]['max_weighting'] = $w; }
        if (!in_array($cid, $risks[$rcid]['control_ids'], true)) { $risks[$rcid]['control_ids'][] = $cid; }
    }
    $out = [];
    foreach ($risks as $r) {
        $r['score'] = self_assessment_risk_score_from_weighting((int)$r['max_weighting']);
        unset($r['max_weighting']);
        $out[] = $r;
    }
    return $out;
}

// ---- Control -> risk lookup + completion ----

/**
 * Guarded batch lookup: given native control ids, return their SCF risk_catalog mappings.
 * @return array rows: control_id, risk_catalog_id, risk_number, risk_name
 */
function get_self_assessment_control_risk_rows(array $control_ids): array
{
    $control_ids = array_values(array_unique(array_map('intval', $control_ids)));
    if (empty($control_ids) || !self_assessment_scf_installed()) { return []; }
    $placeholders = implode(',', array_fill(0, count($control_ids), '?'));

    $db = db_open();
    $stmt = $db->prepare("
        SELECT sc.simplerisk_control_id AS control_id,
               rc.id AS risk_catalog_id,
               rc.number AS risk_number,
               rc.name AS risk_name
        FROM scf_controls sc
        JOIN scf_risks sr ON sr.simplerisk_control_id = sc.simplerisk_control_id
        JOIN risk_catalog rc ON rc.id = sr.risk_catalog_id
        WHERE sc.simplerisk_control_id IN ($placeholders)
        ORDER BY rc.`order`
    ");
    $stmt->execute($control_ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    db_close($db);
    return array_map(function ($r) {
        return [
            'control_id'      => (int)$r['control_id'],
            'risk_catalog_id' => (int)$r['risk_catalog_id'],
            'risk_number'     => (string)$r['risk_number'],
            'risk_name'       => (string)$r['risk_name'],
        ];
    }, $rows);
}

/**
 * Generate pending risks from failed controls, mark the run completed.
 * No-ops (returns []) if the run doesn't exist or is already completed.
 * @return array of ['pending_risk_id','risk_catalog_id','number','name','score','control_ids']
 */
function complete_self_assessment(int $id): array
{
    $data = get_self_assessment_with_controls($id);
    if (!$data || $data['assessment']['status'] === 'completed') { return []; }

    // Map: control_id => weighting, and control_id => full control row (for provenance numbers).
    $weight_by_control = [];
    $control_meta = [];
    foreach ($data['controls'] as $c) {
        $weight_by_control[(int)$c['control_id']] = (int)$c['weighting'];
        $control_meta[(int)$c['control_id']] = $c;
    }

    $failed_weight = [];
    foreach ($data['responses'] as $cid => $resp) {
        if (($resp['response'] ?? '') === 'fail' && isset($weight_by_control[(int)$cid])) {
            $failed_weight[(int)$cid] = $weight_by_control[(int)$cid];
        }
    }

    $generated = [];
    if (!empty($failed_weight)) {
        $mapping_rows = get_self_assessment_control_risk_rows(array_keys($failed_weight));
        $selected = self_assessment_select_pending_risks($failed_weight, $mapping_rows);

        $framework = $data['assessment']['framework_name'];
        $run_name = $data['assessment']['name'];

        $db = db_open();
        foreach ($selected as $risk) {
            // Provenance comment: failed control numbers + SCF risk number.
            $numbers = [];
            foreach ($risk['control_ids'] as $cid) {
                if (isset($control_meta[$cid])) { $numbers[] = $control_meta[$cid]['control_number']; }
            }
            $comment = "Generated by Self-Assessment '" . $run_name . "' (" . $framework . "). "
                     . "Failed control(s): " . implode(', ', $numbers) . ". SCF Risk " . $risk['number'] . ".";
            $subject = mb_substr($risk['name'], 0, 300);

            // `assessment_id`/`assessment_answer_id` are legacy NOT NULL columns (no DB default);
            // self-assessment-generated rows have no legacy assessment/answer, so they're 0.
            $ins = $db->prepare("
                INSERT INTO `pending_risks`
                    (`assessment_id`, `assessment_answer_id`, `subject`, `score`, `affected_assets`, `comment`, `risk_catalog_id`, `self_assessment_id`)
                VALUES (0, 0, :subject, :score, '', :comment, :rcid, :said)
            ");
            $ins->execute([
                ':subject' => $subject, ':score' => $risk['score'], ':comment' => $comment,
                ':rcid' => $risk['risk_catalog_id'], ':said' => $id,
            ]);
            $pending_risk_id = (int)$db->lastInsertId();

            $link = $db->prepare("INSERT IGNORE INTO `pending_risk_to_controls` (`pending_risk_id`, `control_id`) VALUES (:pr, :cid)");
            foreach ($risk['control_ids'] as $cid) { $link->execute([':pr' => $pending_risk_id, ':cid' => (int)$cid]); }

            $generated[] = [
                'pending_risk_id' => $pending_risk_id,
                'risk_catalog_id' => $risk['risk_catalog_id'],
                'number'          => $risk['number'],
                'name'            => $risk['name'],
                'score'           => $risk['score'],
                'control_ids'     => $risk['control_ids'],
            ];
        }
        db_close($db);
    }

    // Write each answer back to the native control status (design decision: on
    // complete only). Yes -> Pass (1), No -> Fail (0); N/A / unanswered leave the
    // control's existing status unchanged. control_id is the native framework_controls.id.
    // Writing a control's status is a governance action, so it requires the
    // 'modify_controls' permission ("Able to Modify Existing Controls"). A user
    // without it can still run + complete the assessment and generate risks; the
    // control statuses are simply left untouched.
    if (check_permission('modify_controls')) {
        $db = db_open();
        $set_status = $db->prepare("UPDATE `framework_controls` SET `control_status` = :status WHERE `id` = :cid");
        foreach ($data['responses'] as $cid => $resp) {
            $answer = $resp['response'] ?? '';
            if ($answer === 'pass') {
                $set_status->execute([':status' => 1, ':cid' => (int)$cid]);
            } elseif ($answer === 'fail') {
                $set_status->execute([':status' => 0, ':cid' => (int)$cid]);
            }
            // 'na' / '' -> leave unchanged
        }
        db_close($db);
    }

    // Mark completed.
    $db = db_open();
    $db->prepare("UPDATE `self_assessments` SET `status` = 'completed', `completed_at` = NOW() WHERE `id` = :id")
       ->execute([':id' => $id]);
    db_close($db);

    return $generated;
}

/**
 * Pending risks generated by self-assessments, each with the failed control numbers.
 * Guarded: the `scf_controls` join is only meaningful (and only run) when SCF is installed.
 */
function get_self_assessment_pending_risks(): array
{
    $db = db_open();
    if (self_assessment_scf_installed()) {
        $stmt = $db->prepare("
            SELECT pr.id, pr.subject, pr.score, pr.comment, pr.risk_catalog_id, pr.self_assessment_id,
                   sa.name AS assessment_name, sa.framework_name,
                   rc.description AS description,
                   GROUP_CONCAT(DISTINCT sc.number ORDER BY sc.number SEPARATOR ', ') AS failed_controls
            FROM `pending_risks` pr
            JOIN `self_assessments` sa ON sa.id = pr.self_assessment_id
            LEFT JOIN `risk_catalog` rc ON rc.id = pr.risk_catalog_id
            LEFT JOIN `pending_risk_to_controls` prc ON prc.pending_risk_id = pr.id
            LEFT JOIN `scf_controls` sc ON sc.simplerisk_control_id = prc.control_id
            WHERE pr.self_assessment_id IS NOT NULL
            GROUP BY pr.id
            ORDER BY pr.id DESC
        ");
    } else {
        // SCF not installed: no scf_controls table to join against, so failed_controls is empty.
        $stmt = $db->prepare("
            SELECT pr.id, pr.subject, pr.score, pr.comment, pr.risk_catalog_id, pr.self_assessment_id,
                   sa.name AS assessment_name, sa.framework_name,
                   rc.description AS description,
                   '' AS failed_controls
            FROM `pending_risks` pr
            JOIN `self_assessments` sa ON sa.id = pr.self_assessment_id
            LEFT JOIN `risk_catalog` rc ON rc.id = pr.risk_catalog_id
            WHERE pr.self_assessment_id IS NOT NULL
            ORDER BY pr.id DESC
        ");
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Attach a structured `controls` array per pending risk (native control id +
    // SCF number + short name) so the UI can build a by-control multi-select
    // filter. SCF-guarded: without the Extra there are no numbers/short names.
    $controls_by_pr = [];
    $pr_ids = array_map(static fn($r) => (int)$r['id'], $rows);
    if ($pr_ids && self_assessment_scf_installed()) {
        $in = implode(',', array_fill(0, count($pr_ids), '?'));
        $cstmt = $db->prepare("
            SELECT prc.pending_risk_id, prc.control_id, sc.number, sc.control AS short_name
            FROM `pending_risk_to_controls` prc
            JOIN `scf_controls` sc ON sc.simplerisk_control_id = prc.control_id
            WHERE prc.pending_risk_id IN ($in)
            ORDER BY sc.number
        ");
        $cstmt->execute($pr_ids);
        foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $controls_by_pr[(int)$c['pending_risk_id']][] = [
                'id'         => (int)$c['control_id'],
                'number'     => (string)$c['number'],
                'short_name' => (string)$c['short_name'],
            ];
        }
    }
    db_close($db);

    // Map each score to its configured risk level (name + color) so the UI can
    // render the score as a severity chip (design-system §7). The whole scale —
    // names, thresholds, colors — is customer config in `risk_levels`.
    global $escaper;
    $levels = get_risk_levels() ?: [];

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['score'] = (int)$r['score'];
        $r['self_assessment_id'] = (int)$r['self_assessment_id'];
        $r['failed_controls'] = (string)($r['failed_controls'] ?? '');
        $r['description'] = (string)($r['description'] ?? '');
        $r['controls'] = $controls_by_pr[$r['id']] ?? [];
        $sl = self_assessment_score_level($r['score'], $levels);
        $r['score_level'] = $sl['name'];
        // escapeCssColor() allow-lists the admin-configured color (hex or a CSS
        // keyword) and collapses anything else to 'transparent', so the value is
        // safe to interpolate into the chip's data-color attribute / inline style
        // — the same guard every other risk_levels.color render site uses.
        $r['score_color'] = $escaper->escapeCssColor($sl['color']);
    }
    return $rows;
}

/**
 * Resolve a numeric score to its configured risk level's display name and color
 * via the canonical risk-level helpers — so a self-assessment severity chip
 * matches how severity renders everywhere else, including the Insignificant /
 * white fallback for a score below every configured threshold. Pure over the
 * passed $levels (rows from get_risk_levels()); unit-testable.
 *
 * @param int   $score
 * @param array $levels
 * @return array{name:string,color:string}
 */
function self_assessment_score_level(int $score, array $levels): array
{
    return [
        'name'  => (string)get_risk_level_name_from_levels($score, $levels),
        'color' => (string)get_risk_color_from_levels($score, $levels),
    ];
}

/**
 * Rows for the "Failed Controls" tab: each answered control response from COMPLETED
 * self-assessments, joined with the assessment (date/framework), the SCF control
 * (number/short name/question), and the native control status.
 *
 * @param string $status 'fail' | 'pass' | 'na' | 'all'  (filters on the response)
 * @param int    $offset
 * @param int    $limit
 * @return array ['rows' => array, 'total' => int]
 *   Each row: assessment_id(int), assessment_date(string 'Y-m-d'), framework(string),
 *   control_number(string), short_name(string), question(string),
 *   answer(string 'pass'|'fail'|'na'), control_status(int 0/1)
 */
function get_self_assessment_control_results(string $status = 'fail', int $offset = 0, int $limit = 25): array
{
    if (!self_assessment_scf_installed()) { return ['rows' => [], 'total' => 0]; }
    $offset = max(0, $offset);
    $limit = max(1, min(200, $limit));

    // Response filter: a specific state, or all *answered* responses.
    $valid = ['pass', 'fail', 'na'];
    if (in_array($status, $valid, true)) {
        $where_resp = "r.response = :status";
        $bind_status = true;
    } else {
        $where_resp = "r.response IN ('pass','fail','na')";
        $bind_status = false;
    }

    $db = db_open();

    // Total (for pagination).
    $count_sql = "
        SELECT COUNT(*)
        FROM `self_assessment_responses` r
        JOIN `self_assessments` sa ON sa.id = r.self_assessment_id AND sa.status = 'completed'
        JOIN `scf_controls` sc ON sc.simplerisk_control_id = r.control_id
        LEFT JOIN `framework_controls` fc ON fc.id = r.control_id
        WHERE {$where_resp}
    ";
    $cstmt = $db->prepare($count_sql);
    if ($bind_status) { $cstmt->bindValue(':status', $status); }
    $cstmt->execute();
    $total = (int)$cstmt->fetchColumn();

    // Page of rows, most-recent assessment first.
    $sql = "
        SELECT r.self_assessment_id AS assessment_id,
               DATE(COALESCE(sa.completed_at, sa.created_at)) AS assessment_date,
               sa.framework_name AS framework,
               sc.number AS control_number,
               sc.control AS short_name,
               sc.question AS question,
               r.response AS answer,
               fc.control_status AS control_status
        FROM `self_assessment_responses` r
        JOIN `self_assessments` sa ON sa.id = r.self_assessment_id AND sa.status = 'completed'
        JOIN `scf_controls` sc ON sc.simplerisk_control_id = r.control_id
        LEFT JOIN `framework_controls` fc ON fc.id = r.control_id
        WHERE {$where_resp}
        ORDER BY COALESCE(sa.completed_at, sa.created_at) DESC, r.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($sql);
    if ($bind_status) { $stmt->bindValue(':status', $status); }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    db_close($db);

    $rows = array_map(function ($r) {
        return [
            'assessment_id'  => (int)$r['assessment_id'],
            'assessment_date'=> (string)($r['assessment_date'] ?? ''),
            'framework'      => (string)($r['framework'] ?? ''),
            'control_number' => (string)($r['control_number'] ?? ''),
            'short_name'     => (string)($r['short_name'] ?? ''),
            'question'       => (string)($r['question'] ?? ''),
            'answer'         => (string)($r['answer'] ?? ''),
            'control_status' => $r['control_status'] === null ? null : (int)$r['control_status'],
        ];
    }, $rows);

    return ['rows' => $rows, 'total' => $total];
}

// ---- Prerequisite guidance panel (server-rendered) ----

/**
 * Renders the two-step "not ready yet" guidance panel shown on the default Self-Assessments
 * view when the instance isn't registered and/or the SCF Extra isn't installed.
 */
function render_self_assessment_prereq_panel(bool $registered, bool $scf_installed): void
{
    global $lang, $escaper;
    $reg_status = $registered ? 'done' : 'todo';
    $scf_status = $scf_installed ? 'done' : 'todo';
    echo "<div class='self-assessment-prereq'>";
    echo "<p class='sa-prereq-intro'>" . $escaper->escapeHtml($lang['SelfAssessmentPrereqIntro']) . "</p>";
    // Step 1 — registration
    echo "<div class='sa-step sa-step-{$reg_status}'>";
    echo "<div class='sa-step-body'><h3>1. " . $escaper->escapeHtml($lang['RegisterYourInstance']) . "</h3>";
    echo "<p>" . $escaper->escapeHtml($lang['RegisterYourInstanceHelp']) . "</p></div>";
    if (!$registered) {
        echo "<a class='btn btn-primary' href='../admin/register.php'>" . $escaper->escapeHtml($lang['GoToRegistration']) . " &rsaquo;</a>";
    } else {
        echo "<span class='sa-step-done-badge'>&checkmark;</span>";
    }
    echo "</div>";
    // Step 2 — SCF install (disabled until registered)
    echo "<div class='sa-step sa-step-{$scf_status}'>";
    echo "<div class='sa-step-body'><h3>2. " . $escaper->escapeHtml($lang['InstallTheSCF']) . "</h3>";
    echo "<p>" . $escaper->escapeHtml($lang['InstallTheSCFHelp']) . "</p></div>";
    if ($registered && !$scf_installed) {
        echo "<a class='btn btn-primary' href='../admin/securecontrolsframework.php'>" . $escaper->escapeHtml($lang['GoToSCF']) . " &rsaquo;</a>";
    } elseif ($scf_installed) {
        echo "<span class='sa-step-done-badge'>&checkmark;</span>";
    }
    echo "</div>";
    echo "<div class='sa-prereq-tip'>" . $escaper->escapeHtml($lang['SelfAssessmentEnableTip']) . "</div>";
    echo "</div>";
}

