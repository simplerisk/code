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
     * Whether the "Edit layout" control (and its header-hoist) should be
     * rendered. Defaults to true so every existing caller (`new UILayout('home')`,
     * etc.) keeps today's behavior unchanged.
     *
     * Type: bool
     */
    private bool $show_edit_layout;

    /**
     * Whether the band can be collapsed to a one-line summary strip. Defaults to
     * false so a full dashboard (Home, the risk dashboards) — where the layout IS
     * the page — keeps today's behavior; it only makes sense where the band sits
     * ABOVE the real content and competes with it for vertical space.
     *
     * Type: bool
     */
    private bool $collapsible;

    /**
     * Initialize the widget
     *
     * @param string $layout_name the name of the layout that needs to be rendered
     * @param array $options optional flags:
     *                       - 'show_edit_layout' (bool, default true): whether to
     *                         render the "Edit layout" control. Set false for a
     *                         read-only/embedded rendering of the layout (e.g. an
     *                         insights band embedded in another page) that should
     *                         not offer layout editing.
     *                       - 'collapsible' (bool, default false): whether to offer
     *                         a collapse control that swaps the tile band for a
     *                         one-line summary strip. For layouts embedded above
     *                         other content, where the tiles cost vertical space
     *                         the content below needs.
     */
    public function __construct($layout_name, $options = []) {
	    $this->id = generate_token(10);
        $this->layout_name = $layout_name;
        $this->show_edit_layout = $options['show_edit_layout'] ?? true;
        $this->collapsible = $options['collapsible'] ?? false;
	}

	/**
	 * Pure decision method (no I/O) for whether the Edit-layout control should be
	 * rendered. Extracted so the decision is unit-testable without invoking
	 * render(), which requires $lang/$escaper/session/DB via get_layout_for_user().
	 *
	 * @return bool
	 */
	public function should_show_edit_layout(): bool {
		return $this->show_edit_layout;
	}

	/**
	 * Pure decision method (no I/O) for whether the collapse control and summary
	 * strip should be rendered. Extracted for the same reason as
	 * should_show_edit_layout(): render() can't run headlessly.
	 *
	 * @return bool
	 */
	public function is_collapsible(): bool {
		return $this->collapsible;
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
	<div class="layout_toolbar sr-editbar d-flex flex-wrap gap-2">
		<div class="sr-editbar__left show-hide hide">
			<div class="sr-editbar__toprow">
				<span class="sr-editbar__mode"><span class="sr-editbar__dot"></span><?= $escaper->escapeHtml($lang['Editing'])?></span>
				<div class="sr-add">
					<button type="button" class="btn sr-editbar__add" id="add_widget_toggle_<?=$this->id?>" aria-haspopup="true" aria-expanded="false"><i class="fas fa-plus sr-add__plus"></i><?= $escaper->escapeHtml($lang['AddWidget'])?><i class="fas fa-chevron-down sr-add__caret"></i></button>
					<div class="sr-add-popover hide" id="add_popover_<?=$this->id?>">
						<div class="sr-add-popover__head"><?= $escaper->escapeHtml($lang['AddWidget'])?></div>
						<div class="sr-add-list" id="add_list_<?=$this->id?>"></div>
<?php if ($has_custom_widgets) { ?>
						<button type="button" class="sr-add-item sr-add-item--custom" id="add_custom_toggle_<?=$this->id?>"><i class="fas fa-pen sr-add-item__ico"></i><span class="sr-add-item__nm"><?= $escaper->escapeHtml($lang['CreateCustomWidget'])?></span><i class="fas fa-chevron-right sr-add-item__go"></i></button>
						<div class="sr-add-custom hide" id="add_custom_panel_<?=$this->id?>">
							<button type="button" class="sr-add-custom__back" id="add_custom_back_<?=$this->id?>"><i class="fas fa-arrow-left me-1"></i><?= $escaper->escapeHtml($lang['CreateCustomWidget'])?></button>
							<select id="widget_creator_<?=$this->id?>" class="form-select form-select-sm sr-add-custom__type">
								<option value='0'><?= $escaper->escapeHtml($lang['SelectCustomWidgetType'])?></option>
<?php foreach ($available_custom_widgets as $custom_widget_name) { ?>
								<option value='<?= $custom_widget_name ?>'><?= $escaper->escapeHtml($lang[$ui_layout_widget_config[$custom_widget_name]['localization_key']])?></option>
<?php } ?>
							</select>
							<button type="button" class="btn sr-add-custom__add" id="add_custom_confirm_<?=$this->id?>"><?= $escaper->escapeHtml($lang['AddToDashboard'])?></button>
						</div>
<?php } ?>
					</div>
				</div>
				<span class="sr-editbar__hint"><?= $escaper->escapeHtml($lang['EditLayoutHint'])?></span>
			</div>
			<div class="sr-editbar__commit">
				<button type="button" class="btn sr-editbar__cancel" id="cancel_layout_<?=$this->id?>"><?= $escaper->escapeHtml($lang['Cancel'])?></button>
				<button type="button" class="btn sr-editbar__save" disabled id="save_layout_<?=$this->id?>"><?= $escaper->escapeHtml($lang['Save'])?></button>
			</div>
		</div>
		<div class="sr-editbar__right show-hide hide">
<?php if ($is_admin) { ?>
			<label class="sr-editbar__default" for="default_layout_<?=$this->id?>">
				<input type="checkbox" class="form-check-input" id="default_layout_<?=$this->id?>"<?= $default_set_by_user ? ' checked' : '' ?>>
				<?= $escaper->escapeHtml($lang['SetAsDefaultForEveryone'])?>
				<span class="sr-editbar__adm"><?= $escaper->escapeHtml($lang['Admin'])?></span>
			</label>
<?php } ?>
			<div class="restore-layout-widget">
				<button type="button" class="btn sr-editbar__restore" data-sr-restore="default"><i class="fas fa-rotate-left me-1"></i><?= $escaper->escapeHtml($lang['RestoreDefaultLayout'])?></button>
			</div>
		</div>
		<div class="settings ms-auto">
<?php if ($this->is_collapsible()) { ?>
			<button type="button" class="btn btn-sm text-nowrap sr-band-toggle" id="collapse_band_<?=$this->id?>" aria-expanded="true" aria-controls="band_panel_<?=$this->id?>" title="<?= $escaper->escapeHtmlAttr($lang['HideInsights'])?>"><i class="fas fa-chevron-down sr-band-toggle__caret"></i><?= $escaper->escapeHtml($lang['Insights'])?></button>
<?php } ?>
<?php if ($this->should_show_edit_layout()) { ?>
			<a class="btn btn-sm text-nowrap sr-edit-layout waves-effect waves-light" id="edit_layout_toggle_<?=$this->id?>" title="<?= $escaper->escapeHtml($lang['EditLayout'])?>" role="button"><i class="fas fa-pen sr-edit-layout__icon"></i><?= $escaper->escapeHtml($lang['EditLayout'])?></a>
<?php } ?>
<?php
			// Import-Export Extra: PDF export of this dashboard. Gated on the Extra
			// being ACTIVATED (import_export_extra() reads the activation setting) —
			// not merely installed. Active extras' index.php is not auto-loaded on
			// every page, so pull the export helper in directly when active. Core
			// keeps no hard dependency: nothing runs unless the Extra is turned on.
			if (import_export_extra()) {
				$im_export_helper = realpath(__DIR__ . '/../../extras/import-export/includes/dashboard_export.php');
				if ($im_export_helper) {
					require_once($im_export_helper);
					if (function_exists('im_export_dashboard_button')) {
						im_export_dashboard_button($this->layout_name, $this->id);
					}
				}
			}
?>
		</div>
	</div>
    <div class="layout_panel rounded-4 p-1" id="band_panel_<?=$this->id?>">
		<div class="grid-stack" id="layout_<?=$this->id?>"></div>
    </div>
<?php if ($this->is_collapsible()) { ?>
	<!-- Collapsed stand-in for the tile band. Populated client-side from the
	     tiles themselves (see below) so the strip can never disagree with them. -->
	<div class="sr-band-summary d-none" id="band_summary_<?=$this->id?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Insights'])?>"></div>
<?php } ?>
</section>
<?php if ($this->is_collapsible()) { ?>
<script type="text/javascript">
	// Collapsing the insights band ------------------------------------------
	//
	// The band costs ~120px above the grid it introduces; on a 1366x768 laptop
	// that is roughly two visible rows of the table below. Collapsing it does
	// NOT hide the numbers -- it swaps the tiles for a one-line summary strip
	// carrying the same values, the same tones and the same drill-through
	// links, at about a quarter of the height. Nothing is lost by collapsing,
	// which is what makes the remembered state (and the short-viewport default)
	// safe: a user who never expands the band still sees every count.
	//
	// The strip is built FROM the rendered tiles rather than from a second data
	// source, so the two can't drift; the values arrive already escaped and go
	// in via .text().
	(function () {
		var id = '<?= $escaper->escapeJs($this->id) ?>';
		// Per layout, not per page: the same band on another page is the same
		// band, and the user's answer to "do I want this open" travels with it.
		var storageKey = 'sr.band.collapsed.<?= $escaper->escapeJs($this->layout_name) ?>';
		var $section = $('#layout_wrapper_' + id);
		var $panel = $('#band_panel_' + id);
		var $summary = $('#band_summary_' + id);
		var $toggle = $('#collapse_band_' + id);

		// A browser with storage disabled (private mode, locked-down policy)
		// must still get a working toggle -- it just won't remember.
		function readStored() {
			try { return window.localStorage.getItem(storageKey); } catch (e) { return null; }
		}
		function writeStored(value) {
			try { window.localStorage.setItem(storageKey, value); } catch (e) { /* not fatal */ }
		}

		function buildSummary() {
			$summary.empty();
			$panel.find('.sr-kpi').each(function () {
				var $tile = $(this);
				var $value = $tile.find('.sr-kpi__value').first();
				var $stat = $('<a>', { 'class': 'sr-band-stat', href: $tile.attr('href') || '#' });
				// Tone travels with the number, matching the tile: App Red is
				// spent on the value alone, never on the label.
				var tone = '';
				if ($value.hasClass('sr-kpi__value--danger')) { tone = ' sr-band-stat__value--danger'; }
				else if ($value.hasClass('sr-kpi__value--success')) { tone = ' sr-band-stat__value--success'; }
				$('<span>', { 'class': 'sr-band-stat__value' + tone, text: $value.text().trim() }).appendTo($stat);
				$('<span>', { 'class': 'sr-band-stat__label', text: $tile.find('.sr-kpi__label').first().text().trim() }).appendTo($stat);
				$summary.append($stat);
			});
		}

		function apply(collapsed) {
			if (collapsed) { buildSummary(); }
			$section.toggleClass('is-band-collapsed', collapsed);
			$panel.toggleClass('d-none', collapsed);
			$summary.toggleClass('d-none', !collapsed);
			$toggle.attr('aria-expanded', collapsed ? 'false' : 'true')
				.attr('title', collapsed ? <?= json_encode($lang['ShowInsights'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> : <?= json_encode($lang['HideInsights'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
			$toggle.find('.sr-band-toggle__caret')
				.toggleClass('fa-chevron-down', !collapsed)
				.toggleClass('fa-chevron-right', collapsed);
		}

		var stored = readStored();
		// No stored answer yet: start collapsed on a short viewport, where the
		// band is most expensive and the strip says the same thing. A viewport
		// with room keeps the tiles. Once the user answers, their answer wins
		// on every viewport -- we never re-decide for them.
		var collapsed = stored === null ? (window.innerHeight <= 800) : stored === '1';
		apply(collapsed);

		$toggle.on('click', function () {
			collapsed = !collapsed;
			apply(collapsed);
			writeStored(collapsed ? '1' : '0');
		});

		// Tiles arrive after this script runs (Gridstack fills the layout
		// asynchronously) and again whenever the layout is restored or edited,
		// so rebuild the strip whenever the grid's contents change while it is
		// the visible half.
		var grid = document.getElementById('layout_' + id);
		if (grid && window.MutationObserver) {
			new MutationObserver(function () {
				if (collapsed) { buildSummary(); }
			}).observe(grid, { childList: true, subtree: true });
		}
	})();
</script>
<?php } ?>
<script>
	// Dashboard header: when this is the ONLY dashboard layout on the page, lift
	// the Edit-layout control up into the shared page header's action slot so it
	// sits on the title line instead of a near-empty toolbar row below the
	// breadcrumb. Reparenting is safe — the edit-mode toggle is bound by id via a
	// delegated handler, and the editing controls (widget picker / add / trash /
	// save) are the .show-hide items that stay in the toolbar. Multi-layout pages
	// (e.g. the open/closed risk dashboard) have >1 control, so each keeps its own
	// control by its own grid, and pages with no .page-actions slot leave it put.
	// When show_edit_layout is false and the Import-Export button isn't rendered
	// either, .settings has no children — skip the hoist so an empty node never
	// gets appended into the header's action slot.
	$(function () {
		var $slot = $('.page-breadcrumb .page-actions').first();
		var $settings = $('#layout_wrapper_<?=$this->id?> .layout_toolbar .settings');
		if ($slot.length && $('.layout_toolbar .settings').length === 1 && $settings.children().length) {
			$slot.append($settings);
			$('#layout_wrapper_<?=$this->id?>').addClass('layout--head-action');
		} else if (!$settings.children().length) {
			// Nothing to hoist and nothing left in the toolbar (e.g. Edit-layout
			// suppressed + Import-Export inactive) — collapse its margin so an empty
			// control row doesn't leave a blank gap above the grid.
			$settings.closest('.layout_toolbar').addClass('layout_toolbar--empty-settings');
		}
	});
</script>
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

	// True while we programmatically toggle static mode (enter/leave edit mode);
	// used to ignore the 'change' Gridstack fires so it isn't counted as a user edit.
	var applying_edit_change_<?=$this->id?> = false;

	// Refresh the widget selector dropdown. It's called when needing a refresh after a widget is
	//	1, added/removed
	//	2, a full layout is dynamically loaded
	//	3, on the initial page load
	// Populate the "Add widget" popover with the widgets not already on the layout.
	function refresh_add_popover_<?=$this->id?>() {
		let placed = layout_<?=$this->id?>.save(false).map(function (w) { return w.name; });
		let $list = $('#add_list_<?=$this->id?>').empty();

		// Collect the not-yet-placed widgets into domain groups (Risk, Compliance,
		// Governance, Incident, General), preserving each group's declared order,
		// so the picker reads as organised sections instead of one long list.
		let groups_<?=$this->id?> = {};
		widget_configurations_<?=$this->id?>.forEach(function (config) {
			if (placed.includes(config.name)) { return; }
			let g = config.group || '<?= $escaper->escapeJs($lang['General']) ?>';
			if (!groups_<?=$this->id?>[g]) {
				groups_<?=$this->id?>[g] = { order: (config.group_order != null ? config.group_order : 9), items: [] };
			}
			groups_<?=$this->id?>[g].items.push(config);
		});

		Object.keys(groups_<?=$this->id?>)
			.sort(function (a, b) { return groups_<?=$this->id?>[a].order - groups_<?=$this->id?>[b].order; })
			.forEach(function (g) {
				$('<div class="sr-add-group">').text(g).appendTo($list);
				groups_<?=$this->id?>[g].items.forEach(function (config) {
					let typeLabel = _lang[`WidgetType_${config.type}`] || config.type;
					let $item = $('<button type="button" class="sr-add-item">').attr('data-widget', config.name);
					$('<i class="sr-add-item__ico">').addClass(add_widget_icon_<?=$this->id?>(config.type)).appendTo($item);
					$('<span class="sr-add-item__nm">').text(config.localization).appendTo($item);
					$('<span class="sr-add-item__chip">').text(typeLabel).appendTo($item);
					$list.append($item);
				});
			});

		if ($list.children().length === 0) {
			$('<div class="sr-add-empty">').text('<?= $escaper->escapeJs($lang['AllWidgetsAdded']) ?>').appendTo($list);
		}
	}

	// FontAwesome icon class for a widget type.
	function add_widget_icon_<?=$this->id?>(type) {
		switch (type) {
			case 'kpi':             return 'fas fa-chart-simple';
			case 'chart':           return 'fas fa-chart-pie';
			case 'list':            return 'fas fa-list-ul';
			case 'editable_widget': return 'fas fa-pen';
			default:                return 'fas fa-table-cells-large';
		}
	}

	// Toggle edit mode on/off
	function editMode_<?=$this->id?>(enabled) {
		// Guard the change handler against the 'change' setStatic() fires below.
		applying_edit_change_<?=$this->id?> = true;
		if (enabled) { // enable
			$('#layout_wrapper_<?=$this->id?> .layout_toolbar .show-hide').removeClass('hide');
			// Hide the "Edit layout" trigger (it lives in the page header once hoisted)
			$('#edit_layout_toggle_<?=$this->id?>').closest('.settings').addClass('hide');
			layout_<?=$this->id?>.setStatic(false);
			add_remove_buttons_<?=$this->id?>();
		} else { // disable
			$('#layout_wrapper_<?=$this->id?> .layout_toolbar .show-hide').addClass('hide');
			$('#edit_layout_toggle_<?=$this->id?>').closest('.settings').removeClass('hide');
			layout_<?=$this->id?>.setStatic(true);
			$('#layout_wrapper_<?=$this->id?> .sr-tile-remove').remove();
		}
		// Clear the guard on the next tick so any change fired synchronously OR on
		// the following microtask by setStatic() is ignored, but real user edits
		// afterward still register.
		setTimeout(function () { applying_edit_change_<?=$this->id?> = false; }, 0);
	}

	// Add a per-tile ✕ remove button to every widget missing one. Called on
	// entering edit mode and whenever a widget is added. Scoped to tiles INSIDE
	// the grid (#layout_<id>) so the toolbar's add/trash zones — which are also
	// .grid-stack-item elements — don't get a remove button.
	function add_remove_buttons_<?=$this->id?>() {
		$('#layout_<?=$this->id?> > .grid-stack-item').each(function() {
			if (!$(this).children('.sr-tile-remove').length) {
				$(this).append('<button type="button" class="sr-tile-remove" title="<?= $escaper->escapeJs($lang['RemoveWidget']) ?>" aria-label="<?= $escaper->escapeJs($lang['RemoveWidget']) ?>">&#10005;</button>');
			}
		});
	}

	// Save the layout. Pass quiet=true to persist without the "Layout saved" toast
	// (used by the Getting Started auto-hide / hide, which run outside edit mode).
	function save_layout_<?=$this->id?>(quiet) {

		// Getting the layout without the content, because we're not storing that as it's dynamically built every time the widgets are rendered
		let layout = layout_<?=$this->id?>.save(false);
        
        // Store whether there's a custom widget thats content overflows
        // Not used for now
        // let custom_widget_overflows = false;
        
        // set the custom widgets' data
        // since they're customizable, so we're refreshing the data before sending it to the server
        for(let widget of layout) {
        	if (widget.hasOwnProperty('custom') && widget.custom && widget.type == 'editable_widget') {

        		// Guard against an id-less custom widget. renderCB assigns a monotonic
        		// data-widget-id to every custom widget, so this should never fire — but
        		// without an id we can't locate the tile anyway, and an UNQUOTED empty
        		// attribute selector (`[data-widget-id=]`) throws a syntax error that
        		// would abort the ENTIRE save, losing every layout change. Skip
        		// defensively so the rest of the layout still saves.
        		if (widget.id === undefined || widget.id === null || widget.id === '') { continue; }

        		// Get the actual widget. Quote the attribute value so a stray/empty id
        		// can never break the selector.
        		let widget_el = $(`div.grid-stack-item-content.${widget.type}[data-widget-id="${widget.id}"]`);

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
                if(!quiet && result.status_message){
                    showAlertsFromArray(result.status_message);
                }

                // The layout is now a saved custom layout
                is_customized_layout_<?=$this->id?> = true;

<?php if ($is_admin) { ?>
                // Sync the org-wide default to the "Set as default for everyone"
                // checkbox — only on an interactive save. The quiet path (Getting
                // Started auto-hide/hide) runs outside edit mode where that checkbox
                // and its helper aren't in scope, and must never touch the default.
                if (!quiet) {
                    set_default_layout_<?=$this->id?>($('#default_layout_<?=$this->id?>').is(':checked'));
                }
<?php } ?>
                // Makes no sense to be able to restore to the saved layout as we just saved it. Making a change will enable that button
                $('#layout_wrapper_<?=$this->id?> .restore-layout-widget button[data-sr-restore="saved"]').prop("disabled", true);

                // Disable the save button as we just saved
                $('#save_layout_<?=$this->id?>').prop("disabled", true);

				// Since it was just saved, we have no pending changes. Stay in edit
				// mode so the user can keep arranging and save again; they leave via
				// Cancel (which exits silently when there's nothing unsaved).
                has_unsaved_changes_<?=$this->id?> = false;

                // On an interactive save, drop out of edit mode (edit -> save ->
                // view) — what the user expects from "Save", and it avoids the
                // stale "unsaved changes" prompt on a later Cancel. The quiet path
                // (Getting Started auto-hide) runs outside edit mode; don't toggle.
                if (!quiet) {
                    editMode_<?=$this->id?>(false);
                }
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
                	// neither is it the org default anymore
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

		// Ignore the 'change' that Gridstack fires when we programmatically toggle
		// static mode on entering/leaving edit mode — that isn't a user edit, and
		// counting it would falsely mark the layout as having unsaved changes.
		if (applying_edit_change_<?=$this->id?>) { return; }

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

<?php if ($is_admin) { ?>
	// POST the org-wide default status. Called from the save flow AFTER the user's
	// own layout is saved. Defined at top level — NOT inside the $(function(){})
	// ready block below — so save_layout_<?=$this->id?>()'s success callback (also
	// top level) can actually see it; otherwise it throws ReferenceError and the
	// post-save cleanup (dirty-state reset + leaving edit mode) never runs. Admin-
	// only on the client; the server enforces the admin permission too.
	function set_default_layout_<?=$this->id?>(isDefault) {
		$.ajax({
			type: "POST",
			url: BASE_URL + "/api/v2/ui/default_layout",
			data: {
				layout_name: '<?= $this->layout_name ?>',
				default: isDefault ? 1 : 0,
			},
			// No success toast: this POST is a side-effect of the layout save, which
			// already shows "Layout saved". Only surface a problem on error.
			error: function(xhr,status,error){
				if(!retryCSRF(xhr, this)) { showAlertsFromArray(xhr.responseJSON.status_message); }
			}
		});
	}
<?php } ?>

	$(function() {

		// Need to set a custom ID for custom widgets because they don't get one on their own
		let id_counter_<?=$this->id?> = 0;

		// Called when an item is added to the grid
		GridStack.renderCB = function(el, w) {

			//console.log('renderCB', w, el, $(el), $(el).closest('section'));

			// Store these, so we don't have to calculate them more than once			
			let custom = w.hasOwnProperty('custom') && w.custom;

			// For now custom widgets are fully handled here, but later if we have widgets thats data needs to be loaded, because it's not included IN the saved layout
			// we'll have to have different logic for them
			if (custom) {
				
				// The edit affordance is a centered overlay (not a corner icon): a
				// soft, click-through scrim with a single clickable pill in the middle
				// that opens the modal editor. Filled widgets reveal it on hover; empty
				// ones surface an "Add text" prompt persistently (styling in the SCSS).
				let html = `<a class='edit' title='<?= $escaper->escapeHtml($lang['EditWidgetText']) ?>'>`
					+ `<span class='edit-pill'><i class="fa-solid fa-pen-to-square"></i> <?= $escaper->escapeHtml($lang['Edit']) ?></span>`
					+ `<span class='edit-empty'><i class="fa-solid fa-plus"></i> <?= $escaper->escapeHtml($lang['AddText']) ?></span>`
					+ `</a>`;
				
				// Add the content based on the widget's name
				switch(w.name) {
					case 'WYSIWYG':
        				// We can set this as data is sanitized on the server side
                        html += `
                        	<textarea class='hide'>${w.data ?? ''}</textarea>
                        	<div class='custom-${w.name}-content'>${w.data ?? ''}</div>
                    	`;
                        
					break;
					
					default:
						// nothing ATM
				}
				
				// We can safely set it as html because it's sanitized on the server side
                $(el).html(html);

	            // Setting the widget's type as a class on the container, so we can apply type-specific css
                $(el).addClass(w.type);

				// Set the widget's ID.
				// Custom widgets need a manually-generated, stable id. Gridstack's
				// internal _id is NOT yet assigned when renderCB runs for a freshly
				// added widget, so the old `w.id = w._id` captured undefined and left
				// the new tile with no data-widget-id — breaking both the edit lookup
				// and the save serialization (both find the tile by data-widget-id).
				// The same monotonic counter serves new and loaded custom widgets alike.
				w.id = ++id_counter_<?=$this->id?>;

				// Add the widget's generated id so we can identify which layout element is for which widget data                
                $(el).attr('data-widget-id', w.id);
			} else {
			
				let layout_name = w.layout;

    			// If the layout name is not set, then we need to set it to the default layout name
    			// This line should not trigger EVER since the layout name should be set on the widget
    			if (!layout_name) {
    				layout_name = '<?= $this->layout_name ?>';
    			}

    			// Tag the tile with its widget name so another widget can target it for
    			// a live refresh (e.g. a Getting Started dismiss re-rendering What's Next).
    			$(el).attr('data-widget-name', w.name);

    			// Dynamically load the content of the widget based on its configuration
                $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/v2/ui/widget?widget_name=" + w.name + "&layout_name=" + layout_name
					+ `
	<?php
		// Need to pass the teams parameter if it's set, so we can load the correct data for the widget
		// This is needed for the 'dashboard_open' and 'dashboard_close' widgets to load the correct data
		if (isset($_GET['teams'])) {
			// Only allow comma-separated integers to prevent XSS injection into the JS template literal
			$teams_filtered = implode(',', array_filter(explode(',', $_GET['teams']), 'ctype_digit'));
			// @phan-suppress-next-line SecurityCheck-XSS -- values sanitized via ctype_digit()+array_filter() to digits-only before output
			echo "&teams={$teams_filtered}";
		} else {
			echo "";
		}
		// Need to pass the frameworks parameter if it's set, so we can load the correct data for the widget
		// This is needed for the widgets on the 'compliance_dashboard' to load the correct data
		if (isset($_GET['frameworks'])) {
			// Only allow comma-separated integers to prevent XSS injection into the JS template literal
			$frameworks_filtered = implode(',', array_filter(explode(',', $_GET['frameworks']), 'ctype_digit'));
			// @phan-suppress-next-line SecurityCheck-XSS -- values sanitized via ctype_digit()+array_filter() to digits-only before output
			echo "&frameworks={$frameworks_filtered}";
		} else {
			echo "";
		}
		// Need to pass the (singular) framework parameter if it's set, so the
		// Define Control Frameworks insights band's FIRST render of every tile
		// is already scoped to the rail's selection -- without this, the
		// initial per-widget fetch below would show "All frameworks" totals
		// and only self-correct a moment later once governance-frameworks.js's
		// own framework-change refresh (window.srRefreshLayoutWidgets()) runs,
		// which is exactly the flash-then-correct this plan exists to avoid.
		// Distinct from the 'frameworks' (plural, comma-list) param above,
		// which belongs to compliance_dashboard's multi-select scope.
		if (isset($_GET['framework']) && ctype_digit((string)$_GET['framework'])) {
			// @phan-suppress-next-line SecurityCheck-XSS -- validated via ctype_digit() to digits-only before output
			echo "&framework=" . $_GET['framework'];
		}
		// Need to pass the incident dashboard date-range params if set, so each
		// incident widget's server-side query can scope to the selected range.
		if (isset($_GET['im_range'])) {
			// Whitelist to a safe token (7d/30d/90d/ytd/all/custom).
			$im_range_filtered = preg_replace('/[^a-z0-9]/', '', strtolower((string)$_GET['im_range']));
			// @phan-suppress-next-line SecurityCheck-XSS -- reduced to [a-z0-9] before output
			echo "&im_range={$im_range_filtered}";
		}
		if (isset($_GET['im_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['im_from'])) {
			// @phan-suppress-next-line SecurityCheck-XSS -- matched against a strict YYYY-MM-DD pattern before output
			echo "&im_from=" . $_GET['im_from'];
		}
		if (isset($_GET['im_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['im_to'])) {
			// @phan-suppress-next-line SecurityCheck-XSS -- matched against a strict YYYY-MM-DD pattern before output
			echo "&im_to=" . $_GET['im_to'];
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

                        // Getting Started: cap the visible cards to 3 (+ Show more), or
                        // auto-remove the tile once every applicable step is complete.
                        if (w.name === 'getting_started') {
                            init_getting_started_<?=$this->id?>(el);
                        }
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
<?php if (in_array($this->layout_name, ['home', 'risk_dashboard', 'compliance_dashboard', 'governance_dashboard', 'incident_dashboard', 'define_tests_insights', 'define_frameworks_insights'], true)) { ?>
            	// KPI-style dashboards use a shorter row so KPI stat-tiles (h2) read
            	// as compact cards (~120px) instead of tall, half-empty cells, and
            	// their charts stay proportionate — matches the dashboard design
            	// system's tile proportion across home, compliance, governance, the
            	// Incident Management dashboard, and the Define Tests insights band.
            	cellHeight: 60,
            	// Reflow instead of shrink. With no breakpoints Gridstack keeps 12
            	// columns at every width, so a band of six w2 tiles just divides
            	// the available space: at an 800px viewport each tile was 69px
            	// wide and every label truncated to "TOT…" / "PAS…" / "FAI…".
            	// The numbers stayed readable and the words -- which say what the
            	// numbers COUNT -- did not.
            	//
            	// The re-layout MODE matters as much as the breakpoints. Gridstack's
            	// default scales widget widths with the column count, so a w2 tile
            	// in 12 columns becomes w1 in 6 and you get six across again, just
            	// narrower -- no help at all. 'move' keeps the widths but also
            	// keeps each tile's x, so tiles that now overlap get pushed DOWN:
            	// six tiles became four ragged rows at 1366px. 'list' is the one
            	// that re-packs them in order, left to right, wrapping when the
            	// row is full.
            	//
            	// Tiles are w2, so the column count IS the row capacity: 12 -> 6
            	// across (the wide-screen default, unchanged), 6 -> 3, 4 -> 2.
            	//
            	// Reflow LATE. Every extra row costs the content below the band
            	// ~120px, which is the opposite of what a narrow screen needs, so
            	// the band only wraps once a tile genuinely can't hold its
            	// contents -- around 1000px, where six across leaves ~120px each.
            	// A 1366 laptop keeps its single row.
            	columnOpts: {
            		breakpointForWindow: true,
            		layout: 'list',
            		breakpoints: [
            			{ w: 700, c: 4 },
            			{ w: 1000, c: 6 },
            		],
            	},
<?php } ?>
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

		// Whenever a widget is added/removed the Add-widget popover list needs refreshing
		layout_<?=$this->id?>.on('added removed', function(event, items) {
			refresh_add_popover_<?=$this->id?>();
			// A newly-added widget needs its per-tile ✕ remove button
			if (event.type === 'added') {
				add_remove_buttons_<?=$this->id?>();
			}
        });
		
		// Whenever a widget is removed there might be some cleanup needed		
		layout_<?=$this->id?>.on('removed', function(event, items) {
			// console.log(event, items);
        });
		
		// Enter edit mode from the "Edit layout" button (hoisted into the header)
		$(document).on('click', '#edit_layout_toggle_<?=$this->id?>', function(e) {
			e.preventDefault();
			editMode_<?=$this->id?>(true);
		});

		// Cancel — leave edit mode, reverting any unsaved changes back to the
		// layout that was showing when editing began.
		$(document).on('click', '#cancel_layout_<?=$this->id?>', function(e) {
			e.stopPropagation();
			if (has_unsaved_changes_<?=$this->id?>) {
				confirm('<?= $escaper->escapeJs($lang['ConfirmDisableEditModeWithPendingChanges'])?>', () => {
					// restore_layout() reloads the layout AND turns edit mode off
					restore_layout_<?=$this->id?>(is_customized_layout_<?=$this->id?> ? 'saved' : 'default');
				});
			} else {
				editMode_<?=$this->id?>(false);
			}
		});

		// Remove a widget via its per-tile ✕ button
		$(document).on('click', '#layout_wrapper_<?=$this->id?> .sr-tile-remove', function(e) {
			e.stopPropagation();
			layout_<?=$this->id?>.removeWidget($(this).closest('.grid-stack-item')[0]);
		});

		// Logic related to saving
    	$(document).on('click', '#save_layout_<?=$this->id?>', function(e) {e.stopPropagation();

    		// Only warn about saving if it'd overwrite a previously saved custom layout
    		if (is_customized_layout_<?=$this->id?>) {
    		
    			var confirmSaveQuestion = '<?= $escaper->escapeJs($lang['ConfirmSave'])?>';

<?php if ($is_admin) { ?>
				// Warn the admin user that the layout is set as default layout and changing it will affect other users
				if ($("#default_layout_<?=$this->id?>").is(':checked')) {
					confirmSaveQuestion = '<?= $escaper->escapeJs($lang['ConfirmSaveAdminDefault'])?>';
				}
<?php } ?>
            	confirm(confirmSaveQuestion, () => {
        			save_layout_<?=$this->id?>();
            	});
        	} else {
        		save_layout_<?=$this->id?>();
        	}
    	});

		// Click-to-add helper: drop a widget straight into the grid (replaces the
		// old select-then-drag flow). renderCB loads the real content afterward.
		function add_widget_to_grid_<?=$this->id?>(config, isCustom) {
			var opts = {
				w: config.w, h: config.h,
				minW: config.minW, minH: config.minH,
				name: config.name, type: config.type,
				layout: config.layout || '<?= $this->layout_name ?>',
				content: '&nbsp;'
			};
			if (isCustom) { opts.custom = true; opts.data = ''; }
			layout_<?=$this->id?>.addWidget(opts);
		}

		// --- Add-widget popover ---
		// Toggle the popover from the "+ Add widget" button.
		$(document).on('click', '#add_widget_toggle_<?=$this->id?>', function(e) {
			e.stopPropagation();
			let $pop = $('#add_popover_<?=$this->id?>');
			let opening = $pop.hasClass('hide');
			$('#add_custom_panel_<?=$this->id?>').addClass('hide');
			$('#add_list_<?=$this->id?>, #add_custom_toggle_<?=$this->id?>').removeClass('hide');
			if (opening) { refresh_add_popover_<?=$this->id?>(); }
			$pop.toggleClass('hide', !opening);
			$(this).attr('aria-expanded', opening ? 'true' : 'false');
		});

		// Click a widget in the list -> drop it in; the 'added' event refreshes the
		// list so the added widget drops off. Popover stays open to add more.
		$(document).on('click', '#add_list_<?=$this->id?> .sr-add-item[data-widget]', function(e) {
			e.stopPropagation();
			add_widget_to_grid_<?=$this->id?>(widget_configurations_<?=$this->id?>.get($(this).attr('data-widget')), false);
		});

		// Close the popover on any outside click.
		$(document).on('click', function(e) {
			if (!$(e.target).closest('.sr-add').length) {
				$('#add_popover_<?=$this->id?>').addClass('hide');
				$('#add_widget_toggle_<?=$this->id?>').attr('aria-expanded', 'false');
			}
		});

<?php if ($has_custom_widgets) { ?>
		// "Create custom widget" -> swap the list for the custom panel.
		$(document).on('click', '#add_custom_toggle_<?=$this->id?>', function(e) {
			e.stopPropagation();
			$('#add_list_<?=$this->id?>, #add_custom_toggle_<?=$this->id?>').addClass('hide');
			$('#add_custom_panel_<?=$this->id?>').removeClass('hide');
		});
		// Back to the widget list.
		$(document).on('click', '#add_custom_back_<?=$this->id?>', function(e) {
			e.stopPropagation();
			$('#add_custom_panel_<?=$this->id?>').addClass('hide');
			$('#add_list_<?=$this->id?>, #add_custom_toggle_<?=$this->id?>').removeClass('hide');
		});
		// Add the chosen custom widget type; edit its content via the tile's edit button.
		$(document).on('click', '#add_custom_confirm_<?=$this->id?>', function(e) {
			e.stopPropagation();
			let type = $('#widget_creator_<?=$this->id?>').val();
			if (type === '0') { return; }
			add_widget_to_grid_<?=$this->id?>(custom_widget_configurations_<?=$this->id?>.get(type), true);
			$('#widget_creator_<?=$this->id?>').val(0);
			$('#add_popover_<?=$this->id?>').addClass('hide');
			$('#add_custom_panel_<?=$this->id?>').addClass('hide');
			$('#add_list_<?=$this->id?>, #add_custom_toggle_<?=$this->id?>').removeClass('hide');
			$('#add_widget_toggle_<?=$this->id?>').attr('aria-expanded', 'false');
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
			e.preventDefault();

			// Flush the HugeRTE editor's live content back into its underlying
			// textarea. The editor only auto-saves on its own 'change' event, so a
			// user who types then clicks Save immediately would otherwise have their
			// latest edits read as stale/empty. triggerSave() syncs every editor.
			if (typeof hugerte !== 'undefined') {
				hugerte.triggerSave();
			}

			// Get the modal window
    		let modal = $(this).closest('div.modal');
    		
			// Transfer the data back to the widget
    		let source = modal.find('input.source').val();
    		let content = modal.find('textarea').val();
			
			// Get the source widget. Quote the attribute value so an empty/stray id
			// can't throw a selector syntax error and silently swallow the edit.
			let source_widget = $(`.grid-stack-item-content.editable_widget[data-widget-id="${source}"]`);

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
    	$(document).on('click', '#layout_wrapper_<?=$this->id?> .restore-layout-widget button', function(e) {e.stopPropagation(); confirm('<?= $escaper->escapeJs($lang['ConfirmRestoreLayout'])?>', () => {
			restore_layout_<?=$this->id?>($(this).attr('data-sr-restore'));
    	})});

        // ===================== Getting Started widget =====================
        // Post-render hook: page through the cards 3 at a time with prev/next
        // buttons (the tile stays a fixed height — never grows/scrolls), or
        // auto-remove the tile once every applicable step is complete.
        const GS_PAGE_<?=$this->id?> = 3;

        function init_getting_started_<?=$this->id?>(containerEl) {
            let gs = containerEl.querySelector('.sr-gs');
            if (!gs) return;
            // All applicable steps done -> remove the tile and persist quietly.
            if (gs.getAttribute('data-gs-complete') === '1') {
                setTimeout(function(){ remove_gs_widget_<?=$this->id?>(containerEl); }, 0);
                return;
            }
            let cards = Array.prototype.slice.call(gs.querySelectorAll('.sr-gs__cards > .sr-gs__card'));
            let pagerBox = gs.querySelector('.sr-gs__more');
            let PAGE = GS_PAGE_<?=$this->id?>;
            let start = 0;
            function paint() {
                // Show only the current window of cards.
                cards.forEach(function(c, i){ c.classList.toggle('sr-gs__card--hidden', i < start || i >= start + PAGE); });
                if (!pagerBox) return;
                if (cards.length > PAGE) {
                    let from = start + 1;
                    let to = Math.min(start + PAGE, cards.length);
                    let atStart = start <= 0;
                    let atEnd = to >= cards.length;
                    pagerBox.innerHTML =
                        '<div class="sr-gs__pager">'
                      +   '<button type="button" class="sr-gs__pager-btn" data-gs-prev aria-label="<?= $escaper->escapeJs($lang['GSPrevCards']) ?>"' + (atStart ? ' disabled' : '') + '>&#8249;</button>'
                      +   '<span class="sr-gs__pager-label"></span>'
                      +   '<button type="button" class="sr-gs__pager-btn" data-gs-next aria-label="<?= $escaper->escapeJs($lang['GSNextCards']) ?>"' + (atEnd ? ' disabled' : '') + '>&#8250;</button>'
                      + '</div>';
                    let label = '<?= $escaper->escapeJs($lang['GSPagerLabel']) ?>'
                        .replace('{from}', from).replace('{to}', to).replace('{total}', cards.length);
                    pagerBox.querySelector('.sr-gs__pager-label').textContent = label;
                } else {
                    pagerBox.innerHTML = '';
                }
            }
            if (pagerBox && !pagerBox.dataset.gsBound) {
                pagerBox.dataset.gsBound = '1';
                pagerBox.addEventListener('click', function(e){
                    if (e.target.closest('[data-gs-prev]')) { start = Math.max(0, start - PAGE); paint(); }
                    else if (e.target.closest('[data-gs-next]')) { if (start + PAGE < cards.length) { start += PAGE; paint(); } }
                });
            }
            paint();
        }

        // Reload just the Getting Started widget's HTML (after a dismiss) and re-init.
        function reload_gs_widget_<?=$this->id?>(containerEl) {
            $.ajax({
                type: 'GET',
                url: BASE_URL + '/api/v2/ui/widget?widget_name=getting_started&layout_name=<?= $this->layout_name ?>',
                success: function(result){
                    $(containerEl).html(result.data).addClass('getting_started');
                    init_getting_started_<?=$this->id?>(containerEl);
                },
                error: function(xhr){ if(!retryCSRF(xhr, this) && xhr.responseJSON) { showAlertsFromArray(xhr.responseJSON.status_message); } }
            });
        }

        // Re-fetch a single (non-custom) widget tile's HTML by name. Used to live-
        // update a widget whose data changed as a side effect of another widget's
        // action — e.g. dismissing a Getting Started card also hides the equivalent
        // What's Next setup item, so What's Next re-renders without a page reload.
        //
        // extraParams (optional, e.g. '&framework=5') rides straight through to
        // the widget-fetch URL -- added for the Define Control Frameworks
        // insights band (rail-scoped, unlike every other layout this refresh
        // mechanism serves), so a framework change can re-scope the tiles
        // without a page reload. Every existing caller omits it (undefined ->
        // ''), so this is a pure addition: nothing about the refresh contract
        // changes for a layout that never passes one.
        function refresh_widget_by_name_<?=$this->id?>(name, extraParams) {
            let el = document.querySelector('#layout_wrapper_<?=$this->id?> .grid-stack-item-content[data-widget-name="' + name + '"]');
            if (!el) return;
            $.ajax({
                type: 'GET',
                url: BASE_URL + '/api/v2/ui/widget?widget_name=' + encodeURIComponent(name) + '&layout_name=<?= $this->layout_name ?>' + (extraParams || ''),
                success: function(result){ $(el).html(result.data); },
                error: function(xhr){ if(!retryCSRF(xhr, this) && xhr.responseJSON) { showAlertsFromArray(xhr.responseJSON.status_message); } }
            });
        }

        // Re-fetch EVERY tile in this layout. A page whose own actions change the
        // data behind a band -- adding, retiring or deleting a test on Define
        // Tests -- needs the tiles to follow, and the tiles are server-rendered
        // per widget, so the only honest refresh is to ask for them again.
        //
        // Published on a per-layout registry so a page's own JS can reach it
        // without knowing this instance's generated id; srRefreshLayoutWidgets()
        // below is the front door.
        function refresh_all_widgets_<?=$this->id?>(extraParams) {
            document
                .querySelectorAll('#layout_wrapper_<?=$this->id?> .grid-stack-item-content[data-widget-name]')
                .forEach(function (el) {
                    refresh_widget_by_name_<?=$this->id?>(el.getAttribute('data-widget-name'), extraParams);
                });
        }

        window.srLayoutRefreshers = window.srLayoutRefreshers || {};
        window.srLayoutRefreshers['<?= $escaper->escapeJs($this->layout_name) ?>'] = refresh_all_widgets_<?=$this->id?>;

        // A no-op when the layout isn't on the page (or hasn't finished
        // initialising), so a caller never has to guard the call itself.
        // extraParams is optional and opaque here -- forwarded verbatim to
        // every tile's own widget-fetch URL (see refresh_widget_by_name_ above).
        window.srRefreshLayoutWidgets = window.srRefreshLayoutWidgets || function (layout_name, extraParams) {
            var refresher = (window.srLayoutRefreshers || {})[layout_name];
            if (typeof refresher === 'function') {
                refresher(extraParams);
            }
        };

        // Remove the Getting Started tile from the grid and persist quietly (so it
        // doesn't recompute back next load). Users re-add it from the picker.
        function remove_gs_widget_<?=$this->id?>(containerEl) {
            let item = containerEl.closest('.grid-stack-item');
            if (!item) return;
            layout_<?=$this->id?>.removeWidget(item);
            save_layout_<?=$this->id?>(true);
        }

        // Per-card dismiss: PUT the dismissal for this user, then reload the widget.
        $(document).on('click', '#layout_wrapper_<?=$this->id?> .sr-gs__x[data-gs-dismiss]', function(e) {
            e.preventDefault(); e.stopPropagation();
            let key = $(this).attr('data-gs-dismiss');
            let containerEl = $(this).closest('.grid-stack-item-content')[0];
            $.ajax({
                type: 'PUT',
                url: BASE_URL + '/api/v2/ui/getting_started/dismissals/' + encodeURIComponent(key),
                success: function(){
                    reload_gs_widget_<?=$this->id?>(containerEl);
                    // Live-update What's Next: the dismissal also hides the equivalent
                    // setup item there. Harmless no-op if the key has no What's Next
                    // counterpart or the widget isn't on this layout.
                    refresh_widget_by_name_<?=$this->id?>('whats_next');
                },
                error: function(xhr){ if(!retryCSRF(xhr, this) && xhr.responseJSON) { showAlertsFromArray(xhr.responseJSON.status_message); } }
            });
        });

        // "Start the Test" icon in the Upcoming Tests list. If an audit already
        // exists for the test (data-audit-id), go straight to it — never start a
        // duplicate. Otherwise initiate the audit (data-test-id) and open the new
        // one. The is-busy guard prevents a double-click from firing twice. The
        // icon is only rendered for users who may initiate; the endpoint re-checks.
        $(document).on('click', '#layout_wrapper_<?=$this->id?> .sr-wn-run', function(e) {
            e.preventDefault(); e.stopPropagation();
            let btn = $(this);
            if (btn.hasClass('is-busy')) return;   // block double-click
            btn.addClass('is-busy');

            let auditId = btn.attr('data-audit-id');
            if (auditId) {
                // A test already exists — take them to it, don't create a new one.
                window.location = BASE_URL + '/compliance/testing.php?id=' + auditId;
                return;
            }

            let testId = btn.attr('data-test-id');
            $.ajax({
                type: 'POST',
                url: BASE_URL + '/api/v2/compliance/audit_initiation/initiate',
                data: { type: 'test', id: testId },
                success: function(result){
                    let newId = result && result.data && result.data.audit_id;
                    if (newId) {
                        window.location = BASE_URL + '/compliance/testing.php?id=' + newId;
                    } else {
                        window.location.reload();
                    }
                },
                error: function(xhr){
                    btn.removeClass('is-busy');
                    if(!retryCSRF(xhr, this) && xhr.responseJSON) { showAlertsFromArray(xhr.responseJSON.status_message); }
                }
            });
        });

<?php if ($is_admin) { ?>
        // Ticking "Set as default for everyone" is a pending change committed on Save
		$('#default_layout_<?=$this->id?>').on('change', function() {
			$('#save_layout_<?=$this->id?>').prop('disabled', false);
			has_unsaved_changes_<?=$this->id?> = true;
		});

		// set_default_layout_<?=$this->id?>() is defined at top level (above the
		// ready block) so the top-level save flow can call it.
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

		// Populate the Add-widget popover with the widgets not on the loaded layout
		refresh_add_popover_<?=$this->id?>();
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