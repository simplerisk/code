<?php

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
  header("Location: ../../index.php");
  exit(0);
}
// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>
<div class="score-overview-container">
    <div class="overview-container">
    <?php
        include(realpath(__DIR__ . '/overview.php'));
    ?>
    </div>
</div>
<div class="content-container">
    <?php
        if($display_risk == true) {
            include(realpath(__DIR__ . '/details.php'));
        }
    ?>
</div>
<div class="accordion mb-2">
    <div class="accordion-item comments--wrapper">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#comments-accordion-body'><?= $escaper->escapeHtml($lang['Comments']); ?></button>
        </h2>
        <div id="comments-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row mt-2">
                    <div class="col-12">
                        <form id="comment" class="comment-form" name="add_comment" method="post" action="/management/comment.php?id=<?php echo $id; ?>">
                            <textarea style="width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;" name="comment" cols="50" rows="3" id="comment-text" class="comment-text form-control"></textarea>
                            <div class="form-actions float-end mt-2" id="comment-div">
                                <input class="btn btn-dark" id="rest-btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset" />
                                <button id="comment-submit" type="submit" name="submit" class="comment-submit btn btn-submit" ><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="comments--list clearfix">
                        <?php
                            include(realpath(__DIR__ . '/comments-list.php'));
                        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#audit-trail-accordion-body'><?= $escaper->escapeHtml($lang['AuditTrail']); ?></button>
        </h2>
        <div id="audit-trail-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-12 audit-trail">
                        <?php get_audit_trail_html($id, 36500, ['risk', 'jira']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
<input type="hidden" id="_lang_reopen_risk" value="<?php echo $escaper->escapeHtml($lang['ReopenRisk']); ?>">
<input type="hidden" id="_lang_close_risk" value="<?php echo $escaper->escapeHtml($lang['CloseRisk']); ?>">