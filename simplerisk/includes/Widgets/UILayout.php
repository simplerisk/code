<?php

namespace includes\Widgets;

class UILayout {

    /**
     * Randomly generated unique id for the widget. Can be modified before rendering.
     * Type: string
     */
    public string $id;

    /**
     * Name of the layout the widget is created for
     * 
     * Type: string
     */
    private string $layout_name;

    /**
     * Initialize the widget
     * 
     * @param string $layout_name the name of the layout that needs to be rendered 
     */
    public function __construct($layout_name) {
	    $this->id = generate_token(10);
        $this->layout_name = $layout_name;
	}

	/**
	 * Renders the widget.
	 */
    public function render() {
        global $escaper, $lang, $ui_layout_widget_config, $ui_layout_config;
        [$layout, $is_custom, $default_set_by_user] = get_layout_for_user($this->layout_name);
        $is_admin = is_admin();
        $available_custom_widgets = $ui_layout_config[$this->layout_name]['available_custom_widgets'] ?? [];
        $has_custom_widgets = !empty($available_custom_widgets);
?>
<section id="layout_wrapper_<?=$this->id?>" class="gridstack mx-auto">
	<div class="layout_toolbar row align-items-center">
		<div class="col-2 d-flex justify-content-start">
			<select id="widget_selector_<?=$this->id?>" class="form-select show-hide hide">
				<option value='0'><?= $escaper->escapeHtml($lang['SelectWidgetToAdd'])?></option>
<?php if ($has_custom_widgets) { ?>
				<option value='1'><?= $escaper->escapeHtml($lang['CreateCustomWidget'])?></option>
<?php } ?>
			</select>
		</div>
<?php if ($has_custom_widgets) { ?>
		<div class="col-2 d-flex justify-content-start">
			<select id="widget_creator_<?=$this->id?>" class="form-select hide">
				<option value='0'><?= $escaper->escapeHtml($lang['SelectCustomWidgetType'])?></option>
<?php
    foreach ($available_custom_widgets as $custom_widget_name) {
?>
				<option value='<?= $custom_widget_name ?>'><?= $escaper->escapeHtml($lang[$ui_layout_widget_config[$custom_widget_name]['localization_key']])?></option>
<?php
    }
?>
			</select>
		</div>
<?php } ?>
		<div class="col-<?= $has_custom_widgets ? '2' : '3' ?> d-flex justify-content-end">
			<div id="add_widget_<?=$this->id?>" class="new_widget_<?=$this->id?> add_widget show-hide hide d-flex align-items-center border border-2 rounded-4 cursor-pointer bg-success-subtle grid-stack-item p-2 fs-6 text disabled prevent-select">
            	<i class="fa-regular fa-hand fa-lg"></i>
            	<div class="text-center text-nowrap text-success ps-1">
            		<?= $escaper->escapeHtml($lang['DragToAddSelectedWidget'])?>
            	</div>
          	</div>
		</div>
		<div class="col-<?= $has_custom_widgets ? '2' : '3' ?> d-flex justify-content-start">
			<div id="trash_<?=$this->id?>" class="delete-widget show-hide hide d-flex align-items-center border border-2 rounded-4 cursor-pointer bg-danger-subtle grid-stack-item p-2 fs-6 text prevent-select">
            	<i class="fa-regular fa-trash-can fa-lg"></i>
            	<div class="text-center text-nowrap text-danger ps-1">
            		<?= $escaper->escapeHtml($lang['DropHereToRemoveWidget'])?>
            	</div>
          	</div>
		</div>
		<div class="col-3 d-flex align-items-center justify-content-center">
			<div class="restore-layout-widget show-hide hide d-flex align-items-center">
				<button type="button" class="btn btn-primary" data-sr-restore="default"<?= $is_custom ? '' : ' disabled' ?>><?= $escaper->escapeHtml($lang['RestoreDefaultLayout'])?></button>
				<button type="button" class="btn btn-primary m-1" data-sr-restore="saved" disabled><?= $escaper->escapeHtml($lang['RestoreSavedLayout'])?></button>
            </div>
		</div>
		<div class="col-1 d-flex justify-content-between">
			<div class="d-flex align-items-center justify-content-center">
				<button type="button" class="show-hide hide btn btn-success" disabled id="save_layout_<?=$this->id?>"><?= $escaper->escapeHtml($lang['Save'])?></button>
          	</div>
			<div class="settings">
				<div class="dropdown">
					<a class="btn btn-primary float-end waves-effect waves-light" title="Settings" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="mdi mdi-menu"></i></a>
  					<div class="edit-mode-dropdown dropdown-menu">
  						<div class="dropdown-header"><?= $escaper->escapeHtml($lang['EditMode'])?><i class="fas fa-info-circle me-1 ms-1" title="<?= $escaper->escapeHtml($lang['EditModeInformation'])?>"></i></div>
  						<div class="switch-widget">
        					<label for="edit_mode_<?=$this->id?>" class="off d-inline-block me-1"><?= $escaper->escapeHtml($lang['Off'])?></label>
        					<div class="form-check form-switch d-inline-block">
            					<input type="checkbox" class="form-check-input" id="edit_mode_<?=$this->id?>">
            					<label for="edit_mode_<?=$this->id?>" class="form-check-label on"><?= $escaper->escapeHtml($lang['On'])?></label>
        					</div>
    					</div>
<?php if ($is_admin) { ?>
						<div class="d-flex align-items-center flex-column default-layout-widget">
        					<hr class="dropdown-divider" />
        					<div class="dropdown-header"><?= $escaper->escapeHtml($lang['DefaultLayout'])?><i class="fas fa-info-circle me-1 ms-1" title="<?= $escaper->escapeHtml($lang['DefaultLayoutInformation'])?>"></i></div>
                          	<div class="switch-widget">
            					<label for="default_layout_<?=$this->id?>" class="off d-inline-block me-1"><?= $escaper->escapeHtml($lang['Off'])?></label>
            					<div class="form-check form-switch d-inline-block">
                					<input type="checkbox" class="form-check-input" id="default_layout_<?=$this->id?>"<?= $default_set_by_user ? ' checked' : '' ?><?= $is_custom ? '' : ' disabled' ?>>
                					<label for="default_layout_<?=$this->id?>" class="form-check-label on"><?= $escaper->escapeHtml($lang['On'])?></label>
            					</div>
            				</div>
        				</div>
<?php } ?>
  					</div>
				</div>
			</div>
		</div>
	</div>
    <div class="layout_panel rounded-4 p-1 mt-8">
		<div class="grid-stack" id="layout_<?=$this->id?>"></div>
    </div>
</section>
<script type="text/javascript">
	// Configurations of the widgets that may appear for this layout
	var widget_configurations_<?=$this->id?> = new Map(Object.entries(<?= json_encode(get_widget_configuration_for_layout_name($this->layout_name)) ?>));

<?php if ($has_custom_widgets) { ?>
    var custom_widget_configurations_<?=$this->id?> = new Map(Object.entries(<?= json_encode(get_widget_configuration_for_layout_name($this->layout_name, true)) ?>));
    var has_custom_widgets_<?=$this->id?> = <?= boolean_to_string($has_custom_widgets) ?>;
<?php } ?>
	// Storing the layout instance so it can be accessed easily 
	var layout_<?=$this->id?>;

	// It's used for dual purposes(kinda'). Storing initially whether the displayed layout is the default or a custom one. Later on the page this variable is
	// used for the same purpose, but by the UI logic. Setting to false when the default layout is restored and set to true when a new custom layout is saved.
	var is_customized_layout_<?=$this->id?> = <?= boolean_to_string($is_custom) ?>;

	// Tracks whether the user made changes to the layout  without saving it, so we can properly display a warning
	// when they want to leave the page(and don't bother them with the warning if there're no unsaved changes) 
	var has_unsaved_changes_<?=$this->id?> = false;

	// Refresh the widget selector dropdown. It's called when needing a refresh after a widget is
	//	1, added/removed
	//	2, a full layout is dynamically loaded
	//	3, on the initial page load
	function refresh_widget_selector_<?=$this->id?>(event = null) {

		// Store the dropdown's value so we can restore it later if needed
		// need it for a better user experience(not losing selection when deleting a widget when there's one already selected for adding)
		let selected_widget = widget_selector_<?=$this->id?>.value;

		// Remove all the dynamic options
		$("#widget_selector_<?=$this->id?> option[value!=0][value!=1]").remove();

		// Get the layout so we can gather what widgets are added to the layout
		let layout = layout_<?=$this->id?>.save(false), widgets_in_use = [];
        layout.forEach(function(widget, index) {
			widgets_in_use.push(widget.name);
        });

		// Add the options for those widgets that aren't added to the layout		
        widget_configurations_<?=$this->id?>.forEach(function(config, key) {
          	if (!widgets_in_use.includes(config.name)) {
          		$('<option>').text('[' + _lang[`WidgetType_${config.type}`] + `] ${config.localization}`).val(config.name).appendTo("#widget_selector_<?=$this->id?>");
          	}
        });

		// Keep the original selection whenever we can
		$('#widget_selector_<?=$this->id?>').val(event == null || event.type == 'added' ? 0 : selected_widget).trigger('change');
		
		// Reset the widget creator selection
		$('#widget_creator_<?=$this->id?>').val(0);
	}

	// Toggle edit mode on/off
	function editMode_<?=$this->id?>(enabled) {
		if (enabled) { // enable
			$('#layout_wrapper_<?=$this->id?> .layout_toolbar .show-hide').removeClass('hide');
			layout_<?=$this->id?>.setStatic(false);
		} else { // disable
			$('#layout_wrapper_<?=$this->id?> .layout_toolbar .show-hide').addClass('hide');
			layout_<?=$this->id?>.setStatic(true);
		}
	}

	// Save the layout
	function save_layout_<?=$this->id?>() {
	
		// Getting the layout without the content, because we're not storing that as it's dynamically built every time the widgets are rendered
		let layout = layout_<?=$this->id?>.save(false);
        
        // Store whether there's a custom widget thats content overflows
        // Not used for now
        // let custom_widget_overflows = false;
        
        // set the custom widgets' data
        // since they're customizable, so we're refreshing the data before sending it to the server
        for(let widget of layout) {
        	if (widget.hasOwnProperty('custom') && widget.custom && widget.type == 'editable_widget') {
        		
        		// Get the actual widget
        		let widget_el = $(`div.grid-stack-item-content.${widget.type}[data-widget-id=${widget.id}]`);

				// Detecting if there's any widgets that are smaller that their content
				// Not fully implemented yet
				/*let widget_content_node = widget_el.find(`div.custom-${widget.name}-content`)[0];
				if (widget_content_node.scrollHeight > widget_content_node.offsetHeight) {
                	custom_widget_overflows = true;
                }*/

				// Get the widget data based on the widget's name
				switch(widget.name) {
					case 'WYSIWYG':
						widget.data = widget_el.find('textarea').val();
					break;
					default:
				}
        	}
        }

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/ui/layout",
            data: {
            	layout_name: '<?= $this->layout_name ?>',
            	layout: layout
        	},
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }

                // Setting these true, because an admin user can't set their layout as the default until it's saved to become a custom layout
                is_customized_layout_<?=$this->id?> = true;

<?php if ($is_admin) { ?>
                $('#default_layout_<?=$this->id?>').prop("disabled", false);
<?php } ?>
                // Makes no sense to be able to restore to the saved layout as we just saved it. Making a change will enable that button
                $('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="saved"]').prop("disabled", true);

                // Disable the save button as we just saved
                $('#save_layout_<?=$this->id?>').prop("disabled", true);

				// Since it was just saved, we have no pending changes
                has_unsaved_changes_<?=$this->id?> = false;
            },
            error: function(xhr,status,error){
                if(!retryCSRF(xhr, this)) {
                	showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
	}
	
	// Restore the layout either to its previously saved state or to the default
    function restore_layout_<?=$this->id?>(type) {
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/ui/layout?type=" + type + "&layout_name=<?= $this->layout_name ?>",
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }

				// Remove the widgets currently added
                layout_<?=$this->id?>.removeAll();
                // Load the widgets defined on the restored layout 
				layout_<?=$this->id?>.load(JSON.parse(result.data));
				
				// Turn off edit mode after successfully restoring the layout 
				$('#edit_mode_<?=$this->id?>').prop('checked', false);
				editMode_<?=$this->id?>(false);
				
				// Since it was just restored, we have no pending changes
				has_unsaved_changes_<?=$this->id?> = false;
				
                // disable the save button as we just restored a layout, there're no changes need to be saved
                $('#save_layout_<?=$this->id?>').prop("disabled", true);
				
				// if we restored the default layout
				if (type == 'default') {
	                // disable both buttons as we have nothing to restore further
                	$('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="default"]').prop("disabled", true);
                	$('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="saved"]').prop("disabled", true);
                	
                	// We restored the default layout, it's not 'custom' anymore
                	is_customized_layout_<?=$this->id?> = false;
                	// neither it is the 'default' (because if a layout that's marked as default )
                	$('#default_layout_<?=$this->id?>').prop("disabled", true);
                	$('#default_layout_<?=$this->id?>').prop("checked", false);
				} else {
	                // if we restored the saved layout disable only the button for the 'saved' layout as it makes no sense to restore that again
	                // but leave the 'default' restore button enabled so we can still restore that layout
                	$('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="default"]').prop("disabled", false);
                	$('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="saved"]').prop("disabled", true);
				}
            },
            error: function(xhr,status,error){
                if(!retryCSRF(xhr, this)) {
                	showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

	// Run this logic on every relevant event that's related to anything changing on the layout
	// This logic is responsible for enabling the restoring/saving of the layout if it changed
    function refresh_buttons_on_layout_change_<?=$this->id?>(event, items) {

		// Things changed, so now there's something to save
		has_unsaved_changes_<?=$this->id?> = true;

		// Enable the save button
        $('#save_layout_<?=$this->id?>').prop("disabled", false);
        
        // Enable the restore buttons once there's a changed layout to restore from
    	// but only enable the 'Restore saved layout' button if there's a custom layout saved already so there's something to restore to
    	if (is_customized_layout_<?=$this->id?>) {
    		$('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="saved"]').prop("disabled", false);
    	}
    	
    	// Always able to restore to the default layout
    	$('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="default"]').prop("disabled", false);
    }


	$(function() {

		// Need to set a custom ID for custom widgets because they don't get one on their own
		let id_counter_<?=$this->id?> = 0;

		// Called when an item is added to the grid
		GridStack.renderCB = function(el, w) {

			//console.log('renderCB', w, el, $(el), $(el).closest('section'));

			// Store these, so we don't have to calculate them more than once			
			let custom = w.hasOwnProperty('custom') && w.custom;
			let custom_new = custom && w.hasOwnProperty('new') && w.new;

			// For now custom widgets are fully handled here, but later if we have widgets thats data needs to be loaded, because it's not included IN the saved layout
			// we'll have to have different logic for them
			if (custom) {
				
				// Add the edit button
				let html = `<a class='edit' title='<?= $escaper->escapeHtml($lang['EditWidgetText']) ?>'><i class="fa-solid fa-pen-to-square"></i></a>`;
				
				// Add the content based on the widget's name
				switch(w.name) {
					case 'WYSIWYG':
        				// We can set this as data is sanitized on the server side
                        html += `
                        	<textarea class='hide'>${w.data}</textarea>
                        	<div class='custom-${w.name}-content'>${w.data}</div>
                    	`;
                        
					break;
					
					default:
						// nothing ATM
				}
				
				// We can safely set it as html because it's sanitized on the server side
                $(el).html(html);

	            // Setting the widget's type as a class on the container, so we can apply type-specific css
                $(el).addClass(w.type);

				// Set the widget's ID
				// non-custom widgets get an ID generated automatically in the _id field
				// but we have to generate IDs manually for custom ones
				if (custom_new) {
					w.id = w._id;
                } else {
                	w.id = ++id_counter_<?=$this->id?>;
                }

				// Add the widget's generated id so we can identify which layout element is for which widget data                
                $(el).attr('data-widget-id', w.id);
			} else {
			
				let layout_name = w.layout;

    			// If the layout name is not set, then we need to set it to the default layout name
    			// This line should not trigger EVER since the layout name should be set on the widget
    			if (!layout_name) {
    				layout_name = '<?= $this->layout_name ?>';
    			}
			
    			// Dynamically load the content of the widget based on its configuration
                $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/v2/ui/widget?widget_name=" + w.name + "&layout_name=" + layout_name
					+ `
	<?php 
		// Need to pass the teams parameter if it's set, so we can load the correct data for the widget
		// This is needed for the 'dashboard_open' and 'dashboard_close' widgets to load the correct data
		if (isset($_GET['teams'])) { 
			echo "&teams={$_GET['teams']}"; 
		} else { 
			echo ""; 
		} 
	?>
	`
	,
                    success: function(result){
                        if(result.status_message) {
                            showAlertsFromArray(result.status_message);
                        }
    
    					if (w.hasOwnProperty('custom') && w.custom === true) {
    						// Put logic here for custom widgets if the data isn't saved in the layout itself, but loaded from the server
    					}

    					// We can set this as data is sanitized on the server side
                        $(el).html(result.data);
    
                        // Setting the widget's type as a class on the container, so we can apply type-specific css
                        $(el).addClass(w.type);
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this)) {
                        	showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                });
            }
      	};

		// Setup Grids without jQuery
		layout_<?=$this->id?> = GridStack.init(
			{
            	minRow: 1,
            	acceptWidgets: '.new_widget_<?=$this->id?>',
            	removable: '#trash_<?=$this->id?>',
            	removeTimeout: 100,
            	children: <?= $layout; ?>,
			},
			'#layout_<?=$this->id?>'
        );
        
        // The layout isn't editable when the page loads
		layout_<?=$this->id?>.setStatic(true);

        // Run this logic on every relevant event that's related to anything changing on the layout
        layout_<?=$this->id?>.on('added change removed', refresh_buttons_on_layout_change_<?=$this->id?>);

		// Whenever a widget is added/removed the widget selector dropdown needs to be updated		
		layout_<?=$this->id?>.on('added removed', function(event, items) {
			refresh_widget_selector_<?=$this->id?>({type: event.type, widget_name: items[0].name});
        });
		
		// Whenever a widget is removed there might be some cleanup needed		
		layout_<?=$this->id?>.on('removed', function(event, items) {
			// console.log(event, items);
        });
		
		// Logic related to enabling/disabling edit mode		
		$(document).on('change', '#edit_mode_<?=$this->id?>', function(e) {

			// Always let it be enabled
			if (this.checked) {
				editMode_<?=$this->id?>(true);
			} else {
				// but on disabling edit mode check if there're any pending changes
				if (has_unsaved_changes_<?=$this->id?>) {
					
					// if there are then don't let it be unchecked
					$('#edit_mode_<?=$this->id?>').prop('checked', true);
				
					// then confirm that the user really want to disable edit mode without saving
    				confirm('<?= $escaper->escapeHtml($lang['ConfirmDisableEditModeWithPendingChanges'])?>', () => {
    					// if the user chose yes, THEN disable the edit mode
						$('#edit_mode_<?=$this->id?>').prop('checked', false);
						editMode_<?=$this->id?>(false);
    				});
				} else {
					// If there're no pending changes, then just disable edit mode
					editMode_<?=$this->id?>(false);
				}
			}
		});

		// Logic related to saving
    	$(document).on('click', '#save_layout_<?=$this->id?>', function(e) {e.stopPropagation();

    		// Only warn about saving if it'd overwrite a previously saved custom layout
    		if (is_customized_layout_<?=$this->id?>) {
    		
    			var confirmSaveQuestion = '<?= $escaper->escapeHtml($lang['ConfirmSave'])?>';

<?php if ($is_admin) { ?>
				// Warn the admin user that the layout is set as default layout and changing it will affect other users
				if ($("#default_layout_<?=$this->id?>").is(':checked')) {
					confirmSaveQuestion = '<?= $escaper->escapeHtml($lang['ConfirmSaveAdminDefault'])?>';
				}
<?php } ?>
            	confirm(confirmSaveQuestion, () => {
        			save_layout_<?=$this->id?>();
            	});
        	} else {
        		save_layout_<?=$this->id?>();
        	}
    	});

        // Handle the widget selector dropdown's changes
    	$(document).on('change', '#widget_selector_<?=$this->id?>', function(e) {
    		switch(this.value) {
    			case '0':
    				// Disable the new widget drag-in area if there's no actual widget selected
    				$('#add_widget_<?=$this->id?>').addClass('disabled');
    				
    				// Hide the custom widget type selector
    				$('#widget_creator_<?=$this->id?>').addClass('hide');
    			break;
    			case '1':
    				// Disable the new widget drag-in area if there's no actual widget selected
    				$('#add_widget_<?=$this->id?>').addClass('disabled');
    				
    				// Show the custom widget type selector
    				$('#widget_creator_<?=$this->id?>').removeClass('hide');
    			break;
    			default:
    				// Hide the custom widget type selector
    				$('#widget_creator_<?=$this->id?>').addClass('hide');
    			
        			// Get the widget configuration by the widget's name
            		var config = widget_configurations_<?=$this->id?>.get(this.value);
    
            		// Have to add some dummy content to the configuration as the renderCB callback is not triggering if it's not there...
            		config.content = 'Failed to load widget';
            		
            		// Setup the new widget drag-in area with the selected widget's information then enable it
                	GridStack.setupDragIn('#add_widget_<?=$this->id?>', undefined, [config]);
            		$('#add_widget_<?=$this->id?>').removeClass('disabled');
    		}
		});
        
<?php if ($has_custom_widgets) { ?>
        // Handle the custom widget creator dropdown's changes
    	$(document).on('change', '#widget_creator_<?=$this->id?>', function(e) {
    		switch(this.value) {
    			case '0':
    				// Disable the new widget drag-in area if there's no actual widget selected
    				$('#add_widget_<?=$this->id?>').addClass('disabled');
    			break;
    			default:
        			// Get the widget configuration by the widget's name
            		var config = custom_widget_configurations_<?=$this->id?>.get(this.value);
    
            		// Have to add some dummy content to the configuration as the renderCB callback is not triggering if it's not there...
            		config.content = 'Failed to load widget';
            		
            		config.new = true;
            		
            		// Setup the new widget drag-in area with the selected widget's information then enable it
                	GridStack.setupDragIn('#add_widget_<?=$this->id?>', undefined, [config]);
            		$('#add_widget_<?=$this->id?>').removeClass('disabled');
    		}
		});
		
        // Handle the event when the editable widget's edit button is clicked 
    	$(document).on('click', '#layout_wrapper_<?=$this->id?> .grid-stack-item:not(.ui-resizable-autohide) .edit', function(e) {

    		// Gather the data from the source widget 
    		let widget = $(this).closest('.grid-stack-item-content');
    		let widget_id = widget.attr('data-widget-id');
    		let content = widget.find('textarea').val();
    		
    		// Store a reference to the modal window to be used later
    		let modal = $('#edit_WYSIWYG_modal_<?=$this->id?>');

    		// Set the Widget ID to store the id of the widget that's being edited  
    		modal.find('input.source').val(widget_id);
    		
    		// Set the data of the widget we want to edit
    		modal.find('textarea').val(content);

    		// Initialize the editor
            init_minimun_editor('#edit_WYSIWYG_modal_<?=$this->id?>_textarea');
                
    		// Show the modal window
    		modal.modal('show');
		});
		
        // Handle the WYSIWYG edit modal's save button click
    	$(document).on('click', '#edit_WYSIWYG_modal_<?=$this->id?> button[type=submit]', function(e) {

			// Get the modal window
    		let modal = $(this).closest('div.modal');
    		
			// Transfer the data back to the widget
    		let source = modal.find('input.source').val();
    		let content = modal.find('textarea').val();
			
			// Get the source widget
			let source_widget = $(`.grid-stack-item-content.editable_widget[data-widget-id=${source}]`);

			// Only update it if the content changed
			if (source_widget.find('textarea').val() !== content) {
    			source_widget.find('textarea').val(content);
    			source_widget.find('.custom-WYSIWYG-content').html(content);
    			refresh_buttons_on_layout_change_<?=$this->id?>();
			}

			// Hide the modal window
    		modal.modal('hide');
		});

        // Handle the WYSIWYG edit modal's hide event by cleaning it up so it can be properly used when a widget is edited		
		$('#edit_WYSIWYG_modal_<?=$this->id?>').on('hidden.bs.modal', function () {
			// Clean out the residual data left after editing the widget by destroying the editor
			destroy_editor('edit_WYSIWYG_modal_<?=$this->id?>_textarea');
        });

<?php } ?>

        // Ask for confirmation before restoring the layout
    	$(document).on('click', '#layout_wrapper_<?=$this->id?> .restore-layout-widget button', function(e) {e.stopPropagation(); confirm('<?= $escaper->escapeHtml($lang['ConfirmRestoreLayout'])?>', () => {
			restore_layout_<?=$this->id?>($(this).attr('data-sr-restore'));
    	})});
        
<?php if ($is_admin) { ?>
        // Admins can set their layouts' default status
		$('#default_layout_<?=$this->id?>').on('change', function() {
			let checked = this.checked;
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/v2/ui/default_layout",
                data: {
                	layout_name: '<?= $this->layout_name ?>',
                	default: checked ? 1 : 0,
            	},
                success: function(result){
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this)) {
                    	showAlertsFromArray(xhr.responseJSON.status_message);
                    }

                    $('#default_layout_<?=$this->id?>').prop('checked', !checked);
                }
            });
        });
<?php } ?>

		// Warn the user on leaving/reloading the page that there are unsaved changes that will be lost if they continue
		$(window).on('beforeunload.<?=$this->id?>', function(e) {
			if (has_unsaved_changes_<?=$this->id?>) {
				e.stopPropagation();
				e.preventDefault();
				return '';
			}

			return undefined;
		});

		// Populate the widget selector with the widgets that aren't added to the loaded template
		refresh_widget_selector_<?=$this->id?>();
	});
</script>



<?php if ($has_custom_widgets) { ?>

<div id='edit_WYSIWYG_modal_<?=$this->id?>' class='modal fade hide' tabindex='-1' role='dialog' aria-labelledby='edit_WYSIWYG_modal_<?=$this->id?>' aria-hidden='true'>
    <div class='modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered'>
        <div class='modal-content'>
            <div class='modal-header'>
                <h4 class='modal-title'><?= $escaper->escapeHtml($lang['EditWidgetText'])?></h4>
                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
            </div>
            <div class='modal-body'>
            	<input type='hidden' class='source'/> 
				<textarea id='edit_WYSIWYG_modal_<?=$this->id?>_textarea'></textarea>
            </div>
            <div class='modal-footer'>
                <button class='btn btn-secondary' data-bs-dismiss='modal'><?= $escaper->escapeHtml($lang['Cancel'])?></button>
                <button type='submit' class='btn btn-submit'><?= $escaper->escapeHtml($lang['Save'])?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php
	} // End of render() function
} // End of class
?>