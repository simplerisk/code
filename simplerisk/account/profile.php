<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

// This page's own PHP now only renders static markup -- every value shown
// (record header, security status, preferences, permissions, API key) is
// fetched and rendered client-side by account-profile.js against the v2
// account API (design-system.md §11 / the Foundational "API-backed UI"
// rule). is_admin()/get_setting() below are defined in includes/functions.php,
// which render_header_and_sidebar() already pulls in transitively (via
// sidebar.php -> header.php); required directly too per the CLAUDE.md
// belt-and-suspenders reachability rule.
require_once(realpath(__DIR__ . '/../includes/functions.php'));

$breadcrumb_title_key = "Profile Details";
render_header_and_sidebar(['CUSTOM:pages/account-profile.js'], breadcrumb_title_key: $breadcrumb_title_key);
?>
<div class="row">
    <div class="col-12">
        <!-- .sr-qform must be a CHILD of .col-12 (not a class on .col-12 itself) --
             _questionnaire.scss's `.content:has(> .row > .col-12 > .sr-qform)` override
             (which strips the legacy white/bordered .content slab so this page's cards
             sit on the page-wrapper gray, matching every other .sr-qform surface) only
             matches that exact four-level nesting. -->
        <div class="sr-qform">
            <div class="sr-rhead">
                <span id="profile-avatar" class="sr-avatar-lg"></span>
                <div class="sr-rhead-id">
                    <div class="sr-rhead-name">
                        <span id="profile-name"></span>
                        <span id="profile-role-pill" class="sr-pill sr-pill-neutral" style="display:none;">
                            <span class="sr-pill-label"></span>
                        </span>
                    </div>
                    <div id="profile-email" class="sr-rhead-email"></div>
                </div>
            </div>

            <div class="sr-grid">
                <div class="sr-col">

                    <div class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-htext"><h2><?= $escaper->escapeHtml($lang['Security']); ?></h2></span>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-mfa-row">
                                <div class="sr-mfa-text">
                                    <div class="sr-mfa-title"><?= $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></div>
                                    <div class="sr-mfa-desc"><?= $escaper->escapeHtml($lang['MultiFactorAuthenticationHint']); ?></div>
                                </div>
                                <span id="profile-mfa-status" class="sr-pill"></span>
                                <a id="profile-mfa-enable-btn" href="mfa.php" class="btn sr-qsave"><?= $escaper->escapeHtml($lang['EnableMFA']); ?></a>
                                <a id="profile-mfa-disable-btn" href="mfa.php" class="btn sr-qcancel"><?= $escaper->escapeHtml($lang['DisableMFA']); ?></a>
                            </div>
                        </div>
                        <div id="profile-password-card-body" class="sr-qcard-body">
                            <!-- Hidden entirely by SRAccountProfileSecurity.render() when
                                 data.user_type !== 'simplerisk' (LDAP/SAML accounts have
                                 no local password to change; the API already rejects the
                                 attempt server-side, but showing a form that can never
                                 succeed is its own UX bug). -->
                            <!-- method="post" is required for csrf-magic to inject a
                                 __csrf_magic token into this form even though
                                 SRAccountProfileSecurity's submit handler always calls
                                 preventDefault() and posts via AJAX instead -- several
                                 e2e tests (bugbounty-fixes.spec.ts,
                                 cross-team-risk-detail-denial.spec.ts,
                                 patch-body-session-persistence.spec.ts,
                                 manage-assets.page.ts's waitForCsrfToken) fall back to
                                 scraping that token from this page's POST forms when
                                 no other form is available on the page they're on. -->
                            <form id="profile-password-form" method="post">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for="profile-current-password"><?= $escaper->escapeHtml($lang['CurrentPassword']); ?></label>
                                    <input class="form-control" type="password" id="profile-current-password" autocomplete="current-password">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for="profile-new-password"><?= $escaper->escapeHtml($lang['NewPassword']); ?></label>
                                    <input class="form-control" type="password" id="profile-new-password" autocomplete="new-password">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for="profile-confirm-password"><?= $escaper->escapeHtml($lang['ConfirmPassword']); ?></label>
                                    <input class="form-control" type="password" id="profile-confirm-password" autocomplete="new-password">
                                </div>
                                <div id="profile-password-requirements" class="req-list"></div>
                                <div id="profile-mfa-code-field" class="sr-qfield" style="display:none;">
                                    <label class="sr-qlabel" for="profile-mfa-code"><?= $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></label>
                                    <input class="form-control" type="text" id="profile-mfa-code" inputmode="numeric" autocomplete="one-time-code">
                                </div>
                                <p class="sr-note"><?= $escaper->escapeHtml($lang['ChangingPasswordSignsOutEverywhere']); ?></p>
                                <button type="submit" class="btn sr-qsave"><?= $escaper->escapeHtml($lang['ChangePassword']); ?></button>
                            </form>
                        </div>
                        <div id="profile-api-key-card" class="sr-qcard-body" style="display:none;">
                            <div class="sr-mfa-title"><?= $escaper->escapeHtml($lang['APIKey']); ?></div>
                            <div class="sr-mfa-desc"><?= $escaper->escapeHtml($lang['APIKeyHint']); ?></div>
                            <pre id="profile-api-key-reveal" class="sr-key-box d-none"></pre>
                            <button type="button" id="profile-api-key-generate-btn" class="btn sr-qsave"></button>
                            <button type="button" id="profile-api-key-revoke-btn" class="btn sr-qcancel"><?= $escaper->escapeHtml($lang['InvalidateAPIKey']); ?></button>
                        </div>
                    </div>

                    <div class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-htext"><h2><?= $escaper->escapeHtml($lang['Preferences']); ?></h2></span>
                        </div>
                        <div class="sr-qcard-body">
                            <!-- method="post" restores the __csrf_magic token contract
                                 these tests rely on -- see the note on
                                 #profile-password-form above. -->
                            <form id="profile-language-form" method="post">
                                <label class="sr-qlabel" for="profile-language-select"><?= $escaper->escapeHtml($lang['Language']); ?></label>
                                <div class="sr-lang-row">
                                    <select id="profile-language-select" class="form-select"></select>
                                    <button type="submit" class="btn sr-qsave"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                                </div>
                            </form>
                            <hr>
                            <button type="button" id="profile-reset-display-btn" class="btn sr-qcancel"><?= $escaper->escapeHtml($lang['ResetCustomDisplaySettings']); ?></button>
                            <p class="sr-note"><?= $escaper->escapeHtml($lang['ResetDisplaySettingsHint']); ?></p>
                        </div>
                    </div>

                    <div class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-htext">
                                <h2><?= $escaper->escapeHtml($lang['YourPermissions']); ?></h2>
                                <span class="sr-qcard-hsub"><?= $escaper->escapeHtml($lang['RoleAndTeamsGrantAccess']); ?></span>
                            </span>
                        </div>
                        <div class="sr-qcard-body">
                            <div id="profile-permissions"></div>
                        </div>
                    </div>

                </div>

                <div class="sr-col">
                    <div class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-htext"><h2><?= $escaper->escapeHtml($lang['AccountDetails']); ?></h2></span>
                        </div>
                        <div class="sr-qcard-body">
                            <dl class="sr-deflist">
                                <div><dt><?= $escaper->escapeHtml($lang['Username']); ?></dt><dd id="profile-username"></dd></div>
                                <div><dt><?= $escaper->escapeHtml($lang['Manager']); ?></dt><dd id="profile-manager"></dd></div>
                                <div><dt><?= $escaper->escapeHtml($lang['Teams']); ?></dt><dd id="profile-teams" class="sr-chip-row"></dd></div>
                                <div><dt><?= $escaper->escapeHtml($lang['Role']); ?></dt><dd id="profile-role"></dd></div>
                            </dl>
                            <div class="sr-note"><?= $escaper->escapeHtml($lang['ManagedByYourAdministrator']); ?></div>
                        </div>
                    </div>

                    <div class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-htext"><h2><?= $escaper->escapeHtml($lang['Resources']); ?></h2></span>
                        </div>
                        <div class="sr-qcard-body">
            <?php
                // Same literal URLs AND icons the home dashboard's "Learn" footer
                // uses (get_home_getting_started_html(), includes/reporting.php) --
                // there is no get_setting()/constant for these, so this reuses the
                // exact strings/icons rather than inventing new ones. Admin Guide is
                // gated the same way there too (is_admin()).
            ?>
                            <a class="sr-resource-link" href="https://www.simplerisk.com/support/user-guide" target="_blank" rel="noopener"><i class="fa-solid fa-book" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['UserGuide']); ?></a>
            <?php if (is_admin()) { ?>
                            <a class="sr-resource-link" href="https://www.simplerisk.com/support/admin-guide" target="_blank" rel="noopener"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['AdminGuide']); ?></a>
            <?php } ?>
                            <a class="sr-resource-link" href="https://www.simplerisk.com/support/video-walkthrough" target="_blank" rel="noopener"><i class="fa-solid fa-circle-play" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['GSWalkthrough']); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MFA step-up prompt, reused by account-profile.js (SRAccountProfileApiKey)
     for both API key rotate and revoke. Uses the shared .sr-modal shell
     (design-system.md §8, scss/modules/_sr-modal.scss). -->
<div class="modal sr-modal" id="profile-mfa-code-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-shield-halved" aria-hidden="true"></i></span>
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="sr-qcard">
                    <div class="sr-qcard-body">
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for="profile-mfa-code-modal-input"><?= $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></label>
                            <input type="text" class="form-control" id="profile-mfa-code-modal-input" inputmode="numeric" autocomplete="one-time-code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <!-- .sr-qcancel/.sr-qsave are functional hooks for
                     promptForMfaCodeIfNeeded() (account-profile.js), which
                     selects them by class; .btn-dark/.btn-submit supply the
                     actual visual treatment (see scss/pages/_account-profile.scss). -->
                <button type="button" class="btn btn-dark sr-qcancel" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit sr-qsave"><?= $escaper->escapeHtml($lang['Save']); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        window.SRAccountProfile.init();
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
