<?php
require_once(realpath(__DIR__ .'/head.php'));

// Define the localization keys required by certain scripts and if there's a match in the requested scripts then the required localizations will be made available for the script to use
// In the script and the page using it you will be able to use _lang['localization_key'] in javascript.
$localization_required_by_scripts = [
    'CUSTOM:common.js' => ['Yes', 'Cancel', 'FieldRequired'],
    'EXTRA:JS:assessments:questionnaire_templates.js' => ['SelectedOnAnotherTab', 'ID', 'SelectedQuestions', 'SearchForQuestion', 'ConfirmDisableTabbedExperience', 'ConfirmDeleteTab', 'NewTab', 'Default', 'Actions'],
    'CUSTOM:pages/plan-project.js' => ['AreYouSureYouWantToDeleteThisProject'],
    'datatables' => ['All', 'datatables_ShowAll', 'datatables_ShowLess', 'First', 'Previous', 'Next', 'Last'],
    'blockUI' => ['ProcessingPleaseWait'],
    'UILayoutWidget' => ['WidgetType_chart', 'WidgetType_table'],
];

?>
<!DOCTYPE html>
<html dir="ltr" lang="<?= $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?= $escaper->escapeHtml($_SESSION['lang']); ?>">
  <head>
    <title><?= isset($title) ? $title : 'SimpleRisk: Enterprise Risk Management Simplified';?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <!-- Favicon icon -->
    <?php setup_favicon("..");?>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />

    <!-- jQuery CSS -->
    <link rel="stylesheet" href="../vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">
    
    <!-- extra css -->

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

  	<script type="text/javascript">
        var BASE_URL = '<?= $escaper->escapeHtml($_SESSION['base_url'] ?? get_setting("simplerisk_base_url"))?>';
  	</script>

    <!-- All Jquery -->
    <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
    <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

    <!-- Bootstrap tether Core JavaScript -->
    <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>
    <!--Wave Effects -->
    <script src="../js/simplerisk/theme/waves.js" defer></script>
    <!--Menu sidebar -->
    <script src="../js/simplerisk/theme/sidebarmenu.js" id="script_sidebarmenu" defer></script>
    <!--Custom JavaScript -->
    <script src="../js/simplerisk/theme/theme.js" defer></script>

<?php

// Make sure it's not undefined
$required_scripts_or_css = $required_scripts_or_css ?? [];

// Add the 'JSLocalization' to the $required_scripts_or_css list if it's not in there, but there's somre localization requested
if (!empty($required_localization_keys) && !in_array('JSLocalization', $required_scripts_or_css)) {
    $required_scripts_or_css[]= 'JSLocalization';
}

// If there're any scripts that's required by a page
if (!empty($required_scripts_or_css)) {

    // Add the other scripts required by the UILayoutWidget
    // Later we could build a kind of dependency management, but right now it's not really needed
    // so I decided to not waste the time on it
    if (in_array('UILayoutWidget', $required_scripts_or_css)) {
        foreach (['gridstack', 'CUSTOM:common.js'] as $script_dependency) {
            if (!in_array($script_dependency, $required_scripts_or_css)) {
                $required_scripts_or_css []= $script_dependency;
            }
        }
    }

    // check if there's a script that needs localization
    $scripts_with_localization_needs = array_intersect(array_keys($localization_required_by_scripts), $required_scripts_or_css);

    // If there is
    if (count($scripts_with_localization_needs) > 0 || !empty($required_localization_keys)) {

        // then make sure that the 'JSLocalization' is in the list of requested features
        if (!in_array('JSLocalization', $required_scripts_or_css)) {
            $required_scripts_or_css[]= 'JSLocalization';
        }

        // Initializa the `$required_localization_keys` variable if it isn't yet
        if (empty($required_localization_keys)) {
            $required_localization_keys = [];
        }

        // Add the list of localization keys that are setup to be required for the requested script
        foreach ($scripts_with_localization_needs as $script_with_localization_needs) {
            $required_localization_keys = array_merge_unique($required_localization_keys, $localization_required_by_scripts[$script_with_localization_needs]);
        }

        // Render the script tag with the localized strings
?>
		<script type="text/javascript">
<?php
            if (!empty($required_localization_keys)) {
?>
    		var _lang = {
<?php
                foreach ($required_localization_keys as $localization_key) {
                    // Escaped as html so it won't cause issues when inserted in the html using JS
?>
        		'<?= $localization_key ?>': '<?= $escaper->escapeHtml($lang[$localization_key]) ?>',
<?php
                }
?>
			};
<?php
            }
?>
		</script>
<?php
    }
}

// Include the required scripts and their css files
// Also setting defaults for certain scripts
foreach ($required_scripts_or_css as $required_script_or_css) {
        switch ($required_script_or_css) {
            case 'blockUI':
?>
    <script src="../vendor/node_modules/block-ui/jquery.blockUI.js?<?= $current_app_version ?>" id="script_blockui" defer></script>
    <script>
    	// Initialize the defaults for the blockUI when the script is loaded
    	$('#script_blockui').on('load', function () {
			$.blockUI.defaults.css = {
				padding: 0,
                margin: 0,
                width: '20%',
                top: '40%',
                left: '40%',
                textAlign: 'center',
                cursor: 'wait',
                color: 'var(--sr-light)',
				backgroundColor: 'rgba(0, 0, 0, 0)', // hide the background, so only the spinner is visible
			};

            $.blockUI.defaults.overlayCSS = { 
                backgroundColor: 'var(--sr-dark)', 
                opacity: 0.6, 
                cursor: 'wait' 
        	}

			$.blockUI.defaults.message = "<i class='fa fa-spinner fa-spin' style='font-size:24px;'></i>";

			// Store the original blockUI object, so we can still call it as a function and reassign its properties to the new implementation
		    var _original_blockui = $.blockUI;

			// Redefine the blockUI function call, so we can have different 'defaults' for it than for the block() function
            $.blockUI = function(arguments){
				_original_blockui({
					css: {
    					padding: 5,
    					border: '3px solid var(--sr-default)',
    					backgroundColor: 'var(--sr-dark)',
    				},
    				message: "<i class='fa fa-spinner fa-spin' style='font-size:24px; padding-right: 10px;'></i>" + _lang['ProcessingPleaseWait'],
    			});
            };

            // Assign the properties of the original to the new implementation
            Object.assign($.blockUI, _original_blockui);
		});

		$(document).on('submit', 'form.block-on-submit', function (e) {
			$.blockUI();
		});
	</script>
<?php 
            break;
        case 'selectize':
?>
    <script src="../vendor/node_modules/@selectize/selectize/dist/js/selectize.min.js?<?= $current_app_version ?>" id="script_selectize" defer></script>
    <link rel="stylesheet" href="../vendor/node_modules/@selectize/selectize/dist/css/selectize.bootstrap5.css?<?= $current_app_version ?>">
<?php 
            break;
        case "sorttable":
?>
    <script src="../vendor/node_modules/sorttable/sorttable.js?<?= $current_app_version ?>" id="script_sorttable" defer></script>
<?php
            break;
        case 'datatables':
?>
	<script src="../vendor/node_modules/datatables.net/js/dataTables.min.js?<?= $current_app_version ?>" defer></script>
	<script src="../vendor/node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables" defer></script>
	<script src="../js/simplerisk/dataTables.renderers.js?<?= $current_app_version ?>" id="script_datatables_renderers" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css?<?= $current_app_version ?>">
	<script>
    	// Initialize the defaults for the Datatable when the script is loaded
    	$('#script_datatables').on('load', function () {

    		// Readjust the columns on datatables when they are on a tab that was just shown
    		// It's required because when datatables are initialized while not shown the columns don't always line up properly with the headers 
    		$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
    			$.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
			});

			Object.assign(DataTable.defaults, {
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, _lang['All']]],
                lengthChange: true,
                filter: true,
                processing: true,
        		serverSide: true,
                layout: {
                	topStart: 'pageLength',
<?php // Using PHP comments so it's not rendered into the page
                    // This is another way it could've been done. Leaving it here so you don't have to research it
                    //topEnd: () => $('<div>', {class: 'col-sm-12 col-md-12 settings'}),
?>
                    topEnd: {div: {className: 'col-sm-12 col-md-12 settings'}},
                    bottomStart: 'info',
                    bottomEnd: {
                    	className: 'd-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto paginate',
                    	features: [
                    		'paging',
                    		{div: {className: 'btn btn-primary shows'}},
<?php
            // Add the specification for the two extra required button for the Dynamic Risk Report
            if (in_array('CUSTOM:dynamic.js', $required_scripts_or_css)) {
?>
							{div: {className: 'print-by-group'}},
							{div: {className: 'download-by-group'}},
<?php
            }
?>
                		]
            		},
				},
				language: {
                	paginate: {
                		first: _lang['First'],
                		previous: _lang['Previous'],
                		next: _lang['Next'],
                		last: _lang['Last'],
                		
                	}
                }
            });

       		$(document).on('preInit.dt', function(e, settings) {
                var table = new $.fn.dataTable.Api(settings).table();

				// Get the ID of the datatable or create it if it doesn't have one             
             	var datatable_uuid = $(table.node()).uniqueId().attr('id');
             	
             	// Get the show all/less button
             	var button = $(table.node()).closest('div.dt-container').find('div.paginate > div.btn.shows');
             	
             	// Save the datatable's id on it, so it doesn't have to search for it in the onClick logic
             	button.data('td-id', datatable_uuid);
             	
             	// Create the localized Show All/Less divs that'll be shown based on the currently displayed result numbers
             	$('<span>').addClass('all').text(_lang['datatables_ShowAll']).prependTo(button);
             	$('<span>').addClass('less').text(_lang['datatables_ShowLess']).prependTo(button);
             	
             	// If there's a settings button for the datatable tagged with the "data-sr-role='dt-settings'" attribute 
             	// and has the [data-sr-target] attribute set then move the settings button to its designated place inside the
             	// datatable wrapper to make it look more like it's part of the datatable
             	$("[data-sr-role='dt-settings'][data-sr-target]").each(function() {
                    $(this).appendTo($('#' + $(this).data('sr-target')).closest('div.dt-container').find('div.settings'));
             	});
            });
                
			$(document).on('draw.dt', function (e, settings) {
                var api = new $.fn.dataTable.Api(settings);
             	var button = $(api.table().node()).closest('div.dt-container').find('div.paginate > div.btn.shows');
             	var info = api.page.info();

				// Toggle the 'all' class on when we're NOT displaying every results so we're showing the "Show All" button 
				button
				// Disable the button if there're less results than the page size(use d-none if you want to hide the button instead of disabling it)
				.toggleClass("disabled", info.recordsTotal < info.length)
				// Toggle the 'all' and 'less' classes('all' - display the "Show All" text, 'less' - display the "Show Less" text)
				.toggleClass("all", info.length != -1).toggleClass("less", info.length === -1);

<?php // Using PHP comments so it's not rendered into the page
				// Use $(this).data('dt-pageSize') in the button click logic if we should go back to the previous page size instead of the default 
				// if (info.length !== -1) {
				// 	button.data('dt-pageSize', info.length);
				// }
?>
            });

			// Switch between the default page size and the show all option on click            
            $('body').on('click', 'div.dt-container div.paginate > div.btn.shows', function(e) {
            	e.preventDefault();
            	var table = $('#' + $(this).data('td-id')).DataTable();
            	table.page.len(table.page.info().length === -1 ? DataTable.defaults.lengthMenu[0][0] : -1).draw();
            });
		});
	</script>
<?php 
            break;
        case 'datatables:rowgroup':
?>
	<script src="../vendor/node_modules/datatables.net-rowgroup/js/dataTables.rowGroup.min.js?<?= $current_app_version ?>" id="script_datatables_rowgroup" defer></script>
	<script src="../vendor/node_modules/datatables.net-rowgroup-bs5/js/rowGroup.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables_rowgroup-bs5" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/datatables.net-rowgroup-bs5/css/rowGroup.bootstrap5.min.css?<?= $current_app_version ?>">
<?php 
            break;
            case 'datatables:rowreorder':
?>
	<script src="../vendor/node_modules/datatables.net-rowreorder/js/dataTables.rowReorder.min.js?<?= $current_app_version ?>" id="script_datatables_rowreorder" defer></script>
	<script src="../vendor/node_modules/datatables.net-rowreorder-bs5/js/rowReorder.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables_rowreorder-bs5" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/datatables.net-rowreorder-bs5/css/rowReorder.bootstrap5.min.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'WYSIWYG':
?>
    <script src="../vendor/node_modules/tinymce/tinymce.min.js?<?= $current_app_version ?>" id="script_tinymce" defer></script>
	<script src="../js/WYSIWYG/editor.js?<?= $current_app_version ?>" id="script_wysiwyg_editor" defer></script>
	<link rel="stylesheet" href="../css/WYSIWYG/editor.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'WYSIWYG:Assessments':
?>
    <script src="../vendor/node_modules/tinymce/tinymce.min.js?<?= $current_app_version ?>" id="script_tinymce" defer></script>
	<script src="../extras/assessments/js/editor.js?<?= $current_app_version ?>" id="script_wysiwyg_editor" defer></script>
<?php 
            break;
        case 'WYSIWYG:Notification':
?>
    <script src="../vendor/node_modules/tinymce/tinymce.min.js?<?= $current_app_version ?>" id="script_tinymce" defer></script>
	<script src="../extras/notification/js/editor.js?<?= $current_app_version ?>" id="script_wysiwyg_editor" defer></script>
<?php 
            break;

        // make a "select2" that is a searchable select element.
        case 'select2':
?>
	<script src="../vendor/node_modules/select2/dist/js/select2.min.js?<?= $current_app_version ?>" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/select2/dist/css/select2.min.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'multiselect':
?>
	<script src="../vendor/node_modules/bootstrap-multiselect/dist/js/bootstrap-multiselect.min.js?<?= $current_app_version ?>" id="script_multiselect" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/bootstrap-multiselect/dist/css/bootstrap-multiselect.min.css?<?= $current_app_version ?>">
	<script>
      // Initialize the defaults when the script is loaded
      $('#script_multiselect').on('load', function () {
        // A supposed workaround to make the multiselect widget work with bootstrap 5
        // (it only supports bootstrap versions up to bootstrap 3)
        $.fn.multiselect.Constructor.prototype.defaults.buttonClass = 'form-select';
        $.fn.multiselect.Constructor.prototype.defaults.templates.button = '<button type="button" class="multiselect dropdown-toggle form-control" data-bs-toggle="dropdown"><span class="multiselect-selected-text"></span></button>';

<?php // Using PHP comments so it's not rendered into the page
    		// Please don't remove the commented part yet, we'll see if it'll be needed for making the multiselect work
          	/*$(document).on('click','.multiselect',function(){
            	// $(this).parent().addClass('open');
              	// $(this).parent().toggleClass('open')
          	});
          	$(document).click(function (event) {
              	var $target = $(event.target);
              	if (!$target.closest('.multiselect-native-select').find('.btn-group').length && $('.multiselect-native-select').find('.btn-group').hasClass("open")) {
                	$('.multiselect-native-select').find('.btn-group').removeClass('open');
              	}
          	});*/
?>
		});
	</script>
<?php 
            break;
        case 'cve_lookup':
?>
	<script src="../js/simplerisk/cve_lookup.js?<?= $current_app_version ?>" defer></script>
<?php 
            break;
        case 'easyui':
?>
    <script src="../vendor/simplerisk/jeasyui/jquery.easyui.min.js?<?= $current_app_version ?>" id="script_easyui" defer></script>
    <link rel="stylesheet" href="../vendor/simplerisk/jeasyui/themes/default/easyui.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'easyui:treegrid':
    ?>
    <script src="../vendor/simplerisk/jeasyui/jquery.easyui.min.js?<?= $current_app_version ?>" id="script_easyui" defer></script>
    <link rel="stylesheet" href="../vendor/simplerisk/jeasyui/themes/default/datagrid.css?<?= $current_app_version ?>">
    <link rel="stylesheet" href="../vendor/simplerisk/jeasyui/themes/default/tree.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'easyui:dnd':
?>
	<script src="../vendor/simplerisk/jeasyui/plugins/treegrid-dnd.js?<?= $current_app_version ?>" defer></script>
    <script src="../vendor/simplerisk/jeasyui/plugins/jquery.draggable.js?<?= $current_app_version ?>" defer></script>
	<script src="../vendor/simplerisk/jeasyui/plugins/jquery.droppable.js?<?= $current_app_version ?>" defer></script>

	<!-- Adding this empty style tag here to prevent easyui to create the rules for the treegrid drag&drop -->
	<style id="treegrid-dnd-style"></style>
<?php 
            break;
        case 'easyui:filter':
?>
	<script src="../vendor/simplerisk/jeasyui/plugins/datagrid-filter.js?<?= $current_app_version ?>" defer></script>
<?php 
            break;
        case 'datetimerangepicker':
?>
	<script type="text/javascript" src="../vendor/node_modules/moment/min/moment.min.js?<?= $current_app_version ?>" id="script_moment" defer></script>
	<script type="text/javascript" src="../vendor/node_modules/daterangepicker/daterangepicker.js?<?= $current_app_version ?>" id="script_daterangepicker" defer></script>
	<link rel="stylesheet" type="text/css" href="../vendor/node_modules/daterangepicker/daterangepicker.css?<?= $current_app_version ?>" />

	<script>
        var default_date_format = '<?=$escaper->escapeHtml(get_default_date_format_for_js())?>';
        var default_datetime_format = '<?=$escaper->escapeHtml(get_default_datetime_format_for_js())?>';

      	// Initialize the defaults when the script is loaded
      	$('#script_daterangepicker').on('load', function () {

            // Defaults that are the same for every date/datetime/range widget
            $.fn.daterangepicker.defaultOptions = {
                "buttonClasses": "btn btn-sm",
                "applyButtonClasses": "btn-submit",
                "cancelClass": "btn-secondary",
            	locale: {
                	"separator": " - ", // added between the two dates in a daterange
                    // cancel button is used to clear the value so the button label should be changed into 'Clear'.
                    "cancelLabel": 'Clear'
                },
                // indicates whether the date range picker should automatically update the value of the <input> element it's attached to at initialization and when the selected dates change.
                // if this value is true, the datepicker initially shows the current date value and if false, the datepicker initially shows empty.
                // if we set this value to false, we should use 'apply.daterangepicker', 'cancel.daterangepicker' event to update the value of the datepicker and trigger 'change' event.
                "autoUpdateInput": false,

            }

            $.fn.extend({
            	/**
            	* Adding date/datetime/range related initialization functions to JQuery.
            	*
            	* Using Using Object.assign() this way for additional options to make sure
            	* that we can have default options that can be overridden and default options that can't.
            	*
            	* Object.assign({defaults that can be changed}, {additional options}, {defaults that can't be changed})
            	*/
            	initAsDatePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_date_format}}, options, {"timePicker": false, "singleDatePicker": true}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'date', false);
            	},
            	initAsDateTimePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_datetime_format}}, options, {"timePicker": true, "singleDatePicker": true}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'time', false);
            	},
            	initAsDateRangePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_date_format}}, options, {"timePicker": false, "singleDatePicker": false}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'date', true);

            	},
            	initAsDateTimeRangePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_datetime_format}}, options, {"timePicker": true, "singleDatePicker": false}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'time', true);
            	}
            });
        });

        // attach event handlers for clicking the Apply and Cancel buttons to the element
        // element: datepicker element
        // type = 'date' or 'time' => datepicker or datetimepicker
        // range = true or false => rangepicker true or false
        function attachApplyAndCancelEventHandler(element, type = 'date', range = false) {

            let default_input_format = '';
            if (type == 'date') {
                default_input_format = default_date_format;
            } else if (type == 'time') {
                default_input_format = default_datetime_format;
            }

            // trigerred when the apply button is clicked.
            $(element).on("apply.daterangepicker", function(ev, picker) {

                if (!range) {
                    $(this).val(picker.startDate.format(default_input_format));
                } else {
                    $(this).val(picker.startDate.format(default_input_format) + ' - ' + picker.endDate.format(default_input_format));
                }

                // if 'autoUpdateInput' is false, the 'change' event is not triggerred automatically even if the value of the datepicker is changed through 'apply.daterangepicker'.
                $(this).trigger('change');

            });

            // trigerred when the cancel button is clicked.
            $(element).on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');

                // if 'autoUpdateInput' is false, the 'change' event is not triggerred automatically even if the value of the datepicker is changed through 'cancel.daterangepicker'.
                $(this).trigger('change');
            });
        }
	</script>
<?php 
            break;
        case 'chart.js':
?>
    <script src="../vendor/node_modules/chart.js/dist/chart.umd.js?<?= $current_app_version ?>" id="script_chartjs" defer></script>
<?php 
            break;
        case 'graphology':
?>
            <script type="text/javascript" src="../vendor/node_modules/sigma/dist/sigma.min.js?<?= $current_app_version ?>" id="script_sigma" defer></script>
            <script type="text/javascript" src="../vendor/node_modules/graphology/dist/graphology.umd.min.js?<?= $current_app_version ?>" id="script_graphology" defer></script>
<?php
            break;
        case 'tabs:logic':
?>

  	<script>
        // Change hash on changing tab
        //$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
        $(document).on('click', 'nav a[data-bs-toggle="tab"]', function (e) {
        	let hash = $(this).data('bs-target');
            window.location.hash = hash.replace('#', '');
            
            // scrolling to the top so it doesn't jump to the tab's content when clicking the tab
            $('.content-wrapper')[0].scrollIntoView();
        });
  	
    	$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
        	$('.content-wrapper')[0].scrollIntoView();
	    });

    	$(function() {

 			// Deactivate all the tabs, but mark the intended active tabs as primary-tabs so later we can activate them
 			// It's needed so the onshow events are executed
        	$('div.tab-pane.active').removeClass('active');
        	$('nav.nav.nav-tabs a.nav-link.active').removeClass('active').addClass('primary-tab');		
		
			// ^ means starting, meaning only match the first hash
            var hash = location.hash.replace(/^#/, '');
            if (hash) {
            	// get the parent tab panes up to the body tag so we can go and activate them so the
            	// path to the required tab is activated
            	let parents = $('.nav-tabs a[data-bs-target="#' + hash + '"]').parents('.tab-pane');

            	// Activate the 'path' to the requested tab in a reverse order
            	// originally the 'parents()' function gets the parents of the requested tab in an order
            	// <requested tab> -->> <body>
            	// but we need them activated <body> -->> <requested tab>   
            	parents.reverse().each((i, el) => $('.nav-tabs a[data-bs-target="#' + $(el).attr('id') + '"]').tab('show'));
            	
            	// Activate the tab itself
                $('.nav-tabs a[data-bs-target="#' + hash + '"]').tab('show');
            }

        	// Add a tab activation listener that checks for tab headers(inside of the tab that just got activated)
        	// that has no tab marked as active and activates the leftmost tab 
            $(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
            	// remove the marker from this tab
            	$(this).removeClass('primary-tab');
            	// get the tab header in this tab's content
            	let inner_nav = $($(this).data('bs-target') + ' nav.nav.nav-tabs').first();
            	
            	// get the list of tabs that are marked as primary-tab
				let primary_tab = inner_nav.find('a.nav-link.primary-tab');
				// if there's any, activate the first one
                if (primary_tab.length != 0){
                	primary_tab.first().tab('show');
                } else if (inner_nav.find('.active').length == 0){
                	// if there's no tab marked as primary-tab and there's no active one either, then activate the leftmost tab
                	inner_nav.find('a[data-bs-toggle="tab"]').first().tab('show');
                }
            });

        	// Check if there's a tab header without an active tab and mark the leftmost active
        	// the above event handler will handle the inner tabs if there are any
        	// this part is just there to kick off that logic
        	let inner_nav = $((hash ? `#${hash} `:'') + 'nav.nav.nav-tabs').first();
        	// get the list of tabs that are marked as primary-tab
			let primary_tab = inner_nav.find('a.nav-link.primary-tab');
			// if there's any, activate the first one
            if (primary_tab.length != 0){
            	primary_tab.first().tab('show');
            } else if (inner_nav.find('.active').length == 0){
            	// if there's no tab marked as primary-tab and there's no active one either, then activate the leftmost tab
            	inner_nav.find('a[data-bs-toggle="tab"]').first().tab('show');
            }
            
        	$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
        		$('.content-wrapper')[0].scrollIntoView();
<?php
            if (in_array('easyui:treegrid', $required_scripts_or_css)) {
?>
            		if ($.fn.treegrid) {
            			$('table.easyui-treegrid', $($(this).data('bs-target'))).each(function() {$(this).treegrid("resize");});
            		}
<?php
            }
?>
	   		});
		});
	</script>
<?php 
            break;

        case 'editable':
?>
    <script type="text/javascript">
    
        function resizable(el, factor) {
            var int = Number(factor) || 7.6;
            function resize() {el.width((el.val().length + 1) * int);}
            var e = ["keyup", "keypress", "focus", "blur", "change"];
            for (var i in e)
                el.on(e[i], resize);
            resize();
        }

        $(document).ready(function(){
            $("input.editable").each(function(){
                resizable($(this));
            });
                
            $("body").on("click", "span.editable", function() {
                $(this).hide();
                $(this).parent().find("input").show().select();
            });
                
            $("body").on("blur", "input.editable", function(){
                let input_value = $(this).val();
                if(!input_value || !input_value.trim()) return false;
                var label = $(this).parent().find("span.editable");
                $(this).hide();
                label.text(input_value);
                label.attr("title", input_value);
                label.show();
            });
        });
    </script>
<?php
            break;
        case 'gridstack':
?>
	<script type="text/javascript" src="../vendor/node_modules/gridstack/dist/gridstack-all.js?<?= $current_app_version ?>" id="script_gridstack" defer></script>
	<link rel="stylesheet" type="text/css" href="../vendor/node_modules/gridstack/dist/gridstack.min.css?<?= $current_app_version ?>" />
<?php
            break;

        case 'UILayoutWidget':
            require_once(realpath(__DIR__ . '/includes/Widgets/UILayout.php'));
            break;
        default:
            // Custom scripts
            if (preg_match("/^CUSTOM:((?:[\w,\s-]+\/)*[\w,\s-]+\.js)$/", $required_script_or_css, $matches)) {
?>
		<script src="../js/simplerisk/<?= $matches[1] ?>?<?= $current_app_version ?>" defer></script>
<?php       // Custom scripts within extras
            } elseif (preg_match("/^EXTRA:JS:([\w_]+):((?:[\w,\s-]+\/)*[\w,\s-]+\.js)$/", $required_script_or_css, $matches)) {
?>
		<script src="../extras/<?= $matches[1] ?>/js/<?= $matches[2] ?>?<?= $current_app_version ?>" defer></script>
<?php       // Custom css within extras
            } elseif (preg_match("/^EXTRA:CSS:([\w_]+):((?:[\w,\s-]+\/)*[\w,\s-]+\.js)$/", $required_script_or_css, $matches)) {
?>
		<link rel="stylesheet" href="../extras/<?= $matches[1] ?>/css/<?= $matches[2] ?>?<?= $current_app_version ?>">
<?php 
            }
            break;
        }
    }
  	
?>
  	<script>
    	$(function() {
        	
        	// It's required because bootstrap's modal windows need to be nested under an element
        	// where none of the parents have 'fixed' or 'relative' set, so moving them under the <body>
        	// is the best option to make them work no matter where they were defined
        	$("div.modal")/*.detach()*/.appendTo("body");
    	});
	</script>
  </head>
    <!-- CSS only -->
  <body>
    <div class="preloader">
      <div class="lds-ripple">
        <div class="lds-pos"></div>
        <div class="lds-pos"></div>
      </div>
    </div>
    <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="full" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full">
      <header class="topbar" data-navbarbg="skin5">
        <nav class="navbar top-navbar navbar-expand-md navbar-dark">
          <div class="navbar-header" data-logobg="skin5">
            <!-- ============================================================== -->
            <!-- Logo -->
            <!-- ============================================================== -->
            <a class="navbar-brand" href="https://www.simplerisk.com">
                <img src="../images/logo@2x.png" alt="homepage" class="logo"/>
            </a>
           
            <a class="nav-toggler waves-effect waves-light d-block d-md-none" href="javascript:void(0)"
              ><i class="ti-menu ti-close"></i></a>
          </div>
          <div class="navbar-collapse collapse show" id="navbarSupportedContent" data-navbarbg="skin5">
            <ul class="navbar-nav float-start me-auto">
              <li class="nav-item">
                <a class="nav-link sidebartoggler waves-effect waves-light" href="javascript:void(0)" data-sidebartype="mini-sidebar"><i class="mdi mdi-menu font-24"></i></a>
              </li>
              <!-- Search -->
              <?php
if (!advanced_search_extra()) { ?>
				<li class="nav-item dropdown nav-item-search">
            		<div class="nav-link">
            			<div class="search-box">
            				<form action="../management/view.php" method="get" autocomplete="off">
            					<button class="search-button" type="button"><i class="fas fa-search align-middle"></i></button>
        	    				<input type="text" class="search-input" name="id" placeholder="ID#" />
    	    				</form>
            			</div>
            		</div>
            	</li>
<?php } else{
    require_once(realpath(__DIR__ . '/extras/advanced_search/index.php'));
    render_advanced_search();
}?>
            </ul>
           
            <!-- Right side toggle and nav items -->
            <ul class="navbar-nav float-end">
			  <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle waves-effect waves-dark" href="#" id="2" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="font-24 far fa-question-circle align-middle"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end animated" aria-labelledby="2">
                  
                  <!-- About This Page -->
                  <li><a class="dropdown-item" href="https://help.simplerisk.com/index.php?page=<?=get_request_uri();?>" target="_blank"><i class="fas fa-info-circle me-1 ms-1"></i><?= $escaper->escapeHtml($lang['AboutThisPage']);?></a></li>

                  <!-- API Documentation -->
                  <li><a class="dropdown-item" href="<?php echo $_SESSION['base_url'];?>/api/v2/documentation.php" target="_blank"><i class="fas fa-info-circle me-1 ms-1"></i><?= $escaper->escapeHtml($lang['APIDocumentation']);?></a></li>
                  
                  <!-- How-To Videos -->
                  <li><a class="dropdown-item" href="https://simplerisk.freshdesk.com/a/solutions/folders/6000228831" target="_blank"><i class="fas fa-video me-1 ms-1"></i><?= $escaper->escapeHtml($lang['HowToVideos']);?></a></li>
                  
                  <!-- FAQs -->
                  <li><a class="dropdown-item" href="https://simplerisk.freshdesk.com/a/solutions/folders/6000168810" target="_blank"><i class="fas fa-question-circle me-1 ms-1"></i><?= $escaper->escapeHtml($lang['FAQs']);?></a></li>

                  <!-- Whats New -->
                  <li><a class="dropdown-item" href="https://github.com/simplerisk/documentation/raw/master/SimpleRisk%20Release%20Notes%20<?= $escaper->escapeHtml(get_latest_app_version());?>.pdf" target="_blank"><i class="fas fa-link me-1 ms-1"></i><?= $escaper->escapeHtml($lang['WhatsNew']);?></a></li>

                  <!-- Roadmap -->
                  <li><a class="dropdown-item" href="https://simplerisk.atlassian.net/jira/discovery/share/views/ecc28d2f-82d4-444c-82ad-f16ea7e1a1c1" target="_blank"><i class="fas fa-map me-1 ms-1"></i><?= $escaper->escapeHtml($lang['Roadmap']);?></a></li>

                  <!-- Support Portal -->
                  <li><a class="dropdown-item" href="https://simplerisk.freshdesk.com/support/solutions" target="_blank"><i class="fas fa-cloud me-1 ms-1"></i><?= $escaper->escapeHtml($lang['SupportPortal']);?></a></li>

                  <!-- Web Support -->
                  <li><a class="dropdown-item" href="https://simplerisk.freshdesk.com/support/tickets/new" target="_blank"><i class="fas fa-ticket-alt me-1 ms-1"></i><?= $escaper->escapeHtml($lang['WebSupport']);?></a></li>
                  
                  <!-- Email Support -->
                  <li><a class="dropdown-item" href="mailto: support@simplerisk.com" target="_blank"><i class="fas fa-envelope me-1 ms-1"></i><?= $escaper->escapeHtml($lang['EmailSupport']);?></a></li>
                </ul>
              </li>

              <!-- Profile dropdown menu -->
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle waves-effect waves-dark" role="button" data-bs-toggle="dropdown"><i class="display-7 mdi mdi-account align-middle"></i></a>
		        <ul class="dropdown-menu dropdown-menu-end animated">
			      <li><a class="dropdown-item" href="../account/profile.php"><i class="fa fa-user me-1 ms-1"></i> <?= $escaper->escapeHtml($lang['MyProfile']);?></a></li>
<?php
                    if (organizational_hierarchy_extra()) {
                        require_once(realpath(__DIR__) . '/extras/organizational_hierarchy/index.php');
                        render_business_unit_selection_menu();
                    }
?>
	              <li><a class="dropdown-item" href="../logout.php"><i class="fa fa-power-off me-1 ms-1"></i><?= $escaper->escapeHtml($lang['Logout']);?></a></li>
                </ul>
              </li>
              <!-- End of Profile dropdown menu -->
              
              
            </ul>
          </div>
        </nav>
      </header>

      <div id="load" style="display:none;"><?=$escaper->escapeHtml($lang['SendingRequestPleaseWait'])?></div>
