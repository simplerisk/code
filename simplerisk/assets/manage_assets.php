<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assets.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_assets" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

    // Check if the user has access to manage assets
    if (!isset($_SESSION["asset"]) || $_SESSION["asset"] != 1)
    {
        header("Location: ../index.php");
        exit(0);
    }
    else $manage_assets = true;

    // Check if an asset was added
    if ((isset($_POST['add_asset'])) && $manage_assets)
    {
        $name       = $_POST['asset_name'];
        $ip         = $_POST['ip'];
        $value      = $_POST['value'];
        $location   = empty($_POST['location']) ? [] : $_POST['location'];
        $teams      = empty($_POST['team']) ? [] : $_POST['team'];
        $details    = $_POST['details'];
        $tags       = empty($_POST['tags']) ? [] : $_POST['tags'];

        foreach($tags as $tag){
            if (strlen($tag) > 255) {
                global $lang;
                
                set_alert(true, "bad", $lang['MaxTagLengthWarning']);
                refresh();
            }
        }
        
        
        if($name)
        {
            // Add the asset
            $success = add_asset($ip, $name, $value, $location, $teams, $details, $tags, true);

            // If the asset add was successful
            if ($success)
            {
                // Display an alert
                set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasAddedSuccessfully']));
            }
            else
            {
                // Get alert text
                $alert_message = get_alert(false, true);
                
                if(!$alert_message)
                {
                    $alert_message = $escaper->escapeHtml($lang['ThereWasAProblemAddingTheAsset']);
                }

                // Display an alert
                set_alert(true, "bad", $alert_message);
            }
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['AssetNameIsRequired']));
        }

        
        refresh();
    }

?>

<!doctype html>
<html>

<head>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

<?php
    // Use these jQuery scripts
    $scripts = [
        'jquery.min.js',
    ];

    // Include the jquery javascript source
    display_jquery_javascript($scripts);

    // Use these jquery-ui scripts
    $scripts = [
        'jquery-ui.min.js',
    ];

    // Include the jquery-ui javascript source
    display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
	// Don't need it yet as we don't have datetimes on the page
	//display_datetimepicker_javascript(true);
?>
    <script src="../js/simplerisk/pages/asset.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
    
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <script src="../vendor/simplerisk/selectize.js/dist/js/standalone/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    
    <script src="../js/simplerisk/dataTables.renderers.js?<?php echo current_version("app"); ?>"></script>
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>  

    <script>
        $.blockUI.defaults.css = {
            padding: 0,
            margin: 0,
            width: '30%',
            top: '40%',
            left: '35%',
            textAlign: 'center',
            cursor: 'wait'
        };
    </script>
</head>

<body>
    <?php
        view_top_menu("AssetManagement");

        // Get any alert messages
        get_alert();
    ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_asset_management_menu("AddDeleteAssets"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <div class="span12">
                        <div class="hero-unit">
                            <div class="row-fluid">
                                <div class="wrap-text span12 text-left"><h4><?php echo $escaper->escapeHTML($lang['AddANewAsset']); ?></h4></div>
                            </div>
                            <br/>
                            <div class="row-fluid">
                                <div class="span8">
                                    <form name="add" method="post" action="" id="add-asset-container">
                                        <?php
                                            display_add_asset();
                                        ?>
                                        <div class="row-fluid">
                                            <div class="span1">
                                                <button type="submit" name="add_asset" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="hero-unit" data-view="asset_unverified">                        
                            <div class="row-fluid">
                                <div class="wrap-text span12 text-left">
                                    <h4><?php echo $escaper->escapeHTML($lang['UnverifiedAssets']); ?></h4>
                                </div>
                            </div>
                            <div class="row-fluid">
                                <div class="span10" style="margin-bottom: 5px">
                                    <button data-action="verify" class="btn btn-primary asset-view-action"><?php echo $escaper->escapeHtml($lang['VerifyAll']); ?></button>
                                    <button data-action="discard" class="btn btn-primary asset-view-action"><?php echo $escaper->escapeHtml($lang['DiscardAll']); ?></button>
                                </div>
                                <div class="span2">
                                    <div style="float: right;">
                                        <?php render_column_selection_widget('asset_unverified'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row-fluid">
                                <div class="span12">
                                    <?php render_view_table('asset_unverified'); ?>
                                </div>
                            </div>
                            <div class="row-fluid">
                                <div class="span12">
                                    <button data-action="verify" class="btn btn-primary asset-view-action"><?php echo $escaper->escapeHtml($lang['VerifyAll']); ?></button>
                                    <button data-action="discard" class="btn btn-primary asset-view-action"><?php echo $escaper->escapeHtml($lang['DiscardAll']); ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="hero-unit" data-view="asset_verified">
                            <div class="row-fluid">
                                <div class="wrap-text span12 text-left">
                                    <h4><?php echo $escaper->escapeHTML($lang['VerifiedAssets']); ?></h4>
                                </div>
                            </div>
                            <div class="row-fluid">
                                <div class="span10" style="margin-bottom: 5px">
                                    <button data-action="delete" class="btn btn-primary asset-view-action"><?php echo $escaper->escapeHtml($lang['DeleteAll']); ?></button>
                                </div>
                                <div class="span2">
                                    <div style="float: right;">
                                        <?php render_column_selection_widget('asset_verified'); ?>
                                    </div>        
                                </div>
                            </div>
                            <div class="row-fluid">
                                <div class="span12">
                                    <?php render_view_table('asset_verified'); ?>
                                </div>
                            </div>
                            <div class="row-fluid">
                                <div class="span12">
                                    <button data-action="delete" class="btn btn-primary asset-view-action"><?php echo $escaper->escapeHtml($lang['DeleteAll']); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="confirm-view-action" class="modal hide fade in" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-body">
            <div class="form-group text-center">
                <label for="" class="confirm-message verify"><?php echo $escaper->escapeHtml($lang['ConfirmVerifyAllAssets']); ?></label>
                <label for="" class="confirm-message discard"><?php echo $escaper->escapeHtml($lang['ConfirmDiscardAllAssets']); ?></label>
                <label for="" class="confirm-message delete"><?php echo $escaper->escapeHtml($lang['ConfirmDeleteAllAssets']); ?></label>
    	   </div>
            <div class="form-group text-center">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button class="btn btn-danger proceed" data-action="" data-view=""><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {

            $('.multiselect').multiselect({buttonWidth: '300px', enableFiltering: true, enableCaseInsensitiveFiltering: true,});
            $('.datepicker').datepicker();

            //Have to remove the 'fade' class for the shown event to work for modals
            // It's there to make sure every time the modal window is opened it's scrolled to the top
            $('div.modal').on('shown.bs.modal', function() {
                $(this).find('.modal-body').scrollTop(0);
            });

            // Event handler for the row level actions
            $('body').on('click', 'button.asset-row-action', function(e) {
                e.preventDefault();

                var _this = $(this);
                var id = _this.closest('span').data('id');
                var action = _this.data('action');
                var view = _this.closest('table').data('view');

                $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/assets/view/action",
                    data: {
                        id: id,
                        action: action,
                        view: view
                    },
                    success: function(data) {
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        // In case of editing the API call returns with the edited asset's data
                        // we have to populate the popup's fields and show it to the user
                        if (action == 'edit') {
                            // Iterate through all the fields in the form and populate them
                            $('select.edit_input, input.edit_input, textarea.edit_input', $(`#edit_popup_modal-${view}`)).each(function() {
                                var tag = $(this);

                                // Theoretically it's already uppercase, so it's just to make sure
                                var tagName = tag[0].tagName.toUpperCase();

                                // Remove the trailing '[]' from the name of multiselects
                                var name = tag.attr("name").replace(/[\[\]']+/g,'');

                                var value = (data.data[name] !== undefined ? data.data[name] : false);
                                if (tagName == 'SELECT' && tag.hasClass('selectized')) {
                                    tag[0].selectize.setValue(value ? value : []);
                                } else if (tagName == 'SELECT' && tag.hasClass('multiselect')) {
                                    if (value) {
                                        tag.multiselect('select', value);
                                    } else {
                                        // Have to do this in case the value is empty to deselect the previous selection
                                        // in the other cases it's enough to set the value as empty, but for multiselect it just doesn't...
                                        tag.find('option:selected').each(function() {
                                            $(this).prop('selected', false);
                                        })
                                        tag.multiselect('refresh');
                                    }
                                } else {
                                    tag.val(value ? value : '');
                                }
                            });

                            $.unblockUI();
                            $(`#edit_popup_modal-${view}`).modal();
                        } else {
                            // Adding this one-fire event to make sure we only unblock the UI when the datatable is done refreshing
                            datatableInstances[view].one('xhr', function (e, settings, json) {
    	                       $.unblockUI();
                            });
                            
                            // Re-draw the view
                            datatableInstances[view].draw();
    
                            if (action == 'verify') {
                                // In case of verification have to re-draw the other table too as the verified asset might appear there
                                datatableInstances['asset_verified'].draw();
                            }
                        }
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if(!retryCSRF(xhr, this)) {}
                    },
                    complete: function() { }
                });
            });

            // Event handler for the view level actions
            $('body').on('click', 'button.asset-view-action', function(e) {
                e.preventDefault();

                var _this = $(this);
                var proceed_button = $('div#confirm-view-action button.proceed');
                var action = _this.data('action');

                // Set the required data on the 'Yes' button so the event handler knows which action got confirmed 
                proceed_button.data('action', action);
                proceed_button.data('view', _this.closest('div.hero-unit').data('view'));

                // Show the right confirmation question
                $('#confirm-view-action label.confirm-message').hide();
                $('#confirm-view-action label.' + action).show();
                
                // Show the confirmation window
                $('#confirm-view-action').modal('show');
            });
            
            // Handle the view level actions once they're confirmed
            $('body').on('click', 'div#confirm-view-action button.proceed', function(e) {
                e.preventDefault();
                $('#confirm-view-action').modal('hide');

                var _this = $(this);
                var action = _this.data('action');
                var view = _this.data('view');

                $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/assets/view/action",
                    data: {
                        action: action,
                        all: true
                    },
                    success: function(data) {
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        // Re-draw the view
                        datatableInstances[view].draw();

                        if (action == 'verify') {
                            // In case of verification have to re-draw the other table too as the verified asset might appear there
                            datatableInstances['asset_verified'].draw();
                        }
                        
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if(!retryCSRF(xhr, this)) {}
                    },
                    complete: function() {
                        $.unblockUI();
                    }
                });
            });
        });
    </script>
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
