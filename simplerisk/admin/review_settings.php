<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true]);

// Check if the risk level update was submitted
if (isset($_POST['update_review_settings_input']) && $_POST['update_review_settings_input'] == 'review_settings')
{
    $veryhigh = (int)$_POST['veryhigh'];
    $high = (int)$_POST['high'];
    $medium = (int)$_POST['medium'];
    $low = (int)$_POST['low'];
    $insignificant = (int)$_POST['insignificant'];

    // Check if all values are integers
    if (is_int($veryhigh) && is_int($high) && is_int($medium) && is_int($low) && is_int($insignificant)){
        // Update the review settings
        update_review_settings($veryhigh, $high, $medium, $low, $insignificant);

        // Display an alert
        set_alert(true, "good", "The review settings have been updated successfully!");
    }
        // NOTE: This will never trigger as we bind $high, $medium, and $low to integer values
    else{
        // Display an alert
        set_alert(true, "bad", "One of your review settings is not an integer value.  Please try again.");
    }
}

?>
<form name="review_settings" method="post" action="">
    <?php $review_levels = get_review_levels(); ?>
    <div class="card-body my-2 border">
        <div class="row align-items-end" >
            <div class="col-md-6 form-group">
                <span>
                    <?= $escaper->escapeHtml($lang['IWantToReviewVeryHighRiskEvery']); ?>
                    <span class="editable"><?= $escaper->escapeHtml($review_levels[0]['value']); ?></span>
                    <input type="text" name="veryhigh" size="2" value="<?= $escaper->escapeHtml($review_levels[0]['value']); ?>" class="editable" style='display: none;'>
                    <?= $escaper->escapeHtml($lang['days']); ?>
                </span>
            </div>
        </div>
        <div class="row align-items-end">
            <div class="col-md-6 form-group">
                <span>
                    <?= $escaper->escapeHtml($lang['IWantToReviewHighRiskEvery']); ?>
                    <span class="editable"><?= $escaper->escapeHtml($review_levels[1]['value']); ?></span>
                    <input type="text" name="high" size="2" value="<?= $escaper->escapeHtml($review_levels[1]['value']); ?>" class="editable" style='display: none;'>
                    <?= $escaper->escapeHtml($lang['days']); ?>
                </span>
            </div>
        </div>
        <div class="row align-items-end">
            <div class="col-md-6 form-group">
                <span>
                    <?= $escaper->escapeHtml($lang['IWantToReviewMediumRiskEvery']); ?>
                    <span class="editable"><?= $escaper->escapeHtml($review_levels[2]['value']); ?></span>
                    <input type="text" name="medium" size="2" value="<?= $escaper->escapeHtml($review_levels[2]['value']); ?>" class="editable" style='display: none;'>
                    <?= $escaper->escapeHtml($lang['days']); ?>
                </span>
            </div>
        </div>
        <div class="row align-items-end">
            <div class="col-md-6 form-group">
                <span>
                    <?= $escaper->escapeHtml($lang['IWantToReviewLowRiskEvery']); ?>
                    <span class="editable"><?= $escaper->escapeHtml($review_levels[3]['value']); ?></span>
                    <input type="text" name="low" size="2" value="<?= $escaper->escapeHtml($review_levels[3]['value']); ?>" class="editable" style='display: none;'>
                    <?= $escaper->escapeHtml($lang['days']); ?>
                </span>
            </div>
        </div>
        <div class="row align-items-end">
            <div class="col-md-6">
                <span>
                    <?= $escaper->escapeHtml($lang['IWantToReviewInsignificantRiskEvery']); ?>
                    <span class="editable"><?= $escaper->escapeHtml($review_levels[4]['value']); ?></span>
                    <input type="text" name="insignificant" size="2" value="<?= $escaper->escapeHtml($review_levels[4]['value']); ?>" class="editable" style='display: none;'>
                    <?= $escaper->escapeHtml($lang['days']); ?>
                </span>
            </div>
        </div>
        <input type="hidden" value="review_settings" name="update_review_settings_input"/>
    </div>
</form>
<script>
    function resizable (el, factor) {
        var int = Number(factor) || 7.6;
        function resize() {el.width((el.val().length + 1) * int);}
        var e = ["keyup", "keypress", "focus", "blur", "change"];
        for (var i in e)
            el.on(e[i], resize);
        resize();
    }

    $(document).ready(function(){
        $("input.editable").each(function(){
            resizable($(this));
        });

        $("body").on("click", "span.editable", function() {
            $(this).hide();
            $(this).parent().find("input").show().select();
        });

        $("body").on("blur", "input.editable", function(){
            if(!$(this).val()) return false;
            var label = $(this).parent().find("span.editable");
            $(this).hide();
            label.text($(this).val());
            label.show();
            $("[name=\'review_settings\']").submit();
        });

        $("input.editable").change(function(){
            $("[name=\'review_settings\']").submit();
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>