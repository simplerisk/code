<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['chart.js']);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/reporting.php'));

?>
<div class="row">
  <div class="col-md-4">
    <?php open_closed_pie(js_string_escape($lang['OpenVsClosed'])); ?>
  </div>
  <div class="col-md-4">
    <?php open_mitigation_pie(js_string_escape($lang['MitigationPlannedVsUnplanned'])); ?>
  </div>
  <div class="col-md-4">
    <?php open_review_pie(js_string_escape($lang['ReviewedVsUnreviewed'])); ?>
  </div>
</div>
<div class="row mt-2">
    &nbsp;
</div>
<div class="row mt-2">
  <div class="col-md-12">
     <?php risks_by_month_table(); ?>
  </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>