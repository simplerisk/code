<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['datatables'], ['check_admin' => true]);

$simplerisk_max_upload_size = get_setting('max_upload_size');

$affected_types = [];

if (has_files_with_encoding_issues('risk')) {
    $affected_types[] = 'risk';
}

if (has_files_with_encoding_issues('compliance')) {
    $affected_types[] = 'compliance';
}

if (table_exists('questionnaire_files') && has_files_with_encoding_issues('questionnaire')) {
    $affected_types[] = 'questionnaire';
}

if (empty($affected_types)) {
    refresh("index.php");
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <p><?php echo $escaper->escapeHtml($lang['FixFileEncodingIssuesDisclaimer']); ?></p>
            <h4><?php echo $escaper->escapeHtml($lang['MaximumUploadFileSize'] . ': ' . $simplerisk_max_upload_size . ' ' . $lang['Bytes']); ?>.</h4>
            <?php
                // If the max upload size for SimpleRisk is bigger than the PHP max upload size
                if($simplerisk_max_upload_size > php_max_allowed_values()) {
                    echo "<font style='color: red;'>" . $escaper->escapeHtml($lang['WarnPHPUploadSize']) . '</font><br />';
                }
                // If the max upload size for SimpleRisk is bigger than the MySQL max upload size
                if ($simplerisk_max_upload_size > mysql_max_allowed_values()) {
                    echo "<font style='color: red;'>" . $escaper->escapeHtml($lang['WarnMySQLUploadSize']) . '</font><br />';
                }
            ?>
        </div>
    <?php foreach ($affected_types as $type) { ?>
        <div class="card-body my-2 border">
            <h3><?php echo $escaper->escapeHtml($lang['FileEncodingFixHeader_' . $type]); ?></h3>
            <?php display_file_encoding_issues($type); ?>
        </div>
    <?php } ?>
    </div>
</div>
<style>
    .table-striped tbody > tr > td a {
        text-transform: none;
        text-align: left;
    }

    .table-striped tbody > tr > td a:before {
        margin-right: 5px;
        font: normal normal normal 14px/1 FontAwesome !important;
        content: '\f08e';
    }
</style>

<script>
    function displayFileSize(label, size) {
        if (<?= $escaper->escapeHtml(get_setting('max_upload_size')) ?> > size) {
            label.attr('class','text-success');
        } else {
            label.attr('class','text-danger');
        }

        var iSize = (size / 1024);
        if (iSize / 1024 > 1) {
            if (((iSize / 1024) / 1024) > 1) {
                iSize = (Math.round(((iSize / 1024) / 1024) * 100) / 100);
                label.html('<?= $escaper->escapeHtml($lang['FileSize'] . ': ')  ?>' + iSize + 'Gb');
            } else {
                iSize = (Math.round((iSize / 1024) * 100) / 100)
                label.html('<?= $escaper->escapeHtml($lang['FileSize'] . ': ') ?>' + iSize + 'Mb');
            }
        } else {
            iSize = (Math.round(iSize * 100) / 100)
            label.html('<?= $escaper->escapeHtml($lang['FileSize'] . ': ') ?>' + iSize  + 'kb');
        }
    }

    $(document).ready(function() { 

        var fileAPISupported = typeof $('<input type=\"file\">').get(0).files != 'undefined';

        if (fileAPISupported) {
            $('body').on('keydown paste focus', 'input.readonly', function(e){
                e.preventDefault();
                e.currentTarget.blur();
            });

            $('body').on('click', '.file-uploader input.readonly', function(){
                $(this).parent().find('input[type=file]').trigger('click');
            });

            $('body').on('change', '.file-uploader input[type=file]', function(e){

                if (!e.target.files[0])
                    return;

                var fileName = e.target.files[0].name;
                var fileNameBox = $(this).parent().find('input.readonly');
                fileNameBox.val(fileName);
                fileNameBox.attr('title', fileName);
                displayFileSize($(this).parent().find('span.file-size label'), e.target.files[0].size);
            });
        } else { // If File API is not supported
            $('input.readonly').remove();
            $('#file-upload').prop('required',true);
        }
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>