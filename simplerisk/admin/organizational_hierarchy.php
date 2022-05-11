<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/organizational_hierarchy'))) {
        // Include the Advanced Search Extra
        require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate'])) {
            // Enable the Advanced Search Extra
            enable_organizational_hierarchy_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate'])) {
            // Disable the Advanced Search Extra
            disable_organizational_hierarchy_extra();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display() {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/organizational_hierarchy'))) {
            // But the extra is not activated
            if (!organizational_hierarchy_extra()) {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("organizational_hierarchy")) {
                    echo "<form id='activate_extra' name=\"activate_extra\" method=\"post\" action=\"\">\n";
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />\n";
                    echo "</form>\n";
                } else // The extra is restricted
                    echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            } else { // Once it has been activated

                // Include the Organizational Hierarchy Extra
                require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

                echo "
                    <form id='deactivate_extra' name=\"deactivate\" method=\"post\">
                        <font color=\"green\">
                            <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                        </font> [" . organizational_hierarchy_version() . "]
                        &nbsp;&nbsp;
                        <input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" />
                    </form>\n";
            }
        } else { // Otherwise, the Extra does not exist
            echo "<a href=\"https://www.simplerisk.com/extras\" target=\"_blank\">Purchase the Extra</a>\n";
        }
    }

?>

<!doctype html>
<html>
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);
?>
	<script src="../js/jquery.easyui.min.js?<?php echo current_version("app"); ?>"></script>
<?php
        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
        <script src="../js/jquery.draggable.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/jquery.droppable.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/treegrid-dnd.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/selectlist.js?<?php echo current_version("app"); ?>"></script>
  
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
          <link rel="stylesheet" href="../css/easyui.css?<?php echo current_version("app"); ?>">
        
        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
        <?php
            setup_favicon("..");
            setup_alert_requirements("..");
        ?>
        
    	<style type="text/css">
            #create_business_unit {
    	       margin-bottom: 3px;
            }
    	   
            a.business-unit--update {
    	       padding-right: 5px;
            }
    	   
            a.business-unit--update, a.business-unit--delete, a.team--remove {
                cursor: pointer;
            }

            td[field='actions'], td[field='actions']>div, .actions-cell, .actions-cell a {
                vertical-align: bottom;
            }
            .modal-body {
                max-height: unset;
            }
            .business-unit-name {
                cursor: pointer;
                user-select: none; /* standard syntax */
                -webkit-user-select: none; /* webkit (safari, chrome) browsers */
                -moz-user-select: none; /* mozilla browsers */
                -khtml-user-select: none; /* webkit (konqueror) browsers */
                -ms-user-select: none; /* IE10+ */
            }
    	</style>    
    </head>

    <body>

        <?php
            display_license_check();

            view_top_menu("Configure");

            // Get any alert messages
            get_alert();
        ?>
        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                    <?php view_configure_menu("OrganizationalHierarchy"); ?>
                </div>
                <div class="span9">
                	<?php if (organizational_hierarchy_extra() && !team_separation_extra()) { ?>
                    	<div class='alert alert-warning' role='alert'>
                          <?php echo $escaper->escapeHtml($lang['OrganizationalHierarchyDisabledWarning']); ?>
                        </div>
                    <?php } ?>
                    <div class="row-fluid">
                        <div class="span12">
                            <div class="hero-unit">
                                <h4><?php echo $escaper->escapeHtml($lang['OrganizationalHierarchyExtra']); ?></h4>
                                <?php display(); ?>
                            </div>
                        </div>
                    </div>
                    <?php if (organizational_hierarchy_extra()) {?>
                        <div class='custom-treegrid-container'>
                            <script>
								function refresh_business_unit_menu_items() {
    			                    $.ajax({
    			                        url: BASE_URL + '/api/organizational_hierarchy/business_unit/available_business_unit_menu_items',
    			                        type: 'GET',
    			                        success : function (response) {
											$('li.dropdown-submenu.business-units ul.dropdown-menu').html(response);
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

								function enableSubmit() {
		                            // Need this because the double-submit prevention script disables submit buttons on a form submit
		                            // and since it's an ajax-driven form submit, there's no page reload to 'enable' the submit buttons
		                            $("[type='submit']").removeAttr("disabled");
								}

        						$(document).ready(function() {

        			                //Have to remove the 'fade' class for the shown event to work for modals
        			                $('#business-unit--create, #business-unit--update').on('shown.bs.modal', function() {
        			                    $(this).find('.modal-body').scrollTop(0);
        			                });

        							$('#business_units').treegrid({
        								iconCls: 'icon-ok',
        				                animate: false,
        				                collapsible: true,
        				                fitColumns: true,
        				                url: BASE_URL + '/api/organizational_hierarchy/business_unit/tree',
        				                method: 'get',
        				                idField: 'value',
        				                treeField: 'name',
        				                scrollbarSize: 0,
        				                loadFilter: function(data, parentId) {
        				                    return data.data;
        				                },
        				                onLoadSuccess: function(row, data){
        				                    //fixTreeGridCollapsableColumn();
        				                    //It's there to be able to have it collapsed on load
        				                    var tree = $('#business_units');
        				                    tree.treegrid('options').animate = false;
        				                    tree.treegrid('collapseAll');
        				                    //tree.treegrid('options').animate = true;

        				                    //$("#business_units").treegrid('resize');
        				                },
        				                onLoadError: function(xhr, status, error) {
    			                            if(!retryCSRF(xhr, this)) {
    			                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
    			                                    showAlertsFromArray(xhr.responseJSON.status_message);
    			                                }
    			                            }
    			                        }
        							});

        							// Enable expanding/collapsing by clicking on the business unit's name
        			                $(document).on('click', '.business-unit-name', function() {
        			                    $('#business_units').treegrid('toggle', $(this).data("id"));
        			                });

        			                $("#create_business_unit").click(function(event) {
        			                    event.preventDefault();
        			                    // Move teams back to the available select if they were previously selected
        			                    $('#business-unit-new-form .select-list-arrows .btnAllLeft')[0].click();
										// Show the modal
        			                    $("#business-unit--create").modal();
        			                });

        			                $("#business-unit-new-form").submit(function(event) {
        			                    event.preventDefault();
        			                    var data = new FormData($('#business-unit-new-form')[0]);

        			                    //adding the ids of the selected teams to the data sent
        			                    $('#business-unit-new-form .select-list-selected select option').each(function() {
        			                        data.append('selected_teams[]', $(this).val());
        			                    });

        			                    $.ajax({
        			                        type: "POST",
        			                        url: BASE_URL + "/api/organizational_hierarchy/business_unit/create",
        			                        data: data,
        			                        async: true,
        			                        cache: false,
        			                        contentType: false,
        			                        processData: false,
        			                        success: function(data) {
        			                            if(data.status_message) {
        			                                showAlertsFromArray(data.status_message);
        			                            }

        			                            $('#business-unit--create').modal('hide');
        			                            $('#business-unit-new-form')[0].reset();

        			                            var tree = $('#business_units');
        			                            tree.treegrid('options').animate = false;
        			                            tree.treegrid('reload');

        			                            refresh_business_unit_menu_items();
        			                        },
        			                        error: function(xhr, status, error) {
        			                            if(!retryCSRF(xhr, this)) {
        			                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
        			                                    showAlertsFromArray(xhr.responseJSON.status_message);
        			                                }
        			                            }
        			                        },
        			                        complete: function(xhr, status) {
        			                        	enableSubmit();
        			                        }
        			                    });
        			                    return false;
        			                });

        			                $(document).on('click', '.business-unit--update', function() {
        			                    var business_unit_id = $(this).data("id");

        			                    $('#business-unit-update-form .select-list-selected select option').remove();
        			                    $('#business-unit-update-form .select-list-available select option').remove();

        			                    $.ajax({
        			                        url: BASE_URL + '/api/organizational_hierarchy/business_unit?id=' + business_unit_id,
        			                        type: 'GET',
        			                        success : function (response) {
        			                            var data = response.data;

        			                            $("#business-unit-update-form [name='business_unit_id']").val(business_unit_id);
        			                            $("#business-unit-update-form [name='name']").val(data.name);
        			                            $("#business-unit-update-form [name='description']").val(data.description);

        			                            addOptions($('#business-unit-update-form .select-list-selected select'), data.selected_teams);
        			                            addOptions($('#business-unit-update-form .select-list-available select'), data.available_teams);

        			                            $("#business-unit--update").modal();
        			                        },
        			                        error: function(xhr, status, error) {
        			                            if(!retryCSRF(xhr, this)) {
        			                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
        			                                    showAlertsFromArray(xhr.responseJSON.status_message);
        			                                }
        			                            }
        			                        }
        			                    });
        			                });

        			                $("#business-unit-update-form").submit(function(event) {
        			                    event.preventDefault();
        			                    var data = new FormData($('#business-unit-update-form')[0]);

        			                    //adding the ids of the selected teams
        			                    $('#business-unit-update-form .select-list-selected select option').each(function() {
        			                        data.append('selected_teams[]', $(this).val());
        			                    });

        			                    $.ajax({
        			                        type: "POST",
        			                        url: BASE_URL + "/api/organizational_hierarchy/business_unit/update",
        			                        data: data,
        			                        async: true,
        			                        cache: false,
        			                        contentType: false,
        			                        processData: false,
        			                        success: function(data){
        			                            if(data.status_message){
        			                                showAlertsFromArray(data.status_message);
        			                            }

        			                            $('#business-unit--update').modal('hide');
        			                            $('#business-unit-update-form')[0].reset();

        			                            var tree = $('#business_units');
        			                            tree.treegrid('options').animate = false;
        			                            tree.treegrid('reload');

        			                            refresh_business_unit_menu_items();
        			                        },
        			                        error: function(xhr, status, error){
        			                            if(!retryCSRF(xhr, this)) {
        			                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
        			                                    showAlertsFromArray(xhr.responseJSON.status_message);
        			                                }
        			                            }
        			                        },
        			                        complete: function(xhr, status) {
        			                        	enableSubmit();
        			                        }
        			                    });

        			                    return false;
        			                });

        			                $(document).on('click', '.business-unit--delete', function() {
        			                    $("#business-unit-delete-form [name='business_unit_id']").val($(this).data("id"));
        			                    $("#business-unit--delete").modal();
        			                });

        			                $("#business-unit-delete-form").submit(function(event) {
        			                    event.preventDefault();
        			                    var data = new FormData($('#business-unit-delete-form')[0]);

        			                    $.ajax({
        			                        type: "POST",
        			                        url: BASE_URL + "/api/organizational_hierarchy/business_unit/delete",
        			                        data: data,
        			                        async: true,
        			                        cache: false,
        			                        contentType: false,
        			                        processData: false,
        			                        success: function(data){
        			                            if(data.status_message){
        			                                showAlertsFromArray(data.status_message);
        			                            }

        			                            $('#business-unit--delete').modal('hide');
        			                            $('#business-unit-delete-form')[0].reset();

        			                            var tree = $('#business_units');
        			                            tree.treegrid('options').animate = false;
        			                            tree.treegrid('reload');

        			                            refresh_business_unit_menu_items();
        			                        },
        			                        error: function(xhr, status, error){
        			                            if(!retryCSRF(xhr, this)) {
        			                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
        			                                    showAlertsFromArray(xhr.responseJSON.status_message);
        			                                }
        			                            }
        			                        },
        			                        complete: function(xhr, status) {
        			                        	enableSubmit();
        			                        }
        			                    });

        			                    return false;
        			                });

        			                $(document).on('click', '.team--remove', function() {
        			                    $("#team-remove-form [name='business_unit_id']").val($(this).data('business-unit-id'));
        			                    $("#team-remove-form [name='team_id']").val($(this).data('team-id'));
        			                    $("#team--remove").modal();
        			                });

        			                $("#team-remove-form").submit(function(event) {
        			                    event.preventDefault();

        			                    var data = new FormData($('#team-remove-form')[0]);
        			                    var business_unit_id = $("#team-remove-form [name='business_unit_id']").val();
        			                    var team_id = $("#team-remove-form [name='team_id']").val();

        			                    $.ajax({
        			                        type: "POST",
        			                        url: BASE_URL + "/api/organizational_hierarchy/business_unit/remove-team",
        			                        data: data,
        			                        async: true,
        			                        cache: false,
        			                        contentType: false,
        			                        processData: false,
        			                        success: function(data){
        			                            if(data.status_message){
        			                                showAlertsFromArray(data.status_message);
        			                            }

        			                            $('#team--remove').modal('hide');
        			                            $('#team-remove-form')[0].reset();

        			                            //$("tr[node-id='" + business_unit_id + "-" + team_id + "']").remove();
        			                            var tree = $('#business_units');
        			                            tree.treegrid('remove', business_unit_id + "-" + team_id);
        			                            
        			                            var teamCountWrapper = $('tr[node-id=' + business_unit_id +'] span.team-count');
        			                            var teamCount = parseInt(teamCountWrapper.data('team-count'));
        			                            teamCountWrapper.data('team-count', teamCount-1);
        			                            teamCountWrapper.html(teamCount-1);
        			                        },
        			                        error: function(xhr, status, error){
        			                            if(!retryCSRF(xhr, this)) {
        			                                if(xhr.responseJSON && xhr.responseJSON.status_message){
        			                                    showAlertsFromArray(xhr.responseJSON.status_message);
        			                                }
        			                            }
        			                        },
        			                        complete: function(xhr, status) {
        			                        	enableSubmit();
        			                        }
        			                    });

        			                    return false;
        			                });
        						});
                             
                            </script>
                            <div class='text-right'>
                            	<button id='create_business_unit'><?php echo $escaper->escapeHtml($lang['CreateNewBusinessUnit']); ?></button>
                            </div>
                            <table id='business_units' class='easyui-treegrid framework-table'>
                            	<thead>
                            		<tr>
            							<th data-options="field:'name'" width='20%'><?php echo $escaper->escapeHtml($lang['Name']); ?></th>
            							<th data-options="field:'description'" width='70%'><?php echo $escaper->escapeHtml($lang['Description']); ?></th>
            							<th data-options="field:'actions'" width='10%'>&nbsp;</th>
            						</tr>
            					</thead>
                            </table>
                        </div>
                    <?php } ?>

                    <!-- MODAL WINDOW FOR ADDING BUSINESS UNIT -->
                    <div id="business-unit--create" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="business-unit--create" aria-hidden="true">
                        <form id="business-unit-new-form" action="#" method="POST" autocomplete="off">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['CreateNewBusinessUnit']); ?></h4>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for=""><?php echo $escaper->escapeHtml($lang['Name']); ?></label>
                                    <input type="text" required name="name" value="" class="form-control" autocomplete="off">

                                    <label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
                            		<textarea name="description" class="form-control" rows="6" style="width:100%;"></textarea>
                            		
                                    <div class="select-list-wrapper" >

                                        <div class="select-list-available">
                                            <label for=""><?php echo $escaper->escapeHtml($lang['AvailableTeams']); ?></label>
                                            <select multiple="multiple" class="form-control">
                                            	<?php foreach (get_all_teams() as $team) {?>
                                            		<option value="<?php echo (int)$team['value'];?>"><?php echo $escaper->escapeHtml($team['name']);?></option>
                                            	<?php }?>
                                            </select>
                                        </div>

                                        <div class="select-list-arrows text-center">
                                            <input type='button' value='&gt;&gt;' class="btn btn-default btnAllRight" /><br />
                                            <input type='button' value='&gt;' class="btn btn-default btnRight" /><br />
                                            <input type='button' value='&lt;' class="btn btn-default btnLeft" /><br />
                                            <input type='button' value='&lt;&lt;' class="btn btn-default btnAllLeft" />
                                        </div>

                                        <div class="select-list-selected">
                                            <label for=""><?php echo $escaper->escapeHtml($lang['SelectedTeams']); ?></label>
                                            <select name="selected-business-units" multiple="multiple" class="form-control">
                                            </select>
                                        </div>

                                        <div class="clearfix"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                                <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Create']); ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- MODAL WINDOW FOR EDITING AN BUSINESS UNIT -->
                    <div id="business-unit--update" class="modal hide no-padding" tabindex="-1" role="dialog" aria-hidden="true">
                        <form id="business-unit-update-form" class="" action="#" method="post" autocomplete="off">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['BusinessUnitUpdate']); ?></h4>
                            </div>
                            <input type="hidden" class="business_unit_id" name="business_unit_id" value="">
            
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for=""><?php echo $escaper->escapeHtml($lang['Name']); ?></label>
                                    <input type="text" required name="name" value="" class="form-control" autocomplete="off">
            
                                    <label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
                                    <textarea name="description" class="form-control" rows="6" style="width:100%;"></textarea>
            
                                    <div class="select-list-wrapper" >

                                        <div class="select-list-available">
                                            <label for=""><?php echo $escaper->escapeHtml($lang['AvailableTeams']); ?></label>
                                            <select multiple="multiple" class="form-control">
                                            </select>
                                        </div>

                                        <div class="select-list-arrows text-center">
                                            <input type='button' value='&gt;&gt;' class="btn btn-default btnAllRight" /><br />
                                            <input type='button' value='&gt;' class="btn btn-default btnRight" /><br />
                                            <input type='button' value='&lt;' class="btn btn-default btnLeft" /><br />
                                            <input type='button' value='&lt;&lt;' class="btn btn-default btnAllLeft" />
                                        </div>

                                        <div class="select-list-selected">
                                            <label for=""><?php echo $escaper->escapeHtml($lang['SelectedTeams']); ?></label>
                                            <select name="selected-business-units" multiple="multiple" class="form-control">
                                            </select>
                                        </div>

                                        <div class="clearfix"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                                <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
                            </div>
                        </form>
                    </div>
            
                    <!-- MODAL WINDOW FOR BUSINESS UNIT DELETE CONFIRM -->
                    <div id="business-unit--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="business-unit-delete-form" aria-hidden="true">
                        <div class="modal-body">
            
                            <form class="" id="business-unit-delete-form" action="" method="post">
                                <div class="form-group text-center">
                                    <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisBusinessUnit']); ?></label>
                                    <input type="hidden" name="business_unit_id" value="" />
                                </div>
            
                                <div class="form-group text-center">
                                    <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                                    <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
            
                    <!-- MODAL WINDOW FOR TEAM REMOVAL CONFIRM -->
                    <div id="team--remove" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="team-remove-form" aria-hidden="true">
                        <div class="modal-body">
            
                            <form class="" id="team-remove-form" action="" method="post">
                                <div class="form-group text-center">
                                    <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToRemoveThisTeam']); ?></label>
                                    <input type="hidden" name="business_unit_id" value="" />
                                    <input type="hidden" name="team_id" value="" />
                                </div>
            
                                <div class="form-group text-center">
                                    <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                                    <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            <?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']); ?>
        </script>
    </body>
</html>
