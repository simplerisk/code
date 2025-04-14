<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
	* License, v. 2.0. If a copy of the MPL was not distributed with this
	* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Render the header and sidebar
	require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
	render_header_and_sidebar(['easyui:treegrid', 'easyui:dnd', 'CUSTOM:selectlist.js', 'CUSTOM:common.js'], ['check_admin' => true]);

	// If the extra directory exists
	if (is_dir(realpath(__DIR__ . '/../extras/organizational_hierarchy'))) {
		// Include the Advanced Search Extra
		require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

		// If the user wants to activate the extra
		if (isset($_POST['activate'])) {
			// Enable the Advanced Search Extra
			enable_organizational_hierarchy_extra();
		}

		// If the user wants to deactivate the extra
		if (isset($_POST['deactivate'])) {
			// Disable the Advanced Search Extra
			disable_organizational_hierarchy_extra();
		}
	}

	/*********************
	 * FUNCTION: DISPLAY *
	 *********************/
	function display() {

		global $lang;
		global $escaper;

		// If the extra directory exists
		if (is_dir(realpath(__DIR__ . '/../extras/organizational_hierarchy'))) {

			// But the extra is not activated
			if (!organizational_hierarchy_extra()) {

				echo "
					<div class='card-body my-2 border'>
				";

				// If the extra is not restricted based on the install type
				if (!restricted_extra("organizational_hierarchy")) {

					echo "
						<form id='activate_extra' name='activate_extra' method='post' action=''>
							<input type='submit' value='{$escaper->escapeHtml($lang['Activate'])}' name='activate' class='btn btn-submit'/>
						</form>
					";

				// The extra is restricted
				} else {
					echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
				}

				echo "
					</div>
				";

			// Once it has been activated
			} else {

				// Include the Organizational Hierarchy Extra
				require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

				echo "
					<div class='card-body my-2 border'>
						<form id='deactivate_extra' name='deactivate' method='post'>
							<font color='green'>
								<b>{$escaper->escapeHtml($lang['Activated'])}</b>
							</font> 
							[" . organizational_hierarchy_version() . "]
							<input type='submit' name='deactivate' value='" . $escaper->escapeHtml($lang['Deactivate']) . "' class='btn btn-dark ms-2'/>
						</form>
					</div>
				";

			}

		// Otherwise, the Extra does not exist
		} else {
			echo "
					<div class='card-body my-2 border'>
						<a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a>
					</div>
			";
		}
	}

?>
<div class="row bg-white"> 
	<div class="col-12">
	<?php 
		display(); 
	?>
	</div>
	<?php 
		if (organizational_hierarchy_extra()) {
	?>
	<div class='organizational-hierarchy-page custom-treegrid-container mb-2'>
		<div class='text-end'>
			<button id='create_business_unit' type='button' class='btn btn-submit mb-2'><?= $escaper->escapeHtml($lang['CreateNewBusinessUnit']); ?></button>
		</div>
		<table id='business_units' class='easyui-treegrid framework-table'>
			<thead>
				<tr>
					<th data-options="field:'name'" width='20%'><?= $escaper->escapeHtml($lang['Name']); ?></th>
					<th data-options="field:'description'" width='70%'><?= $escaper->escapeHtml($lang['Description']); ?></th>
					<th data-options="field:'actions'" width='10%'><?= $escaper->escapeHtml($lang['Actions']); ?></th>
				</tr>
			</thead>
		</table>
	</div>
	<?php
		} 
	?>
</div>

<!-- MODAL WINDOW FOR ADDING BUSINESS UNIT -->
<div id="business-unit--create" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="business-unit--create" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<form id="business-unit-new-form" action="#" method="POST" autocomplete="off">
				<div class="modal-header">
					<h5 class="modal-title"><?= $escaper->escapeHtml($lang['CreateNewBusinessUnit']); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label for=""><?= $escaper->escapeHtml($lang['Name']); ?><span class="required">*</span> :</label>
						<input type="text" required name="name" value="" class="form-control" autocomplete="off" title="<?= $escaper->escapeHtml($lang['Name']); ?>">
					</div>
					<div class="form-group">
						<label for=""><?= $escaper->escapeHtml($lang['Description']); ?> :</label>
						<textarea name="description" class="form-control" rows="6"></textarea>
					</div>
					<div class="select-list-wrapper" >
						<div class="select-list-available">
							<label for=""><?= $escaper->escapeHtml($lang['AvailableTeams']); ?> :</label>
							<select multiple="multiple" class="form-control">
	<?php 
		foreach (get_all_teams() as $team) {
	?>
								<option value="<?= (int)$team['value'];?>"><?= $escaper->escapeHtml($team['name']);?></option>
	<?php
		}
	?>
							</select>
						</div>
						<div class="select-list-arrows text-center">
							<input type='button' value='&gt;&gt;' class="btn btn-secondary btnAllRight" /><br />
							<input type='button' value='&gt;' class="btn btn-secondary btnRight" /><br />
							<input type='button' value='&lt;' class="btn btn-secondary btnLeft" /><br />
							<input type='button' value='&lt;&lt;' class="btn btn-secondary btnAllLeft" />
						</div>
						<div class="select-list-selected">
							<label for=""><?= $escaper->escapeHtml($lang['SelectedTeams']); ?> :</label>
							<select name="selected-business-units" multiple="multiple" class="form-control"></select>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
					<button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Create']); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL WINDOW FOR EDITING AN BUSINESS UNIT -->
<div id="business-unit--update" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<form id="business-unit-update-form" class="" action="#" method="post" autocomplete="off">
				<input type="hidden" class="business_unit_id" name="business_unit_id" value="">
				<div class="modal-header">
					<h5 class="modal-title"><?= $escaper->escapeHtml($lang['BusinessUnitUpdate']); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label for=""><?= $escaper->escapeHtml($lang['Name']); ?><span class="required">*</span> :</label>
						<input type="text" required name="name" value="" class="form-control" autocomplete="off" title="<?= $escaper->escapeHtml($lang['Name']); ?>">
					</div>
					<div class="form-group">
						<label for=""><?= $escaper->escapeHtml($lang['Description']); ?> :</label>
						<textarea name="description" class="form-control" rows="6" style="width:100%;"></textarea>
					</div>
					<div class="select-list-wrapper" >
						<div class="select-list-available">
							<label for=""><?= $escaper->escapeHtml($lang['AvailableTeams']); ?> :</label>
							<select multiple="multiple" class="form-control">
							</select>
						</div>
						<div class="select-list-arrows text-center">
							<input type='button' value='&gt;&gt;' class="btn btn-secondary btnAllRight" /><br />
							<input type='button' value='&gt;' class="btn btn-secondary btnRight" /><br />
							<input type='button' value='&lt;' class="btn btn-secondary btnLeft" /><br />
							<input type='button' value='&lt;&lt;' class="btn btn-secondary btnAllLeft" />
						</div>
						<div class="select-list-selected">
							<label for=""><?= $escaper->escapeHtml($lang['SelectedTeams']); ?> :</label>
							<select name="selected-business-units" multiple="multiple" class="form-control"></select>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
					<button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL WINDOW FOR BUSINESS UNIT DELETE CONFIRM -->
<div id="business-unit--delete" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="business-unit-delete-form" aria-hidden="true">
	<div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<form class="" id="business-unit-delete-form" action="" method="post">
				<input type="hidden" name="business_unit_id" value="" />
				<div class="modal-body">
					<div class="form-group text-center">
						<label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisBusinessUnit']); ?></label>
					</div>
					<div class="form-group text-center">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
						<button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL WINDOW FOR TEAM REMOVAL CONFIRM -->
<div id="team--remove" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="team-remove-form" aria-hidden="true">
	<div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<form class="" id="team-remove-form" action="" method="post">
				<input type="hidden" name="business_unit_id" value="" />
				<input type="hidden" name="team_id" value="" />
				<div class="modal-body">
					<div class="form-group text-center">
						<label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToRemoveThisTeam']); ?></label>
						<input type="hidden" name="business_unit_id" value="" />
						<input type="hidden" name="team_id" value="" />
					</div>
					<div class="form-group text-center">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
						<button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	function refresh_business_unit_menu_items() {
		$.ajax({
			url: BASE_URL + '/api/organizational_hierarchy/business_unit/available_business_unit_menu_items',
			type: 'GET',
			success : function (response) {
				$('li.dropdown-submenu.business-units ul.dropdown-menu').html(response);
			},
			error: function(xhr, status, error) {
				if(!retryCSRF(xhr, this)) {
					if(xhr.responseJSON && xhr.responseJSON.status_message) {
						showAlertsFromArray(xhr.responseJSON.status_message);
					}
				}
			}
		});
	}

	function enableSubmit() {
		// Need this because the double-submit prevention script disables submit buttons on a form submit
		// and since it's an ajax-driven form submit, there's no page reload to 'enable' the submit buttons
		$('[type=\"submit\"]').removeAttr('disabled');
	}

	$(document).ready(function() {
		//Have to remove the 'fade' class for the shown event to work for modals
		$('#business-unit--create, #business-unit--update').on('shown.bs.modal', function() {
			$(this).find('.modal-body').scrollTop(0);
		});

		$('#business_units').treegrid({
			iconCls: 'icon-ok',
			animate: false,
			collapsible: true,
			fitColumns: true,
			url: BASE_URL + '/api/organizational_hierarchy/business_unit/tree',
			method: 'get',
			idField: 'value',
			treeField: 'name',
			scrollbarSize: 0,
			loadFilter: function(data, parentId) {
				return data.data;
			},
			onLoadSuccess: function(row, data){
				//fixTreeGridCollapsableColumn();
				//It's there to be able to have it collapsed on load
				var tree = $('#business_units');
				tree.treegrid('options').animate = false;
				tree.treegrid('collapseAll');
				//tree.treegrid('options').animate = true;

				//$('#business_units').treegrid('resize');
			},
			onLoadError: function(xhr, status, error) {
				if(!retryCSRF(xhr, this)) {
					if(xhr.responseJSON && xhr.responseJSON.status_message) {
						showAlertsFromArray(xhr.responseJSON.status_message);
					}
				}
			}
		});

		// Enable expanding/collapsing by clicking on the business unit's name
		$(document).on('click', '.business-unit-name', function() {
			$('#business_units').treegrid('toggle', $(this).data('id'));
		});

		$('#create_business_unit').click(function(event) {

			event.preventDefault();

			// Reset the form
			resetForm('#business-unit-new-form', false);

			// Move teams back to the available select if they were previously selected
			$('#business-unit-new-form .select-list-arrows .btnAllLeft')[0].click();

			// Show the modal
			$('#business-unit--create').modal('show');

		});

		$('#business-unit-new-form').submit(function(event) {

			event.preventDefault();

			// Check empty/trimmed empty valiation for the required fields 
			if (!checkAndSetValidation(this)) {
				return;
			}

			var data = new FormData($('#business-unit-new-form')[0]);

			//adding the ids of the selected teams to the data sent
			$('#business-unit-new-form .select-list-selected select option').each(function() {
				data.append('selected_teams[]', $(this).val());
			});

			$.ajax({
				type: 'POST',
				url: BASE_URL + '/api/organizational_hierarchy/business_unit/create',
				data: data,
				async: true,
				cache: false,
				contentType: false,
				processData: false,
				success: function(data) {
					if(data.status_message) {
						showAlertsFromArray(data.status_message);
					}

					$('#business-unit--create').modal('hide');

					// Reset the form
					resetForm('#business-unit-new-form', false);

					var tree = $('#business_units');
					tree.treegrid('options').animate = false;
					tree.treegrid('reload');

					refresh_business_unit_menu_items();
				},
				error: function(xhr, status, error) {
					if(!retryCSRF(xhr, this)) {
						if(xhr.responseJSON && xhr.responseJSON.status_message) {
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}
				},
				complete: function(xhr, status) {
					enableSubmit();
				}
			});
			return false;
		});

		$(document).on('click', '.business-unit--update', function() {
			var business_unit_id = $(this).data('id');

			$('#business-unit-update-form .select-list-selected select option').remove();
			$('#business-unit-update-form .select-list-available select option').remove();

			$.ajax({
				url: BASE_URL + '/api/organizational_hierarchy/business_unit?id=' + business_unit_id,
				type: 'GET',
				success : function (response) {
					var data = response.data;

					$('#business-unit-update-form [name=\"business_unit_id\"]').val(business_unit_id);
					$('#business-unit-update-form [name=\"name\"]').val(data.name);
					$('#business-unit-update-form [name=\"description\"]').val(data.description);

					addOptions($('#business-unit-update-form .select-list-selected select'), data.selected_teams);
					addOptions($('#business-unit-update-form .select-list-available select'), data.available_teams);

					$('#business-unit--update').modal('show');
				},
				error: function(xhr, status, error) {
					if(!retryCSRF(xhr, this)) {
						if(xhr.responseJSON && xhr.responseJSON.status_message) {
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}
				}
			});
		});

        // variable which is used to prevent multiple form submissions
        var loading = false;

		$('#business-unit-update-form').submit(function(event) {
			event.preventDefault();

            // prevent multiple form submissions
            if (loading) {
                return;
            }

			// Check empty/trimmed empty valiation for the required fields 
			if (!checkAndSetValidation(this)) {
				return;
			}

			var data = new FormData($('#business-unit-update-form')[0]);

			//adding the ids of the selected teams
			$('#business-unit-update-form .select-list-selected select option').each(function() {
				data.append('selected_teams[]', $(this).val());
			});

            // set the loading to true to prevent form submission
            loading = true;

			$.ajax({
				type: 'POST',
				url: BASE_URL + '/api/organizational_hierarchy/business_unit/update',
				data: data,
				async: true,
				cache: false,
				contentType: false,
				processData: false,
				success: function(data){
					if(data.status_message){
						showAlertsFromArray(data.status_message);
					}

					$('#business-unit--update').modal('hide');

                    // set loading to false to allow form submission
                    loading = false;


					// Reset the form
					resetForm('#business-unit-update-form', false);

					var tree = $('#business_units');
					tree.treegrid('options').animate = false;
					tree.treegrid('reload');

					refresh_business_unit_menu_items();
				},
				error: function(xhr, status, error){
					if(!retryCSRF(xhr, this)) {
						if(xhr.responseJSON && xhr.responseJSON.status_message) {
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}

					// set loading to false to allow form submission
					loading = false;

				},
				complete: function(xhr, status) {
					enableSubmit();
				}
			});

			return false;
		});

		$(document).on('click', '.business-unit--delete', function() {
			$('#business-unit-delete-form [name=\"business_unit_id\"]').val($(this).data('id'));
			$('#business-unit--delete').modal('show');
		});

		// Variable which is used to prevent multiple form submissions
		var loading = false;

		$('#business-unit-delete-form').submit(function(event) {

			// Prevent form submission
			event.preventDefault();

			// Prevent multiple form submissions
			if (loading) {
				return;
			}
			
			var data = new FormData($('#business-unit-delete-form')[0]);

			// Set the loading to true to prevent form submission
			loading = true;

			$.ajax({
				type: 'POST',
				url: BASE_URL + '/api/organizational_hierarchy/business_unit/delete',
				data: data,
				async: true,
				cache: false,
				contentType: false,
				processData: false,
				success: function(data){
					if(data.status_message){
						showAlertsFromArray(data.status_message);
					}

					$('#business-unit--delete').modal('hide');

					// Reset the form
					resetForm('#business-unit-delete-form', false);

					// Set loading to false to allow form submission
					loading = false;

					var tree = $('#business_units');
					tree.treegrid('options').animate = false;
					tree.treegrid('reload');

					refresh_business_unit_menu_items();
				},
				error: function(xhr, status, error){
					if(!retryCSRF(xhr, this)) {
						if(xhr.responseJSON && xhr.responseJSON.status_message) {
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}

					// Set loading to false to allow form submission
					loading = false;

				},
				complete: function(xhr, status) {
					enableSubmit();
				}
			});

			return false;
		});

		$(document).on('click', '.team--remove', function() {
			$('#team-remove-form [name=\"business_unit_id\"]').val($(this).data('business-unit-id'));
			$('#team-remove-form [name=\"team_id\"]').val($(this).data('team-id'));
			$('#team--remove').modal('show');
		});

		$('#team-remove-form').submit(function(event) {
	
			// Prevent form submission
			event.preventDefault();

			// Prevent multiple form submissions
			if (loading) {
				return;
			}

			var data = new FormData($('#team-remove-form')[0]);
			var business_unit_id = $('#team-remove-form [name=\"business_unit_id\"]').val();
			var team_id = $('#team-remove-form [name=\"team_id\"]').val();

			// Set the loading to true to prevent form submission
			loading = true;

			$.ajax({
				type: 'POST',
				url: BASE_URL + '/api/organizational_hierarchy/business_unit/remove-team',
				data: data,
				async: true,
				cache: false,
				contentType: false,
				processData: false,
				success: function(data){
					if(data.status_message){
						showAlertsFromArray(data.status_message);
					}

					$('#team--remove').modal('hide');

					// Reset the form
					resetForm('#team-remove-form', false);

					// Set loading to false to allow form submission
					loading = false;

					//$('tr[node-id=\"' + business_unit_id + '-' + team_id + '\"]').remove();
					var tree = $('#business_units');
					tree.treegrid('remove', business_unit_id + '-' + team_id);
					
					var teamCountWrapper = $('tr[node-id=' + business_unit_id +'] span.team-count');
					var teamCount = parseInt(teamCountWrapper.data('team-count'));
					teamCountWrapper.data('team-count', teamCount-1);
					teamCountWrapper.html(teamCount-1);
				},
				error: function(xhr, status, error){
					if(!retryCSRF(xhr, this)) {
						if(xhr.responseJSON && xhr.responseJSON.status_message){
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}

					// Set loading to false to allow form submission
					loading = false;

				},
				complete: function(xhr, status) {
					enableSubmit();
				}
			});

			return false;
		});
		
	});

</script>
<script>
	<?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra', 'business-unit-delete-form', 'team-remove-form']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>