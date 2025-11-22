<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['blockUI', 'selectize', 'multiselect', 'datatables'], ['check_admin' => true]);

?>

    <div class="row bg-white">
        <div class="col-12">
            <div class="card-body border my-2">
                <div class="row">
                    <div class="col-10">
                        <p><strong><?php echo $escaper->escapeHtml($lang['QueueMonitor']); ?></strong></p>
                    </div>
                    <div class="col-2 text-end">
                        <a data-sr-role="dt-settings" data-sr-target="queue-table" href="#" title="Settings" role="button" class="btn btn-dark float-end" data-bs-toggle="modal" data-bs-target="#setting_modal"><i class="fa fa-cog"></i></a>
                    </div>
                </div>
                <?php display_queue_monitor_table(); ?>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->

    <div class="modal fade" id="setting_modal" tabindex="-1" aria-labelledby="setting_modallable" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Column Selections</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="custom_display_settings" name="custom_display_settings" method="post">
                        <?php echo display_custom_queue_monitor_columns("custom_queue_display_settings"); ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="save_display_settings" class="btn btn-submit">Save</button>
                </div>
            </div>
        </div>
    </div>

<?php
render_footer();
?>