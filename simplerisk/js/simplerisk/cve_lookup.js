/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/******************************
 * FUNCTION: GET NVD CVE INFO *
 ******************************/
function get_nvd_cve_info(cve, parent) {
    if (!cve) return;

    // 1. STRICT VALIDATION: Anchored regex - must match EXACTLY
    const cvePattern = /^CVE-\d{4}-\d{4,7}$/i;

    if (!cvePattern.test(cve)) {
        console.error("Invalid CVE format. Must be CVE-YYYY-NNNN:", cve);
        return;
    }

    // 2. SANITIZATION: Remove all non-alphanumeric except hyphen
    const sanitizedCVE = cve.toUpperCase().replace(/[^A-Z0-9-]/g, '');

    // 3. RE-VALIDATE after sanitization
    if (!cvePattern.test(sanitizedCVE)) {
        console.error("CVE failed post-sanitization validation:", sanitizedCVE);
        return;
    }

    // 4. Final length check (prevent extremely long CVE IDs)
    if (sanitizedCVE.length > 20) {
        console.error("CVE ID too long:", sanitizedCVE);
        return;
    }

    // 5. Use same-origin API (avoids CORS); 
    // fetches from https://cve.circl.lu/api/cve/ directly cause CORS issues
    const url = BASE_URL + '/api/cve/lookup?cve_id=' + encodeURIComponent(sanitizedCVE);

    // 6. Make the request with additional security options
    $.ajax({
        type: 'GET',
        url: url,
        dataType: 'json',
        cache: true,
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if(response.status_message){
                showAlertsFromArray(response.status_message);
            }
            process_nvd_cve_info_from_response(response.data);
        },
        error: function(xhr, status, error) {
            if(!retryCSRF(xhr, this)) {
                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        }
    });
}

/**************************
 * FUNCTION: CHECK CVE ID *
 **************************/
function check_cve_id(fieldName, parent) {
    const cve = $("[name=" + fieldName + "]", parent).val();

    // Strict validation with anchored regex
    const pattern = /^CVE-\d{4}-\d{4,7}$/i;

    // Trim whitespace
    const trimmedCVE = cve ? cve.trim() : '';

    // Only validate and fetch if it matches the complete pattern
    if (trimmedCVE && pattern.test(trimmedCVE)) {
        // Select CVSS scoring method and show modal
        select_cvss(parent);

        // Fetch CVE data and populate modal selects
        get_nvd_cve_info(trimmedCVE, parent);
    }
}

/*************************
 * FUNCTION: SELECT CVSS *
 *************************/
function select_cvss(parent) {
    // Set the scoring method to CVSS (value 2)
    const ddl = $("[name=scoring_method]", parent);
    ddl.val(2).trigger('change');

    // Show CVSS scoring div
    $(".cvss-holder", parent).show();

    // Hide other scoring divs
    $(".classic-holder, .dread-holder, .owasp-holder, .custom-holder, .contributing-risk-holder", parent).hide();

    // Open the modal if present
    const modalEl = parent.find('#cvssModal');
    if (modalEl.length) {
        const modal = new bootstrap.Modal(modalEl[0]);
        modal.show();

        // Recalculate whenever any select changes
        modalEl.on('change', 'select', function() {
            if (typeof calculateCVSS === "function") calculateCVSS();
        });
    }
}

/************************************************************
 * FUNCTION: PROCESS NVD CVE INFO                           *
 * Leave this in place for now, as it may be needed later   *
 * Updated for CVSS modal with hidden fields
 ************************************************************/
function process_nvd_cve_info(cve_info_json) {
    let cve = cve_info_json.cve || cve_info_json;
    let reference_id = cve.id || (cve.CVE_data_meta ? cve.CVE_data_meta.ID : '');

    // Description
    let assessment = "";
    if (cve.descriptions && cve.descriptions.length > 0) {
        assessment = cve.descriptions[0].value;
    } else if (cve.description && cve.description.description_data) {
        assessment = cve.description.description_data[0].value;
    }

    let subject = assessment.includes(". ") ? assessment.substring(0, assessment.indexOf(". ") + 1) : assessment;
    let notes = 'https://nvd.nist.gov/vuln/detail/' + reference_id;

    // CVSS v2 vector
    let cvssV2 = null;
    if (cve_info_json.metrics && cve_info_json.metrics.cvssMetricV2 && cve_info_json.metrics.cvssMetricV2.length > 0) {
        cvssV2 = cve_info_json.metrics.cvssMetricV2[0].cvssData.vectorString;
    } else if (cve_info_json.impact && cve_info_json.impact.baseMetricV2) {
        cvssV2 = cve_info_json.impact.baseMetricV2.cvssV2.vectorString;
    }

    // Parse v2
    let metricsV2 = parseCVSSVector(cvssV2);

    // Helper function to set both modal select and hidden field
    function setCVSSValue(fieldName, value) {
        // Set modal select
        $("#" + fieldName).val(value);
        // Set hidden field
        $("#" + fieldName + "_hidden").val(value);
    }

    // Populate Base Metrics (both modal and hidden fields)
    setCVSSValue("AccessVector", metricsV2.AccessVector || 'N');
    setCVSSValue("AccessComplexity", metricsV2.AccessComplexity || 'L');
    setCVSSValue("Authentication", metricsV2.Authentication || 'N');
    setCVSSValue("ConfImpact", metricsV2.ConfImpact || 'C');
    setCVSSValue("IntegImpact", metricsV2.IntegImpact || 'C');
    setCVSSValue("AvailImpact", metricsV2.AvailImpact || 'C');

    // Populate Temporal Metrics (both modal and hidden fields)
    setCVSSValue("Exploitability", metricsV2.Exploitability || 'ND');
    setCVSSValue("RemediationLevel", metricsV2.RemediationLevel || 'ND');
    setCVSSValue("ReportConfidence", metricsV2.ReportConfidence || 'ND');

    // Populate Environmental Metrics (both modal and hidden fields)
    setCVSSValue("CollateralDamagePotential", metricsV2.CollateralDamagePotential || 'ND');
    setCVSSValue("TargetDistribution", metricsV2.TargetDistribution || 'ND');
    setCVSSValue("ConfidentialityRequirement", metricsV2.ConfidentialityRequirement || 'ND');
    setCVSSValue("IntegrityRequirement", metricsV2.IntegrityRequirement || 'ND');
    setCVSSValue("AvailabilityRequirement", metricsV2.AvailabilityRequirement || 'ND');

    // CVSS v3 (optional)
    let cvssV3 = null;
    if (cve_info_json.metrics) {
        if (cve_info_json.metrics.cvssMetricV31 && cve_info_json.metrics.cvssMetricV31.length > 0) {
            cvssV3 = cve_info_json.metrics.cvssMetricV31[0].cvssData;
        } else if (cve_info_json.metrics.cvssMetricV30 && cve_info_json.metrics.cvssMetricV30.length > 0) {
            cvssV3 = cve_info_json.metrics.cvssMetricV30[0].cvssData;
        }
    }

    if (cvssV3) {
        $("#CVSS3_Vector").val(cvssV3.vectorString || '');
        $("#CVSS3_BaseScore").val(cvssV3.baseScore || '');
        $("#CVSS3_AttackVector").val(cvssV3.attackVector || '');
        $("#CVSS3_AttackComplexity").val(cvssV3.attackComplexity || '');
        $("#CVSS3_PrivilegesRequired").val(cvssV3.privilegesRequired || '');
        $("#CVSS3_UserInteraction").val(cvssV3.userInteraction || '');
        $("#CVSS3_Scope").val(cvssV3.scope || '');
        $("#CVSS3_ConfImpact").val(cvssV3.confidentialityImpact || '');
        $("#CVSS3_IntegImpact").val(cvssV3.integrityImpact || '');
        $("#CVSS3_AvailImpact").val(cvssV3.availabilityImpact || '');
    } else {
        $("#CVSS3_Vector, #CVSS3_BaseScore, #CVSS3_AttackVector, #CVSS3_AttackComplexity, #CVSS3_PrivilegesRequired, #CVSS3_UserInteraction, #CVSS3_Scope, #CVSS3_ConfImpact, #CVSS3_IntegImpact, #CVSS3_AvailImpact").val('');
    }

    // Reference & subject
    if (reference_id) $("#reference_id").val(reference_id);
    if (subject) $("#subject").val(subject);

    // Helper function to set editor content with retries
    function setEditorWithRetry(editorName, content, attempts = 0) {
        if (attempts > 10) {
            console.warn("Failed to set editor content after 10 attempts:", editorName);
            return;
        }

        // ALSO set the underlying textarea directly
        const textarea = document.getElementById(editorName) ||
            document.querySelector('textarea[name="' + editorName + '"]');
        if (textarea) {
            textarea.value = content;
        }

        // Try setEditorContent function first
        if (typeof setEditorContent === "function") {
            try {
                setEditorContent(editorName, content);
                return;
            } catch(e) {
                // Continue to TinyMCE fallback
            }
        }

        // Try TinyMCE/HugeRTE API
        if (typeof tinymce !== "undefined" || typeof hugerte !== "undefined") {
            var mce = typeof hugerte !== "undefined" ? hugerte : tinymce;
            var editor = mce.get(editorName + '_1') || mce.get(editorName);

            if (editor) {
                try {
                    editor.setContent(content);
                    return;
                } catch(e) {
                    console.warn("Error setting editor content:", e);
                }
            }
        }

        // Editor not ready, try again in 100ms
        setTimeout(function() {
            setEditorWithRetry(editorName, content, attempts + 1);
        }, 100);
    }

    // Assessment & notes
    if (assessment) {
        setEditorWithRetry('assessment', assessment);
    }

    if (notes) {
        setEditorWithRetry('notes', notes);
    }

    // Trigger CVSS recalculation
    if (typeof calculateCVSS === "function") calculateCVSS();
}

/************************************************
 * FUNCTION: PROCESS NVD CVE INFO               *
 * Updated for CVSS modal with hidden fields    *
 ************************************************/
function process_nvd_cve_info_from_response(cve_info_json) {
    
    let cve = cve_info_json.vulnerabilities && cve_info_json.vulnerabilities.length > 0 && cve_info_json.vulnerabilities[0].cve ? cve_info_json.vulnerabilities[0].cve : {};
    let reference_id = cve.id ? cve.id : '';

    // Description
    let assessment = "";
    if (cve.descriptions && cve.descriptions.length > 0) {
        assessment = cve.descriptions[0].value;
    }

    let subject = assessment.includes(". ") ? assessment.substring(0, assessment.indexOf(". ") + 1) : assessment;
    let notes = 'https://nvd.nist.gov/vuln/detail/' + reference_id;

    // CVSS v2 vector
    let cvssV2 = null;
    if (cve.metrics && cve.metrics.cvssMetricV2 && cve.metrics.cvssMetricV2.length > 0) {
        cvssV2 = cve.metrics.cvssMetricV2[0].cvssData.vectorString;
    }

    // Parse v2
    let metricsV2 = parseCVSSVector(cvssV2);

    // Helper function to set both modal select and hidden field
    function setCVSSValue(fieldName, value) {
        // Set modal select
        $("#" + fieldName).val(value);
        // Set hidden field
        $("#" + fieldName + "_hidden").val(value);
    }

    // Populate Base Metrics (both modal and hidden fields)
    setCVSSValue("AccessVector", metricsV2.AccessVector || 'N');
    setCVSSValue("AccessComplexity", metricsV2.AccessComplexity || 'L');
    setCVSSValue("Authentication", metricsV2.Authentication || 'N');
    setCVSSValue("ConfImpact", metricsV2.ConfImpact || 'C');
    setCVSSValue("IntegImpact", metricsV2.IntegImpact || 'C');
    setCVSSValue("AvailImpact", metricsV2.AvailImpact || 'C');

    // Populate Temporal Metrics (both modal and hidden fields)
    setCVSSValue("Exploitability", metricsV2.Exploitability || 'ND');
    setCVSSValue("RemediationLevel", metricsV2.RemediationLevel || 'ND');
    setCVSSValue("ReportConfidence", metricsV2.ReportConfidence || 'ND');

    // Populate Environmental Metrics (both modal and hidden fields)
    setCVSSValue("CollateralDamagePotential", metricsV2.CollateralDamagePotential || 'ND');
    setCVSSValue("TargetDistribution", metricsV2.TargetDistribution || 'ND');
    setCVSSValue("ConfidentialityRequirement", metricsV2.ConfidentialityRequirement || 'ND');
    setCVSSValue("IntegrityRequirement", metricsV2.IntegrityRequirement || 'ND');
    setCVSSValue("AvailabilityRequirement", metricsV2.AvailabilityRequirement || 'ND');

    // CVSS v3 (optional)
    let cvssV3 = null;
    if (cve.metrics && cve.metrics.cvssMetricV31 && cve.metrics.cvssMetricV31.length > 0) {
        cvssV3 = cve.metrics.cvssMetricV31[0].cvssData;
    }

    if (cvssV3) {
        $("#CVSS3_Vector").val(cvssV3.vectorString || '');
        $("#CVSS3_BaseScore").val(cvssV3.baseScore || '');
        $("#CVSS3_AttackVector").val(cvssV3.attackVector || '');
        $("#CVSS3_AttackComplexity").val(cvssV3.attackComplexity || '');
        $("#CVSS3_PrivilegesRequired").val(cvssV3.privilegesRequired || '');
        $("#CVSS3_UserInteraction").val(cvssV3.userInteraction || '');
        $("#CVSS3_Scope").val(cvssV3.scope || '');
        $("#CVSS3_ConfImpact").val(cvssV3.confidentialityImpact || '');
        $("#CVSS3_IntegImpact").val(cvssV3.integrityImpact || '');
        $("#CVSS3_AvailImpact").val(cvssV3.availabilityImpact || '');
    } else {
        $("#CVSS3_Vector, #CVSS3_BaseScore, #CVSS3_AttackVector, #CVSS3_AttackComplexity, #CVSS3_PrivilegesRequired, #CVSS3_UserInteraction, #CVSS3_Scope, #CVSS3_ConfImpact, #CVSS3_IntegImpact, #CVSS3_AvailImpact").val('');
    }

    // Reference & subject
    if (reference_id) $("#reference_id").val(reference_id);
    if (subject) $("#subject").val(subject);

    // Helper function to set editor content with retries
    function setEditorWithRetry(editorName, content, attempts = 0) {
        if (attempts > 10) {
            console.warn("Failed to set editor content after 10 attempts:", editorName);
            return;
        }

        // ALSO set the underlying textarea directly
        const textarea = document.getElementById(editorName) ||
            document.querySelector('textarea[name="' + editorName + '"]');
        if (textarea) {
            textarea.value = content;
        }

        // Try setEditorContent function first
        if (typeof setEditorContent === "function") {
            try {
                setEditorContent(editorName, content);
                return;
            } catch(e) {
                // Continue to TinyMCE fallback
            }
        }

        // Try TinyMCE/HugeRTE API
        if (typeof tinymce !== "undefined" || typeof hugerte !== "undefined") {
            var mce = typeof hugerte !== "undefined" ? hugerte : tinymce;
            var editor = mce.get(editorName + '_1') || mce.get(editorName);

            if (editor) {
                try {
                    editor.setContent(content);
                    return;
                } catch(e) {
                    console.warn("Error setting editor content:", e);
                }
            }
        }

        // Editor not ready, try again in 100ms
        setTimeout(function() {
            setEditorWithRetry(editorName, content, attempts + 1);
        }, 100);
    }

    // Assessment & notes
    if (assessment) {
        setEditorWithRetry('assessment', assessment);
    }

    if (notes) {
        setEditorWithRetry('notes', notes);
    }

    // Trigger CVSS recalculation
    if (typeof calculateCVSS === "function") calculateCVSS();

}

/************************************
 * FUNCTION: PARSE CVSS VECTOR STRING *
 * Supports CVSS v2 only (v3 handled separately) *
 ************************************/
function parseCVSSVector(vector) {
    let result = {};

    if (!vector) return result;

    // Strip CVSS:2.0/ prefix if present
    vector = vector.replace(/^CVSS:\d+\.\d+\//i, '');

    // Split each metric
    vector.split("/").forEach(pair => {
        let [key, value] = pair.split(":");
        if (!key || !value) return;

        switch (key) {
            case "AV": result.AccessVector = value; break;
            case "AC": result.AccessComplexity = value; break;
            case "Au": result.Authentication = value; break;
            case "C":  result.ConfImpact = value; break;
            case "I":  result.IntegImpact = value; break;
            case "A":  result.AvailImpact = value; break;

            // Optional: fallback for future metrics
            case "E":  result.Exploitability = value; break;
            case "RL": result.RemediationLevel = value; break;
            case "RC": result.ReportConfidence = value; break;
            case "CDP": result.CollateralDamagePotential = value; break;
            case "TD":  result.TargetDistribution = value; break;
            case "CR":  result.ConfidentialityRequirement = value; break;
            case "IR":  result.IntegrityRequirement = value; break;
            case "AR":  result.AvailabilityRequirement = value; break;

            default: result[key] = value; break;
        }
    });

    return result;
}

/*************************
 * FUNCTION: Show/Hide Scoring elements *
 *************************/
function handleSelection(choice, parent) {
    if (choice=="1") {
        $(".classic-holder", parent).show();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="2") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).show();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="3") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).show();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="4") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).show();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="5") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).show();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="6") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).show();
    }
}