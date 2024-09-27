<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['datatables', 'CUSTOM:common.js'], ['check_admin' => true]);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/datefix.php'));

if (getTypeOfColumn('mgmt_reviews', 'next_review') != 'varchar') {
    refresh("index.php");
}

$format_group = !empty($_POST["format_group"]) ? $_POST["format_group"] : "";
$format = !empty($_POST["format"]) && strlen($_POST["format"]) == 5 ? $_POST["format"] : "";

$mass_update_options = [];
$count = count($reviews = getAllReviewsWithDateIssues());
$mass_fixed = 0;

if ($count) {
    foreach ($reviews as $review) {
        $date = $review['next_review'];
        $pf = possibleFormats($date);

        if (count($pf) == 0) {//Not a date
            resetNextReviewDate($review['review_id']);
            $count -= 1;
        }
        //save the date
        elseif (count($pf) == 1 && fixNextReviewDateFormat($review['review_id'], $pf[0])) {
            $count -= 1;
        } else {
            $key = implode(',', $pf);

            if ($format_group === $key && in_array($format, $pf)) {
                //save the date
                if (fixNextReviewDateFormat($review['review_id'], $format)) {
                    $count -= 1;
                    $mass_fixed += 1;
                }
            } elseif (!array_key_exists($key, $mass_update_options)) {
                $mass_update_options[$key] = $pf;
            }
        }
    }
}

// Only re-count if we have to, but do it to make sure
if (!$count && !count(getAllReviewsWithDateIssues())) {
    // Change `next_review` column to date type
    if (changeNextReviewToDateType()) {
        set_alert(true, "good", $lang['NextReviewTypeUpdateSuccess']);
        refresh("index.php");
    } else {
        set_alert(true, "bad", $lang['NextReviewTypeUpdateFailed']);
    }
}

if ($mass_fixed) {
    set_alert(true, "good", _lang('NextReviewMassUpdateSuccess', array('mass_fixed' => $mass_fixed)));
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <p><?php echo $escaper->escapeHtml($lang['NextReviewDateFixDisclaimer']); ?>.</p>
    <?php if (count($mass_update_options) >= 1) { ?>
            <div class="hero-unit">
        <?php foreach($mass_update_options as $format_group=>$formats) { ?>
                <form action="" method="POST">
                    <input type="hidden" name="format_group" value="<?php echo $escaper->escapeHtml($format_group); ?>">
                    <?php echo $escaper->escapeHtml(_lang('NextReviewMassUpdateInfo', array('format1' => convertDateFormatFromPHP($formats[0]), 'format2' => convertDateFormatFromPHP($formats[1])))); ?>&nbsp;
                    <select name="format" style="width:auto;height:auto;padding:0px;margin:0px;" required>
                        <option value=""><?php echo $escaper->escapeHtml($lang['PleaseSelect']); ?></option>
                <?php foreach($formats as $format) { ?>
                        <option value="<?php echo $format; ?>"><?php echo $escaper->escapeHtml(convertDateFormatFromPHP($format)); ?></option>
                <?php } ?>
                    </select>
                    <input type="submit" class="btn btn-submit" value="<?php echo $escaper->escapeHtml($lang['ConfirmAll']); ?>" style="padding: 2px 15px;margin-top:0px;"/>
                </form>
        <?php } ?>
            </div>
    <?php } ?>
            <?php display_review_date_issues(); ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>