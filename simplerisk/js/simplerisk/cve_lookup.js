/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Bumped on every get_nvd_cve_info() call so a slower, older lookup's
// response can never overwrite a newer one's -- check_cve_id() fires on
// every 'keyup' with no debounce, and /^CVE-\d{4}-\d{4,7}$/i matches ANY
// 4-7 digit CVE number, so typing a real ID like "CVE-2021-44228"
// genuinely fires a SEPARATE, real NVD lookup for "CVE-2021-4422" the
// instant that shorter, syntactically-complete prefix appears mid-keystroke
// -- confirmed live: both requests actually fire, and network scheduling
// gives no ordering guarantee on which response lands first. Same pattern
// js/simplerisk/pages/connectivity-visualizer.js's runSearch() already uses
// for the identical class of bug (searchRequestSeq).
let cveLookupRequestSeq = 0;

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
    const url = BASE_URL + '/api/v2/cve/lookup?cve_id=' + encodeURIComponent(sanitizedCVE);

    // 6. Make the request with additional security options
    const requestSeq = ++cveLookupRequestSeq;
    $.ajax({
        type: 'GET',
        url: url,
        dataType: 'json',
        cache: true,
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (requestSeq !== cveLookupRequestSeq) {
                // A newer lookup superseded this one while the request was
                // in flight -- discard the stale response instead of
                // clobbering the form with an out-of-date CVE's data.
                return;
            }
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
    // Set the scoring method to CVSS (value 2). Stable handle:
    // '.scoring-method-select', not [name=scoring_method] alone --
    // syncScoringMethodVisibility() (risk-details-form.js) dynamically
    // STRIPS this select's `name` attribute whenever the current value
    // isn't Classic/Custom/CVSS. [name=scoring_method] stays in this
    // combined selector for the legacy page (includes/display.php's
    // create_dropdown('scoring_methods', ..., 'scoring_method', ...)),
    // whose <select> keeps that name permanently and carries no such
    // class.
    const ddl = $(".scoring-method-select, [name=scoring_method]", parent);
    ddl.val(2).trigger('change');

    // Show CVSS scoring div -- syncScoringMethodVisibility() already does
    // this on the 'change' triggered above for the Cards-rendered pages;
    // this direct show/hide is kept for the legacy page this function's
    // combined selector above also still supports.
    $(".cvss-holder", parent).show();

    // Hide other scoring divs
    $(".classic-holder, .dread-holder, .owasp-holder, .custom-holder, .contributing-risk-holder", parent).hide();

    // No modal to open: CVSS's 14 fields render inline in .cvss-holder
    // (Phase 4d-ii inline redesign) and are already visible via the show()
    // call above. .cvss-holder's own delegated 'change' listener
    // (buildCvssHolder(), risk-details-form.js) is bound once at build
    // time and needs no re-binding here.
}

// Subject is meant to be a short, one-line summary -- the CVE's full
// description is separately set on the Risk Assessment field
// (setEditorWithRetry('assessment', assessment) below), so this doesn't
// need to preserve every word, just enough to identify the risk at a
// glance. Previously just `assessment.includes(". ") ? substring to the
// first ". " : the whole description` -- a CVE description with no
// internal sentence break before its own final period (e.g. CVE-2013-0123's
// single run-on "... via (1) ... or (2) ..." sentence, ~240 characters)
// fell straight through to the "whole description" branch, dumping the
// entire paragraph into Subject. Bounding the result to MAX_LEN regardless
// of sentence structure fixes that without changing behavior for the
// common case (a real first sentence under the limit passes through
// unchanged).
//
// MAX_LEN matches the admin-configurable `maximum_risk_subject_length`
// setting (default 300, range 1-1000 -- admin/settings_preferences.php),
// the same value every other Subject-length enforcement site in the app
// reads live via get_setting('maximum_risk_subject_length', 300)
// (includes/functions.php, includes/api.php, includes/display.php,
// extras/assessments/index.php). header.php exposes it as
// window.MAXIMUM_RISK_SUBJECT_LENGTH for exactly this reason -- a
// hardcoded 300 here would silently disagree with every one of those
// sites the moment an admin changes the setting. The truncation cutoff is
// MAX_LEN minus the 3-character '...' suffix, not MAX_LEN itself:
// truncating AT the limit and then appending '...' would produce a
// string 3 characters over, which MySQL silently truncates on
// INSERT/UPDATE (non-strict SQL mode) or the request errors outright
// (strict mode) -- either way losing the very ellipsis meant to signal
// "this was cut off".
var CVE_SUBJECT_MAX_LEN = (typeof MAXIMUM_RISK_SUBJECT_LENGTH === 'number') ? MAXIMUM_RISK_SUBJECT_LENGTH : 300;
function cveSubjectFromAssessment(assessment) {
    var firstSentence = assessment.includes(". ") ? assessment.substring(0, assessment.indexOf(". ") + 1) : assessment;
    if (firstSentence.length <= CVE_SUBJECT_MAX_LEN) {
        return firstSentence;
    }
    var truncated = firstSentence.substring(0, CVE_SUBJECT_MAX_LEN - 3);
    var lastSpace = truncated.lastIndexOf(' ');
    if (lastSpace > 0) {
        truncated = truncated.substring(0, lastSpace);
    }
    return truncated + '...';
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

    let subject = cveSubjectFromAssessment(assessment);
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

    // Sets the CVSS field's value. Holder-scoped first, unscoped fallback
    // second: management/view.php also renders the pre-existing "Update
    // Score" accordion (includes/display.php's edit_cvss_score(), reusing
    // these SAME 14 literal ids via create_cvss_dropdown()) as part of the
    // page's original server-rendered HTML, so an unscoped `$('#' +
    // fieldName)` could resolve to that legacy select instead of this
    // widget's own. The fallback (used when .cvss-holder doesn't exist on
    // the page at all) covers any surviving page that never got this
    // phase's script.
    function setCVSSValue(fieldName, value) {
        var $select = $('.cvss-holder #' + fieldName);
        if (!$select.length) { $select = $('#' + fieldName); }
        $select.val(value);

        // The legacy static modal (management/partials/
        // cvss_modal_content.php, still rendered by risk.js's "Add Risk"
        // flow) keeps a hidden companion input that actually submits with
        // the form; the new inline .cvss-holder has none -- the visible
        // select IS the submitted field there, so this lookup finds
        // nothing and is a no-op on that page, by design.
        var $hidden = $('#' + fieldName + '_hidden');
        if ($hidden.length) { $hidden.val(value); }
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
    //
    // BUG FIX: these were pure ID selectors (#reference_id/#subject), which
    // silently matched nothing -- the Cards engine's buildTextInput()
    // (risk-details-form.js) sets only a `name` attribute on these fields,
    // never an `id`. The only surviving `id='reference_id'` in the whole
    // codebase is the legacy display_add_risk()-style markup this redesign
    // already replaced (includes/displayrisks.php:906) -- unreachable from
    // any page this function is actually called from today (both Submit
    // Risk / the risk view page's Edit Details flow are Cards-engine-
    // rendered). Selecting by [name=...] instead matches both the current
    // Cards markup and any legacy markup (which always set name= too), and
    // mirrors setEditorWithRetry()'s own by-name fallback for
    // assessment/notes just below.
    if (reference_id) $('[name="reference_id"]').val(reference_id);
    if (subject) $('[name="subject"]').val(subject);

    // Helper function to set editor content with retries
    //
    // BUG FIX: this used to look up the HugeRTE editor instance by the
    // field's FORM NAME ('assessment'/'notes' -- mce.get(editorName) etc.),
    // which never matched anything real. The Cards engine
    // (risk-details-form.js) keys the rendered <textarea>'s id via its own
    // internal fieldElementId() scheme (e.g.
    // 'submit-risk-field-RiskAssessment'), never by the form name -- and
    // HugeRTE keys its editor instance by THAT SAME element id
    // (init_compact_editor('#' + id), initRichTextField()). Setting the raw
    // textarea.value (which this always did, and still does below) has no
    // visible effect: HugeRTE renders its own iframe/contenteditable UI and
    // does not re-read the backing textarea after its own init. Find the
    // real textarea by NAME first (its `name` attribute IS the form name;
    // its `id` is not), then use ITS actual id for the editor lookup.
    //
    // Also drops the old setEditorContent()-first branch: that helper
    // (js/WYSIWYG/helpers.js) does the SAME hugerte.get(id).setContent()
    // internally but has no way to report a not-found editor (no throw, no
    // return value) -- calling it, then unconditionally returning, is
    // exactly what silently skipped the working mce.get() fallback and the
    // retry loop below on every past call. Going straight to mce.get() is
    // strictly more reliable, not a capability loss.
    function setEditorWithRetry(editorName, content, attempts = 0) {
        if (attempts > 10) {
            console.warn("Failed to set editor content after 10 attempts:", editorName);
            return;
        }

        const textarea = document.getElementById(editorName) ||
            document.querySelector('textarea[name="' + editorName + '"]');
        if (textarea) {
            textarea.value = content;
        }
        const realEditorId = textarea ? textarea.id : editorName;

        if (typeof tinymce !== "undefined" || typeof hugerte !== "undefined") {
            var mce = typeof hugerte !== "undefined" ? hugerte : tinymce;
            var editor = mce.get(realEditorId + '_1') || mce.get(realEditorId);

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

    // Trigger CVSS recalculation. Prefer the new module (Task 1 relocated the
    // math out of a bare `calculateCVSS` page global into
    // window.CvssV2Scoring.calculateCVSS() -- the bare global no longer
    // exists on any of the 5 pages this phase loads cvss-v2-scoring.js on,
    // so `typeof calculateCVSS === "function"` was always false there and
    // this recalculation silently no-oped). setCVSSValue() above already
    // wrote directly into the .cvss-holder-scoped visible selects
    // CvssV2Scoring.calculateCVSS() reads (with an unscoped fallback for the
    // legacy cvss_modal_content.php modal, which still has its own hidden
    // companions), so no extra loadFromHiddenFields() sync is needed here.
    // The fallback to the legacy bare global fires whenever
    // window.CvssV2Scoring itself doesn't exist -- it is no longer gated on
    // #cvssModal's presence.
    //
    // Also refreshes the CVSS holder's live risk-level pill (risk-details-
    // form.js's window.CvssRiskLevelPill bridge -- see that file's docblock
    // on it) from calculateCVSS()'s now-returned current score, so a CVE
    // lookup's populated fields show the same risk-level preview a manual
    // edit would.
    if (window.CvssV2Scoring) {
        var cvssCurrentScore = window.CvssV2Scoring.calculateCVSS();
        if (window.CvssRiskLevelPill) {
            window.CvssRiskLevelPill.update($('.cvss-holder'), cvssCurrentScore);
        }
    } else if (typeof calculateCVSS === "function") {
        calculateCVSS();
    }
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

    let subject = cveSubjectFromAssessment(assessment);
    let notes = 'https://nvd.nist.gov/vuln/detail/' + reference_id;

    // CVSS v2 vector
    let cvssV2 = null;
    if (cve.metrics && cve.metrics.cvssMetricV2 && cve.metrics.cvssMetricV2.length > 0) {
        cvssV2 = cve.metrics.cvssMetricV2[0].cvssData.vectorString;
    }

    // Parse v2
    let metricsV2 = parseCVSSVector(cvssV2);

    // Sets the CVSS field's value -- see the identical helper's docblock
    // in process_nvd_cve_info() above for the full collision-with-the-
    // legacy-"Update Score"-accordion explanation.
    function setCVSSValue(fieldName, value) {
        var $select = $('.cvss-holder #' + fieldName);
        if (!$select.length) { $select = $('#' + fieldName); }
        $select.val(value);

        var $hidden = $('#' + fieldName + '_hidden');
        if ($hidden.length) { $hidden.val(value); }
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
    //
    // BUG FIX: these were pure ID selectors (#reference_id/#subject), which
    // silently matched nothing -- the Cards engine's buildTextInput()
    // (risk-details-form.js) sets only a `name` attribute on these fields,
    // never an `id`. The only surviving `id='reference_id'` in the whole
    // codebase is the legacy display_add_risk()-style markup this redesign
    // already replaced (includes/displayrisks.php:906) -- unreachable from
    // any page this function is actually called from today (both Submit
    // Risk / the risk view page's Edit Details flow are Cards-engine-
    // rendered). Selecting by [name=...] instead matches both the current
    // Cards markup and any legacy markup (which always set name= too), and
    // mirrors setEditorWithRetry()'s own by-name fallback for
    // assessment/notes just below.
    if (reference_id) $('[name="reference_id"]').val(reference_id);
    if (subject) $('[name="subject"]').val(subject);

    // Helper function to set editor content with retries
    //
    // BUG FIX: this used to look up the HugeRTE editor instance by the
    // field's FORM NAME ('assessment'/'notes' -- mce.get(editorName) etc.),
    // which never matched anything real. The Cards engine
    // (risk-details-form.js) keys the rendered <textarea>'s id via its own
    // internal fieldElementId() scheme (e.g.
    // 'submit-risk-field-RiskAssessment'), never by the form name -- and
    // HugeRTE keys its editor instance by THAT SAME element id
    // (init_compact_editor('#' + id), initRichTextField()). Setting the raw
    // textarea.value (which this always did, and still does below) has no
    // visible effect: HugeRTE renders its own iframe/contenteditable UI and
    // does not re-read the backing textarea after its own init. Find the
    // real textarea by NAME first (its `name` attribute IS the form name;
    // its `id` is not), then use ITS actual id for the editor lookup.
    //
    // Also drops the old setEditorContent()-first branch: that helper
    // (js/WYSIWYG/helpers.js) does the SAME hugerte.get(id).setContent()
    // internally but has no way to report a not-found editor (no throw, no
    // return value) -- calling it, then unconditionally returning, is
    // exactly what silently skipped the working mce.get() fallback and the
    // retry loop below on every past call. Going straight to mce.get() is
    // strictly more reliable, not a capability loss.
    function setEditorWithRetry(editorName, content, attempts = 0) {
        if (attempts > 10) {
            console.warn("Failed to set editor content after 10 attempts:", editorName);
            return;
        }

        const textarea = document.getElementById(editorName) ||
            document.querySelector('textarea[name="' + editorName + '"]');
        if (textarea) {
            textarea.value = content;
        }
        const realEditorId = textarea ? textarea.id : editorName;

        if (typeof tinymce !== "undefined" || typeof hugerte !== "undefined") {
            var mce = typeof hugerte !== "undefined" ? hugerte : tinymce;
            var editor = mce.get(realEditorId + '_1') || mce.get(realEditorId);

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

    // Trigger CVSS recalculation -- see the identical fix's docblock in
    // process_nvd_cve_info() above for why this now prefers
    // window.CvssV2Scoring.calculateCVSS() over the no-longer-existing bare
    // `calculateCVSS` global, and also refreshes the risk-level pill.
    if (window.CvssV2Scoring) {
        var cvssCurrentScoreFromResponse = window.CvssV2Scoring.calculateCVSS();
        if (window.CvssRiskLevelPill) {
            window.CvssRiskLevelPill.update($('.cvss-holder'), cvssCurrentScoreFromResponse);
        }
    } else if (typeof calculateCVSS === "function") {
        calculateCVSS();
    }

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

/***********************************************************
 * External Reference ID -> CVE lookup trigger              *
 *                                                            *
 * Moved here from js/simplerisk/pages/risk.js, which owns   *
 * this file's only caller of check_cve_id() but is loaded   *
 * ONLY on the risk view page (management/view.php's script  *
 * token list carries 'CUSTOM:pages/risk.js'; management/    *
 * index.php's -- the standalone Submit Risk page -- never   *
 * has). Since risk.js's Submit-Risk-page equivalent was      *
 * rebuilt into a thin wrapper around the Cards engine        *
 * (05d51d9c50), CVE lookup silently stopped firing on Submit *
 * Risk entirely -- check_cve_id() itself, and every function *
 * in this file, kept working fine; nothing ever called them. *
 * This file (cve_lookup.js) is already loaded on BOTH pages, *
 * so binding the trigger here fixes Submit Risk and keeps    *
 * the risk view page working from a single source, with no   *
 * risk of double-binding.                                    *
 ***********************************************************/
$(document).ready(function () {
    $('body').on('keyup', 'input[name=reference_id]', function (e) {
        e.preventDefault();
        var formContainer = $(this).parents('form');
        check_cve_id('reference_id', formContainer);
    });
});