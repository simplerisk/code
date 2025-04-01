<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['datetimerangepicker', 'easyui:treegrid', 'easyui:filter', 'multiselect', 'tabs:logic', 'CUSTOM:common.js', 'CUSTOM:pages/governance.js', 'datatables'], ['check_governance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));

    checkUploadedFileSizeErrors();

?>
<div class="row bg-white">
    <div class="col-12">
        <!--  Documents container Begin -->
        <div id="documents-tab-content" class="plan-projects tab-data mt-2">
            <div class="status-tabs" >
                <div>
                    <nav class="nav nav-tabs">
    <?php 
        if($_SESSION['add_documentation']) { 
    ?>
                        <a class='btn btn-primary project--add' href='#' id='document-add-btn' role='button'><i class='fa fa-plus'></i></a>
    <?php 
        } 
    ?>
                        <a class="nav-link active" data-bs-target="#document-hierachy-content" data-bs-toggle="tab" data-type="document-hierarchy"><?= $escaper->escapeHtml($lang['DocumentHierarchy']); ?></a>
                        <a class="nav-link" data-bs-target="#policies-content" data-bs-toggle="tab" data-type="policies"><?= $escaper->escapeHtml($lang['Policies']); ?></a>
                        <a class="nav-link" data-bs-target="#guidelines-content" data-bs-toggle="tab" data-type="guidelines"><?= $escaper->escapeHtml($lang['Guidelines']); ?></a>
                        <a class="nav-link" data-bs-target="#standards-content" data-bs-toggle="tab" data-type="standards"><?= $escaper->escapeHtml($lang['Standards']); ?></a>
                        <a class="nav-link" data-bs-target="#procedures-content" data-bs-toggle="tab" data-type="procedures"><?= $escaper->escapeHtml($lang['Procedures']); ?></a>
                    </nav>
                </div>
                <div class="tab-content">
                    <div id="document-hierachy-content" class="tab-pane active custom-treegrid-container card-body border my-2">
    <?php 
                        get_document_hierarchy_tabs() 
    ?>
                    </div>
                    <div id="policies-content" class="tab-pane custom-treegrid-container card-body border my-2">
    <?php 
                        get_document_tabular_tabs("policies") 
    ?>
                    </div>
                    <div id="guidelines-content" class="tab-pane custom-treegrid-container card-body border my-2">
    <?php 
                        get_document_tabular_tabs("guidelines") 
    ?>
                    </div>
                    <div id="standards-content" class="tab-pane custom-treegrid-container card-body border my-2">
    <?php 
                        get_document_tabular_tabs("standards") 
    ?>
                    </div>
                    <div id="procedures-content" class="tab-pane custom-treegrid-container card-body border my-2">
    <?php 
                        get_document_tabular_tabs("procedures") 
    ?>
                    </div>
                </div>
            </div> <!-- status-tabs -->
        </div>
        <!-- Documents container Ends -->
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING DOCUMENT -->
<div id="document-program--add" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="document-program--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['AddDocument']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-document-form" class="" action="#" method="post" autocomplete="off" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['DocumentType']); ?><span class="required">*</span> :</label>
                            <select required class="document_type form-select" name="document_type">
                                <option value="">--</option>
                                <option value="policies"><?= $escaper->escapeHtml($lang['Policies']); ?></option>
                                <option value="guidelines"><?= $escaper->escapeHtml($lang['Guidelines']); ?></option>
                                <option value="standards"><?= $escaper->escapeHtml($lang['Standards']); ?></option>
                                <option value="procedures"><?= $escaper->escapeHtml($lang['Procedures']); ?></option>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['DocumentName']); ?><span class="required">*</span> :</label>
                            <input required type="text" name="document_name" id="document_name" value="" class="form-control" />
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['Frameworks']); ?> :</label>
    <?php 
                            create_multiple_dropdown("frameworks", NULL, "framework_ids"); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['Controls']); ?> :</label>
    <?php  
                            // create_multiple_dropdown("framework_controls", NULL, "control_ids"); 
    ?>
                            <select multiple="multiple" id="control_ids" name="control_ids[]"></select>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?> :</label>
    <?php 
                            create_multiusers_dropdown("additional_stakeholders"); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['DocumentOwner']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "document_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['Team']); ?> :</label>
    <?php 
                            create_multiple_dropdown("team", NULL, "team_ids"); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['CreationDate']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="creation_date" value="<?= $escaper->escapeHtml(date(get_default_date_format())); ?>">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['LastReview']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="last_review_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['ReviewFrequency']); ?> :</label>
                            <div class="input-group">
                                <input type="number" min="0" name="review_frequency" value="0" class="form-control">
                                <span class="input-group-text">(<?= $escaper->escapeHtml($lang['days']); ?>) </span>
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['NextReviewDate']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="next_review_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['ApprovalDate']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="approval_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['Approver']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "approver", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['ParentDocument']); ?> :</label>
                            <div class="parent_documents_container">
                                <select class="form-select">
                                    <option>--</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['DocumentStatus']); ?> :</label>
    <?php 
                            create_dropdown("document_status", "1", "status", false, false, false); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <div class="file-uploader">
                                <label for="" ><?= $escaper->escapeHtml($lang['File']); ?><span class="required">*</span> :</label>
                                <div class="input-group">
                                    <input required type="text" class="form-control readonly" style="width: 50%; margin-bottom: 0px; cursor: default;"/>
                                    <label for="file-upload" class="btn btn-submit m-r-10"><?= $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                    <label class="align-self-center">Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
                                </div>
                                <input type="file" id="file-upload" name="file[]" class="d-none" />
                                <label id="file-size" for="" class="d-none"></label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" form="add-document-form" class="btn btn-submit" id="add_document"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div> 
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR UPDATING DOCUMENT -->
<div id="document-update-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="document-update-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['EditDocument']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="update-document-form" class="" action="#" method="post" autocomplete="off" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label for="" ><?= $escaper->escapeHtml($lang['DocumentType']); ?><span class="required">*</span> :</label>
                            <select required="" class="document_type form-select" name="document_type">
                                <option value="">--</option>
                                <option value="policies"><?= $escaper->escapeHtml($lang['Policies']); ?></option>
                                <option value="guidelines"><?= $escaper->escapeHtml($lang['Guidelines']); ?></option>
                                <option value="standards"><?= $escaper->escapeHtml($lang['Standards']); ?></option>
                                <option value="procedures"><?= $escaper->escapeHtml($lang['Procedures']); ?></option>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['DocumentName']); ?><span class="required">*</span> :</label>
                            <input required="" type="text" name="document_name" id="document_name" value="" class="form-control" />
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['Frameworks']); ?> :</label>
    <?php 
                            create_multiple_dropdown("frameworks", NULL, "framework_ids"); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['Controls']); ?> :</label>
    <?php 
                            // create_multiple_dropdown("framework_controls", NULL, "control_ids"); 
    ?>
                            <select multiple="multiple" id="control_ids" name="control_ids[]" class="form-select"></select>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?> :</label>
    <?php 
                            create_multiusers_dropdown("additional_stakeholders"); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['DocumentOwner']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "document_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['Team']); ?> :</label>
    <?php 
                            create_multiple_dropdown("team", NULL, "team_ids"); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['CreationDate']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="creation_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['LastReview']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="last_review_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['ReviewFrequency']); ?> :</label>
                            <div class="input-group">
                                <input type="number" min="0" name="review_frequency" value="0" class="form-control">
                                <span class="input-group-text">(<?= $escaper->escapeHtml($lang['days']); ?>) </span>
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['NextReviewDate']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="next_review_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['ApprovalDate']); ?> :</label>
                            <input type="text" class="form-control datepicker" name="approval_date">
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['Approver']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "approver", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); 
    ?>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['ParentDocument']); ?> :</label>
                            <div class="parent_documents_container">
                                <select class="form-select">
                                    <option>--</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for=""><?= $escaper->escapeHtml($lang['DocumentStatus']); ?> :</label>
    <?php 
                            create_dropdown("document_status", NULL, "status", false, false, false); 
    ?>
                            <input type="hidden" name="document_id" value="">
                        </div>
                        <div class="col-12 form-group">
                            <div class="file-uploader">
                                <label for=""><?= $escaper->escapeHtml($lang['File']); ?><span class="required">*</span> :</label>
                                <div class="input-group">
                                    <input type="text" class="form-control readonly" style="width: 50%; margin-bottom: 0px; cursor: default;"/>
                                    <label for="file-upload-update" class="btn btn-submit m-r-10"><?= $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                    <label class="align-self-center" size="2">Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
                                </div>
                                <input type="file" id="file-upload-update" name="file[]" class="d-none" />
                                <label id="file-size" for="" class="d-none"></label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="update_document"><?= $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </div>
    </div>
</div>
    
<!-- MODEL WINDOW FOR DOCUMENT DELETE CONFIRM -->
<div class="modal hide" id="document-delete-modal" tabindex="-1" aria-labelledby="document-delete-modal" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <form id="delete-document-form" action="" method="post">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisDocument']); ?></label>
                        <input type="hidden" class="document_id" name="document_id" value="" />
                        <input type="hidden" class="version" name="version" value="" />
                        <input type="hidden" class="document_type" name="document_type" value="" />
                    </div>
                    <div class="form-group text-center control-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_document" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    function displayFileSize(label, size) {

        if (<?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> > size) {

            label.attr("class","text-success");

        } else {

            label.attr("class","text-danger");

        }

        var iSize = (size / 1024);

        if (iSize / 1024 > 1) {

            if (((iSize / 1024) / 1024) > 1) {

                iSize = (Math.round(((iSize / 1024) / 1024) * 100) / 100);
                label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Gb");

            } else {
                iSize = (Math.round((iSize / 1024) * 100) / 100)
                label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Mb");

            }

        } else {

            iSize = (Math.round(iSize * 100) / 100)
            label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize  + "kb");

        }
    }
    
    // Sets controls multiselect options by framework ids
    function sets_controls_by_framework_ids($frameworks, selected_control_ids) {

        $parent = $frameworks.closest('.modal');
        $controls = $parent.find("#control_ids");
        var fids = $frameworks.val();

        if (fids == null) {
            return;
        }

        $.ajax({
            url: BASE_URL + '/api/governance/related_controls_by_framework_ids?fids=' + fids.join(","),
            type: 'GET',
            success : function (res) {
                var options = "";
                for (var key in res.data.control_ids) {
                    var control = res.data.control_ids[key];
                    if (selected_control_ids && selected_control_ids.indexOf('' + control.value) !== -1) {
                        options += "<option value='"+ control.value +"' selected>"+ control.name +"</option>";
                    } else {
                        options += "<option value='"+ control.value +"'>"+ control.name +"</option>";
                    }
                }
                $controls.html(options)
                $controls.multiselect("rebuild")
            }
        });
    }

	// Have to init the treegrid when the tab is first displayed, because it's rendered incorrectly when initialized in the background
    $(document).on('shown.bs.tab', 'nav a[data-bs-toggle=\"tab\"][data-type]', function(e) {
        let type = $(this).data('type');
        $(`#${type}-table`).initAsDocumentProgramTreegrid(type);
    });

    $(document).ready(function() {

        $("body").on("click", "#document-add-btn", function() {
            // reset the form
            $("#document-program--add form").trigger('reset');
            // re-draw the multiselects as they ARE reset, but their texts still display the previous selections
            $('#document-program--add form span.multiselect-native-select select[multiple]').multiselect('updateButtonText');
            $('#document-program--add form span.multiselect-native-select select[multiple]').multiselect('deselectAll', false);
            // remove the options from the parent selector dropdown
            $('div.parent_documents_container select').find('option').remove().end().append('<option value="0">--</option>');

            $('#document-program--add .file-uploader input').val('');
            $('#document-program--add #file-size').html('');
            // show the modal window
            $("#document-program--add").modal("show");
        });

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#document-program--add, #document-update-modal').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });

        // Build multiselect
        $("[name='framework_ids[]'], [name='control_ids[]'], [name='team_ids[]']").multiselect({
            enableFiltering: true,
            enableCaseInsensitiveFiltering: true,
            buttonWidth: '100%',
            maxHeight: 150,
//                dropUp: true,
            onDropdownHide: function(event) {
                // Get related select jquery obj
                $select = $(event.currentTarget).prev();
                
                // If framework is selected, sets control options
                if ($select.attr('id') == "framework_ids") {
                    sets_controls_by_framework_ids($select, []);
                }
            }
        });


        $(".datepicker").initAsDatePicker();

        $("[name='framework_ids[]'], [name='control_ids[]'], [name='additional_stakeholders[]']").multiselect({buttonWidth: '100%'});

        $("#document-program--add .document_type").change(function() {
            $parent = $(this).parents(".modal");
            $.ajax({
                url: BASE_URL + '/api/governance/parent_documents_dropdown?type=' + encodeURI($(this).val()),
                type: 'GET',
                success : function (res) {
                    $(".parent_documents_container", $parent).html(res.data.html)
                }
            });
        });

        $("#document-update-modal .document_type").change(function() {
            $parent = $(this).parents(".modal");
            var document_id = $("[name=document_id]", $parent).val();
            $.ajax({
                url: BASE_URL + '/api/governance/selected_parent_documents_dropdown?type=' + encodeURI($(this).val()) + "&child_id=" + document_id,
                type: 'GET',
                success : function (res) {
                    $(".parent_documents_container", $parent).html(res.data.html)
                }
            });
        });

        $("body").on("click", ".document--edit", function() {
            var document_id = $(this).data("id");
            $("#document-update-modal [name='control_ids[]']").multiselect("deselectAll", false);
            $("#document-update-modal [name='framework_ids[]']").multiselect("deselectAll", false);
            $("#document-update-modal [name='additional_stakeholders[]']").multiselect("deselectAll", false);
            $("#document-update-modal [name='team_ids[]']").multiselect("deselectAll", false);

            $('#document-update-modal .file-uploader input').val('');
            $('#document-update-modal #file-size').html('');

            $.ajax({
                url: BASE_URL + '/api/governance/document?id=' + document_id,
                type: 'GET',
                success : function (res) {
                    var data = res.data;
                    $.ajax({
                        url: BASE_URL + '/api/governance/selected_parent_documents_dropdown?type=' + encodeURI(data.document_type) + '&child_id=' + document_id,
                        type: 'GET',
                        success : function (res){
                            $("#document-update-modal .parent_documents_container").html(res.data.html)
                        }
                    });
                    $("#document-update-modal [name=document_id]").val(data.id);
                    $("#document-update-modal [name=document_type]").val(data.document_type);
                    $("#document-update-modal [name=document_name]").val(data.document_name);
                    $("#document-update-modal [name='framework_ids[]']").multiselect('select', data.framework_ids);
                    sets_controls_by_framework_ids($("#document-update-modal [name='framework_ids[]']"), data.control_ids);
                    $("#document-update-modal [name=creation_date]").val(data.creation_date);
                    $("#document-update-modal [name=last_review_date]").val(data.last_review_date);
                    $("#document-update-modal [name=review_frequency]").val(data.review_frequency);
                    $("#document-update-modal [name=next_review_date]").val(data.next_review_date);
                    $("#document-update-modal [name=approval_date]").val(data.approval_date);
                    $("#document-update-modal [name=status]").val(data.status);
                    $("#document-update-modal [name=document_owner]").val(data.document_owner);
                    $("#document-update-modal [name='additional_stakeholders[]']").multiselect('select', data.additional_stakeholders);
                    $("#document-update-modal [name=approver]").val(data.approver);
                    $("#document-update-modal [name='team_ids[]']").multiselect('select', data.team_ids);
                    if (data.file_name) {
                        $('#document-update-modal .file-uploader input.readonly').val(data.file_name);
                        displayFileSize($("#document-update-modal #file-size"), data.file_size);
                    }
                    $("#document-update-modal").modal("show");
                }
            });

        });

        var fileAPISupported = typeof $("<input type='file'>").get(0).files != "undefined";

        if (fileAPISupported) {
            $("input.readonly").on('keydown paste focus', function(e){
                e.preventDefault();
                e.currentTarget.blur();
            });

            $("#add-document-form input.readonly").click(function(){
                $("#file-upload").trigger("click");
            });

            $("#update-document-form input.readonly").click(function(){
                $("#file-upload-update").trigger("click");
            });

            $('#file-upload').change(function(e){
                if (!e.target.files[0]) {
                    return;
                }

                var fileName = e.target.files[0].name;
                $("#add-document-form input.readonly").val(fileName);

                displayFileSize($("#add-document-form #file-size"), e.target.files[0].size);

            });

            $('#file-upload-update').change(function(e){
                if (!e.target.files[0]) {
                    return;
                }

                var fileName = e.target.files[0].name;
                $("#update-document-form input.readonly").val(fileName);

                displayFileSize($("#update-document-form #file-size"), e.target.files[0].size);

            });

            $("#add-document-form").on('submit', function(event) { 
                event.preventDefault();
                if ($('#file-upload')[0].files[0] && <?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload')[0].files[0].size) {
                    showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                    return false;
                }
                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/documents/create",
                    data: new FormData($('#add-document-form')[0]),
                    async: true,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        $('#document-program--add').modal('hide');
                        $('#add-document-form')[0].reset();
                        $('#add-document-form #file-size').text("");
                        $("#add-document-form [name='framework_ids[]']").multiselect('select', []);
                        $("#add-document-form [name='control_ids[]']").multiselect('select', []);
                        $("#add-document-form [name='additional_stakeholders[]']").multiselect('select', []);
                        $("#add-document-form [name='document_owner[]']").multiselect('select', []);
                        $("#add-document-form [name='team_ids[]']").multiselect('select', []);

                        var tree = $('#document-hierachy-content #document-hierarchy-table');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');

                        var tree = $('#' + data.data.type + '-table');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
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

            $("#update_document").click(function(event) {
                event.preventDefault();
                if ($('#file-upload-update')[0].files[0] && <?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload-update')[0].files[0].size) {
                    showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                    return false;
                }
                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/documents/update",
                    data: new FormData($('#update-document-form')[0]),
                    async: true,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        $('#document-update-modal').modal('hide');
                        $('#update-document-form')[0].reset();
                        $('#update-document-form #file-size').text("");
                        $("#update-document-form [name='framework_ids[]']").multiselect('select', []);
                        $("#update-document-form [name='control_ids[]']").multiselect('select', []);
                        $("#update-document-form [name='additional_stakeholders[]']").multiselect('select', []);
                        $("#update-document-form [name='document_owner[]']").multiselect('select', []);
                        $("#update-document-form [name='team_ids[]']").multiselect('select', []);

                        var tree = $('#document-hierachy-content #document-hierarchy-table');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');

                        var tree = $('#' + data.data.type + '-table');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
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

            // variable which is used to prevent multiple form submissions
            var loading = false;
            $("#delete-document-form").submit(function(event) {
                event.preventDefault();

                // prevent multiple form submissions
                if (loading) {
                    return;
                }

                loading = true;
                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/documents/delete",
                    data: new FormData($('#delete-document-form')[0]),
                    async: true,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        $('#document-delete-modal').modal('hide');

                        // set loading to false to allow form submission
                        loading = false;

                        var tree = $('#document-hierachy-content #document-hierarchy-table');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');

                        var tree = $('#' + data.data.type + '-table');
                        tree.treegrid('options').animate = false;
                        tree.treegrid('reload');
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }

                        // set loading to false to allow form submission
                        loading = false;
                    }
                });
                return false;
            });
        } else { // If File API is not supported
            $("input.readonly").remove();
            $('#file-upload').prop('required',true);
        }

        $("body").on("change keyup", "input[name=review_frequency], input[name=last_review_date]", function(){
            var form = $(this).closest("form");
            var last_review_date = $(form).find("input[name=last_review_date]").val();
            var review_frequency = $(form).find("input[name=review_frequency]").val();
            if(last_review_date != "" && review_frequency != ""){
                var next_review_date = new Date(last_review_date);
                next_review_date.setDate(next_review_date.getDate() + parseInt(review_frequency));
                var next_review_date_str = $.datepicker.formatDate('<?= get_default_date_format_for_datepicker() ?>', next_review_date);
                $(form).find("input[name=next_review_date]").val(next_review_date_str);
            }
            return true;
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>