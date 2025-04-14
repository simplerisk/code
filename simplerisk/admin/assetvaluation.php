<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(permissions: ['check_admin' => true]);

    // Check if the default asset valuation was submitted
    if (isset($_POST['update_default_value'])) {

        // If the currency is set and is not empty
        if (isset($_POST['currency']) && ($_POST['currency'] != "")) {

            // If the currency value is one character long
            if (strlen($_POST['currency']) <= 6) {

                // Update the currency
                update_setting("currency", $_POST['currency']);

            }
        }

        // If value is set and is numeric
        if (isset($_POST['value']) && is_numeric($_POST['value'])) {

            $value = (int)$_POST['value'];

            // If the value is between 1 and 10
            if ($value >= 1 && $value <= 10) {

                // Update the default asset valuation
                update_default_asset_valuation($value);

            }
        }
    }

    // Check if the automatic asset valuation was submitted
    if (isset($_POST['update_auto_value'])) {

        $min_value = $_POST['min_value'];
        $max_value = $_POST['max_value'];

        // If the minimum value is an integer >= 0
        if (is_numeric($min_value) && $min_value >= 0) {

            // If the maximum value is an integer
            if (is_numeric($max_value)) {

                // Update the asset values
                $success = update_asset_values($min_value, $max_value);

                // If the update was successful
                if ($success) {

                    // Display an alert
                    set_alert(true, "good", "The asset valuation settings were updated successfully.");

                } else {

                    // Display an alert
                    set_alert(true, "bad", "There was an issue updating the asset valuation settings.");

                }

            } else {

                // Display an alert
                set_alert(true, "bad", "Please specify an integer for the maximum value.");

            }

        } else {

            // Display an alert
            set_alert(true, "bad", "Please specify an integer greater than or equal to zero for the minimum value.");

        }
    }

    // Check if the manual asset valuation was submitted
    if (isset($_POST['update_manual_value'])) {

        // For each value range
        for ($i=1; $i<=10; $i++) {

            $valuation_level_name = $_POST["valuation_level_name_" . $i];

            if (strlen($valuation_level_name) > 100) {

                set_alert(true, "bad", _lang('ValuationLevelNameSizeError', array('valuation_level_name' => $valuation_level_name)));
                refresh();
                
            }
        }

        // For each value range
        for ($i=1; $i<=10; $i++) {

            $id = $i;
            $min_value = $_POST["min_value_" . $i];
            $max_value = $_POST["max_value_" . $i];
            $valuation_level_name = $_POST["valuation_level_name_" . $i];

            // If the min_value and max_value are numeric
            if (is_numeric($min_value) && is_numeric($max_value)) {

                // Update the asset value
                $success = update_asset_value($id, $min_value, $max_value, $valuation_level_name);

                // If the update was successful
                if ($success) {

                    // Display an alert
                    set_alert(true, "good", "The asset valuation settings were updated successfully.");

                } else {

                    // Display an alert
                    set_alert(true, "bad", "There was an issue updating the asset valuation settings.");

                }
            }
        }
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <form name="automatic" method="post" action="">
                <h4><?= $escaper->escapeHtml($lang['AutomaticAssetValuation']); ?></h4>
                <div class="row form-group">
                    <div class="col-6">
                        <label><?= $escaper->escapeHtml($lang['MinimumValue']); ?> :</label>
                        <input id="dollarsign" type="number" name="min_value" min="0" size="20" value="<?= asset_min_value(); ?>" class="form-control"/>
                    </div>
                </div>
                <div class="row form-group">
                    <div class="col-6">
                        <label><?= $escaper->escapeHtml($lang['MaximumValue']); ?> :</label>
                       <input id="dollarsign" type="number" name="max_value" size="20" value="<?= asset_max_value(); ?>" class="form-control"/>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_auto_value" class="btn btn-submit"/>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body my-2 border">
             <form name="manual" method="post" action="">
                <h4><?= $escaper->escapeHtml($lang['ManualAssetValuation']); ?></h4>
    <?php 
                display_asset_valuation_table(); 
    ?>
                <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_manual_value" class="btn btn-submit"/>
            </form>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>