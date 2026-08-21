<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));

/** The lifecycle states of an AI proposal. */
function ai_proposal_statuses(): array
{
    return ['pending', 'approved', 'rejected', 'applied'];
}

/**
 * Single source of truth for the ai_proposals table DDL — used by the
 * migration (upgrade.php) and by tests. Idempotent (IF NOT EXISTS).
 */
function ai_proposals_table_ddl(): string
{
    return "CREATE TABLE IF NOT EXISTS `ai_proposals` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `capability` VARCHAR(100) NOT NULL,
        `target_type` VARCHAR(50) NOT NULL,
        `target_id` INT NOT NULL,
        `proposed_payload` MEDIUMTEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `model` VARCHAR(191) NULL,
        `prompt_fingerprint` CHAR(64) NULL,
        `source_context` MEDIUMTEXT NULL,
        `confidence` FLOAT NULL,
        `created_at` DATETIME NOT NULL,
        `reviewer` INT NULL,
        `decided_at` DATETIME NULL,
        `applied_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `idx_target` (`target_type`, `target_id`, `status`),
        KEY `idx_capability` (`capability`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
}

/**
 * Process-wide registry of proposal capabilities. Each capability declares
 * the permission a reviewer must hold and the handler that performs the
 * actual GRC write when a proposal is approved. Plan B (control-test)
 * registers here; nothing writes GRC data except a registered handler.
 */
function &ai_proposal_registry(): array
{
    static $registry = [];
    return $registry;
}

function ai_proposal_register_capability(string $capability, string $review_permission, callable $apply_handler): void
{
    $reg = &ai_proposal_registry();
    $reg[$capability] = ['permission' => $review_permission, 'handler' => $apply_handler];
}

function ai_proposal_registered_capabilities(): array
{
    return array_keys(ai_proposal_registry());
}

function ai_proposal_capability_permission(string $capability): ?string
{
    $reg = ai_proposal_registry();
    return $reg[$capability]['permission'] ?? null;
}

function ai_proposal_apply_handler(string $capability): ?callable
{
    $reg = ai_proposal_registry();
    return $reg[$capability]['handler'] ?? null;
}

/**
 * Insert a pending proposal. Payload + source_context are JSON-encoded.
 * Returns the new proposal id. Does NOT touch any GRC table.
 */
function create_ai_proposal(string $capability, string $target_type, int $target_id, array $payload, array $provenance = []): int
{
    $db = db_open();
    $stmt = $db->prepare(
        "INSERT INTO ai_proposals
            (capability, target_type, target_id, proposed_payload, status,
             model, prompt_fingerprint, source_context, confidence, created_at)
         VALUES
            (:cap, :ttype, :tid, :payload, 'pending',
             :model, :fp, :ctx, :conf, NOW())");
    $encoded   = json_encode($payload);
    $ctx       = isset($provenance['source_context']) ? json_encode($provenance['source_context']) : null;
    $model     = $provenance['model'] ?? null;
    $fp        = $provenance['prompt_fingerprint'] ?? null;
    $conf      = $provenance['confidence'] ?? null;
    $stmt->bindParam(':cap',   $capability);
    $stmt->bindParam(':ttype', $target_type);
    $stmt->bindParam(':tid',   $target_id, PDO::PARAM_INT);
    $stmt->bindParam(':payload', $encoded);
    $stmt->bindParam(':model', $model);
    $stmt->bindParam(':fp',    $fp);
    $stmt->bindParam(':ctx',   $ctx);
    $stmt->bindParam(':conf',  $conf);
    $stmt->execute();
    $id = (int)$db->lastInsertId();
    db_close($db);
    return $id;
}

/**
 * PROVENANCE-ONLY SIBLING OF create_ai_proposal(). Records that an AI
 * capability has ALREADY WRITTEN the fields in $payload onto a target, as an
 * `ai_proposals` row that is 'applied' from birth: applied_at stamped, reviewer
 * and decided_at NULL because no human was ever asked to decide. Returns the
 * new row's id. Writes NOTHING outside `ai_proposals` — the caller has already
 * done the GRC write and is recording who did it.
 *
 * WHY THIS EXISTS RATHER THAN A SECOND PROVENANCE MECHANISM. SimpleRisk's AI
 * jobs split deliberately in two:
 *
 *   - GENERATION (ai_control_test_generate) invents something new, so it goes
 *     through create_ai_proposal() and lands 'pending' for a human to approve.
 *   - ENHANCEMENT (ai_control_reference_enhance, ai_control_to_document_process,
 *     ai_document_to_control_chunk_process, ai_risk_fair_analyze) improves a
 *     record that already exists, so it writes directly.
 *
 * That split is intentional and this function does not narrow it: an
 * enhancement still writes directly and is never queued for review. What it
 * gains is an attributable record, in the SAME table and the SAME vocabulary
 * (model / prompt_fingerprint / source_context / confidence) that the reviewed
 * half already uses, instead of a parallel and weaker one bolted onto each
 * target table.
 *
 * WHY AN 'applied' ROW CANNOT LEAK INTO A REVIEW QUEUE. Every reader that
 * treats this table as a queue gates on status='pending' — and the control-test
 * grid additionally on capability='control_test_generation':
 *
 *   - api_v2_ai_proposals_get()               get_ai_proposals(..., 'pending')
 *   - renderable_control_test_proposals()     status='pending' AND capability=…
 *   - is_renderable_control_test_proposal()   both, again, per row
 *   - approve_ai_proposal()/reject_ai_proposal()  refuse a non-'pending' row,
 *     and ai_proposal_can_review() fails closed on an unregistered capability
 *     (an enhancement capability registers no review permission or handler).
 *
 * A provenance row therefore fails two independent gates on every path. See
 * tests/unit/AiControlReferenceProvenanceTest.php, which pins this.
 *
 * $provenance takes the same keys create_ai_proposal() takes:
 * source_context, model, prompt_fingerprint, confidence. Omit a key you cannot
 * honestly fill — a NULL confidence means "this capability does not measure
 * confidence", which is the truth for the enhancement jobs, and is better than
 * a fabricated number.
 */
function record_applied_ai_proposal(string $capability, string $target_type, int $target_id, array $payload, array $provenance = []): int
{
    $db = db_open();
    $stmt = $db->prepare(
        "INSERT INTO ai_proposals
            (capability, target_type, target_id, proposed_payload, status,
             model, prompt_fingerprint, source_context, confidence, created_at, applied_at)
         VALUES
            (:cap, :ttype, :tid, :payload, 'applied',
             :model, :fp, :ctx, :conf, NOW(), NOW())");
    $encoded   = json_encode($payload);
    $ctx       = isset($provenance['source_context']) ? json_encode($provenance['source_context']) : null;
    $model     = $provenance['model'] ?? null;
    $fp        = $provenance['prompt_fingerprint'] ?? null;
    $conf      = $provenance['confidence'] ?? null;
    $stmt->bindParam(':cap',   $capability);
    $stmt->bindParam(':ttype', $target_type);
    $stmt->bindParam(':tid',   $target_id, PDO::PARAM_INT);
    $stmt->bindParam(':payload', $encoded);
    $stmt->bindParam(':model', $model);
    $stmt->bindParam(':fp',    $fp);
    $stmt->bindParam(':ctx',   $ctx);
    $stmt->bindParam(':conf',  $conf);
    $stmt->execute();
    $id = (int)$db->lastInsertId();
    db_close($db);
    return $id;
}

/**
 * The complete vocabulary ai_authorship_for_field() answers in.
 *
 * NOTE WHAT IS ABSENT: there is no 'human', no 'manual', no 'not_ai'. We can
 * observe that a model wrote a value; we can never observe that a person did.
 * Every state other than 'ai_written' means WE DO NOT KNOW who authored the
 * current value — 'unknown' because nothing was ever recorded, 'superseded'
 * because what was recorded is no longer what is stored. Neither is evidence of
 * a human, and a caller must not render either as one.
 *
 * This matters most for the rows that predate provenance: as of this change
 * `framework_control_mappings` holds 4,437 rows of which 3 carry a
 * reference_subject, and NONE of the 4,437 has a provenance record. If absence
 * were allowed to mean "human-written", this change would instantly mislabel
 * every one of them.
 */
function ai_authorship_states(): array
{
    return ['ai_written', 'superseded', 'unknown'];
}

/**
 * What we know about who authored $field on ($target_type, $target_id).
 *
 * Returns an array whose 'state' is one of ai_authorship_states():
 *
 *   'ai_written' — the newest provenance record for this field carries exactly
 *                  the value the caller says is stored now (or the caller did
 *                  not say, and is therefore asking the weaker question "did a
 *                  model ever write this field?").
 *   'superseded' — a model wrote this field once, but $current_value differs, so
 *                  the CURRENT value's author is unknown. 'recorded_value' says
 *                  what the model had written, for a diff.
 *   'unknown'    — nothing was ever recorded. Says nothing about who wrote it.
 *
 * Pass $current_value whenever you have it. Omitting it cannot upgrade the
 * answer into a claim about what is stored today — it only narrows the question.
 *
 * A field counts as recorded when the proposal's payload has that KEY, so a
 * run that authored only reference_text never attributes reference_subject.
 */
function ai_authorship_for_field(string $target_type, int $target_id, string $field, ?string $current_value = null): array
{
    $unknown = [
        'state' => 'unknown', 'capability' => null, 'model' => null,
        'prompt_fingerprint' => null, 'confidence' => null, 'source_context' => null,
        'recorded_value' => null, 'recorded_at' => null, 'proposal_id' => null,
    ];

    $db = db_open();
    $stmt = $db->prepare(
        "SELECT * FROM ai_proposals
          WHERE target_type = :ttype AND target_id = :tid AND status = 'applied'
          ORDER BY id DESC");
    $stmt->bindParam(':ttype', $target_type);
    $stmt->bindParam(':tid', $target_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    // Newest-first: the first row that recorded THIS field is the authority.
    foreach ($rows as $row) {
        $payload = json_decode($row['proposed_payload'] ?? 'null', true);
        if (!is_array($payload) || !array_key_exists($field, $payload)) {
            continue;
        }
        $recorded = $payload[$field];
        $recorded = is_string($recorded) ? $recorded : null;

        $answer = [
            'state'              => 'ai_written',
            'capability'         => $row['capability'],
            'model'              => $row['model'],
            'prompt_fingerprint' => $row['prompt_fingerprint'],
            'confidence'         => $row['confidence'] !== null ? (float)$row['confidence'] : null,
            'source_context'     => $row['source_context'] !== null ? json_decode($row['source_context'], true) : null,
            'recorded_value'     => $recorded,
            'recorded_at'        => $row['applied_at'] ?? $row['created_at'],
            'proposal_id'        => (int)$row['id'],
        ];

        if ($current_value !== null && $current_value !== $recorded) {
            // Something replaced the model's value. We do not know what — a
            // person in the control-edit modal, or an older build that wrote
            // without recording. Either way it is NOT attributable to the model.
            $answer['state'] = 'superseded';
        }
        return $answer;
    }

    return $unknown;
}

/**
 * THE READER CONSUMERS SHOULD CALL for a framework_control_mappings field
 * ('reference_text' or 'reference_subject'). Resolves the mapping by its
 * NATURAL key — the same (control_id, framework, reference_name) triple the
 * table's UNIQUE index uses and the enhancement job writes by — reads the value
 * actually stored right now, and only then asks ai_authorship_for_field().
 *
 * WHY NOT JUST CALL ai_authorship_for_field() WITH THE MAPPING id. Provenance
 * rows reference the mapping by its surrogate `id`, and there is no foreign key:
 * `framework_control_mappings` rows are deleted wholesale when a control is
 * unmapped from a framework (delete_control_to_frameworks_except()), when a
 * framework is deleted (delete_framework()), and when the ComplianceForge SCF
 * Extra re-seeds its catalog. Those deletions orphan provenance rows. InnoDB on
 * MySQL 8 persists the AUTO_INCREMENT counter so ids are not normally reused,
 * but a table rebuild (ALTER/OPTIMIZE TABLE) resets it to max(id)+1 and a
 * re-seed after a mass delete can then hand an old id to a NEW mapping. This
 * function makes that harmless: it re-checks the recorded source_context's
 * natural key against the mapping actually being asked about, and answers
 * 'unknown' when they disagree. A recycled id can never launder an old record
 * into a claim about a different clause.
 *
 * Returns the same shape as ai_authorship_for_field(), in the same vocabulary —
 * see ai_authorship_states(). NOTHING HERE EVER MEANS "a human wrote this": a
 * missing mapping, a missing record and a mismatched key all answer 'unknown'.
 */
function ai_authorship_for_control_reference(int $control_id, int $framework, string $reference_name, string $field): array
{
    $unknown = [
        'state' => 'unknown', 'capability' => null, 'model' => null,
        'prompt_fingerprint' => null, 'confidence' => null, 'source_context' => null,
        'recorded_value' => null, 'recorded_at' => null, 'proposal_id' => null,
    ];

    if (!in_array($field, ['reference_text', 'reference_subject'], true)) {
        return $unknown;
    }

    $db = db_open();
    $stmt = $db->prepare(
        "SELECT `id`, `reference_text`, `reference_subject`
           FROM `framework_control_mappings`
          WHERE `control_id` = :cid AND `framework` = :fw AND `reference_name` = :ref");
    $stmt->bindParam(':cid', $control_id, PDO::PARAM_INT);
    $stmt->bindParam(':fw',  $framework,  PDO::PARAM_INT);
    $stmt->bindParam(':ref', $reference_name);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    if ($row === false) {
        return $unknown; // no such mapping — nothing to attribute
    }

    $current = $row[$field];
    $answer = ai_authorship_for_field(
        'framework_control_mapping',
        (int)$row['id'],
        $field,
        is_string($current) ? $current : null
    );

    if ($answer['state'] === 'unknown') {
        return $answer;
    }

    // The recycled-id guard. The record must say it was written about THIS
    // clause; a record whose natural key disagrees belongs to a mapping that no
    // longer exists and tells us nothing about this one.
    $ctx = is_array($answer['source_context']) ? $answer['source_context'] : [];
    $same_clause = (int)($ctx['control_id'] ?? 0) === $control_id
        && (int)($ctx['framework_id'] ?? 0) === $framework
        && (string)($ctx['reference_name'] ?? '') === $reference_name;

    return $same_clause ? $answer : $unknown;
}

/**
 * Proposals for a target in a given status, newest first. Payload +
 * source_context are decoded back to arrays for the caller/UI.
 */
function get_ai_proposals(string $target_type, int $target_id, string $status = 'pending'): array
{
    if (!in_array($status, ai_proposal_statuses(), true)) {
        return [];
    }
    $db = db_open();
    $stmt = $db->prepare(
        "SELECT * FROM ai_proposals
         WHERE target_type = :ttype AND target_id = :tid AND status = :status
         ORDER BY id DESC");
    $stmt->bindParam(':ttype', $target_type);
    $stmt->bindParam(':tid', $target_id, PDO::PARAM_INT);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    foreach ($rows as &$r) {
        $r['proposed_payload'] = json_decode($r['proposed_payload'] ?? 'null', true);
        $r['source_context']   = $r['source_context'] !== null ? json_decode($r['source_context'], true) : null;
    }
    unset($r);
    return $rows;
}

/**
 * Whether the current user may review (approve/reject) a proposal. Requires
 * the proposal's capability to be registered and the session to hold that
 * capability's declared review permission. NOTE: target-record visibility
 * (team separation) is enforced at the API boundary using the graph's L2-L4
 * scoping for the target type — this helper is the capability-permission gate.
 */
function ai_proposal_can_review(array $proposal): bool
{
    $perm = ai_proposal_capability_permission($proposal['capability'] ?? '');
    if ($perm === null) {
        return false; // unregistered capability -> fail closed
    }
    return check_permission($perm);
}

/** Load a single proposal row (payload/source_context decoded) or null. */
function get_ai_proposal(int $id): ?array
{
    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM ai_proposals WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);
    if ($row === false) { return null; }
    $row['proposed_payload'] = json_decode($row['proposed_payload'] ?? 'null', true);
    $row['source_context']   = $row['source_context'] !== null ? json_decode($row['source_context'], true) : null;
    return $row;
}

/**
 * Atomically claim a pending proposal, moving it pending -> $to only if it is
 * still pending (compare-and-swap). Returns true iff THIS caller won the claim
 * (exactly one row changed). Closes the approve/reject TOCTOU: two concurrent
 * decisions cannot both proceed. $reviewer is the reviewing user's id.
 */
function ai_proposal_claim(int $id, string $to, int $reviewer): bool
{
    $db = db_open();
    $stmt = $db->prepare(
        "UPDATE ai_proposals SET status = :to, reviewer = :rev, decided_at = NOW()
          WHERE id = :id AND status = 'pending'");
    $stmt->bindParam(':to', $to);
    $stmt->bindParam(':rev', $reviewer, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $won = $stmt->rowCount() === 1;
    db_close($db);
    return $won;
}

/** Finalize a claimed ('approved') proposal as applied. */
function ai_proposal_mark_applied(int $id): void
{
    $db = db_open();
    $stmt = $db->prepare(
        "UPDATE ai_proposals SET status = 'applied', applied_at = NOW()
          WHERE id = :id AND status = 'approved'");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);
}

/** Revert a claimed ('approved') proposal to pending after a failed apply (no partial write). */
function ai_proposal_revert_to_pending(int $id): void
{
    $db = db_open();
    $stmt = $db->prepare(
        "UPDATE ai_proposals SET status = 'pending', reviewer = NULL, decided_at = NULL
          WHERE id = :id AND status = 'approved'");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);
}

/**
 * Approve a pending proposal: authorize, atomically claim it (pending ->
 * approved) so only one concurrent caller can proceed, dispatch the
 * capability's apply handler (the sole GRC write), then mark applied +
 * audit-log. Fails closed and reverts the claim to pending if the handler
 * fails (no partial write, proposal stays re-approvable).
 */
function approve_ai_proposal(int $id, int $reviewer): array
{
    $p = get_ai_proposal($id);
    if ($p === null)                 { return ['ok' => false, 'error' => 'not_found']; }
    if (!ai_proposal_can_review($p)) { return ['ok' => false, 'error' => 'forbidden']; }
    if ($p['status'] !== 'pending')  { return ['ok' => false, 'error' => 'not_pending']; }
    $handler = ai_proposal_apply_handler($p['capability']);
    if ($handler === null)           { return ['ok' => false, 'error' => 'no_handler']; }

    // Claim-before-dispatch: atomically move pending -> approved. Only the winner
    // dispatches the handler, so concurrent approvals can't double-write GRC data.
    if (!ai_proposal_claim($id, 'approved', $reviewer)) {
        return ['ok' => false, 'error' => 'not_pending']; // lost the race / already decided
    }

    $ok = false;
    try { $ok = (bool)$handler($p); }
    catch (\Throwable $e) {
        write_debug_log("ai_proposal {$id} apply handler threw: " . $e->getMessage(), 'error');
        $ok = false;
    }
    if (!$ok) {
        ai_proposal_revert_to_pending($id); // no partial write; re-approvable
        return ['ok' => false, 'error' => 'apply_failed'];
    }

    ai_proposal_mark_applied($id);
    // +1000: write_log subtracts 1000 (SimpleRisk risk-id offset); store the true proposal id
    write_log($id + 1000, $reviewer,
        "AI proposal {$id} ({$p['capability']} on {$p['target_type']}/{$p['target_id']}) approved and applied.",
        'ai_proposal');
    return ['ok' => true, 'error' => null];
}

/**
 * Reject a pending proposal: authorize, atomically claim it (pending ->
 * rejected) so concurrent decisions can't race, then audit-log. Never
 * dispatches a handler — no GRC write.
 */
function reject_ai_proposal(int $id, int $reviewer): array
{
    $p = get_ai_proposal($id);
    if ($p === null)                 { return ['ok' => false, 'error' => 'not_found']; }
    if (!ai_proposal_can_review($p)) { return ['ok' => false, 'error' => 'forbidden']; }
    if ($p['status'] !== 'pending')  { return ['ok' => false, 'error' => 'not_pending']; }

    // Atomic pending -> rejected; only the winner audits. No GRC write.
    if (!ai_proposal_claim($id, 'rejected', $reviewer)) {
        return ['ok' => false, 'error' => 'not_pending'];
    }
    // +1000: write_log subtracts 1000 (SimpleRisk risk-id offset); store the true proposal id
    write_log($id + 1000, $reviewer,
        "AI proposal {$id} ({$p['capability']} on {$p['target_type']}/{$p['target_id']}) rejected.",
        'ai_proposal');
    return ['ok' => true, 'error' => null];
}
