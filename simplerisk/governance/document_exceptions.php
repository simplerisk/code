<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['easyui:treegrid', 'WYSIWYG', 'multiselect', 'datatables', 'datetimerangepicker', 'CUSTOM:common.js', 'CUSTOM:pages/governance.js', 'tabs:logic'], ['check_governance' => true]);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));

enforce_permission_exception('view');

if(isset($_POST['download_audit_log']))
{
    if(is_admin())
    {
        // If extra is activated, download audit logs
        if (import_export_extra())
        {
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
            download_audit_logs(get_param('post', 'days', 7), 'exception', $escaper->escapeHtml($lang['ExeptionAuditTrailReport']));
        }else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
            refresh();
        }
    }
    // If this is not admin user, disable download
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
        refresh();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display($display = "")
{
    global $lang;
    global $escaper;

    // If import/export extra is enabled and admin user, shows export audit log button
    if (import_export_extra() && is_admin())
    {
        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

        display_audit_download_btn();
    }
}

?>
<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">
<?php
   $risks = get_risks(0, "id", "asc");
?>
<div class="row bg-white">
    <div class="col-12">
        <div id="exceptions-tab-content" class="my-2">
            <div class="status-tabs" >
                <div>
                    <nav class="nav nav-tabs">
        <?php if (check_permission_exception('create')) { ?>
                        <a href="#" title="Settings" role="button" class="btn btn-primary project--add" id="exception-add-btn"><i class="fa fa-plus"></i></a>
        <?php } ?>
                        <a data-bs-target="#policy-exceptions" data-bs-toggle="tab" class="nav-link active" data-type="policy"><?php echo $escaper->escapeHtml($lang['PolicyExceptions']); ?> (<span id="policy-exceptions-count">-</span>)</a>
                        <a data-bs-target="#control-exceptions" data-bs-toggle="tab" class="nav-link" data-type="control"><?php echo $escaper->escapeHtml($lang['ControlExceptions']); ?> (<span id="control-exceptions-count">-</span>)</a>
        <?php if (check_permission_exception('approve')) { ?>
                        <a data-bs-target="#unapproved-exceptions" data-bs-toggle="tab" class="nav-link" data-type="unapproved"><?php echo $escaper->escapeHtml($lang['UnapprovedExceptions']); ?> (<span id="unapproved-exceptions-count">-</span>)</a>
        <?php } ?>
                    </nav>
                </div>
                <div class="tab-content card-body border my-2">
                    <div id="policy-exceptions" class="tab-pane active custom-treegrid-container">
                        <?php get_exception_tabs('policy') ?>
                    </div>
                    <div id="control-exceptions" class="tab-pane custom-treegrid-container">
                        <?php get_exception_tabs('control') ?>
                    </div>
            <?php if (check_permission_exception('approve')) { ?>
                    <div id="unapproved-exceptions" class="tab-pane custom-treegrid-container">
                        <?php get_exception_tabs('unapproved') ?>
                    </div>
            <?php } ?>
                </div>
            </div>
        </div>
        <div class="accordion my-2">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#audit-trail-accordion-body'><span class="d-flex align-items-center"><?= $escaper->escapeHtml($lang['AuditTrail']) ?><a href="#" class="refresh-audit-trail m-l-10"><i class="fa fa-sync"></i></a></span></button>
                </h2>
                <div id='audit-trail-accordion-body' class='accordion-collapse collapse'>
                    <div class='accordion-body'>
                        <div class="row">
                            <div class="col-12 audit-trail">
                                <div class="audit-option-container">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="audit-select-folder">
                                                <select name="days" class="audit-select-days form-select">
                                                    <option value="7" selected >Past Week</option>
                                                    <option value="30">Past Month</option>
                                                    <option value="90">Past Quarter</option>
                                                    <option value="180">Past 6 Months</option>
                                                    <option value="365">Past Year</option>
                                                    <option value="36500">All Time</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-6 text-right">
                                            <?php display(); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="audit-contents mt-2 pt-2 border-top"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>

	// Have to init the treegrid when the tab is first displayed, because it's rendered incorrectly when initialized in the background
    $(document).on('shown.bs.tab', 'nav a[data-bs-toggle=\"tab\"][data-type]', function (e) {
        let type = $(this).data('type');
        $(`#exception-table-${type}`).initAsExceptionTreegrid(type);
    });

    function wireActionButtons(tab) {

        //Edit
        $("#"+ tab + "-exceptions .exception--edit").click(function(){
            var exception_id = $(this).data("id");
            var type = $(this).data("type");
            $("#exception-update-form [name='additional_stakeholders[]']").multiselect('deselectAll', false);
            $("#exception-update-form [name='associated_risks[]']").multiselect('deselectAll', false);
            $("#exception-update-form .file-uploader input").val("");
            $("#exception-update-form #file-size").text("");
            
            $.ajax({
                url: BASE_URL + '/api/exceptions/exception?id=' + exception_id,
                type: 'GET',
                success : function (res){
                    var data = res.data;

                    $("#exception-update-form [name=type]").val(type);

                    $("#exception-update-form [name=exception_id]").val(exception_id);
                    $("#exception-update-form [name=document_exceptions_status]").val(data.document_exceptions_status);
                    $("#exception-update-form [name=name]").val(data.name);
                    $("#exception-update-form [name=policy]").val(data.policy_document_id);
                    $("#exception-update-form [name=framework]").val(data.framework_id);
                    $("#exception-update-form .selected_control_values").val(data.control_framework_id);
                    load_framework_controls($('#exception--update'));
                    $("#exception-update-form [name=owner]").val(data.owner);
                    $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', data.additional_stakeholders);

                    $("#exception-update-form [name='additional_stakeholders[]']").multiselect('updateButtonText');

                    $("#exception-update-form [name='associated_risks[]']").multiselect('select', data.associated_risks);
                    $("#exception-update-form [name=creation_date]").val(data.creation_date);
                    $("#exception-update-form [name=review_frequency]").val(data.review_frequency);
                    $("#exception-update-form [name=next_review_date]").val(data.next_review_date);
                    $("#exception-update-form [name=approval_date]").val(data.approval_date);
                    $("#exception-update-form [name=approver]").val(data.approver);
                    $("#exception-update-form [name=approved_original]").prop('checked', data.approved);
                    $("#exception-update-form [name=description]").val(data.description);
                    $("#exception-update-form [name=justification]").val(data.justification);

                    // set contents into tinymce editor dynamically.
                    tinymce.get('update_description').setContent(data.description);
                    tinymce.get('update_justification').setContent(data.justification);

                    if (data.file_name) {
                        $("#exception-update-form input.readonly").val(data.file_name);
                        displayFileSize($("#exception-update-form #file-size"), data.file_size);
                    }

                    refresh_type_selects_display($('#exception--update'));

                    $("#exception--update").modal('show');
                }
            });
        });

        //Info + Approve
        $("#"+ tab + "-exceptions span.exception-name > a, #"+ tab + "-exceptions a.exception--approve").click(function(){
            event.preventDefault();
            var exception_id = $(this).data("id");
            var type = $(this).data("type");
            var approval = $(this).hasClass("exception--approve");
            
            $.ajax({
                url: BASE_URL + '/api/exceptions/info',
                data: {
                    id: exception_id,
                    type: type,
                    approval: approval
                },
                type: 'GET',
                success : function (res){
                    var data = res.data;

                    $("#exception--view #name").html(data.name);
                    $("#exception--view #type").html(data.type_text);
                    if (data.type == 'policy') {
                        $("#exception--view #policy").html(data.policy_name);
                        $("#exception--view #policy").parent().show();
                        $("#exception--view #framework").parent().hide();
                        $("#exception--view #control").parent().hide();
                    } else {
                        $("#exception--view #framework").html(data.framework_name);
                        $("#exception--view #framework").parent().show();
                        $("#exception--view #control").html(data.control_name);
                        $("#exception--view #control").parent().show();
                        $("#exception--view #policy").parent().hide();
                    }

                    $("#exception--view #document_exceptions_status").html(data.document_exceptions_status);
                    $("#exception--view #owner").html(data.owner);
                    $("#exception--view #additional_stakeholders").html(data.additional_stakeholders);
                    $("#exception--view #associated_risks").html(data.associated_risks);
                    $("#exception--view #creation_date").html(data.creation_date);
                    $("#exception--view #review_frequency").html(data.review_frequency);
                    $("#exception--view #next_review_date").html(data.next_review_date);
                    $("#exception--view #approval_date").html(data.approval_date);
                    $("#exception--view #approver").html(data.approver);
                    $("#exception--view #description").html(data.description);
                    $("#exception--view #justification").html(data.justification);
                    $("#exception--view #file_download").html(data.file_download);

                    if (approval) {
                        $(".approve-footer").show();
                        $(".info-footer").hide();
                        $("#exception-approve-form [name='exception_id']").val(exception_id);
                        $("#exception-approve-form [name='type']").val(type);
                    } else {
                        $(".approve-footer").hide();
                        $(".info-footer").show();
                        $("#exception-approve-form [name='type']").val("");
                    }

                    $("#exception--view").modal('show');
                }
            });
        });

        //Delete
        $("#"+ tab + "-exceptions a.exception--delete").click(function(){
            $("#exception-delete-form [name='exception_id']").val($(this).data("id"));
            $("#exception-delete-form [name='type']").val($(this).data("type"));
            $("#exception-delete-form #approved").prop('checked', $(this).data("approved"));
            $("#exception--delete").modal('show');
        });

        //Batch-delete
        $("#"+ tab + "-exceptions a.exception-batch--delete").click(function(){
            $("#exception-batch-delete-form [name='parent_id']").val($(this).data("id"));
            $("#exception-batch-delete-form [name='type']").val($(this).data("type"));
            $("#exception-batch-delete-form [name='approved']").prop('checked', $(this).data("approved"));
            $("#exception-batch-delete-form #all-approved").prop('checked', $(this).data("all-approved"));
            $("#exception-batch--delete").modal('show');
        });
    }

    //Refresh audit logs if the log section is not collapsed
    // if it is, mark it for refresh on the next time it's opened
    function refreshAuditLogsIfOpen() {
        if (!$(".accordion-header .accordion-button").hasClass(".collapsed")) {
            refreshAuditLogs();
        } else {
            $(".accordion-header .accordion-button").data('need-refresh', true);
        }
    }

    function refreshAuditLogs() {
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/exceptions/audit_log",
            data: {
                days: $('.audit-trail select.audit-select-days').val()
            },
            async: true,
            cache: false,
            success: function(data){
                var div = $("<div>");
                $.each( data.data, function( key, value ) {
                    div.append($("<p>" + value.timestamp + " > " + value.message + "</p>" ));
                });
                $('.audit-trail>div.audit-contents').html(div.html());
                $(".accordion-header .accordion-button").data('need-refresh', false);
            },
            error: function(xhr,status,error){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            }
        });
    }



    function refresh_type_selects_display(root) {

        var policy = root.find('#policy');
        var framework = root.find('#framework');
        var control = root.find('#control');

        if ((policy.val() && policy.val() > 0) || (control.val() && control.val() > 0)) {
            if ((policy.val() && policy.val() > 0)) {
                policy.prop("disabled", false);
                control.prop("disabled", true);
                framework.prop("disabled", true);
            } else {
                control.prop("disabled", false);
                framework.prop("disabled", false);
                policy.prop("disabled", true);
            }
        } else {
            policy.prop("disabled", false);
            control.prop("disabled", false);
            framework.prop("disabled", false);
        }
    }

    function displayFileSize(label, size) {
        if (<?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> > size)
            label.attr("class","text-success");
        else
            label.attr("class","text-danger");

        var iSize = (size / 1024);
        if (iSize / 1024 > 1)
        {
            if (((iSize / 1024) / 1024) > 1)
            {
                iSize = (Math.round(((iSize / 1024) / 1024) * 100) / 100);
                label.html("<?php echo $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Gb");
            }
            else
            {
                iSize = (Math.round((iSize / 1024) * 100) / 100)
                label.html("<?php echo $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Mb");
            }
        }
        else
        {
            iSize = (Math.round(iSize * 100) / 100)
            label.html("<?php echo $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize  + "kb");
        }
    }
    function load_framework_controls(obj){
        var $frameworks = obj.find('#framework');
        var $controls = obj.find('#control');
        var framework_id = $frameworks.val();
        if(framework_id == null) return;
        $.ajax({
            url: BASE_URL + '/api/governance/related_controls_by_framework_ids?fids=' + framework_id,
            type: 'GET',
            success : function (res){
                var options = "<option value='0' selected=''>--</option>";
                var selected_control_ids = obj.find(".selected_control_values").length ?  obj.find(".selected_control_values").val() : "";
                for(var key in res.data.control_ids){
                    var control = res.data.control_ids[key];
                    if(selected_control_ids && selected_control_ids == control.value){
                        options += "<option value='"+ control.value +"' selected>"+ control.name +"</option>";
                    }else{
                        options += "<option value='"+ control.value +"'>"+ control.name +"</option>";
                    }
                }
                $controls.html(options);
            }
        });
    }

     $(document).ready(function(){

        $("#add_exception").click(function(event) {
            event.preventDefault();
            if ($('#file-upload')[0].files[0] && <?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload')[0].files[0].size) {
                toastr.error("<?php echo $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                return false;
            }
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/exceptions/create",
                data: new FormData($('#exception-new-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--add').modal('hide');
                    $('#exception-new-form')[0].reset();
                    $('#exception-new-form #file-size').text("");
                    $("#exception-new-form [name='additional_stakeholders[]']").multiselect('select', []);
                    $("#exception-new-form [name='associated_risks[]']").multiselect('select', []);

                    if (!data.data.approved) {
                        var tree = $('#exception-table-unapproved');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    } else {
                        var tree = $('#exception-table-' + data.data.type);
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    }

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $("#update_exception").click(function(event) {
            event.preventDefault();
            if ($('#file-upload-update')[0].files[0] && <?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload-update')[0].files[0].size) {
                toastr.error("<?php echo $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                return false;
            }

            var old_type = $("#exception-update-form [name=type]").val();

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/exceptions/update",
                data: new FormData($('#exception-update-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--update').modal('hide');
                    $('#exception-update-form')[0].reset();
                    $('#exception-update-form #file-size').text("");
                    $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', []);
                    $("#exception-update-form [name='associated_risks[]']").multiselect('select', []);
                    var tree = $('#exception-table-' + data.data.type);
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');

                    // If exception_update_resets_approval we have to refresh after an update
                    if (<?php if (get_setting('exception_update_resets_approval')) echo "true || ";  ?>!data.data.approved) {
                        var tree = $('#exception-table-unapproved');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    }                    // If type is changed we have to refresh the tab of the old type as well


                    if (data.data.type !== old_type) {
                        var tree = $('#exception-table-' + old_type);
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    }

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $("#exception-approve-form").submit(function(event) {
            event.preventDefault();

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/exceptions/approve",
                data: new FormData($('#exception-approve-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--view').modal('hide');

                    var tree = $('#exception-table-' + $("#exception-approve-form [name='type']").val());
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');

                    tree = $('#exception-table-unapproved');
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $("#exception-delete-form").submit(function(event) {
            event.preventDefault();

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/exceptions/delete",
                data: new FormData($('#exception-delete-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--delete').modal('hide');

                    var tree = $('#exception-table-' + $("#exception-delete-form [name='type']").val());
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');

                    if (!$("#exception-delete-form #approved").prop('checked')) {
                        tree = $('#exception-table-unapproved');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    }

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });
        
        $("#exception-batch-delete-form").submit(function(event) {
            event.preventDefault();
                               
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/exceptions/batch-delete",
                data: new FormData($('#exception-batch-delete-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){                   
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception-batch--delete').modal('hide');

                    var tree = $('#exception-table-' + $("#exception-batch-delete-form [name='type']").val());
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');
                   
                    if (!$("#exception-batch-delete-form #all-approved").prop('checked')) {
                        tree = $('#exception-table-unapproved');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    }

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $('#exception--add').find('#policy, #control').change(function() {refresh_type_selects_display($('#exception--add'));});

        $('#exception--update').find('#policy, #control').change(function() {refresh_type_selects_display($('#exception--update'));});

        $('#exception--add').find('#framework').change(function() {load_framework_controls($('#exception--add'));});
        $('#exception--update').find('#framework').change(function() {load_framework_controls($('#exception--update'));});

        $("[name='additional_stakeholders[]']").multiselect({
            buttonWidth: '100%',
        });
        $("[name='associated_risks[]']").multiselect({
            enableFiltering: true,
            buttonWidth: '100%',
            maxHeight: '400',
        });

        $("[name='approval_date']").initAsDatePicker({maxDate: new Date()});
        $("[name='creation_date']").initAsDatePicker({maxDate: new Date()});
        $("[name='next_review_date']").initAsDatePicker({minDate: new Date()});

        $("#exception-add-btn").click(function () {
            $("#exception--add .file-uploader input").val("");
            $("#exception--add #file-size").text("");
            $('#exception--add').modal('show');
        });

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#exception--add, #exception--update, #exception--view').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
            refresh_type_selects_display($(this));
        });

        $('.accordion-header .accordion-button').click(function(event) {
            event.preventDefault();
            
            if ($(".accordion-header .accordion-button").hasClass("collapsed") && $(".accordion-header .accordion-button").data('need-refresh')) {
                refreshAuditLogs();
            }

        });

        $('.refresh-audit-trail').click(function(event) {
            event.preventDefault();
            refreshAuditLogs();
        });

        $('.audit-trail select.audit-select-days').change(refreshAuditLogs);

        refreshAuditLogs();

        // file upload
        var fileAPISupported = typeof $("<input type='file'>").get(0).files != "undefined";

        if (fileAPISupported) {
            $("input.readonly").on('keydown paste focus', function(e){
                e.preventDefault();
                e.currentTarget.blur();
            });

            $("#exception-new-form input.readonly").click(function(){
                $("#file-upload").trigger("click");
            });

            $("#exception-update-form input.readonly").click(function(){
                $("#file-upload-update").trigger("click");
            });

            $('#file-upload').change(function(e){
                if (!e.target.files[0])
                    return;

                var fileName = e.target.files[0].name;
                $("#exception-new-form input.readonly").val(fileName);

                displayFileSize($("#exception-new-form #file-size"), e.target.files[0].size);

            });

            $('#file-upload-update').change(function(e){
                if (!e.target.files[0])
                    return;

                var fileName = e.target.files[0].name;
                $("#exception-update-form input.readonly").val(fileName);

                displayFileSize($("#exception-update-form #file-size"), e.target.files[0].size);

            });
        } else { // If File API is not supported
            $("input.readonly").remove();
            $('#file-upload').prop('required',true);
        }
        init_minimun_editor("#add_description");
        init_minimun_editor("#add_justification");
        init_minimun_editor("#update_description");
        init_minimun_editor("#update_justification");
    });
</script>
 <!-- MODAL WINDOW FOR ADDING EXCEPTION -->
<?php if (check_permission_exception('create')) { ?>
<div id="exception--add" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ExceptionAdd']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exception-new-form" action="#" method="POST" autocomplete="off">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ExceptionName']); ?>:</label>
                            <input type="text" required name="name" value="" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ExceptionStatus']); ?>:</label>
                            <?php create_dropdown("document_exceptions_status", NULL, "document_exceptions_status", false); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Policy']); ?>:</label>
                            <?php create_dropdown("policies", NULL, "policy", true); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Framework']); ?>:</label>
                            <?php create_dropdown("frameworks", NULL, "framework", true); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Control']); ?>:</label>
                            <select id="control" name="control" class="form-field form-select">
                                <option value="0">--</option>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['AssociatedRisks']); ?>:</label>
                            <select name="associated_risks[]" multiple="true" class="form-select">
                            <?php 
                                foreach ($risks as $risk) {
                                    $risk_id = $risk['id'];
                                    $subject = "(" . ($risk['id'] + 1000) . ") " . $risk['subject'];
                                    echo "<option value='{$risk_id}'>" . $escaper->escapeHTML($subject) . "</option>\n";
                                }

                            ?>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?>:</label>
                            <?php create_dropdown("enabled_users", NULL, "owner", false, false, false); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?>:</label>
                            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['CreationDate']); ?>:</label>
                            <input type="text" name="creation_date" value="<?php echo $escaper->escapeHtml(date(get_default_date_format())); ?>" class="form-control datepicker">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?>:</label>
                            <div class="input-group">
                                <input type="number" min="0" name="review_frequency" value="0" class="form-control">
                                <span class="input-group-text">(<?php echo $escaper->escapeHtml($lang['days']); ?>) </span>
                            </div> 
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?>:</label>
                            <input type="text" name="next_review_date" value="" class="form-control datepicker">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?>:</label>
                            <input type="text" name="approval_date" value="" class="form-control datepicker">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Approver']); ?>:</label>
                            <?php create_dropdown("enabled_users", NULL, "approver", true); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Description']); ?>:</label>
                            <textarea name="description" value="" class="form-control" rows="6"  id="add_description"  style="width:100%;"></textarea>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Justification']); ?>:</label>
                            <textarea name="justification" value="" class="form-control"  id="add_justification" rows="6" style="width:100%;"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="file-uploader">
                                <label for="" ><?php echo $escaper->escapeHtml($lang['File']); ?>:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control readonly"/>
                                    <label for="file-upload" class="btn btn-submit m-r-10"><?php echo $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                    <label class="text-dark align-self-center">Max <?php echo $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
                                </div>
                                <input type="file" id="file-upload" name="file[]" class="d-none" />
                                <label id="file-size" for="" class="d-none"></label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" id="add_exception" class="btn btn-submit"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
            </div> 
        </div>
    </div>
</div>
<?php } ?>

<?php if (check_permission_exception('update')) { ?>
<!-- MODAL WINDOW FOR EDITING EXCEPTION -->
<div id="exception--update" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception--update" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ExceptionUpdate']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exception-update-form" class="" action="#" method="post" autocomplete="off">
                    <input type="hidden" class="exception_id" name="exception_id" value="">
                    <input type="hidden" name="type" value="">
                    <input type="checkbox" name="approved_original" style="display:none;" />
                    <div class="row">
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ExceptionName']); ?>:</label>
                            <input type="text" required name="name" value="" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ExceptionStatus']); ?>:</label>
                            <?php create_dropdown("document_exceptions_status", NULL, "document_exceptions_status", false); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Policy']); ?>:</label>
                            <?php create_dropdown("policies", NULL, "policy", true, false, false, "", "--", "0"); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Framework']); ?>:</label>
                            <?php create_dropdown("frameworks", NULL, "framework", true); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Control']); ?>:</label>
                            <select id="control" name="control" class="form-field form-control">
                                <option value="0">--</option>
                            </select>
                            <input type="hidden" value="" class="selected_control_values">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['AssociatedRisks']); ?>:</label>
                            <select name="associated_risks[]" multiple="true">
                            <?php 
                                foreach ($risks as $risk) {
                                    $risk_id = $risk['id'];
                                    $subject = "(" . ($risk['id'] + 1000) . ") " . $risk['subject'];
                                    echo "<option value='{$risk_id}'>" . $escaper->escapeHTML($subject) . "</option>\n";
                                }
                            ?>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?>:</label>
                            <?php create_dropdown("enabled_users", NULL, "owner", false, false, false); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?>:</label>
                            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['CreationDate']); ?>:</label>
                            <input type="text" name="creation_date" value="" class="form-control datepicker">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?>:</label>
                            <div class="input-group">
                                <input type="number" min="0" name="review_frequency" value="" class="form-control"> 
                                <span class="input-group-text">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span> 
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?>:</label>
                            <input type="text" name="next_review_date" value="" class="form-control datepicker">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?>:</label>
                            <input type="text" name="approval_date" value="" class="form-control datepicker">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Approver']); ?>:</label>
                            <?php create_dropdown("enabled_users", NULL, "approver", true, false, false, "", "--", "0"); ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Description']); ?>:</label>
                            <textarea name="description" value="" class="form-control"  id="update_description" rows="6" style="width:100%;"></textarea>
                        </div>
                        <div class="col-12 form-group" >
                            <label for="" ><?php echo $escaper->escapeHtml($lang['Justification']); ?>:</label>
                            <textarea name="justification" value=""  id="update_justification" class="form-control" rows="6" style="width:100%;"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="file-uploader">
                                <label for="" ><?php echo $escaper->escapeHtml($lang['File']); ?>:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control readonly"/>
                                    <label for="file-upload-update" class="btn btn-submit m-r-10"><?php echo $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                    <label class="text-dark align-self-center">Max <?php echo $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
                                </div>
                                <input type="file" id="file-upload-update" name="file[]" class="d-none" />
                                <label id="file-size" for="" class="d-none"></label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" id="update_exception" class="btn btn-submit"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- MODAL WINDOW FOR DISPLAYING AN EXCEPTION -->
<div id="exception--view" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception--update" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h4 id="name" class="modal-title"></h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['ExceptionType']); ?>:</label>
                <span id="type" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['PolicyName']); ?>:</label>
                <span id="policy" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['FrameworkName']); ?>:</label>
                <span id="framework" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['ControlName']); ?>:</label>
                <span id="control" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['ExceptionStatus']); ?>:</label>
                <span id="document_exceptions_status" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['AssociatedRisks']); ?>:</label>
                <span id="associated_risks" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?>:</label>
                <span id="owner" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?>:</label>
                <span id="additional_stakeholders" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['CreationDate']); ?>:</label>
                <span id="creation_date" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?>:</label>
                <div>
                    <span id="review_frequency" class="exception-data"></span><span style="margin-left: 5px;" class="white-labels"><?php echo $escaper->escapeHtml($lang['days']); ?></span>
                </div>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?>:</label>
                <span id="next_review_date" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?>:</label>
                <span id="approval_date" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['Approver']); ?>:</label>
                <span id="approver" class="exception-data d-block"></span>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['Description']); ?>:</label>
                <div id="description" class="exception-data d-block"></div>
            </div>
            <div class="form-group">
                <label><?php echo $escaper->escapeHtml($lang['Justification']); ?>:</label>
                <div id="justification" class="exception-data d-block"></div>
            </div>
            <div>
                <label><?php echo $escaper->escapeHtml($lang['File']); ?>:</label>
                <div id="file_download" class="exception-data d-block"></div>
            </div>
        </div>
        <div class="modal-footer info-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Close']); ?></button>
        </div>
        <?php if (check_permission_exception('approve')) { ?>
            <div class="modal-footer approve-footer">
                <form class="" id="exception-approve-form" action="" method="post">
                    <input type="hidden" name="exception_id" value="" />
                    <input type="hidden" name="type" value="" />
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" name="approve_exception" class="btn btn-submit"><?php echo $escaper->escapeHtml($lang['Approve']); ?></button>
                </form>
            </div>
        <?php } ?>
    </div>
    </div>
</div>

<?php if (check_permission_exception('delete')) { ?>
<!-- MODAL WINDOW FOR EXCEPTION DELETE CONFIRM -->
<div id="exception--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception--delete" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <form class="" id="exception-delete-form" action="" method="post">
                    <div class="form-group text-center">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisException']); ?></label>
                        <input type="hidden" name="exception_id" value="" />
                        <input type="hidden" name="type" value="" />
                        <input type="checkbox" id="approved" style="display:none;" />
                    </div>

                    <div class="form-group text-center project-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_exception" class="delete_project btn btn-submit"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR EXCEPTION BATCH DELETE CONFIRM -->
<div id="exception-batch--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception-batch-delete-form" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <form class="" id="exception-batch-delete-form" action="" method="post">
                    <div class="form-group text-center">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteTheseExceptions']); ?></label>
                        <input type="hidden" name="parent_id" value="" />
                        <input type="hidden" name="type" value="" />
                        <input type="checkbox" name="approved" style="display:none;" />
                        <input type="checkbox" id="all-approved" style="display:none;" />
                    </div>
                    <div class="form-group text-center project-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_exception" class="delete_project btn btn-submit"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>