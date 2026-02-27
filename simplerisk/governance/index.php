<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'datetimerangepicker', 'WYSIWYG', 'multiselect', 'easyui:treegrid', 'easyui:dnd', 'tabs:logic', 'CUSTOM:pages/governance.js', 'CUSTOM:common.js', 'JSLocalization'], ['check_governance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));

    // Check if a new framework was submitted
    if (isset($_POST['add_framework'])) {
        $name         = get_param("POST", "framework_name", "");
        $descripiton  = get_param("POST", "framework_description", "");
        $parent       = get_param("POST", "parent", "");

        // Check if the framework name is null/empty/trimmed empty
        if (isset($name) && !trim($name)) {

            // Display an alert
            set_alert(true, "bad", $lang["FrameworkNameCantBeEmpty"]);

        // Otherwise
        } else {

            $name = trim($name);

            if (empty($_SESSION['add_new_frameworks'])) {

                // Display an alert
                set_alert(true, "bad", $lang['NoAddFrameworkPermission']);

            // Insert a new framework up to 100 chars
            } elseif (add_framework($name, $descripiton, $parent)) {

                // Display an alert
                set_alert(true, "good", $lang['FrameworkAdded']);

            } else {

                // Display an alert
                set_alert(true, "bad", $lang['FrameworkNameExist']);

            }

        }

        refresh();
        
    }

    // Delete if a new framework was submitted
    if (isset($_POST['delete_framework'])) {
        $value = (int)$_POST['framework_id'];

        // Verify value is an integer
        if (is_int($value)) {

            // If user has no permission for modify frameworks
            if (empty($_SESSION['delete_frameworks'])) {

                set_alert(true, "bad", $lang['NoDeleteFrameworkPermission']);

            // If the framework ID is 0 (ie. Unassigned Risks)
            } elseif ($value == 0) {

                // Display an alert
                set_alert(true, "bad", $lang['CantDeleteUnassignedFramework']);

            } elseif (complianceforge_scf_extra()) {

                // Include the SCF extra
                require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));

                // Get the latest ID of the SCF framework
                $latest_id = (int) get_scf_framework_id(null, true);

                // If we are trying to delete the latest SCF framework
                if ($latest_id === (int) $value)
                {
                    // Set an alert so that we don't delete it
                    set_alert(true, "bad", $lang['CantDeleteComplianceForgeSCFFramework']);
                }
                // If it's not the latest SCF framework
                else
                {
                    // Have SCF handle the framework deletion
                    disable_scf_frameworks($value);
                }
            } else {

                // If the ucf extra is enabled
                if (ucf_extra()) {

                    // Include the ucf extra
                    require_once(realpath(__DIR__ . '/../extras/ucf/index.php'));

                    // Disable the UCF framework
                    disable_ucf_framework($value);

                }

                // Delete the framework
                delete_frameworks($value);

                // Display an alert
                set_alert(true, "good", "An existing framework was deleted successfully.");

            }

        // We should never get here as we bound the variable as an int
        } else {

            // Display an alert
            set_alert(true, "bad", "The framework ID was not a valid value.  Please try again.");

        }

        refresh();
    }

    // Delete if a delete control was submitted
    if (isset($_POST['delete_control'])) {
        $value = (int)$_POST['control_id'];

        // If user has no permission for delete controls
        if (empty($_SESSION['delete_controls'])) {

            // Display an alert
            set_alert(true, "bad", $lang['NoDeleteControlPermission']);

        // Verify value is an integer
        } elseif (is_int($value)) {
        
            // Delete the control
            delete_framework_control($value);

            // Display an alert
            set_alert(true, "good", "An existing control was deleted successfully.");

        // We should never get here as we bound the variable as an int
        } else {

            // Display an alert
            set_alert(true, "bad", "The control ID was not a valid value.  Please try again.");

        }

        // Refresh current page
        refresh();

    }

    // If delete controls were submitted
    if (isset($_POST['delete_controls'])) {
        $control_ids = $_POST['control_ids'];

        // If user has no permission for delete controls
        if (empty($_SESSION['delete_controls'])) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoDeleteControlPermission']));

        // Verify control ids for deleting was submitted
        } elseif (is_array($control_ids)) {

            foreach ($control_ids as $control_id) {
                
                // Delete the control
                delete_framework_control($control_id);

            }

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['TheSelectedControlsWereDeletedSuccessfully']));
            
        // We should never get here as we bound the variable as an int
        } else {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['NothingControlsForDeletingWereSelected']));

        }

        // Refresh current page
        refresh();

    }

    $active_framework_count = get_frameworks_count(1);
    $inactive_framework_count = get_frameworks_count(2);
    $framework_count = $active_framework_count + $inactive_framework_count;

?>
<script>
    
    // Set current mouse position
    var mouseX, mouseY;
    $(document).mousemove(function(e) {mouseX = e.pageX;mouseY = e.pageY;}).mouseover();

    $(document).ready(function() {

    <?php 
        if (customization_extra()) {
    ?>
        $('.datepicker').initAsDatePicker();
        $("select[id^='custom_field'].multiselect").multiselect({buttonWidth: '300px', enableFiltering: true, enableCaseInsensitiveFiltering: true});
    <?php 
        }
    ?>
        $('#controls-tab-content select[multiple]').multiselect({
            allSelectedText: '<?= $escaper->escapeHtml($lang['ALL']); ?>',
            enableFiltering: true,
            maxHeight: 250,
            buttonWidth: '100%',
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
            onChange: function () {
            	// mark the dropdown value 'changed' so on dropdown hide it can redraw the datatable
                this.$select.data('changed', true);
            },
            onSelectAll: function() {
            	// mark the dropdown value 'changed' so on dropdown hide it can redraw the datatable
                this.$select.data('changed', true);
            },
            onDeselectAll: function() {
            	// mark the dropdown value 'changed' so on dropdown hide it can redraw the datatable
                this.$select.data('changed', true);
            },
            onDropdownHide: function() {
            	// If the data didn't change, we have nothing to do here
            	if (!(this.$select.data('changed'))) {
            		return;
            	}

            	// reset the 'changed' flag on the select
            	this.$select.data('changed', false);

                if (this.$select.attr('id') == 'filter_by_control_framework') {
                    rebuild_filters();
                } else {
                    controlDatatable.draw();
                }
            }
        });

        $("select[name='control_type[]'").multiselect({
        	allSelectedText: '<?= $escaper->escapeHtml($lang['ALL']); ?>',
            enableFiltering: true,
            maxHeight: 250,
            buttonWidth: '100%',
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        $("#framework--add [name=framework_description]").attr("id", "add_framework_description");
        init_minimun_editor('#add_framework_description');
        $("#framework--update [name=framework_description]").attr("id", "update_framework_description");
        init_minimun_editor('#update_framework_description');

        // Add WYSIWYG editor to control modal
        $("#control--add [name=description]").attr("id", "add_control_description");
        init_minimun_editor('#add_control_description');
        $("#control--add [name=supplemental_guidance]").attr("id", "add_supplemental_guidance");
        init_minimun_editor('#add_supplemental_guidance');
        $("#control--update [name=description]").attr("id", "update_control_description");
        init_minimun_editor('#update_control_description');
        $("#control--update [name=supplemental_guidance]").attr("id", "update_supplemental_guidance");
        init_minimun_editor('#update_supplemental_guidance');
    });

	$(document).on('show.bs.modal', '#framework--add', function(e) {
		$.ajax({
            url: BASE_URL + '/api/governance/parent_frameworks_dropdown?status=1',
            type: 'GET',
            async: false,
            success : function (res){
                $("#framework--add .parent_frameworks_container").html(res.data.html)
            }
        });
	});

	// When yes is selected in the delete confirmation window, submit the form
    $("body").on("click", "#confirm_delete_controls", function() {
    	document.controls_form.submit();
    });

    $("body").on("click", "#active-controls .checkbox-in-div input[type=checkbox]", function(){
    	// enable/disable the delete controls button based on whether there's any controls selected
		$('#delete-controls-btn').attr('disabled', $('#active-controls .checkbox-in-div input[type=checkbox]:checked').length == 0);
    });

	// Not initializing the treegrid in a static call, but rather initializing it when its tab is activated
	// because it's not initialized properly while it's in the background
    $(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"][data-status]', function (e) {
        let status = $(this).data('status');
        $('.framework-table-'+ status).initAsFrameworkTreegrid(status, <?= has_permission('modify_frameworks') ? 'true' : 'false' ?>);

        // Need to trigger a resize event to make the treegrid visible
        $(window).trigger('resize');
    });

    // When the frameworks tab is shown, initialize the treegrid for the table of the active tab
    $(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"][data-bs-target="#frameworks-tab-content"]', function (e) {
        let activeTab = $(this).find('nav a[data-bs-toggle="tab"].active');
        let status = $(activeTab).data('status');
        $('.framework-table-'+ status).initAsFrameworkTreegrid(status, <?= has_permission('modify_frameworks') ? 'true' : 'false' ?>);

        // Need to trigger a resize event to make the treegrid visible
        $(window).trigger('resize');
    });

</script>
<div class="row">
    <div class="col-12 mt-2">
        <div>
            <nav class="nav nav-tabs">
                <a class="nav-link active" data-bs-target="#frameworks-tab-content" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['Frameworks']); ?>(<span id="frameworks-count"><?= $framework_count ?></span>)</a>
                <a class="nav-link" data-bs-target="#controls-tab-content" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['Controls']); ?>(<span id="controls_count"><?= get_framework_controls_count() ?></span>)</a>
            </nav>
        </div>
    </div>
    <div class="col-12 tab-content my-2">
        <!--  Frameworks container Begin -->
        <div id="frameworks-tab-content" class="active tab-pane">
            <div>
                <nav class="nav nav-tabs">
                    <a class="btn btn-primary" data-bs-target="#framework--add" data-bs-toggle="modal"><i class="fa fa-plus"></i></a>
                    <a class="nav-link easyui-droppable targetarea active" data-bs-target="#active-frameworks" data-bs-toggle="tab" data-status="1" data-options = "
                            accept: '.datagrid-row.droppable.2',
                            onDragEnter:function(e,source){
                                $(this).toggleClass('highlight');
                                $('span.tree-dnd-icon').removeClass('tree-dnd-no').addClass('tree-dnd-yes');
                            },
                            onDragLeave: function(e,source){
                                $(this).toggleClass('highlight');
                                $('span.tree-dnd-icon').removeClass('tree-dnd-yes').addClass('tree-dnd-no');
                            }
                        "><?= $escaper->escapeHtml($lang['ActiveFrameworks']); ?>(<span id="active-frameworks-count"><?= $active_framework_count ?></span>)</a>
                    <a class="nav-link easyui-droppable targetarea" data-bs-target="#inactive-frameworks" data-bs-toggle="tab" data-status="2" data-options = "
                            accept: '.datagrid-row.droppable.1',
                            onDragEnter:function(e,source){
                                $(this).toggleClass('highlight');
                                $('span.tree-dnd-icon').removeClass('tree-dnd-no').addClass('tree-dnd-yes');  
                            },
                            onDragLeave: function(e,source){
                                $(this).toggleClass('highlight');
                                $('span.tree-dnd-icon').removeClass('tree-dnd-yes').addClass('tree-dnd-no');
                            }
                        "><?= $escaper->escapeHtml($lang['InactiveFrameworks']); ?>(<span id="inactive-frameworks-count"><?= $inactive_framework_count ?></span>)</a>
                </nav>
            </div>
            <div class="tab-content mt-2 card-body border">
                <div id="active-frameworks" class="active tab-pane custom-treegrid-container">
    <?php 
                    get_framework_tabs(1) 
    ?>
                </div>
                <div id="inactive-frameworks" class="tab-pane custom-treegrid-container">
    <?php 
                    get_framework_tabs(2) 
    ?>
                </div>    
            </div>
    <?php
        // If there are no frameworks
        if ($framework_count == 0) {

            // URL for the frameworks
            $url = "https://raw.githubusercontent.com/simplerisk/import-content/master/Control%20Frameworks/frameworks.xml";
        
            // Configure the proxy server if one exists
            $method = "GET";
            $header = "content-type: application/xml";
            $context = set_proxy_stream_context($method, $header);
        
            $frameworks = @file_get_contents($url, false, $context);
            $frameworks_xml = simplexml_load_string($frameworks);
    ?>
            <div class="card-body border my-2">
                <h3>No frameworks?  No problem.</h3>
                <h4>Try one of the following ways to load frameworks into SimpleRisk:</h4>
                <ol>
                    <li>Click the plus (+) icon above to manually create a new framework.</li>
                    <li><a href="../admin/register.php">Register</a> your SimpleRisk instance to download the free Secure Controls Framework (SCF) Extra and <a href="../admin/securecontrolsframework.php">select from over 200 different frameworks</a> that have been expertly mapped against over 1000 security and privacy controls.</li>
                    <li>Use the licensed <a href="../admin/content.php">Import-Export Extra</a> to instantly install any of the following frameworks or import your own:
                        <ol style="list-style-type: disc;">
    <?php
            // For each framework returned from GitHub
            foreach ($frameworks_xml as $framework_xml) {
    ?>
                            <li><?= $escaper->escapeHtml($framework_xml->{'name'}) ?></li>
    <?php
            }
    ?>
                        </ol>
                    </li>
                </ol>
            </div>
    <?php
        }
    ?>
        </div>
        <!-- Frameworks container Ends -->

        <!--  Controls container Begin -->
        <div id="controls-tab-content" class="tab-pane">
            <div class="row">
                <div class="accordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#filters">Filters</button>
                        </h2>
                        <div id="filters" class="accordion-collapse collapse show">
                            <div class="accordion-body card-body">
                                <div class="row">
                                    <div class="col-4 form-group">
                                        <label><?= $escaper->escapeHtml($lang['ControlClass']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_class", "all", null, getAvailableControlClassList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                    <div class="col-4 form-group">
                                        <label><?= $escaper->escapeHtml($lang['ControlPhase']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_phase", "all", null, getAvailableControlPhaseList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                    <div class="col-4 form-group">
                                        <label><?= $escaper->escapeHtml($lang['ControlFamily']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_family", "all", null, getAvailableControlFamilyList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-4 form-group">
                                        <label><?= $escaper->escapeHtml($lang['ControlOwner']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_owner", "all", null, getAvailableControlOwnerList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                    <div class="col-4 form-group">
                                        <label><?= $escaper->escapeHtml($lang['ControlFramework']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_framework", "all", null, getAvailableControlFrameworkList(true), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                    <div class="col-4 form-group">
                                        <label><?= $escaper->escapeHtml($lang['ControlPriority']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_priority", "all", null, getAvailableControlPriorityList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <label><?= $escaper->escapeHtml($lang['ControlType']); ?>:</label>
    <?php 
                                        create_multiple_dropdown("filter_by_control_type", "all", null, get_options_from_table("control_type"), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); 
    ?>
                                    </div>
                                    <div class="col-4">
                                        <label><?= $escaper->escapeHtml($lang['ControlStatus']); ?>:</label>
                                        <select id="filter_by_control_status" multiple="multiple">
                                            <option selected value="1"><?= $escaper->escapeHtml($lang['Pass']);?></option>
                                            <option selected value="0"><?= $escaper->escapeHtml($lang['Fail']);?></option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label><?= $escaper->escapeHtml($lang['FilterByText']); ?>:</label>
                                        <input type="text" class="form-control" id="filter_by_control_text">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body border mt-2">
                <!-- h4 class="mt-4 mb-4"><?= $escaper->escapeHtml($lang['Controls']); ?> <span id="controls_count"></span></h4-->
                <form action="" name="controls_form" method="POST" id="controls-form" style="margin-top: -0.5rem">
                    <input type="hidden" name="delete_controls" value="1">
                    <div data-sr-role="dt-settings" data-sr-target="active-controls" class="text-end" >
                        <button type="button" id="delete-controls-btn" class="btn btn-secondary" disabled data-bs-toggle="modal" data-bs-target="#controls--delete"><?= $escaper->escapeHtml($lang['DeleteSelectedControls']) ?></button>
                        <a href="#control--add" role="button" data-bs-toggle="modal" data-bs-target="#control--add" class="btn btn-primary control--add"><?= $escaper->escapeHtml($lang['CreateControl']) ?></i></a>
                    </div> <!-- status-tabs -->
                    <table id="active-controls" style="width:100%">
                        <thead style='display:none;'>
                            <tr>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        <!-- Controls container Ends -->
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING FRAMEWORK -->
<div class="modal fade" id="framework--add" tabindex="-1" aria-labelledby="framework--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $escaper->escapeHtml($lang['NewFramework']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="framework-create-form" action="#" method="post" autocomplete="off">
    <?php 
                    display_add_framework();
    ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" form="framework-create-form" name="add_framework" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div>
        </div>
    </div>    
</div>

<!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
<?php
    display_update_framework_modal('governance');
?>

<!-- MODEL WINDOW FOR FRAMEWORK DELETE CONFIRM -->
<div class="modal fade" id="framework--delete" tabindex="-1" aria-labelledby="framework--delete" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dark">
        <div class="modal-content">
            <form class="" id="framework-delete-form" action="" method="post">
                <input type="hidden" class="delete-id" name="framework_id" value="" />
                <div class="modal-body">
                    <div class="form-group text-center message-container">
                        <label class="message"><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisFramework']); ?></label>
                    </div>
                    <div class="form-group text-center project-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_framework" class="delete_project btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
<div class="modal fade" id="control--delete" tabindex="-1" aria-labelledby="control--delete" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dark">
        <div class="modal-content">
            <form class="" id="control--delete-form" action="" method="post">
                <input type="hidden" class="delete-id" name="control_id" value="" />
                <div class="modal-body">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisControl']); ?></label>
                    </div>
                    <div class="form-group text-center control-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_control" form="control--delete-form" class="delete_control btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="controls--delete" tabindex="-1" aria-labelledby="controls--delete" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dark">
        <div class="modal-content">
            <div class="modal-body">
                <div class="form-group text-center">
                    <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteTheSelectedControls']); ?></label>
                </div>
                <div class="form-group text-center control-delete-actions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="button" id="confirm_delete_controls" class="delete_control btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING CONTROL -->
<div class="modal fade" id="control--add" tabindex="-1" aria-labelledby="control--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $escaper->escapeHtml($lang['NewControl']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-control-form" action="#controls-tab" method="post" autocomplete="off">
    <?php 
                    display_add_control();
    ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" id="add_control" form="add-control-form" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR UPDATING CONTROL -->
<?php
    display_update_control_modal('governance');
?>
<?php
    // Display the add mapping and asset rows
    // These are used in the control add and update modals
    display_add_mapping_row();
    display_add_asset_row();
?>
<script>
    <?php prevent_form_double_submit_script(['framework-delete-form', 'control--delete-form']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>