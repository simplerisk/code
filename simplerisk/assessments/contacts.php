<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file

    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    require_once(realpath(__DIR__ . '/../includes/assessments.php'));

    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'tabs:logic', 'CUSTOM:common.js', 'CUSTOM:pages/assessment.js'], ['check_assessments' => true], '');

    // Check if assessment extra is enabled
    if (assessments_extra()) {

        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    } else {

        header("Location: ../index.php");
        exit(0);

    }

    // Process actions on contact pages
    if (process_assessment_contact()) {
        refresh();
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
    <?php 
        if (isset($_GET['action']) && $_GET['action']=="add") { 
    ?>
            <div class="hero-unit bg-white">
                <div class="row">
                    <div class="col-6">
    <?php 
                        display_assessment_contacts_add(); 
    ?>
                    </div>
                </div>
            </div>
    <?php
        } elseif (isset($_GET['action']) && $_GET['action']=="edit" && $_GET['id']) { 
    ?>
            <div class="hero-unit bg-white">
                <div class="row">
                    <div class="col-6">
    <?php 
                        display_assessment_contacts_edit($_GET['id']); 
    ?>
                    </div>
                </div>
            </div>
    <?php
        } else { 
    ?>
            <div class="row">
                <div class="col-6">
                    <input type="text" class="form-control" placeholder="Filter by text" id="filter_by_text">
                </div>
            </div>
            <div data-sr-role='dt-settings' data-sr-target='assessment-contacts-table' class='float-end'>
    <?php
            if (has_permission("assessment_add_contact")) {
    ?>
                <a id="assessment-contact--add-btn" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></a>
    <?php
            }
    ?>
            </div>
            <div class="row">
                <div class="col-12">
    <?php 
                    display_assessment_contacts(); 
    ?>
                </div>
            </div>
    <?php
        }
    ?>
        </div>
    </div>
</div>

<!-- MODAL FOR ADDING A NEW CONTACT -->
<div id="assessment-contact--add" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="assessment-contact--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" name="assessment-contact--add-form">
                <div class="modal-header">
                    <h4 class="modal-title"><?= $escaper->escapeHtml($lang['AddNewAssessmentContact']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['Company']);?><span class="required">*</span> :</label>
                        <input required class = "form-control" name="company" maxlength="255" size="100" value="" type="text" title="<?= $escaper->escapeHtml($lang['Company']);?>">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['Name']);?><span class="required">*</span> :</label>
                        <input required class = "form-control" name="name" maxlength="255" size="100" value="" type="text" title="<?= $escaper->escapeHtml($lang['Name']);?>">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['EmailAddress']);?><span class="required">*</span> :</label>
                        <input required class = "form-control" name="email" maxlength="200" size="100" value="" type="email" title="<?= $escaper->escapeHtml($lang['EmailAddress']);?>">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['Phone']);?> :</label>
                        <input class = "form-control" name="phone" maxlength="200" size="100" value="" type="text">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['ContactManager']);?> :</label>
    <?php
                        create_dropdown("enabled_users", NULL, "manager", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
    ?>
                    </div>
                    <div>
                        <label><?= $escaper->escapeHtml($lang['Details']);?> :</label>
                        <textarea class = "form-control" name='details'></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-submit" name="add_contact"><?= $escaper->escapeHtml($lang['Save']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL FOR EDITING A CONTACT -->
<div id="assessment-contact--edit" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="assessment-contact--edit" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" name="assessment-contact--edit-form">
                <input type='hidden' name='id' value=''/>
                <div class="modal-header">
                    <h4 class="modal-title"><?= $escaper->escapeHtml($lang['UpdateAssessmentContact']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['Company']);?><span class="required">*</span> :</label>
                        <input required class = "form-control" name="company" maxlength="255" size="100" value="" type="text" title="<?= $escaper->escapeHtml($lang['Company']);?>">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['Name']);?><span class="required">*</span> :</label>
                        <input required class = "form-control" name="name" maxlength="255" size="100" type="text" value="" title="<?= $escaper->escapeHtml($lang['Name']);?>">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['EmailAddress']);?><span class="required">*</span> :</label>
                        <input required class = "form-control" name="email" maxlength="200" size="100" type="email" value="" title="<?= $escaper->escapeHtml($lang['EmailAddress']);?>">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['Phone']);?> :</label>
                        <input name="phone" class = "form-control" maxlength="200" value="" size="100" type="text">
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['ContactManager']);?> :</label>
    <?php 
                        create_dropdown("enabled_users", null, "manager", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])) 
    ?>
                    </div>
                    <div>
                        <label><?= $escaper->escapeHtml($lang['Details']);?> :</label>
                        <textarea name='details' class='form-control'></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-submit" name="update_contact"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL FOR DELETING A CONTACT -->
<div id='assessment-contact--delete' class='modal fade' aria-labelledby='assessment-contact--delete' tabindex='-1' aria-hidden='true'>
    <div class='modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered'>
        <div class='modal-content'>
            <form class='' action="" method='post' id='assessment-contact--delete-form'>
                <input type='hidden' name='contact_id' value=''/>
                <div class='modal-body'>
                    <div class='form-group text-center'>
                        <label for=''><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisContact']) ?></label>
                    </div>
                    <div class='text-center control-delete-actions'>
                        <button type="button" class='btn btn-secondary' data-bs-dismiss='modal'><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                        <button type='submit' class='btn btn-submit' name='delete_contact'><?= $escaper->escapeHtml($lang['Yes']) ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
        
<script>
    
    let assessment_add_contact_permission = <?= has_permission("assessment_add_contact") ?>;
    let assessment_edit_contact_permission = <?= has_permission("assessment_edit_contact") ?>;

    $(function() {

        // Open a modal for adding a contact
        $('body').on("click", "#assessment-contact--add-btn", function() {

            if (!assessment_add_contact_permission) {
                return toastr.error("<?= $escaper->escapeHtml($lang['NoPermissionForAddAssessmentContacts']) ?>");
            }

            // Reset the form fields
            resetForm("[name='assessment-contact--add-form']");

            $("#assessment-contact--add").modal("show");

        });
        
        // Open a modal for editing a contact
        $('body').on("click", ".assessment-contact--edit-btn", function() {

            if (!assessment_edit_contact_permission) {
                return toastr.error("<?= $escaper->escapeHtml($lang['NoPermissionForThisAction']) ?>");
            }
            
            let contact_id = $(this).data("id");
            $("#assessment-contact--edit [name=id]").val(contact_id);

            $.ajax({
                url: BASE_URL + '/api/assessment/contacts/edit',
                type: 'POST',
                data: {id: contact_id},
                success : function (result){
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }

                    $("#assessment-contact--edit [name='company']").val(result.data.company || '');
                    $("#assessment-contact--edit [name='name']").val(result.data.name || '');
                    $("#assessment-contact--edit [name='email']").val(result.data.email || '');
                    $("#assessment-contact--edit [name='phone']").val(result.data.phone || '');
                    $("#assessment-contact--edit [name='manager']").val(result.data.manager || '');
                    $("#assessment-contact--edit [name='details']").val(result.data.details || '');

                    $("#assessment-contact--edit").modal("show");

                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
            
        });
        
        // Open a modal for deleting a contact
        $('body').on("click", ".assessment-contact--delete-btn", function() {

            $("#assessment-contact--delete [name=contact_id]").val($(this).data("id"));

            $("#assessment-contact--delete").modal("show");
            
        });
        
        $('body').on("submit", "[name=assessment-contact--add-form], [name=assessment-contact--edit-form]", function() {

			// Check empty/trimmed empty valiation for the required fields 
			if (!checkAndSetValidation(this)) {
				event.preventDefault();
			}

            // Phone number validation before submitting the add/edit assessment contact form data
            let phone_regex = /^[+]?[\d\s-]{10,15}$/;
            let input_value = $(this).find("[name=phone]").val();

            // if there is a phone number inputted and it doesn't satisfy the verification
            if (input_value && !phone_regex.test(input_value)) {
                toastr.error("<?= $escaper->escapeHtml($lang['PleaseEnterAValidPhoneNumber']) ?>");
                event.preventDefault();
            }
        });
    });

</script>
<script>
    <?php prevent_form_double_submit_script(['assessment-contact--delete-form']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>