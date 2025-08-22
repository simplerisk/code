<?php

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
  header("Location: ../../index.php");
  exit(0);
}
// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>
<div class="score-overview-container">
    <div class="overview-container">
    <?php
        include(realpath(__DIR__ . '/overview.php'));
    ?>
    </div>
</div>
<div class="content-container">
    <?php
        if($display_risk == true) {
            include(realpath(__DIR__ . '/details.php'));
        }
    ?>
</div>
<div class="accordion mb-2">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#associated-exceptions-accordion-body'><?= $escaper->escapeHtml($lang['AssociatedExceptions']); ?></button>
        </h2>
        <div id="associated-exceptions-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-12">
                        <div>
                            <nav class="nav nav-tabs">
                                <a data-bs-target="#policy-exceptions" data-bs-toggle="tab" class="nav-link active" data-type="policy"><?php echo $escaper->escapeHtml($lang['PolicyExceptions']); ?> (<span id="policy-exceptions-count">-</span>)</a>
                                <a data-bs-target="#control-exceptions" data-bs-toggle="tab" class="nav-link" data-type="control"><?php echo $escaper->escapeHtml($lang['ControlExceptions']); ?> (<span id="control-exceptions-count">-</span>)</a>
    <?php 
        if (check_permission_exception('approve')) { 
    ?>
                                <a data-bs-target="#unapproved-exceptions" data-bs-toggle="tab" class="nav-link" data-type="unapproved"><?php echo $escaper->escapeHtml($lang['UnapprovedExceptions']); ?> (<span id="unapproved-exceptions-count">-</span>)</a>
    <?php
        } 
    ?>
                            </nav>
                        </div>
                        <div class="tab-content card-body border my-2">
                            <div id="policy-exceptions" class="tab-pane active custom-treegrid-container">
                                <?php get_associated_exception_tabs('policy') ?>
                            </div>
                            <div id="control-exceptions" class="tab-pane custom-treegrid-container">
                                <?php get_associated_exception_tabs('control') ?>
                            </div>
    <?php if (check_permission_exception('approve')) { ?>
                            <div id="unapproved-exceptions" class="tab-pane custom-treegrid-container">
                                <?php get_associated_exception_tabs('unapproved') ?>
                            </div>
    <?php } ?>
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
            $(`#associated-exception-table-${type}`).initAsAssociatedExceptionTreegrid(type);
        });

        function wireActionButtons(tab) {

            //Info + Approve
            $("#"+ tab + "-exceptions span.exception-name > a").click(function(){
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

        }
    </script>
    
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
    <div class="accordion-item comments--wrapper">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#comments-accordion-body'><?= $escaper->escapeHtml($lang['Comments']); ?></button>
        </h2>
        <div id="comments-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="comment-wrapper">
                            <form id="comment" class="comment-form" name="add_comment" method="post" action="/management/comment.php?id=<?php echo $id; ?>">
                                <textarea style="width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;" name="comment" cols="50" rows="3" id="comment-text" class="comment-text form-control"></textarea>
                                <div class="form-actions text-end mt-2" id="comment-div">
                                    <input class="btn btn-dark" id="rest-btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset" />
                                    <button id="comment-submit" type="submit" name="submit" class="comment-submit btn btn-submit" ><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="comments--list clearfix">
                        <?php
                            include(realpath(__DIR__ . '/comments-list.php'));
                        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#audit-trail-accordion-body'><?= $escaper->escapeHtml($lang['AuditTrail']); ?></button>
        </h2>
        <div id="audit-trail-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-12 audit-trail">
                        <?php get_audit_trail_html($id, 36500, ['risk', 'jira']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">