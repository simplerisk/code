<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_governance" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

checkUploadedFileSizeErrors();

// Check if a new document was submitted
if (isset($_POST['add_document']))
{
      $document_type = $_POST['document_type'];
      $document_name = $_POST['document_name'];
      $framework_ids = empty($_POST['framework_ids']) ? [] : $_POST['framework_ids'];
      $control_ids   = empty($_POST['control_ids']) ? [] : $_POST['control_ids'];
      $parent        = $_POST['parent'];
      $status        = $_POST['status'];
      $creation_date = get_standard_date_from_default_format($_POST['creation_date']);
      $creation_date = ($creation_date && $creation_date!="0000-00-00") ? $creation_date : date("Y-m-d");
      $last_review_date   = get_standard_date_from_default_format($_POST['last_review_date']);
      $review_frequency = (int)$_POST['review_frequency'];
      $next_review_date   = get_standard_date_from_default_format($_POST['next_review_date']);
      $approval_date   = get_standard_date_from_default_format($_POST['approval_date']);
      $document_owner = (int)$_POST['document_owner'];
      $additional_stakeholders   = empty($_POST['additional_stakeholders']) ? [] : $_POST['additional_stakeholders'];
      $approver = (int)$_POST['approver'];
      $team_ids     = empty($_POST['team_ids']) ? [] : $_POST['team_ids'];

      // Check if the document name is null
      if (!$document_type || !$document_name)
      {
            // Display an alert
            set_alert(true, "bad", "The document name cannot be empty.");
      }
      // Otherwise
      else
      {
            if(empty($_SESSION['add_documentation']))
            {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoAddDocumentationPermission']));
            }
            // Insert a new document
            elseif($errors = add_document($document_type, $document_name, implode(',', $control_ids), implode(',', $framework_ids), $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, implode(',', $additional_stakeholders), $approver, implode(',', $team_ids)))
            {
                // Display an alert
                set_alert(true, "good", $escaper->escapeHtml($lang['DocumentAdded']));
            }
      }
      refresh();
}

// Check if a document was submitted to update
if (isset($_POST['update_document']))
{
      $id            = $_POST['document_id'];
      $document_type = $_POST['document_type'];
      $document_name = $_POST['document_name'];
      $framework_ids = empty($_POST['framework_ids']) ? [] : $_POST['framework_ids'];
      $control_ids   = empty($_POST['control_ids']) ? [] : $_POST['control_ids'];
      $parent        = (int)$_POST['parent'];
      $status        = $_POST['status'];
      $creation_date = get_standard_date_from_default_format($_POST['creation_date']);
      $creation_date = ($creation_date && $creation_date!="0000-00-00") ? $creation_date : date("Y-m-d");
      $last_review_date   = get_standard_date_from_default_format($_POST['last_review_date']);
      $review_frequency = (int)$_POST['review_frequency'];
      $next_review_date   = get_standard_date_from_default_format($_POST['next_review_date']);
      $approval_date   = get_standard_date_from_default_format($_POST['approval_date']);
      $document_owner = (int)$_POST['document_owner'];
      $additional_stakeholders   = empty($_POST['additional_stakeholders']) ? [] : $_POST['additional_stakeholders'];
      $approver = (int)$_POST['approver'];
      $team_ids     = empty($_POST['team_ids']) ? [] : $_POST['team_ids'];

      // Check if the document name is null
      if (!$document_type || !$document_name)
      {
            // Display an alert
            set_alert(true, "bad", "The document name cannot be empty.");
      }
      // Otherwise
      else
      {
            if(empty($_SESSION['modify_documentation']))
            {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyDocumentationPermission']));
            }
            // Update document
            elseif($errors = update_document($id, $document_type, $document_name, implode(',', $control_ids), implode(',', $framework_ids), $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, implode(',', $additional_stakeholders), $approver, implode(',', $team_ids)))
            {
                // Display an alert
                set_alert(true, "good", $escaper->escapeHtml($lang['DocumentUpdated']));
            }
      }
      refresh();
}

// Check if a document was submitted to update
if (isset($_POST['delete_document']))
{
    $id           = $_POST['document_id'];
    $version      = $_POST['version'];
      
    if(empty($_SESSION['delete_documentation']))
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoDeleteDocumentationPermission']));
    }
    // Delete documents
    elseif($errors = delete_document($id, $version))
    {
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['DocumentDeleted']));
    }
    refresh();
}


?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
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
?>
  <script src="../js/jquery.draggable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/jquery.droppable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/treegrid-dnd.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/datagrid-filter.js?<?php echo current_version("app"); ?>"></script>
  <?php display_bootstrap_javascript(); ?>
  <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/pages/governance.js?<?php echo current_version("app"); ?>"></script>

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/easyui.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/prioritize.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
  <?php
      setup_favicon("..");
      setup_alert_requirements("..");
  ?>

  <style>
    button.multiselect {
        max-width: 500px;
        overflow-x: hidden;
    }
  </style>
</head>

<body>

       
  <?php
      view_top_menu("Governance");

      // Get any alert messages
      get_alert();
  ?>

  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_governance_menu("DocumentProgram"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span12">
            <!--  Documents container Begin -->
            <div id="documents-tab-content" class="plan-projects tab-data hide">

              <div class="status-tabs" >

                <?php 
                    if($_SESSION['add_documentation'])
                    {
                        echo "<a href=\"#document-program--add\" id=\"document-add-btn\" role=\"button\" data-toggle=\"modal\" class=\"project--add\"><i class=\"fa fa-plus\"></i></a>";
                    }
                ?>
                

                <ul class="clearfix tabs-nav">
                  <li><a href="#document-hierachy-content" class="status" data-status="1"><?php echo $escaper->escapeHtml($lang['DocumentHierarchy']); ?></a></li>
                  <li><a href="#policies-content" class="status" data-status="2"><?php echo $escaper->escapeHtml($lang['Policies']); ?> </a></li>
                  <li><a href="#guidelines-content" class="status" data-status="2"><?php echo $escaper->escapeHtml($lang['Guidelines']); ?> </a></li>
                  <li><a href="#standards-content" class="status" data-status="2"><?php echo $escaper->escapeHtml($lang['Standards']); ?> </a></li>
                  <li><a href="#procedures-content" class="status" data-status="2"><?php echo $escaper->escapeHtml($lang['Procedures']); ?> </a></li>
                </ul>

                  <div id="document-hierachy-content" class="custom-treegrid-container">
                        <?php get_document_hierarchy_tabs() ?>
                  </div>
                  <div id="policies-content" class="custom-treegrid-container">
                        <?php get_document_tabular_tabs("policies") ?>
                  </div>
                  <div id="guidelines-content" class="custom-treegrid-container">
                        <?php get_document_tabular_tabs("guidelines") ?>
                  </div>
                  <div id="standards-content" class="custom-treegrid-container">
                        <?php get_document_tabular_tabs("standards") ?>
                  </div>
                  <div id="procedures-content" class="custom-treegrid-container">
                        <?php get_document_tabular_tabs("procedures") ?>
                  </div>
              </div> <!-- status-tabs -->

            </div>
            <!-- Documents container Ends -->

            
          </div>
        </div>
      </div>
    </div>
  </div>
          
    <!-- MODEL WINDOW FOR ADDING DOCUMENT -->
    <div id="document-program--add" class="modal hide fade" tabindex="-1" role="dialog">
      <div class="modal-body">
        <form id="add-document-form" class="" action="#" method="post" autocomplete="off" enctype="multipart/form-data">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentType']); ?></label>
            <select required="" class="document_type" name="document_type">
                <option value="">--</option>
                <option value="policies"><?php echo $escaper->escapeHtml($lang['Policies']); ?></option>
                <option value="guidelines"><?php echo $escaper->escapeHtml($lang['Guidelines']); ?></option>
                <option value="standards"><?php echo $escaper->escapeHtml($lang['Standards']); ?></option>
                <option value="procedures"><?php echo $escaper->escapeHtml($lang['Procedures']); ?></option>
            </select>
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentName']); ?></label>
            <input required="" type="text" name="document_name" id="document_name" value="" class="form-control" />
            <label for=""><?php echo $escaper->escapeHtml($lang['Frameworks']); ?></label>
            <?php create_multiple_dropdown("frameworks", NULL, "framework_ids"); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['Controls']); ?></label>
            <?php  // create_multiple_dropdown("framework_controls", NULL, "control_ids"); ?>
            <select multiple="multiple" id="control_ids" name="control_ids[]"></select>
            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?>:</label>
            <?php create_multiusers_dropdown("additional_stakeholders"); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentOwner']); ?>:</label>
            <?php create_dropdown("enabled_users", NULL, "document_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['Team']); ?>:</label>
            <?php create_multiple_dropdown("team", NULL, "team_ids"); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['CreationDate']); ?></label>
            <input type="text" class="form-control datepicker" name="creation_date" value="<?php echo $escaper->escapeHtml(date(get_default_date_format())); ?>">
            <label for=""><?php echo $escaper->escapeHtml($lang['LastReview']); ?></label>
            <input type="text" class="form-control datepicker" name="last_review_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
            <input type="number" min="0" name="review_frequency" value="0" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            <label for=""><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
            <input type="text" class="form-control datepicker" name="next_review_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
            <input type="text" class="form-control datepicker" name="approval_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['Approver']); ?>:</label>
            <?php create_dropdown("enabled_users", NULL, "approver", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['ParentDocument']); ?></label>
            <div class="parent_documents_container">
                <select>
                    <option>--</option>
                </select>
            </div>
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentStatus']); ?></label>
            <?php create_dropdown("document_status", "1", "status", false, false, false); ?>
            <div class="file-uploader">
                <label for=""><?php echo $escaper->escapeHtml($lang['File']); ?></label>
                <input required="" type="text" class="form-control readonly" style="width: 50%; margin-bottom: 0px; cursor: default;"/>
                <label for="file-upload" class="btn"><?php echo $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                <font size="2"><strong>Max <?php echo $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</strong></font>
                <input type="file" id="file-upload" name="file[]" class="hidden-file-upload active" />
                <label id="file-size" for=""></label>
            </div>
          </div>
          <br>
          
          <div class="form-group text-right">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="add_document" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- MODEL WINDOW FOR UPDATING DOCUMENT -->
    <div id="document-update-modal" class="modal hide fade" tabindex="-1" role="dialog">
      <div class="modal-body">
        <form id="update-document-form" class="" action="#" method="post" autocomplete="off" enctype="multipart/form-data">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentType']); ?></label>
            <select required="" class="document_type" name="document_type">
                <option value="">--</option>
                <option value="policies"><?php echo $escaper->escapeHtml($lang['Policies']); ?></option>
                <option value="guidelines"><?php echo $escaper->escapeHtml($lang['Guidelines']); ?></option>
                <option value="standards"><?php echo $escaper->escapeHtml($lang['Standards']); ?></option>
                <option value="procedures"><?php echo $escaper->escapeHtml($lang['Procedures']); ?></option>
            </select>
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentName']); ?></label>
            <input required="" type="text" name="document_name" id="document_name" value="" class="form-control" />
            <label for=""><?php echo $escaper->escapeHtml($lang['Frameworks']); ?></label>
            <?php create_multiple_dropdown("frameworks", NULL, "framework_ids"); ?>
            <input type="hidden" value="" class="selected_control_values">
            <label for=""><?php echo $escaper->escapeHtml($lang['Controls']); ?></label>
            <?php // create_multiple_dropdown("framework_controls", NULL, "control_ids"); ?>
            <select multiple="multiple" id="control_ids" name="control_ids[]"></select>
            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?>:</label>
            <?php create_multiusers_dropdown("additional_stakeholders"); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentOwner']); ?>:</label>
            <?php create_dropdown("enabled_users", NULL, "document_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['Team']); ?>:</label>
            <?php create_multiple_dropdown("team", NULL, "team_ids"); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['CreationDate']); ?></label>
            <input type="text" class="form-control datepicker" name="creation_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['LastReview']); ?></label>
            <input type="text" class="form-control datepicker" name="last_review_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
            <input type="number" min="0" name="review_frequency" value="0" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            <label for=""><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
            <input type="text" class="form-control datepicker" name="next_review_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
            <input type="text" class="form-control datepicker" name="approval_date">
            <label for=""><?php echo $escaper->escapeHtml($lang['Approver']); ?>:</label>
            <?php create_dropdown("enabled_users", NULL, "approver", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0); ?>
            <label for=""><?php echo $escaper->escapeHtml($lang['ParentDocument']); ?></label>
            <div class="parent_documents_container">
                <select>
                    <option>--</option>
                </select>
            </div>
            <label for=""><?php echo $escaper->escapeHtml($lang['DocumentStatus']); ?></label>
            <?php create_dropdown("document_status", NULL, "status", false, false, false); ?>
            <input type="hidden" name="document_id" value="">
            <div class="file-uploader">
                <label for=""><?php echo $escaper->escapeHtml($lang['File']); ?></label>
                <input type="text" class="form-control readonly" style="width: 50%; margin-bottom: 0px; cursor: default;"/>
                <label for="file-upload-update" class="btn"><?php echo $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                <font size="2"><strong>Max <?php echo $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</strong></font>
                <input type="file" id="file-upload-update" name="file[]" class="hidden-file-upload active" />
                <label id="file-size" for=""></label>
            </div>
          </div>
          <br>
          
          <div class="form-group text-right">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="update_document" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- MODEL WINDOW FOR DOCUMENT DELETE CONFIRM -->
    <div id="document-delete-modal" class="modal hide fade" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-body">

        <form action="" method="post">
          <div class="form-group text-center">
            <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisDocument']); ?></label>
            <input type="hidden" class="document_id" name="document_id" value="" />
            <input type="hidden" class="version" name="version" value="" />
          </div>

          <div class="form-group text-center control-delete-actions">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="delete_document" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <?php display_set_default_date_format_script(); ?>

    <script>
        function displayFileSize(label, size) {
            if (<?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> > size)
                label.attr("class","success");
            else
                label.attr("class","danger");

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
        
        // Sets controls multiselect options by framework ids
        function sets_controls_by_framework_ids($frameworks)
        {
            $parent = $frameworks.closest('.modal');
            $controls = $parent.find("#control_ids");
            var fids = $frameworks.val();
            if(fids == null) return;
            $.ajax({
                url: BASE_URL + '/api/governance/related_controls_by_framework_ids?fids=' + fids.join(","),
                type: 'GET',
                success : function (res){
                    var options = "";
                    var selected_control_ids = $parent.find(".selected_control_values").length ?  $parent.find(".selected_control_values").val() : "";
                    for(var key in res.data.control_ids){
                        var control = res.data.control_ids[key];
                        if(selected_control_ids && selected_control_ids.split(",").indexOf(control.value) !== -1){
                            options += "<option value='"+ control.value +"' selected>"+ control.name +"</option>";
                        }else{
                            options += "<option value='"+ control.value +"'>"+ control.name +"</option>";
                        }
                    }
                    $controls.html(options)
                    $controls.multiselect("rebuild")
                }
            });
        }

        // Build multiselect
        $(document).ready(function(){
            $("[name='framework_ids[]'], [name='control_ids[]'], [name='team_ids[]']").multiselect({
                enableFiltering: true,
                enableCaseInsensitiveFiltering: true,
                buttonWidth: '100%',
                maxHeight: 150,
//                dropUp: true,
                onDropdownHide: function(event){
                    // Get related select jquery obj
                    $select = $(event.currentTarget).prev();
                    
                    // If framework is selected, sets control options
                    if($select.attr('id') == "framework_ids"){
                        sets_controls_by_framework_ids($select)
                    }
                }
            });

            var $tabs = $( "#documents-tab-content" ).tabs({
                activate: function(event, ui){
                    $(".document-table").treegrid('resize');
                }
            })
            
            var tabContentId = document.location.hash ? document.location.hash : "#documents-tab";
            tabContentId += "-content";
            $(".tab-show").removeClass("selected");
            
            $(".tab-show[data-content='"+ tabContentId +"']").addClass("selected");
            $(".tab-data").addClass("hide");
            $(tabContentId).removeClass("hide");

            $(".datepicker").datepicker();

            $("[name='framework_ids[]'], [name='control_ids[]'], [name='additional_stakeholders[]']").multiselect({buttonWidth: '100%'});

            $("#document-program--add .document_type").change(function(){
                $parent = $(this).parents(".modal");
                $.ajax({
                    url: BASE_URL + '/api/governance/parent_documents_dropdown?type=' + encodeURI($(this).val()),
                    type: 'GET',
                    success : function (res){
                        $(".parent_documents_container", $parent).html(res.data.html)
                    }
                });
            })
            $(".document-table").treegrid('resize');

            $("#document-update-modal .document_type").change(function(){
                $parent = $(this).parents(".modal");
                var document_id = $("[name=document_id]", $parent).val();
                $.ajax({
                    url: BASE_URL + '/api/governance/selected_parent_documents_dropdown?type=' + encodeURI($(this).val()) + "&child_id=" + document_id,
                    type: 'GET',
                    success : function (res){
                        $(".parent_documents_container", $parent).html(res.data.html)
                    }
                });
            })

            $("body").on("click", ".document--edit", function(){
                var document_id = $(this).data("id");
                $("#document-update-modal [name='control_ids[]']").multiselect("deselectAll", false);
                $("#document-update-modal [name='framework_ids[]']").multiselect("deselectAll", false);
                $("#document-update-modal [name='additional_stakeholders[]']").multiselect("deselectAll", false);
                $.ajax({
                    url: BASE_URL + '/api/governance/document?id=' + document_id,
                    type: 'GET',
                    success : function (res){
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
                        $("#document-update-modal .selected_control_values").val(data.control_ids);
//                        $("#document-update-modal [name='control_ids[]']").multiselect('select', data.control_ids);
                        $("#document-update-modal [name='framework_ids[]']").multiselect('select', data.framework_ids);
                        sets_controls_by_framework_ids($("#document-update-modal [name='framework_ids[]']"));
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
                        $("#document-update-modal").modal();
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
                    if (!e.target.files[0])
                        return;

                    var fileName = e.target.files[0].name;
                    $("#add-document-form input.readonly").val(fileName);

                    displayFileSize($("#add-document-form #file-size"), e.target.files[0].size);

                });

                $('#file-upload-update').change(function(e){
                    if (!e.target.files[0])
                        return;

                    var fileName = e.target.files[0].name;
                    $("#update-document-form input.readonly").val(fileName);

                    displayFileSize($("#update-document-form #file-size"), e.target.files[0].size);

                });

                $("#add-document-form").submit(function(event) {
                    if (<?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload')[0].files[0].size) {
                        toastr.error("<?php echo $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                        event.preventDefault();
                    }
                });

                $("#update-document-form").submit(function(event) {
                    if ($('#file-upload-update')[0].files[0] && <?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload-update')[0].files[0].size) {
                        toastr.error("<?php echo $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                        event.preventDefault();
                    }
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
                    var next_review_date_str = $.datepicker.formatDate(default_date_format, next_review_date);
                    $(form).find("input[name=next_review_date]").val(next_review_date_str);
                }
                return true;
            });
        });
    </script>
    
    <style type="">
        .document--edit, .document--delete{
            cursor: pointer;
        }
    </style>
</body>

</html>
