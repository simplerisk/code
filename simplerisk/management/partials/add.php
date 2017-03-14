<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../../includes/display.php'));
    require_once(realpath(__DIR__ . '/../../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');
    
    // Add various security headers
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // If we want to enable the Content Security Policy (CSP) - This may break Chrome
    if (CSP_ENABLED == "true")
    {
        // Add the Content-Security-Policy header
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
    }

    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        if (!isset($_SESSION))
        {
        	session_name('SimpleRisk');
        	session_start();
        }

    // Include the language file
    require_once(language_file());
    global $lang;

    require_once(realpath(__DIR__ . '/../../includes/csrf-magic/csrf-magic.php'));

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        header("Location: ../../index.php");
        exit(0);
    }
    

?>
    <div class="row-fluid">

        <form name="submit_risk" method="post" action="" enctype="multipart/form-data" id="risk-submit-form">

            <div class="row-fluid padded-bottom subject-field">
                <div class="span2 text-right"><?php echo $escaper->escapeHtml($lang['Subject']); ?>:</div>
                <div class="span8"><input maxlength="300" name="subject" id="subject" class="form-control" type="text"></div>
            </div>

            <div class="row-fluid">
                <!-- first coulmn -->
                <div class="span5">
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Category']); ?>:</div>
                        <div class="span7"><?php create_dropdown("category"); ?></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['SiteLocation']); ?>:</div>
                        <div class="span7"><?php create_dropdown("location"); ?></div>
                    </div>
                    <div class="row-fluid">
                        <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['ExternalReferenceId']); ?>:</div>
                        <div class="span7"><input maxlength="20" size="20" name="reference_id" autocomplete="off" id="reference_id" class="form-control" type="text" ></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['ControlRegulation']); ?>:</div>
                        <div class="span7"><?php create_dropdown("regulation"); ?></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['ControlNumber']); ?>:</div>
                        <div class="span7"><input maxlength="20" name="control_number" id="control_number" class="form-control" type="text"></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right" id="AffectedAssetsTitle" class="AffectedAssetsTitle"><?php echo $escaper->escapeHtml($lang['AffectedAssets']); ?>:</div>
                        <div class="span7"><div class="ui-widget"><textarea type="text" id="assets" name="assets" class="assets" class="form-control" tabindex="1"></textarea></div></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Technology']); ?>:</div>
                        <div class="span7"><?php create_dropdown("technology"); ?></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Team']); ?>:</div>
                        <div class="span7"><?php create_dropdown("team"); ?></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Owner']); ?>:</div>
                        <div class="span7"><?php create_dropdown("user", NULL, "owner"); ?></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['OwnersManager']); ?>:</div>
                        <div class="span7"><?php create_dropdown("user", NULL, "manager"); ?></div>
                    </div>
                </div>
                <!-- first coulmn end -->
                
                <!-- second coulmn -->
                <div class="span5">


                    <div class="row-fluid">
                      <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['RiskSource']); ?>:</div>
                      <div class="span7"><?php create_dropdown("source"); ?></div>
                    </div>
                    
                    <?php risk_score_method_html(); ?>

                    

                    <div class="row-fluid">
                        <div class="span5 text-right" id="RiskAssessmentTitle" class="RiskAssessmentTitle"><?php echo $escaper->escapeHtml($lang['RiskAssessment']); ?>:</div>
                        <div class="span7"><textarea name="assessment" cols="50" rows="3" id="assessment" class="form-control" tabindex="1"></textarea></div>
                    </div>
                    <div class="row-fluid">
                        <div class="span5 text-right" id="NotesTitle" class="NotesTitle"><?php echo $escaper->escapeHtml($lang['AdditionalNotes']); ?>:</div>
                        <div class="span7"><textarea name="notes" cols="50" rows="3" id="notes" class="form-control" tabindex="1"></textarea></div>
                    </div>
                    <div class="row-fluid">
                        <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['SupportingDocumentation']); ?>:</div>
                        <div class="span7">

                            <div class="file-uploader">
                                <label for="file-upload" class="btn">Choose File</label>
                                <span class="file-count-html"> <span class="file-count">0</span> File Added</span>
                                <ul class="file-list">

                                </ul>
                                <input type="file" id="file-upload" name="file[]" class="hidden-file-upload active" />
                            </div>

                        </div>
                    </div>
                </div>
                <!-- second coulmn end -->
            </div>

            <div class="row-fluid">
                <div class="span10">
                    <div class="actions risk-form-actions">
                        <span>Complete the form above to document a risk for consideration in Risk Management Process</span>
                        <button type="button" name="submit" class="btn btn-primary pull-right save-risk-form"><?php echo $escaper->escapeHtml($lang['SubmitRisk']); ?></button>
                        <input class="btn pull-right" value="<?php echo $escaper->escapeHtml($lang['ClearForm']); ?>" type="reset">
                    </div>
                </div>
            </div>

        </form>

    </div>
