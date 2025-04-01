<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
	* License, v. 2.0. If a copy of the MPL was not distributed with this
	* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Render the header and sidebar
	require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
	render_header_and_sidebar(['blockUI', 'tabs:logic', 'multiselect', 'datetimerangepicker', 'CUSTOM:common.js', 'CUSTOM:pages/plan-project.js', 'JSLocalization'], ['check_riskmanagement' => true], required_localization_keys: ['ThereAreRequiredFields']);
    
?>
<div class="row bg-white">
	<div class="col-12 my-2">
		<div id="tabs1" class="plan-projects">
			<nav class="nav nav-tabs">
	<?php 
		if (isset($_SESSION["add_projects"]) && $_SESSION["add_projects"] == 1) { 
	?>
				<a class="btn btn-primary project-block--add"><i class="fa fa-plus"></i></a>
	<?php 
		}
	?>
				<!-- Check if the  status class is really needed -->
				<a class="nav-link active status" data-bs-target="#active-projects" data-status="1" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['ActiveProjects']); ?> (<span class='project-count'><?= get_projects_count(1) ?></span>)</a>
				<a class="nav-link status" data-bs-target="#on-hold-projects" data-status="2" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['OnHoldProjects']); ?> (<span class='project-count'><?= get_projects_count(2) ?></span>)</a>
				<a class="nav-link status" data-bs-target="#closed-projects" data-status="3" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['CompletedProjects']); ?> (<span class='project-count'><?= get_projects_count(3) ?></span>)</a>
				<a class="nav-link status" data-bs-target="#canceled-projects" data-status="4" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['CanceledProjects']); ?> (<span class='project-count'><?= get_projects_count(4) ?></span>)</a>
			</nav>
			<div class="tab-content">
				<div id="active-projects" class="sortable tab-pane fade show active card-body border mt-2">
	<?php 
					get_project_tabs(1) 
	?>
				</div>
				<div id="on-hold-projects" class="sortable tab-pane fade card-body border mt-2">
	<?php 
					get_project_tabs(2) 
	?>
				</div>
				<div id="closed-projects" class="sortable tab-pane fade card-body border mt-2">
	<?php 
					get_project_tabs(3) 
	?>
				</div>
				<div id="canceled-projects" class="sortable tab-pane fade card-body border mt-2">
	<?php 
					get_project_tabs(4) 
	?>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	var is_draggable = <?= boolean_to_string(isset($_SESSION["manage_projects"]) && $_SESSION["manage_projects"] == 1) ?>;

	$(function() {
		$(".datepicker").initAsDatePicker();
	<?php 
		if (customization_extra()) {
	?>
    	$("select[id^='custom_field'].multiselect").multiselect({buttonWidth: '100%', enableFiltering: true, enableCaseInsensitiveFiltering: true});
	<?php 
		}
	?>
  	});
</script>

<!-- MODEL WINDOW FOR ADDING PROJECT -->
<div class="modal fade" id="project--add" tabindex="-1" aria-labelledby="project--add" aria-hidden="true">
	<div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<form class="" id="project-new" action="#" method="post">
				<div class="modal-header">
					<h5 class="modal-title"><?= $escaper->escapeHtml($lang['NewProject']); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
	<?php 
					display_add_projects();
	?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"  data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
					<button type="submit" name="add_project" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODEL WINDOW FOR EDIT PROJECT -->
<div class="modal fade" id="project--edit" tabindex="-1" aria-labelledby="project--edit" aria-hidden="true">
	<div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<form class="" id="project-edit" action="#" method="post">
				<input type='hidden' name='project_id' value=''>
				<div class="modal-header">
					<h5 class="modal-title"><?= $escaper->escapeHtml($lang['EditProject']); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="form-group">
	<?php 
						display_edit_projects();
	?>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary"  data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
					<button type="submit" name="edit_project" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php  
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>