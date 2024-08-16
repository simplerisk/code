<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(permissions: ['check_admin' => true]);

?>
<div class="row bg-white">
	<div class="col-12">
		<div class="card-body my-2 border">
			<h4>SimpleRisk Announcements</h4>
			<div class="font-16">
				<?php echo get_announcements(); ?>
			</div>
		</div>
	</div>
</div>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>