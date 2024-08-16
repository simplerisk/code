<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['check_admin' => true]);
	
?>
<div class="row bg-white">
	<div class="col-12">
		<div class="card-body my-2 border">
			<div>
				<p>The use of this software is subject to the terms of the <a class="link text-info" href="http://mozilla.org/MPL/2.0/" target="_blank">Mozilla Public License, v. 2.0</a>.</p>
				<h4>Application Version</h4>
				<ul>
					<li>The latest Application version is <?= $escaper->escapeHtml(latest_version("app")); ?></li>
					<li>You are running Application version <?= $escaper->escapeHtml(current_version("app")); ?></li>
				</ul>
				<h4>Database Version</h4>
				<ul>
					<li>The latest Database version is <?= $escaper->escapeHtml(latest_version("db")); ?></li>
					<li>You are running Database version <?= $escaper->escapeHtml(current_version("db")); ?></li>
				</ul>
				<p>You can download the most recent code <a class="link text-info" href="https://www.simplerisk.com/download" target="_blank">here</a>.</p>
			</div>
		</div>
	</div>
</div>
<?php
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>