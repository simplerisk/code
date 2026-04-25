<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Load sub-components
require_once(realpath(__DIR__ . '/workflows/variables.php'));
require_once(realpath(__DIR__ . '/workflows/actions/data_ops.php'));
require_once(realpath(__DIR__ . '/workflows/actions/risk_management.php'));
require_once(realpath(__DIR__ . '/workflows/actions/governance.php'));
require_once(realpath(__DIR__ . '/workflows/actions/compliance.php'));
require_once(realpath(__DIR__ . '/workflows/actions/field_updates.php'));
require_once(realpath(__DIR__ . '/workflows/actions/flow_control.php'));
require_once(realpath(__DIR__ . '/workflows/actions/integrations.php'));
require_once(realpath(__DIR__ . '/workflows/executor.php'));

// Load action extensions registered by the Workflows Extra (if installed).
// The hook file itself returns early when the Extra is not active.
$_wf_extra_hook = realpath(__DIR__ . '/../extras/workflows/includes/action_hooks.php');
if ($_wf_extra_hook) {
    require_once($_wf_extra_hook);
}
unset($_wf_extra_hook);

/***********************************************
 * WORKFLOW TRIGGER CATALOG                    *
 * Defines every supported trigger event and  *
 * the context variables it provides.          *
 ***********************************************/
function get_workflow_trigger_catalog(): array
{
    return [
        // ── Governance ────────────────────────────────────────────────────
        // Framework triggers are enriched with full framework fields by enrich_workflow_context().
        'framework.created' => [
            'label'       => 'Framework Added',
            'category'    => 'Governance',
            'description' => 'Fires when a new framework is added.',
            'variables'   => [
                'framework_id' => 'Framework ID',
                'name'         => 'Framework name',
                'description'  => 'Framework description',
                'status'       => 'Framework status (1=Active, 0=Inactive)',
                'parent'       => 'Parent framework ID',
                'parent_name'  => 'Parent framework name',
            ],
        ],
        'framework.updated' => [
            'label'       => 'Framework Updated',
            'category'    => 'Governance',
            'description' => 'Fires when a framework is updated.',
            'variables'   => [
                'framework_id' => 'Framework ID',
                'name'         => 'Framework name',
                'description'  => 'Framework description',
                'status'       => 'Framework status (1=Active, 0=Inactive)',
                'parent'       => 'Parent framework ID',
                'parent_name'  => 'Parent framework name',
            ],
        ],
        'framework.deleted' => [
            'label'       => 'Framework Deleted',
            'category'    => 'Governance',
            'description' => 'Fires when a framework is deleted.',
            'variables'   => [
                'framework_id' => 'Framework ID',
                'name'         => 'Framework name',
            ],
        ],
        // Control triggers are enriched with full control fields by enrich_workflow_context().
        'control.created' => [
            'label'       => 'Control Added',
            'category'    => 'Governance',
            'description' => 'Fires when a new control is added.',
            'variables'   => [
                'control_id'            => 'Control ID',
                'short_name'            => 'Control short name',
                'long_name'             => 'Control long name',
                'description'           => 'Control description',
                'supplemental_guidance' => 'Supplemental guidance',
                'control_owner'         => 'Control owner user ID',
                'control_owner_name'    => 'Control owner display name',
                'control_class'         => 'Control class ID',
                'control_class_name'    => 'Control class name',
                'control_phase'         => 'Control phase ID',
                'control_phase_name'    => 'Control phase name',
                'control_number'        => 'Control number',
                'control_maturity'      => 'Control maturity ID',
                'control_maturity_name' => 'Control maturity name',
                'desired_maturity'      => 'Desired maturity ID',
                'desired_maturity_name' => 'Desired maturity name',
                'control_priority'      => 'Control priority ID',
                'control_priority_name' => 'Control priority name',
                'family'                => 'Control family ID',
                'family_name'           => 'Control family name',
                'control_type_name'     => 'Control type name',
                'control_status'        => 'Control status (1=Active, 0=Inactive)',
                'mitigation_percent'    => 'Mitigation percent (0–100)',
            ],
        ],
        'control.updated' => [
            'label'       => 'Control Updated',
            'category'    => 'Governance',
            'description' => 'Fires when a control is updated.',
            'variables'   => [
                'control_id'            => 'Control ID',
                'short_name'            => 'Control short name',
                'long_name'             => 'Control long name',
                'description'           => 'Control description',
                'supplemental_guidance' => 'Supplemental guidance',
                'control_owner'         => 'Control owner user ID',
                'control_owner_name'    => 'Control owner display name',
                'control_class'         => 'Control class ID',
                'control_class_name'    => 'Control class name',
                'control_phase'         => 'Control phase ID',
                'control_phase_name'    => 'Control phase name',
                'control_number'        => 'Control number',
                'control_maturity'      => 'Control maturity ID',
                'control_maturity_name' => 'Control maturity name',
                'desired_maturity'      => 'Desired maturity ID',
                'desired_maturity_name' => 'Desired maturity name',
                'control_priority'      => 'Control priority ID',
                'control_priority_name' => 'Control priority name',
                'family'                => 'Control family ID',
                'family_name'           => 'Control family name',
                'control_type_name'     => 'Control type name',
                'control_status'        => 'Control status (1=Active, 0=Inactive)',
                'mitigation_percent'    => 'Mitigation percent (0–100)',
            ],
        ],
        'control.deleted' => [
            'label'       => 'Control Deleted',
            'category'    => 'Governance',
            'description' => 'Fires when a control is deleted.',
            'variables'   => [
                'control_id' => 'Control ID',
                'short_name' => 'Control short name',
                'long_name'  => 'Control long name',
            ],
        ],
        // Document triggers are enriched with full document fields by enrich_workflow_context().
        'document.created' => [
            'label'       => 'Document Added',
            'category'    => 'Governance',
            'description' => 'Fires when a new document is added.',
            'variables'   => [
                'document_id'             => 'Document ID',
                'document_name'           => 'Document name',
                'document_type'           => 'Document type',
                'submitted_by'            => 'Submitter user ID',
                'document_owner'          => 'Document owner user ID',
                'document_owner_name'     => 'Document owner display name',
                'document_status'         => 'Document status ID',
                'document_status_name'    => 'Document status name',
                'creation_date'           => 'Creation date',
                'last_review_date'        => 'Last review date',
                'review_frequency'        => 'Review frequency (days)',
                'next_review_date'        => 'Next review date',
                'approval_date'           => 'Approval date',
                'approver'                => 'Approver user ID',
                'approver_name'           => 'Approver display name',
                'additional_stakeholders' => 'Additional stakeholder user IDs (comma-separated)',
                'team_ids'                => 'Team IDs (comma-separated)',
                'parent_document_id'      => 'Parent document ID',
                'parent_document_name'    => 'Parent document name',
                'document_frameworks'     => 'Associated framework IDs (comma-separated)',
                'document_controls'       => 'Associated control IDs (comma-separated)',
            ],
        ],
        'document.updated' => [
            'label'       => 'Document Updated',
            'category'    => 'Governance',
            'description' => 'Fires when a document is updated.',
            'variables'   => [
                'document_id'             => 'Document ID',
                'document_name'           => 'Document name',
                'document_type'           => 'Document type',
                'updated_by'              => 'Updater user ID',
                'document_owner'          => 'Document owner user ID',
                'document_owner_name'     => 'Document owner display name',
                'document_status'         => 'Document status ID',
                'document_status_name'    => 'Document status name',
                'creation_date'           => 'Creation date',
                'last_review_date'        => 'Last review date',
                'review_frequency'        => 'Review frequency (days)',
                'next_review_date'        => 'Next review date',
                'approval_date'           => 'Approval date',
                'approver'                => 'Approver user ID',
                'approver_name'           => 'Approver display name',
                'additional_stakeholders' => 'Additional stakeholder user IDs (comma-separated)',
                'team_ids'                => 'Team IDs (comma-separated)',
                'parent_document_id'      => 'Parent document ID',
                'parent_document_name'    => 'Parent document name',
                'document_frameworks'     => 'Associated framework IDs (comma-separated)',
                'document_controls'       => 'Associated control IDs (comma-separated)',
            ],
        ],
        'document.deleted' => [
            'label'       => 'Document Deleted',
            'category'    => 'Governance',
            'description' => 'Fires when a document is deleted.',
            'variables'   => [
                'document_id'   => 'Document ID',
                'document_name' => 'Document name',
            ],
        ],
        // Exception triggers are enriched with full exception fields by enrich_workflow_context().
        'exception.created' => [
            'label'       => 'Exception Added',
            'category'    => 'Governance',
            'description' => 'Fires when a new exception is created.',
            'variables'   => [
                'exception_id'     => 'Exception ID',
                'name'             => 'Exception name',
                'owner'            => 'Exception owner user ID',
                'owner_name'       => 'Exception owner display name',
                'status'           => 'Exception status ID',
                'status_name'      => 'Exception status name',
                'approver'         => 'Approver user ID',
                'approver_name'    => 'Approver display name',
                'creation_date'    => 'Creation date',
                'next_review_date' => 'Next review date',
                'approval_date'    => 'Approval date',
                'policy_name'      => 'Associated policy name',
                'description'      => 'Exception description',
                'justification'    => 'Exception justification',
            ],
        ],
        'exception.approved' => [
            'label'       => 'Exception Approved',
            'category'    => 'Governance',
            'description' => 'Fires when an exception is approved.',
            'variables'   => [
                'exception_id'     => 'Exception ID',
                'name'             => 'Exception name',
                'approver'         => 'Approver user ID',
                'approver_name'    => 'Approver display name',
                'owner'            => 'Exception owner user ID',
                'owner_name'       => 'Exception owner display name',
                'status_name'      => 'Exception status name',
                'approval_date'    => 'Approval date',
                'policy_name'      => 'Associated policy name',
            ],
        ],
        'exception.unapproved' => [
            'label'       => 'Exception Unapproved',
            'category'    => 'Governance',
            'description' => 'Fires when an exception approval is revoked.',
            'variables'   => [
                'exception_id'  => 'Exception ID',
                'name'          => 'Exception name',
                'owner'         => 'Exception owner user ID',
                'owner_name'    => 'Exception owner display name',
                'status_name'   => 'Exception status name',
                'policy_name'   => 'Associated policy name',
            ],
        ],
        'exception.updated' => [
            'label'       => 'Exception Updated',
            'category'    => 'Governance',
            'description' => 'Fires when an exception is updated.',
            'variables'   => [
                'exception_id'     => 'Exception ID',
                'name'             => 'Exception name',
                'owner'            => 'Exception owner user ID',
                'owner_name'       => 'Exception owner display name',
                'status'           => 'Exception status ID',
                'status_name'      => 'Exception status name',
                'approver'         => 'Approver user ID',
                'approver_name'    => 'Approver display name',
                'creation_date'    => 'Creation date',
                'next_review_date' => 'Next review date',
                'approval_date'    => 'Approval date',
                'policy_name'      => 'Associated policy name',
                'description'      => 'Exception description',
                'justification'    => 'Exception justification',
            ],
        ],
        'exception.deleted' => [
            'label'       => 'Exception Deleted',
            'category'    => 'Governance',
            'description' => 'Fires when an exception is deleted.',
            'variables'   => [
                'exception_id' => 'Exception ID',
                'name'         => 'Exception name',
            ],
        ],
        // ── Risk Management ───────────────────────────────────────────────
        // All risk triggers are enriched with full risk fields by enrich_workflow_context().
        'risk.submitted' => [
            'label'       => 'Risk Submitted',
            'category'    => 'Risk Management',
            'description' => 'Fires when a new risk is submitted.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID (risk_id + 1000)',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Owner user ID',
                'owner_name'      => 'Owner display name',
                'manager'         => 'Manager user ID',
                'manager_name'    => 'Manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category'        => 'Category ID',
                'category_name'   => 'Category name',
                'source'          => 'Source ID',
                'source_name'     => 'Source name',
                'project_id'      => 'Project ID',
                'project_name'    => 'Project name',
                'submission_date' => 'Submission date',
                'reference_id'    => 'External reference ID',
                'control_number'  => 'Control number',
                'regulation'      => 'Regulation/framework ID',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'risk.updated' => [
            'label'       => 'Risk Updated',
            'category'    => 'Risk Management',
            'description' => 'Fires when an existing risk is updated.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'changed_fields'  => 'Comma-separated list of field names that changed',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Owner user ID',
                'owner_name'      => 'Owner display name',
                'manager'         => 'Manager user ID',
                'manager_name'    => 'Manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category'        => 'Category ID',
                'category_name'   => 'Category name',
                'source'          => 'Source ID',
                'source_name'     => 'Source name',
                'project_id'      => 'Project ID',
                'project_name'    => 'Project name',
                'submission_date' => 'Submission date',
                'reference_id'    => 'External reference ID',
                'control_number'  => 'Control number',
                'regulation'      => 'Regulation/framework ID',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'risk.scored' => [
            'label'       => 'Risk Score Changed',
            'category'    => 'Risk Management',
            'description' => 'Fires when a risk score is recalculated.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'old_score'       => 'Previous calculated risk score',
                'new_score'       => 'New calculated risk score',
                'scoring_method'  => 'Scoring method used',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Owner user ID',
                'owner_name'      => 'Owner display name',
                'manager'         => 'Manager user ID',
                'manager_name'    => 'Manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category_name'   => 'Category name',
                'source_name'     => 'Source name',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'risk.closed' => [
            'label'       => 'Risk Closed',
            'category'    => 'Risk Management',
            'description' => 'Fires when a risk is marked as Closed.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'closure_reason'  => 'Reason for closure',
                'subject'         => 'Risk subject',
                'owner'           => 'Owner user ID',
                'owner_name'      => 'Owner display name',
                'manager'         => 'Manager user ID',
                'manager_name'    => 'Manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category_name'   => 'Category name',
                'source_name'     => 'Source name',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'risk.reopened' => [
            'label'       => 'Risk Reopened',
            'category'    => 'Risk Management',
            'description' => 'Fires when a closed risk is reopened.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Owner user ID',
                'owner_name'      => 'Owner display name',
                'manager'         => 'Manager user ID',
                'manager_name'    => 'Manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category_name'   => 'Category name',
                'source_name'     => 'Source name',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'mitigation.submitted' => [
            'label'       => 'Mitigation Submitted',
            'category'    => 'Risk Management',
            'description' => 'Fires when a mitigation is submitted for a risk.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'mitigation_id'   => 'Mitigation ID',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Risk owner user ID',
                'owner_name'      => 'Risk owner display name',
                'manager'         => 'Risk manager user ID',
                'manager_name'    => 'Risk manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category_name'   => 'Category name',
                'source_name'     => 'Source name',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'mitigation.updated' => [
            'label'       => 'Mitigation Updated',
            'category'    => 'Risk Management',
            'description' => 'Fires when an existing mitigation is updated.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'mitigation_id'   => 'Mitigation ID',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Risk owner user ID',
                'owner_name'      => 'Risk owner display name',
                'manager'         => 'Risk manager user ID',
                'manager_name'    => 'Risk manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category_name'   => 'Category name',
                'source_name'     => 'Source name',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        'review.submitted' => [
            'label'       => 'Management Review Submitted',
            'category'    => 'Risk Management',
            'description' => 'Fires when a management review is submitted.',
            'variables'   => [
                'risk_id'         => 'Internal risk ID',
                'display_risk_id' => 'Display risk ID',
                'review_id'       => 'Review ID',
                'reviewer'        => 'Reviewer user ID',
                'decision'        => 'Review decision',
                'subject'         => 'Risk subject',
                'status'          => 'Risk status',
                'owner'           => 'Risk owner user ID',
                'owner_name'      => 'Risk owner display name',
                'manager'         => 'Risk manager user ID',
                'manager_name'    => 'Risk manager display name',
                'submitted_by'    => 'Submitter user ID',
                'submitter_name'  => 'Submitter display name',
                'category_name'   => 'Category name',
                'source_name'     => 'Source name',
                'calculated_risk' => 'Calculated risk score',
            ],
        ],
        // ── Compliance ────────────────────────────────────────────────────
        // Test triggers are enriched with full test + control fields by enrich_workflow_context().
        'test.created' => [
            'label'       => 'Test Added',
            'category'    => 'Compliance',
            'description' => 'Fires when a new test is added to a control.',
            'variables'   => [
                'test_id'                 => 'Test ID',
                'control_id'              => 'Framework control ID',
                'name'                    => 'Test name',
                'tester'                  => 'Assigned tester user ID',
                'tester_name'             => 'Assigned tester display name',
                'test_frequency'          => 'Test frequency (days)',
                'objective'               => 'Test objective',
                'last_date'               => 'Last test date',
                'next_date'               => 'Next test date',
                'additional_stakeholders' => 'Additional stakeholder user IDs (comma-separated)',
                'test_steps'              => 'Test steps',
                'approximate_time'        => 'Approximate time to complete',
                'expected_results'        => 'Expected results',
                'audit_initiation_offset' => 'Audit initiation offset (days before next_date; NULL = disabled)',
                'teams'                   => 'Assigned team IDs (comma-separated)',
                'short_name'              => 'Control short name',
                'long_name'               => 'Control long name',
                'control_owner_name'      => 'Control owner display name',
            ],
        ],
        'test.updated' => [
            'label'       => 'Test Updated',
            'category'    => 'Compliance',
            'description' => 'Fires when an existing test is edited.',
            'variables'   => [
                'test_id'                 => 'Test ID',
                'control_id'              => 'Framework control ID',
                'name'                    => 'Test name',
                'tester'                  => 'Assigned tester user ID',
                'tester_name'             => 'Assigned tester display name',
                'test_frequency'          => 'Test frequency (days)',
                'objective'               => 'Test objective',
                'last_date'               => 'Last test date',
                'next_date'               => 'Next test date',
                'additional_stakeholders' => 'Additional stakeholder user IDs (comma-separated)',
                'test_steps'              => 'Test steps',
                'approximate_time'        => 'Approximate time to complete',
                'expected_results'        => 'Expected results',
                'audit_initiation_offset' => 'Audit initiation offset (days before next_date; NULL = disabled)',
                'teams'                   => 'Assigned team IDs (comma-separated)',
                'short_name'              => 'Control short name',
                'long_name'               => 'Control long name',
                'control_owner_name'      => 'Control owner display name',
            ],
        ],
        'test.deleted' => [
            'label'       => 'Test Deleted',
            'category'    => 'Compliance',
            'description' => 'Fires when a test is deleted.',
            'variables'   => [
                'test_id'    => 'Test ID',
                'control_id' => 'Framework control ID',
            ],
        ],
        // Audit triggers are enriched with full audit/result fields by enrich_workflow_context().
        'audit.initiated' => [
            'label'       => 'Audit Initiated',
            'category'    => 'Compliance',
            'description' => 'Fires when a new audit is initiated for a test.',
            'variables'   => [
                'audit_id'          => 'Audit ID',
                'test_id'           => 'Test ID',
                'control_id'        => 'Framework control ID',
                'name'              => 'Audit/test name',
                'status'            => 'Audit status ID',
                'audit_status_name' => 'Audit status name',
                'tester'            => 'Tester user ID',
                'tester_name'       => 'Tester display name',
                'test_result'       => 'Test result',
                'summary'           => 'Test result summary',
                'test_date'         => 'Test date',
            ],
        ],
        'audit.updated' => [
            'label'       => 'Audit Updated',
            'category'    => 'Compliance',
            'description' => 'Fires when an audit is edited (result saved).',
            'variables'   => [
                'audit_id'          => 'Audit ID',
                'test_id'           => 'Test ID',
                'control_id'        => 'Framework control ID',
                'name'              => 'Audit/test name',
                'status'            => 'Audit status ID',
                'audit_status_name' => 'Audit status name',
                'tester'            => 'Tester user ID',
                'tester_name'       => 'Tester display name',
                'test_result'       => 'Test result',
                'summary'           => 'Test result summary',
                'test_date'         => 'Test date',
            ],
        ],
        // ── Assets ────────────────────────────────────────────────────────
        // Asset triggers are enriched with full asset fields by enrich_workflow_context().
        'asset.created' => [
            'label'       => 'Asset Added',
            'category'    => 'Assets',
            'description' => 'Fires when a new asset is added.',
            'variables'   => [
                'asset_id' => 'Asset ID',
                'name'     => 'Asset name',
                'ip'       => 'IP address',
                'value'    => 'Asset valuation ID',
                'details'  => 'Asset details',
                'location' => 'Location IDs (comma-separated)',
                'teams'    => 'Team IDs (comma-separated)',
                'verified' => 'Verified (1=Yes, 0=No)',
            ],
        ],
        'asset.updated' => [
            'label'       => 'Asset Updated',
            'category'    => 'Assets',
            'description' => 'Fires when an asset is updated.',
            'variables'   => [
                'asset_id' => 'Asset ID',
                'name'     => 'Asset name',
                'ip'       => 'IP address',
                'value'    => 'Asset valuation ID',
                'details'  => 'Asset details',
                'location' => 'Location IDs (comma-separated)',
                'teams'    => 'Team IDs (comma-separated)',
                'verified' => 'Verified (1=Yes, 0=No)',
            ],
        ],
        'asset.deleted' => [
            'label'       => 'Asset Deleted',
            'category'    => 'Assets',
            'description' => 'Fires when an asset is deleted.',
            'variables'   => [
                'asset_id' => 'Asset ID',
                'name'     => 'Asset name',
            ],
        ],
        // ── Scheduled ─────────────────────────────────────────────────────
        'scheduled.run' => [
            'label'       => 'Run On Schedule',
            'category'    => 'Scheduled',
            'description' => 'Fires on a configured recurring schedule (daily, weekly, monthly, or annual).',
            'variables'   => [
                'date'      => 'Current date (Y-m-d)',
                'time'      => 'Scheduled time (HH:MM)',
                'timestamp' => 'Unix timestamp',
            ],
        ],
    ];
}

/***********************************************
 * WORKFLOW ACTION CATALOG                     *
 * Defines every supported action type and    *
 * the input schema the builder uses to render *
 * configuration fields.                       *
 ***********************************************/
function get_workflow_action_catalog(): array
{
    $catalog = [
        // Communications
        // Note: send_email, send_webhook, send_slack, send_teams are registered by the Workflows Extra (extras/workflows)

        // assign_risk_owner and set_risk_status removed from catalog — covered by the
        // unified update_field action (owner and status fields). Backend cases retained
        // for backwards compatibility with saved workflows.
        // assign_control_owner and create_test_task removed from catalog — covered by
        // the unified update_field action. Backend cases retained for backwards compatibility.
        // flag_for_review removed — not associated with real SimpleRisk functionality.
        // assign_audit removed — not associated with real SimpleRisk functionality.
        'submit_risk' => [
            'label'    => 'Submit Risk',
            'category' => 'Actions',
            'sync'     => true,
            'inputs'   => [
                'subject'  => ['type' => 'string',   'label' => 'Subject (supports {{variable}})',  'required' => true],
                'owner'    => ['type' => 'user',     'label' => 'Owner',    'required' => false],
                'source'   => ['type' => 'string',   'label' => 'Source ID (or {{variable}})', 'required' => false],
                'category' => ['type' => 'string',   'label' => 'Category ID (or {{variable}})', 'required' => false],
                'notes'    => ['type' => 'textarea', 'label' => 'Notes (supports {{variable}})', 'required' => false],
            ],
        ],
        'submit_mitigation' => [
            'label'             => 'Submit Mitigation',
            'category'          => 'Actions',
            'trigger_categories'=> ['Risk Management'],
            'sync'              => true,
            'inputs'            => [
                'strategy' => ['type' => 'string', 'label' => 'Mitigation Strategy ID', 'required' => false],
                'owner'    => ['type' => 'user',   'label' => 'Mitigation Owner', 'required' => false],
                'cost'     => ['type' => 'string', 'label' => 'Cost Estimate', 'required' => false],
            ],
        ],
        'submit_review' => [
            'label'             => 'Submit Management Review',
            'category'          => 'Actions',
            'trigger_categories'=> ['Risk Management'],
            'sync'              => true,
            'inputs'            => [
                'reviewer' => ['type' => 'user',     'label' => 'Reviewer', 'required' => true],
                'decision' => ['type' => 'select',   'label' => 'Decision', 'options' => ['Approve', 'Reject', 'Accept', 'Avoid', 'Transfer', 'Discuss'], 'required' => true],
                'notes'    => ['type' => 'textarea', 'label' => 'Notes', 'required' => false],
            ],
        ],
        // Universal field updater — available to all trigger categories.
        // The 'trigger_category' property on each option is used by the JS builder
        // to filter visible options based on the current trigger type.
        'update_field' => [
            'label'                    => 'Update Field',
            'category'                 => 'Actions',
            'sync'                     => true,
            'excluded_trigger_suffix'  => '.deleted',
            'inputs'                   => [
                'field' => [
                    'type'     => 'select',
                    'label'    => 'Field',
                    'required' => true,
                    'options'  => [
                        // Risk Management
                        // Risk Management — direct risk fields
                        ['value' => 'status',                        'label' => 'Status',                   'trigger_category' => 'Risk Management'],
                        ['value' => 'subject',                       'label' => 'Subject',                  'trigger_category' => 'Risk Management'],
                        ['value' => 'category',                      'label' => 'Category',                 'trigger_category' => 'Risk Management'],
                        ['value' => 'source',                        'label' => 'Risk Source',              'trigger_category' => 'Risk Management'],
                        ['value' => 'owner',                         'label' => 'Owner',                    'trigger_category' => 'Risk Management'],
                        ['value' => 'manager',                       'label' => 'Owner\'s Manager',         'trigger_category' => 'Risk Management'],
                        ['value' => 'project_id',                    'label' => 'Project',                  'trigger_category' => 'Risk Management'],
                        ['value' => 'reference_id',                  'label' => 'External Reference ID',    'trigger_category' => 'Risk Management'],
                        ['value' => 'regulation',                    'label' => 'Control Regulation',       'trigger_category' => 'Risk Management'],
                        ['value' => 'control_number',                'label' => 'Control Number',           'trigger_category' => 'Risk Management'],
                        ['value' => 'assessment',                    'label' => 'Risk Assessment',          'trigger_category' => 'Risk Management'],
                        ['value' => 'notes',                         'label' => 'Additional Notes',         'trigger_category' => 'Risk Management'],
                        ['value' => 'submission_date',               'label' => 'Submission Date',          'trigger_category' => 'Risk Management'],
                        // Risk Management — junction table fields on risks
                        ['value' => 'risk__location',                'label' => 'Site/Location',            'trigger_category' => 'Risk Management'],
                        ['value' => 'risk__team',                    'label' => 'Team',                     'trigger_category' => 'Risk Management'],
                        ['value' => 'risk__technology',              'label' => 'Technology',               'trigger_category' => 'Risk Management'],
                        ['value' => 'risk__additional_stakeholders', 'label' => 'Additional Stakeholders',  'trigger_category' => 'Risk Management'],
                        ['value' => 'risk__risk_mapping',            'label' => 'Risk Mapping',             'trigger_category' => 'Risk Management'],
                        ['value' => 'risk__threat_mapping',          'label' => 'Threat Mapping',           'trigger_category' => 'Risk Management'],
                        ['value' => 'risk__affected_assets',         'label' => 'Affected Assets',          'trigger_category' => 'Risk Management'],
                        // Risk Management — mitigation fields
                        ['value' => 'mitigation__submission_date',       'label' => 'Mitigation Submission Date', 'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__planning_date',         'label' => 'Planned Mitigation Date',    'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__planning_strategy',     'label' => 'Planning Strategy',          'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__mitigation_effort',     'label' => 'Mitigation Effort',          'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__mitigation_cost',       'label' => 'Mitigation Cost',            'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__mitigation_owner',      'label' => 'Mitigation Owner',           'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__mitigation_team',       'label' => 'Mitigation Team',            'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__mitigation_percent',    'label' => 'Mitigation Percent',         'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__current_solution',      'label' => 'Current Solution',           'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__security_requirements', 'label' => 'Security Requirements',      'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        ['value' => 'mitigation__security_recommendations','label' => 'Security Recommendations', 'trigger_types' => ['mitigation.submitted', 'mitigation.updated']],
                        // Risk Management — management review fields
                        ['value' => 'review__submission_date', 'label' => 'Review Date',       'trigger_types' => ['review.submitted']],
                        ['value' => 'review__reviewer',        'label' => 'Reviewer',          'trigger_types' => ['review.submitted']],
                        ['value' => 'review__review',          'label' => 'Review',            'trigger_types' => ['review.submitted']],
                        ['value' => 'review__next_step',       'label' => 'Next Step',         'trigger_types' => ['review.submitted']],
                        ['value' => 'review__next_review',     'label' => 'Next Review Date',  'trigger_types' => ['review.submitted']],
                        ['value' => 'review__comments',        'label' => 'Comment',           'trigger_types' => ['review.submitted']],
                        // Governance — Framework triggers
                        ['value' => 'framework__name',        'label' => 'Framework Name',        'trigger_types' => ['framework.created', 'framework.updated']],
                        ['value' => 'framework__description', 'label' => 'Framework Description', 'trigger_types' => ['framework.created', 'framework.updated']],
                        ['value' => 'framework__parent',      'label' => 'Parent Framework',      'trigger_types' => ['framework.created', 'framework.updated']],
                        // Governance — Control triggers
                        ['value' => 'control__short_name',            'label' => 'Control Short Name',        'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__long_name',             'label' => 'Control Long Name',         'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__description',           'label' => 'Control Description',       'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__supplemental_guidance', 'label' => 'Supplemental Guidance',     'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_owner',         'label' => 'Control Owner',             'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_class',         'label' => 'Control Class',             'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_phase',         'label' => 'Control Phase',             'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_number',        'label' => 'Control Number',            'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_maturity',      'label' => 'Current Control Maturity',  'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__desired_maturity',      'label' => 'Desired Control Maturity',  'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_priority',      'label' => 'Control Priority',          'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__family',                'label' => 'Control Family',            'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_type',          'label' => 'Control Type',              'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__control_status',        'label' => 'Control Status',            'trigger_types' => ['control.created', 'control.updated']],
                        ['value' => 'control__mitigation_percent',    'label' => 'Mitigation Percent',        'trigger_types' => ['control.created', 'control.updated']],
                        // Governance — Document triggers
                        ['value' => 'document__document_type',          'label' => 'Document Type',          'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__document_name',          'label' => 'Document Name',          'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__document_frameworks',    'label' => 'Frameworks',             'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__document_controls',      'label' => 'Controls',               'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__additional_stakeholders','label' => 'Additional Stakeholders','trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__document_owner',         'label' => 'Document Owner',         'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__team_ids',               'label' => 'Team',                   'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__creation_date',          'label' => 'Creation Date',          'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__last_review_date',       'label' => 'Last Review',            'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__review_frequency',       'label' => 'Review Frequency',       'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__next_review_date',       'label' => 'Next Review Date',       'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__approval_date',          'label' => 'Approval Date',          'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__approver',               'label' => 'Approver',               'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__parent',                 'label' => 'Parent Document',        'trigger_types' => ['document.created', 'document.updated']],
                        ['value' => 'document__document_status',        'label' => 'Document Status',        'trigger_types' => ['document.created', 'document.updated']],
                        // Governance — Exception triggers
                        ['value' => 'exception__name',                   'label' => 'Exception Name',          'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__status',                 'label' => 'Exception Status',        'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__policy',                 'label' => 'Policy',                  'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__framework',              'label' => 'Framework',               'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__control',                'label' => 'Control',                 'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__associated_risks',       'label' => 'Associated Risks',        'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__owner',                  'label' => 'Exception Owner',         'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__additional_stakeholders','label' => 'Additional Stakeholders', 'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__creation_date',          'label' => 'Creation Date',           'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__review_frequency',       'label' => 'Review Frequency',        'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__next_review_date',       'label' => 'Next Review Date',        'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__approval_date',          'label' => 'Approval Date',           'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__approver',               'label' => 'Approver',                'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__description',            'label' => 'Description',             'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        ['value' => 'exception__justification',          'label' => 'Justification',           'trigger_types' => ['exception.created', 'exception.approved', 'exception.unapproved', 'exception.updated']],
                        // Compliance
                        ['value' => 'test__name',                    'label' => 'Test Name',                   'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__tester',                  'label' => 'Tester',                      'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__additional_stakeholders', 'label' => 'Additional Stakeholders',     'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__teams',                   'label' => 'Team(s)',                     'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__test_frequency',          'label' => 'Test Frequency',              'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__last_date',               'label' => 'Last Test Date',             'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__auto_audit_initiation',   'label' => 'Automatically Initiate Audit','trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__audit_initiation_offset', 'label' => 'Audit Initiation Offset',    'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__objective',               'label' => 'Objective',                  'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__test_steps',              'label' => 'Test Steps',                 'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__approximate_time',        'label' => 'Approximate Time',           'trigger_types' => ['test.created', 'test.updated']],
                        ['value' => 'test__expected_results',        'label' => 'Expected Results',           'trigger_types' => ['test.created', 'test.updated']],
                        // Assets
                        ['value' => 'asset__name',             'label' => 'Asset Name',         'trigger_category' => 'Assets'],
                        ['value' => 'asset__ip',               'label' => 'IP Address',         'trigger_category' => 'Assets'],
                        ['value' => 'asset__value',            'label' => 'Asset Valuation',    'trigger_category' => 'Assets'],
                        ['value' => 'asset__location',         'label' => 'Site/Location',      'trigger_category' => 'Assets'],
                        ['value' => 'asset__teams',            'label' => 'Team',               'trigger_category' => 'Assets'],
                        ['value' => 'asset__details',          'label' => 'Asset Details',      'trigger_category' => 'Assets'],
                        ['value' => 'asset__associated_risks', 'label' => 'Associated Risks',   'trigger_category' => 'Assets'],
                        ['value' => 'asset__verified',         'label' => 'Verified',           'trigger_category' => 'Assets'],
                        // Audit fields
                        ['value' => 'audit__status',      'label' => 'Audit Status',  'trigger_types' => ['audit.initiated', 'audit.updated']],
                        ['value' => 'audit__test_result', 'label' => 'Test Result',   'trigger_types' => ['audit.initiated', 'audit.updated']],
                        ['value' => 'audit__tester',      'label' => 'Tester',        'trigger_types' => ['audit.initiated', 'audit.updated']],
                        ['value' => 'audit__test_date',   'label' => 'Test Date',     'trigger_types' => ['audit.initiated', 'audit.updated']],
                        ['value' => 'audit__teams',       'label' => 'Team(s)',        'trigger_types' => ['audit.initiated', 'audit.updated']],
                        ['value' => 'audit__summary',     'label' => 'Summary',       'trigger_types' => ['audit.initiated', 'audit.updated']],
                    ],
                ],
                'value' => ['type' => 'field_value', 'label' => 'New Value', 'required' => true, 'depends_on' => 'field'],
            ],
        ],
        'add_risk_comment' => [
            'label'             => 'Add Risk Comment',
            'category'          => 'Actions',
            'trigger_categories'=> ['Risk Management'],
            'sync'              => true,
            'inputs'   => [
                'comment' => ['type' => 'textarea', 'label' => 'Comment (supports {{variable}})', 'required' => true],
                'author'  => ['type' => 'user',     'label' => 'Author',                          'required' => false],
            ],
        ],
        'add_audit_comment' => [
            'label'             => 'Add Audit Comment',
            'category'          => 'Actions',
            'trigger_types'     => ['audit.initiated', 'audit.updated'],
            'sync'              => true,
            'inputs'            => [
                'comment' => ['type' => 'textarea', 'label' => 'Comment (supports {{variable}})', 'required' => true],
                'author'  => ['type' => 'user',     'label' => 'Author',                          'required' => false],
            ],
        ],
        // Flow Control
        'delay' => [
            'label'    => 'Delay',
            'category' => 'Flow Control',
            'sync'     => true,
            'inputs'   => [
                'duration' => ['type' => 'number', 'label' => 'Duration', 'required' => true],
                'unit'     => ['type' => 'select', 'label' => 'Unit', 'options' => ['minutes', 'hours', 'days'], 'required' => true],
            ],
        ],
        'branch' => [
            'label'    => 'Branch (If/Then)',
            'category' => 'Flow Control',
            'sync'     => true,
            'inputs'   => [
                'field'    => ['type' => 'string', 'label' => 'Field (e.g. risk.score or {{variable}})', 'required' => true],
                'operator' => ['type' => 'select', 'label' => 'Operator', 'options' => ['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'contains', 'is_empty', 'is_not_empty'], 'required' => true],
                'value'    => ['type' => 'string', 'label' => 'Value', 'required' => false],
            ],
        ],
        'wait_for_condition' => [
            'label'    => 'Wait for Condition',
            'category' => 'Flow Control',
            'sync'     => true,
            'inputs'   => [
                'field'    => ['type' => 'string', 'label' => 'Field to watch', 'required' => true],
                'operator' => ['type' => 'select', 'label' => 'Operator', 'options' => ['=', '!=', '>', '>=', '<', '<='], 'required' => true],
                'value'    => ['type' => 'string', 'label' => 'Expected value', 'required' => true],
                'timeout'  => ['type' => 'number', 'label' => 'Timeout (hours)', 'required' => false],
            ],
        ],
        // Integrations — risk-specific (require risk_id in context)
        'notify_new_risk' => [
            'label'             => 'Send New Risk Notification',
            'category'          => 'Integrations',
            'trigger_categories'=> ['Risk Management'],
            'sync'              => false,
            'inputs'            => [],
        ],
        'create_jira_issue' => [
            'label'             => 'Create JIRA Issue',
            'category'          => 'Integrations',
            'trigger_categories'=> ['Risk Management'],
            'sync'              => false,
            'inputs'            => [],
        ],
        'sync_jira_issue' => [
            'label'             => 'Sync JIRA Issue',
            'category'          => 'Integrations',
            'trigger_categories'=> ['Risk Management'],
            'sync'              => false,
            'inputs'            => [],
        ],
    ];

    // Merge in actions registered by the Workflows Extra (or other extras)
    // Communications actions (send_email, send_webhook, send_slack, send_teams) are added here
    foreach ($GLOBALS['_workflow_extra_action_catalog'] ?? [] as $type => $entry) {
        $catalog[$type] = $entry;
    }

    // Data Ops — placed after Communications so it appears below in the step picker
    $catalog['http_request'] = [
        'label'    => 'HTTP Request',
        'category' => 'Data Ops',
        'sync'     => true,
        'inputs'   => [
            'url'               => ['type' => 'string',  'label' => 'URL', 'required' => true],
            'method'            => ['type' => 'select',  'label' => 'Method', 'options' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'required' => true],
            'headers'           => ['type' => 'textarea','label' => 'Headers (JSON)', 'required' => false],
            'body'              => ['type' => 'textarea','label' => 'Body', 'required' => false],
            'timeout'           => ['type' => 'number',  'label' => 'Timeout (seconds)', 'required' => false, 'default' => 10],
            'response_variable' => ['type' => 'string',  'label' => 'Store response as variable name', 'required' => false],
        ],
    ];
    $catalog['log_audit_trail'] = [
        'label'    => 'Log to Audit Trail',
        'category' => 'Data Ops',
        'sync'     => true,
        'inputs'   => [
            'message' => ['type' => 'textarea', 'label' => 'Message', 'required' => true],
        ],
    ];

    return $catalog;
}

/***********************************************
 * FUNCTION: ENRICH WORKFLOW CONTEXT           *
 * Supplements the minimal trigger context     *
 * with full field data from the DB so that    *
 * {{variable}} substitution in actions like   *
 * Add Comment can reference any risk or audit *
 * field. Only fills keys not already set by   *
 * the trigger callsite.                       *
 ***********************************************/
function enrich_workflow_context(string $event_type, array $context, PDO $db): array
{
    // ── Risk enrichment ────────────────────────────────────────────────
    $risk_id = (int)($context['risk_id'] ?? 0);
    if ($risk_id > 0) {
        $stmt = $db->prepare("
            SELECT r.`subject`, r.`status`, r.`owner`, r.`manager`, r.`category`,
                   r.`source`, r.`submission_date`, r.`reference_id`, r.`control_number`,
                   r.`project_id`, r.`regulation`, r.`submitted_by`,
                   u1.`name`  AS owner_name,
                   u2.`name`  AS manager_name,
                   u3.`name`  AS submitter_name,
                   c.`name`   AS category_name,
                   s.`name`   AS source_name,
                   p.`name`   AS project_name,
                   rs.`calculated_risk`
            FROM `risks` r
            LEFT JOIN `user`         u1 ON u1.`value` = r.`owner`
            LEFT JOIN `user`         u2 ON u2.`value` = r.`manager`
            LEFT JOIN `user`         u3 ON u3.`value` = r.`submitted_by`
            LEFT JOIN `category`     c  ON c.`value`  = r.`category`
            LEFT JOIN `source`       s  ON s.`value`  = r.`source`
            LEFT JOIN `projects`     p  ON p.`value`  = r.`project_id`
            LEFT JOIN `risk_scoring` rs ON rs.`id`    = r.`id`
            WHERE r.`id` = :id
        ");
        $stmt->bindParam(':id', $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fill = [
                'subject'          => try_decrypt($row['subject']),
                'status'           => $row['status'],
                'owner'            => (int)$row['owner'],
                'owner_name'       => $row['owner_name']       ?? '',
                'manager'          => (int)$row['manager'],
                'manager_name'     => $row['manager_name']     ?? '',
                'submitted_by'     => (int)$row['submitted_by'],
                'submitter_name'   => $row['submitter_name']   ?? '',
                'category'         => (int)$row['category'],
                'category_name'    => $row['category_name']    ?? '',
                'source'           => (int)$row['source'],
                'source_name'      => $row['source_name']      ?? '',
                'submission_date'  => $row['submission_date'],
                'reference_id'     => $row['reference_id']     ?? '',
                'control_number'   => $row['control_number']   ?? '',
                'project_id'       => (int)$row['project_id'],
                'project_name'     => try_decrypt($row['project_name'] ?? ''),
                'regulation'       => (int)$row['regulation'],
                'display_risk_id'  => $risk_id + 1000,
                'calculated_risk'  => $row['calculated_risk']  ?? '',
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) {
                    $context[$k] = $v;
                }
            }
        }
    }

    // ── Audit enrichment ───────────────────────────────────────────────
    $audit_id = (int)($context['audit_id'] ?? 0);
    if ($audit_id > 0) {
        $stmt = $db->prepare("
            SELECT a.`name`, a.`status`, a.`tester`, a.`framework_control_id` AS control_id,
                   u.`name`  AS tester_name,
                   ts.`name` AS audit_status_name,
                   r.`test_result`, r.`summary`, r.`test_date`
            FROM `framework_control_test_audits` a
            LEFT JOIN `user`        u  ON u.`value`          = a.`tester`
            LEFT JOIN `test_status` ts ON ts.`value`         = a.`status`
            LEFT JOIN `framework_control_test_results` r ON r.`test_audit_id` = a.`id`
            WHERE a.`id` = :id
        ");
        $stmt->bindParam(':id', $audit_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fill = [
                'name'              => $row['name']              ?? '',
                'status'            => (int)$row['status'],
                'audit_status_name' => $row['audit_status_name'] ?? '',
                'tester'            => (int)$row['tester'],
                'tester_name'       => $row['tester_name']       ?? '',
                'control_id'        => (int)$row['control_id'],
                'test_result'       => $row['test_result']       ?? '',
                'summary'           => $row['summary']           ?? '',
                'test_date'         => $row['test_date']         ?? '',
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) {
                    $context[$k] = $v;
                }
            }
        }
    }

    // ── Framework enrichment ──────────────────────────────────────────
    $framework_id = (int)($context['framework_id'] ?? 0);
    if ($framework_id > 0) {
        $stmt = $db->prepare("
            SELECT f.`name`, f.`description`, f.`status`, f.`parent`,
                   p.`name` AS parent_name
            FROM `frameworks` f
            LEFT JOIN `frameworks` p ON p.`value` = f.`parent`
            WHERE f.`value` = :id
        ");
        $stmt->bindParam(':id', $framework_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fill = [
                'name'        => try_decrypt($row['name']        ?? ''),
                'description' => try_decrypt($row['description'] ?? ''),
                'status'      => (int)$row['status'],
                'parent'      => (int)$row['parent'],
                'parent_name' => try_decrypt($row['parent_name'] ?? ''),
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) $context[$k] = $v;
            }
        }
    }

    // ── Control enrichment ────────────────────────────────────────────
    // Skipped in audit context — audit enrichment already captures control_id.
    $control_id = (int)($context['control_id'] ?? 0);
    if ($control_id > 0 && $audit_id === 0) {
        $stmt = $db->prepare("
            SELECT fc.`short_name`, fc.`long_name`, fc.`description`, fc.`supplemental_guidance`,
                   fc.`control_owner`, fc.`control_class`, fc.`control_phase`, fc.`control_number`,
                   fc.`control_maturity`, fc.`desired_maturity`, fc.`control_priority`,
                   fc.`family`, fc.`control_status`, fc.`mitigation_percent`,
                   u.`name`   AS control_owner_name,
                   cc.`name`  AS control_class_name,
                   cp.`name`  AS control_phase_name,
                   cm.`name`  AS control_maturity_name,
                   dm.`name`  AS desired_maturity_name,
                   cpr.`name` AS control_priority_name,
                   fam.`name` AS family_name,
                   ct.`name`  AS control_type_name
            FROM `framework_controls` fc
            LEFT JOIN `user`             u   ON u.`value`   = fc.`control_owner`
            LEFT JOIN `control_class`    cc  ON cc.`value`  = fc.`control_class`
            LEFT JOIN `control_phase`    cp  ON cp.`value`  = fc.`control_phase`
            LEFT JOIN `control_maturity` cm  ON cm.`value`  = fc.`control_maturity`
            LEFT JOIN `control_maturity` dm  ON dm.`value`  = fc.`desired_maturity`
            LEFT JOIN `control_priority` cpr ON cpr.`value` = fc.`control_priority`
            LEFT JOIN `family`           fam ON fam.`value` = fc.`family`
            LEFT JOIN `framework_control_type_mappings` fctm ON fctm.`control_id` = fc.`id`
            LEFT JOIN `control_type`     ct  ON ct.`value`  = fctm.`control_type_id`
            WHERE fc.`id` = :id
        ");
        $stmt->bindParam(':id', $control_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fill = [
                'short_name'            => $row['short_name']            ?? '',
                'long_name'             => $row['long_name']             ?? '',
                'description'           => $row['description']           ?? '',
                'supplemental_guidance' => $row['supplemental_guidance'] ?? '',
                'control_owner'         => (int)$row['control_owner'],
                'control_owner_name'    => $row['control_owner_name']    ?? '',
                'control_class'         => (int)$row['control_class'],
                'control_class_name'    => $row['control_class_name']    ?? '',
                'control_phase'         => (int)$row['control_phase'],
                'control_phase_name'    => $row['control_phase_name']    ?? '',
                'control_number'        => $row['control_number']        ?? '',
                'control_maturity'      => (int)$row['control_maturity'],
                'control_maturity_name' => $row['control_maturity_name'] ?? '',
                'desired_maturity'      => (int)$row['desired_maturity'],
                'desired_maturity_name' => $row['desired_maturity_name'] ?? '',
                'control_priority'      => (int)$row['control_priority'],
                'control_priority_name' => $row['control_priority_name'] ?? '',
                'family'                => (int)$row['family'],
                'family_name'           => $row['family_name']           ?? '',
                'control_type_name'     => $row['control_type_name']     ?? '',
                'control_status'        => (int)$row['control_status'],
                'mitigation_percent'    => (int)$row['mitigation_percent'],
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) $context[$k] = $v;
            }
        }
    }

    // ── Document enrichment ───────────────────────────────────────────
    $document_id = (int)($context['document_id'] ?? 0);
    if ($document_id > 0) {
        $stmt = $db->prepare("
            SELECT d.`document_name`, d.`document_type`, d.`document_owner`, d.`document_status`,
                   d.`creation_date`, d.`last_review_date`, d.`review_frequency`,
                   d.`next_review_date`, d.`approval_date`, d.`approver`,
                   d.`additional_stakeholders`, d.`team_ids`, d.`parent`,
                   ds.`name` AS document_status_name,
                   u1.`name` AS document_owner_name,
                   u2.`name` AS approver_name,
                   pd.`document_name` AS parent_document_name
            FROM `documents` d
            LEFT JOIN `document_status` ds ON ds.`value` = d.`document_status`
            LEFT JOIN `user`            u1 ON u1.`value` = d.`document_owner`
            LEFT JOIN `user`            u2 ON u2.`value` = d.`approver`
            LEFT JOIN `documents`       pd ON pd.`id`    = d.`parent`
            WHERE d.`id` = :id
        ");
        $stmt->bindParam(':id', $document_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Fetch associated framework IDs (comma-separated)
            $s = $db->prepare("SELECT GROUP_CONCAT(`framework_id`) AS ids FROM `document_framework_mappings` WHERE `document_id` = :id");
            $s->bindParam(':id', $document_id, PDO::PARAM_INT);
            $s->execute();
            $fw_row = $s->fetch(PDO::FETCH_ASSOC);

            // Fetch associated control IDs (comma-separated)
            $s = $db->prepare("SELECT GROUP_CONCAT(`control_id`) AS ids FROM `document_control_mappings` WHERE `document_id` = :id AND `selected` = 1");
            $s->bindParam(':id', $document_id, PDO::PARAM_INT);
            $s->execute();
            $ctrl_row = $s->fetch(PDO::FETCH_ASSOC);

            $fill = [
                'document_name'           => $row['document_name']           ?? '',
                'document_type'           => $row['document_type']           ?? '',
                'document_owner'          => (int)$row['document_owner'],
                'document_owner_name'     => $row['document_owner_name']     ?? '',
                'document_status'         => (int)$row['document_status'],
                'document_status_name'    => $row['document_status_name']    ?? '',
                'creation_date'           => $row['creation_date']           ?? '',
                'last_review_date'        => $row['last_review_date']        ?? '',
                'review_frequency'        => $row['review_frequency']        ?? '',
                'next_review_date'        => $row['next_review_date']        ?? '',
                'approval_date'           => $row['approval_date']           ?? '',
                'approver'                => (int)$row['approver'],
                'approver_name'           => $row['approver_name']           ?? '',
                'additional_stakeholders' => $row['additional_stakeholders'] ?? '',
                'team_ids'                => $row['team_ids']                ?? '',
                'parent_document_id'      => (int)$row['parent'],
                'parent_document_name'    => $row['parent_document_name']    ?? '',
                'document_frameworks'     => $fw_row['ids']                  ?? '',
                'document_controls'       => $ctrl_row['ids']                ?? '',
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) $context[$k] = $v;
            }
        }
    }

    // ── Exception enrichment ──────────────────────────────────────────
    $exception_id = (int)($context['exception_id'] ?? 0);
    if ($exception_id > 0) {
        $stmt = $db->prepare("
            SELECT de.`name`, de.`owner`, de.`status`, de.`approver`,
                   de.`creation_date`, de.`next_review_date`, de.`approval_date`,
                   de.`description`, de.`justification`,
                   des.`name` AS status_name,
                   u1.`name`  AS owner_name,
                   u2.`name`  AS approver_name,
                   doc.`document_name` AS policy_name
            FROM `document_exceptions` de
            LEFT JOIN `document_exceptions_status` des ON des.`value`  = de.`status`
            LEFT JOIN `user`                        u1  ON u1.`value`  = de.`owner`
            LEFT JOIN `user`                        u2  ON u2.`value`  = de.`approver`
            LEFT JOIN `documents`                   doc ON doc.`id`    = de.`policy_document_id`
            WHERE de.`value` = :id
        ");
        $stmt->bindParam(':id', $exception_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fill = [
                'name'             => $row['name']             ?? '',
                'owner'            => (int)$row['owner'],
                'owner_name'       => $row['owner_name']       ?? '',
                'status'           => (int)$row['status'],
                'status_name'      => $row['status_name']      ?? '',
                'approver'         => (int)$row['approver'],
                'approver_name'    => $row['approver_name']    ?? '',
                'creation_date'    => $row['creation_date']    ?? '',
                'next_review_date' => $row['next_review_date'] ?? '',
                'approval_date'    => $row['approval_date']    ?? '',
                'description'      => $row['description']      ?? '',
                'justification'    => $row['justification']    ?? '',
                'policy_name'      => $row['policy_name']      ?? '',
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) $context[$k] = $v;
            }
        }
    }

    // ── Test enrichment ───────────────────────────────────────────────
    // Skipped in audit context — test fields there belong to the audit record.
    $test_id = (int)($context['test_id'] ?? 0);
    if ($test_id > 0 && $audit_id === 0) {
        $stmt = $db->prepare("
            SELECT t.`name`, t.`tester`, t.`test_frequency`, t.`objective`,
                   t.`last_date`, t.`next_date`, t.`additional_stakeholders`,
                   t.`test_steps`, t.`approximate_time`, t.`expected_results`,
                   t.`audit_initiation_offset`,
                   u.`name` AS tester_name
            FROM `framework_control_tests` t
            LEFT JOIN `user` u ON u.`value` = t.`tester`
            WHERE t.`id` = :id
        ");
        $stmt->bindParam(':id', $test_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Fetch teams (comma-separated IDs)
            $s = $db->prepare("SELECT GROUP_CONCAT(`team_id`) AS ids FROM `items_to_teams` WHERE `item_id` = :id AND `type` = 'test'");
            $s->bindParam(':id', $test_id, PDO::PARAM_INT);
            $s->execute();
            $team_row = $s->fetch(PDO::FETCH_ASSOC);

            $fill = [
                'name'                    => $row['name']                    ?? '',
                'tester'                  => (int)$row['tester'],
                'tester_name'             => $row['tester_name']             ?? '',
                'test_frequency'          => $row['test_frequency']          ?? '',
                'objective'               => $row['objective']               ?? '',
                'last_date'               => $row['last_date']               ?? '',
                'next_date'               => $row['next_date']               ?? '',
                'additional_stakeholders' => $row['additional_stakeholders'] ?? '',
                'test_steps'              => $row['test_steps']              ?? '',
                'approximate_time'        => $row['approximate_time']        ?? '',
                'expected_results'        => $row['expected_results']        ?? '',
                'audit_initiation_offset' => $row['audit_initiation_offset'],
                'teams'                   => $team_row['ids']                ?? '',
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) $context[$k] = $v;
            }
        }
    }

    // ── Asset enrichment ──────────────────────────────────────────────
    $asset_id = (int)($context['asset_id'] ?? 0);
    if ($asset_id > 0) {
        $stmt = $db->prepare("
            SELECT `name`, `ip`, `value`, `details`, `location`, `teams`, `verified`
            FROM `assets`
            WHERE `id` = :id
        ");
        $stmt->bindParam(':id', $asset_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fill = [
                'name'     => try_decrypt($row['name']    ?? ''),
                'ip'       => try_decrypt($row['ip']      ?? ''),
                'value'    => (int)$row['value'],
                'details'  => try_decrypt($row['details'] ?? ''),
                'location' => $row['location']            ?? '',
                'teams'    => $row['teams']               ?? '',
                'verified' => (int)$row['verified'],
            ];
            foreach ($fill as $k => $v) {
                if (!array_key_exists($k, $context)) $context[$k] = $v;
            }
        }
    }

    return $context;
}

/***********************************************
 * FUNCTION: DISPATCH WORKFLOW EVENT           *
 * Called by trigger_workflow_event() in       *
 * functions.php after the tables are          *
 * confirmed to exist.                         *
 ***********************************************/
function dispatch_workflow_event(string $event_type, array $context): void
{
    $db = db_open();

    // Find all enabled workflows matching this trigger type
    $stmt = $db->prepare("
        SELECT `id`, `name`, `trigger_conditions`, `definition`, `sync_execution`
        FROM `workflow_definitions`
        WHERE `trigger_type` = :trigger_type AND `enabled` = 1
    ");
    $stmt->bindParam(':trigger_type', $event_type, PDO::PARAM_STR);
    $stmt->execute();
    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($workflows)) {
        // Enrich the context with full risk/audit field data so actions like
        // Add Comment can reference any field via {{variable}} substitution.
        $context = enrich_workflow_context($event_type, $context, $db);
    }

    foreach ($workflows as $workflow)
    {
        // Evaluate trigger conditions
        if (!workflow_trigger_conditions_match($workflow['trigger_conditions'], $context))
        {
            write_debug_log("WORKFLOW: Skipping workflow '{$workflow['name']}' (ID {$workflow['id']}) — trigger conditions not met for event '{$event_type}'", 'debug');
            continue;
        }

        // Ensure the event type is available inside the stored context
        $execution_context = ['trigger_type' => $event_type] + $context;

        if ((bool)$workflow['sync_execution'])
        {
            // Synchronous: create the execution record and run inline immediately
            write_debug_log("WORKFLOW: Executing '{$workflow['name']}' (ID {$workflow['id']}) synchronously for event '{$event_type}'", 'info');
            $execution_id = create_workflow_execution((int)$workflow['id'], $execution_context, $db);
            execute_workflow_execution($execution_id, $db, true);
        }
        else
        {
            // Asynchronous: hand off to the job queue
            write_debug_log("WORKFLOW: Queuing '{$workflow['name']}' (ID {$workflow['id']}) for event '{$event_type}'", 'info');
            queue_workflow_execution((int)$workflow['id'], $execution_context, $db);
        }
    }

    db_close($db);
}

/***********************************************
 * FUNCTION: WORKFLOW TRIGGER CONDITIONS MATCH *
 ***********************************************/
function workflow_trigger_conditions_match(?string $conditions_json, array $context): bool
{
    if (empty($conditions_json)) {
        return true;
    }

    $conditions_data = json_decode($conditions_json, true);
    if (empty($conditions_data['conditions'])) {
        return true;
    }

    $logic   = $conditions_data['condition_logic'] ?? 'AND';
    $results = [];

    foreach ($conditions_data['conditions'] as $condition)
    {
        $results[] = evaluate_workflow_condition($condition, $context, []);
    }

    if ($logic === 'OR') {
        return in_array(true, $results, true);
    }

    // Default: AND — all conditions must be true
    return !in_array(false, $results, true);
}

/***********************************************
 * FUNCTION: CREATE WORKFLOW EXECUTION         *
 * Inserts the execution record and returns    *
 * the new execution ID. Used by both the sync *
 * and async paths.                            *
 ***********************************************/
function create_workflow_execution(int $workflow_id, array $context, PDO $db): int
{
    $trigger_type = $context['trigger_type'] ?? '';
    $context_json = json_encode($context);

    $stmt = $db->prepare("
        INSERT INTO `workflow_executions`
            (`workflow_id`, `status`, `trigger_type`, `context`, `created_at`)
        VALUES
            (:workflow_id, 'queued', :trigger_type, :context, NOW())
    ");
    $stmt->bindParam(':workflow_id',  $workflow_id,  PDO::PARAM_INT);
    $stmt->bindParam(':trigger_type', $trigger_type, PDO::PARAM_STR);
    $stmt->bindParam(':context',      $context_json, PDO::PARAM_STR);
    $stmt->execute();

    $execution_id = (int)$db->lastInsertId();
    write_debug_log("WORKFLOW: Created execution #{$execution_id} for workflow #{$workflow_id}", 'info');
    return $execution_id;
}

/***********************************************
 * FUNCTION: QUEUE WORKFLOW EXECUTION          *
 * Creates the execution record and hands off  *
 * to the async job runner.                    *
 ***********************************************/
function queue_workflow_execution(int $workflow_id, array $context, PDO $db): void
{
    $execution_id = create_workflow_execution($workflow_id, $context, $db);

    // Hand off to the async job runner
    require_once(realpath(__DIR__ . '/queues.php'));
    queue_task($db, 'core_workflow_execute', ['execution_id' => $execution_id]);
}

/***********************************************
 * FUNCTION: EXECUTE WORKFLOW ACTION           *
 * Central dispatcher — maps action type       *
 * strings to their handler functions.         *
 ***********************************************/
function execute_workflow_action(string $type, array $inputs, array $context): array
{
    switch ($type)
    {
        // Communications (all handled via the extras registry below)

        // Data Ops
        case 'http_request':      return workflow_action_http_request($inputs, $context);
        case 'log_audit_trail':   return workflow_action_log_audit_trail($inputs, $context);

        // Risk Management
        case 'submit_risk':       return workflow_action_submit_risk($inputs, $context);
        case 'assign_risk_owner': return workflow_action_assign_risk_owner($inputs, $context);
        case 'update_risk_field': return workflow_action_update_risk_field($inputs, $context);
        case 'set_risk_status':   return workflow_action_set_risk_status($inputs, $context);
        case 'submit_mitigation': return workflow_action_submit_mitigation($inputs, $context);
        case 'submit_review':      return workflow_action_submit_review($inputs, $context);
        case 'add_risk_comment':   return workflow_action_add_risk_comment($inputs, $context);

        // Governance
        case 'assign_control_owner': return workflow_action_assign_control_owner($inputs, $context);
        case 'create_test_task':     return workflow_action_create_test_task($inputs, $context);

        // Compliance
        case 'flag_for_review':    return workflow_action_flag_for_review($inputs, $context);
        case 'assign_audit':       return workflow_action_assign_audit($inputs, $context);
        case 'add_audit_comment':  return workflow_action_add_audit_comment($inputs, $context);
        // Unified field updater (also handles legacy per-type keys)
        case 'update_field':           return workflow_action_update_field($inputs, $context);
        case 'update_asset_field':     return workflow_action_update_asset_field($inputs, $context);
        case 'update_control_field':   return workflow_action_update_control_field($inputs, $context);
        case 'update_framework_field': return workflow_action_update_framework_field($inputs, $context);
        case 'update_document_field':  return workflow_action_update_document_field($inputs, $context);
        case 'update_test_field':      return workflow_action_update_test_field($inputs, $context);

        // Integrations
        case 'notify_new_risk':   return workflow_action_notify_new_risk($inputs, $context);
        case 'create_jira_issue': return workflow_action_create_jira_issue($inputs, $context);
        case 'sync_jira_issue':   return workflow_action_sync_jira_issue($inputs, $context);

        // Flow Control (handled by executor directly, but included for completeness)
        case 'delay':
        case 'branch':
        case 'wait_for_condition':
            return ['status' => 'failed', 'error' => "Flow control action '{$type}' must be handled by the executor, not execute_workflow_action()."];

        default:
            // Check action handlers registered by extras
            $extra_handlers = $GLOBALS['_workflow_extra_action_handlers'] ?? [];
            if (isset($extra_handlers[$type]) && function_exists($extra_handlers[$type])) {
                return call_user_func($extra_handlers[$type], $inputs, $context);
            }

            write_debug_log("WORKFLOW: Unknown action type '{$type}'", 'error');
            return ['status' => 'failed', 'error' => "Unknown action type: {$type}"];
    }
}
