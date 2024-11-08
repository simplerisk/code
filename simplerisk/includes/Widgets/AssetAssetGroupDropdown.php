<?php

namespace includes\Widgets;

class AssetAssetGroupDropdown {
    
    /**
     * Randomly generated unique id for the widget. Can be modified before rendering.
     * Type: string
     */
    public $id;

    /**
     * Language key of the placeholder text in the input box when it's empty
     * Type: string
     * Default: AssetAssetGroupWidgetPlaceholder
     */
    public $placeholderLangKey = 'AssetAssetGroupWidgetPlaceholder';

    /**
     * Allow the selection of multiple items. In case of a multiselect `[]` is appended to the name.
     * Type: bool
     * Default: true
     */
    public $multiselect = true;

    /**
     * Name of the select generated that will be sent when the form is submitted. In case of a multiselect `[]` is appended to the name.
     * Type: string
     * Default: assets_asset_groups
     */
    public $name = 'assets_asset_groups';
    
    /**
     * List of additional classes for the widget.
     * The widget always have the 'asset-assetgroup-select' class or 'asset-assetgroup-select-wide' class in case of a wide widget.
     * 
     * Type: array[string, string, ...]
     * Default: []
     */
    public $additionalClasses = [];
    
    /**
     * Whether the widget rendered is wide enough to have the dropdown part fit the assets/asset groups.
     * If the widget itself is wide enough we don't have to alter the width of the dropdown to make the assets and asset groups properly fit 
     * Type: bool
     * Default: false
     */
    public $wideWidget = false;
    
    /**
     * Should the widget only display the selected Assets/Asset groups
     * Type: bool
     * Default: false
     */
    public $viewOnly = false;

    /**
     * How the widget render the Assets and Asset Groups in view only mode.
     * 'button' mode renders them as they appear in the dropdown
     * 'text' mode renders them as text. Asset groups are marked by square brackets, like 'Asset, [Asset Group]' 
     * Type: string
     * Default: button
     */
    public $viewType = 'button';

    /**
     * Should the widget display the instructions under the input field.
     * Type: bool
     * Default: true
     */
    public $instructions = true;

    /**
     * Language key of the instruction text.
     * Type: string
     * Default: AssetAssetGroupWidgetInstructions
     */
    public $instructionsLangKey = 'AssetAssetGroupWidgetInstructions';
    
    /**
     * Class of the instruction element
     * Type: string
     * Default: asset-assetgroup-instructions
     */
    public $instructionsClass = 'asset-assetgroup-instructions';

    /**
     * Id of the item the widget should display the selected Asset/Asset Groups for.
     * Both itemId and itemType needs to be set before rendering the widget or these settings will not be used.
     * Type: int
     * Default: null
     */
    public $itemId = null;

    /**
     * Type of the item the widget should display the selected Asset/Asset Groups for.
     * Both itemId and itemType needs to be set before rendering the widget or these settings will not be used.
     * Type: string
     * Default: null
     */
    public $itemType = null;
    
    /**
     * Whether the widget's data should be rendered into the initialization code and loaded from there(false)
     * or it should load the data through an async API call to make the page load faster(true).
     * Type: bool
     * Default: true
     */
    public $lazyInit = true;

    // List of the attributes allowed to be changed through the constructor to be able to customer
    private $availableSettingNames = [
        'itemId',
        'itemType',
        'lazyInit',
        'wideWidget',
        'viewOnly',
        'viewType',
        'placeholderLangKey',
        'multiselect',
        'name',
        'additionalClasses',
        'instructions',
        'instructionsClass',
        'instructionsLangKey',
    ];

    /**
     * Initialize the widget with custom settings. Please refer to the documentation of individual attributes
     * 
     * @param array $settings the array that can contain multiple attributes to set 
     * 
     * Available attributes to set through the constructor: 
     * 
     *  'itemId'
     *  'itemType'
     *  'lazyInit'
     *  'wideWidget'
     *  'viewOnly'
     *  'viewType'
     *  'placeholderLangKey'
     *  'multiselect'
     *  'name'
     *  'additionalClasses'
     *  'instructions'
     *  'instructionsClass'
     *  'instructionsLangKey'
     * 
     */
    public function __construct($settings = []) {
	    $this->id = generate_token(10);

	    if (!empty($settings)) {
	        // Set the data received in the settings
            foreach ($settings as $name => $value) {
                // but only if the name is in the list of available setting manes
                if (in_array($name, $this->availableSettingNames)) {
                    $this->{$name} = $value;
                }
            }
        }
	}

	/**
	 * Renders the widget.
	 */
	public function render() {
	    echo $this->renderString();
	}
	
	/**
	 * Returns the string representation of the rendered widget that can be inserted into strings like
	 * echo "<div class='span10'>{$widget->renderString()}</span>";
	 * 
	 * @return string
	 */
    public function renderString() {
        global $escaper, $lang;

        // In case we just want to display the selected items
        if ($this->viewOnly) {
            
            $html = '';
            if ($this->itemId && $this->itemType) {
                
                // Get the assets/asset groups associated to the item
                $selected_items = get_assets_and_asset_groups_of_type($this->itemId, $this->itemType, true);
                if ($selected_items) {
                    $html .= "<div class='asset-assetgroup-select-view'>";
                    switch ($this->viewType) {
                        case 'button':
                            foreach($selected_items as $item) {
                                $html .= "<button class='btn btn-secondary {$item['class']}' role='button' aria-disabled='true'>{$escaper->escapeHtml($item['name'])}</button>";
                            }
                            break;
                        case 'text':
                            $html .= $escaper->escapeHtml(implode(',', array_map(function($item) {
                                return $item['class'] == 'asset' ? $item['name'] : "[{$item['name']}]";
                            }, $selected_items)));
                            break;
                    }
                    $html .= "</div>";
                }
            }
            return $html;
        }

        // Render the select using the provided settings(or the defaults)
        $html = "<select id='{$this->id}' class='asset-assetgroup-select" . ($this->wideWidget ? '-wide ' : ' ')  . "" . implode(' ', $this->additionalClasses) . "' name='{$this->name}" . ($this->multiselect ? "[]' multiple" : "'") . " placeholder='{$escaper->escapeHtml($lang[$this->placeholderLangKey])}'></select>";
		if ($this->instructions) {
		    // Add the instructions if they're needed
		    $html .= "<span class='{$this->instructionsClass}'>{$escaper->escapeHtml($lang[$this->instructionsLangKey])}</span>";
		}
		
		$html .= "
            <script>
                $(document).ready(function(){
                    var select_{$this->id} = $('#{$this->id}').selectize({
                        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
                        delimiter: ',',
                        create: function (input){
                            return { id:'new_asset_' + input, name:input };
                        },
                        persist: false,
                        valueField: 'id',
                        labelField: 'name',
                        searchField: 'name',
                        sortField: 'name',
                        optgroups: [
                            {class: 'asset', name: 'Standard Assets'},
                            {class: 'group', name: 'Asset Groups'}
                        ],
                        optgroupField: 'class',
                        optgroupLabelField: 'name',
                        optgroupValueField: 'class',
                        preload: true,
                        render: {
                            item: function(item, escape) {
                                return '<div class=\"' + item.class + '\">' + escape(item.name) + '</div>';
                            }
                        },
        ";

		// Load the data from the API endpoint if it's a lazy initialization
		if ($this->lazyInit) {
		    $html .= "
                        onInitialize: function() {
                            $('#{$this->id}').parent().find('.selectize-control div').block({message:'<i class=\"fa fa-spinner fa-spin\" style=\"font-size:24px\"></i>'});
                        },
                        load: function(query, callback) {
                            if (query.length) {
                                return callback();
                            }
                            $.ajax({
                                url: BASE_URL + '/api/asset-group/options',
                                type: 'GET',
                                dataType: 'json',
                                " . ($this->itemId && $this->itemType ? "data: {id: '{$this->itemId}', type: '{$this->itemType}'},": '') . "
                                error: function() {
                                    callback();
                                },
                                success: function(res) {
                                    var data = res.data;
                                    var control = select_{$this->id}[0].selectize;
                                    var selected_ids = [];
                                    // Have to do it this way, because addition with simple addOption() will
                                    // bug out when we deselect an option(it wouldn't be added back to the
                                    // list of selectable items)
                                    len = data.length;
                                    for (var i = 0; i < len; i++) {
                                        var item = data[i];
                                        item.id += '_' + item.class;
                                        control.registerOption(item);
                                        if (item.selected == '1') {
                                            selected_ids.push(item.id);
                                        }
                                    }
                                    if (selected_ids.length) {
                                        control.setValue(selected_ids);
                                    }
                                },
                                complete: function() {
                                    $('#{$this->id}').parent().find('.selectize-control div').unblock({message:null});
                                }
                            });
                        },
            ";
		} else { // In case we want to render the whole thing into the initialization code
		    
		    // load all the items(selected items are marked if there's any)
		    $items = get_assets_and_asset_groups_of_type($this->itemId, $this->itemType, false);
		    if ($items) {
		        
		        $selected_item_ids = [];
		        $html .= "
                        options: [
                ";

		        // render the options...
		        foreach($items as $item) {
		            $id = "{$item['id']}_{$item['class']}";
		            $html .= "
                            {id: '{$id}', name: '{$escaper->escapeJs($item['name'])}', class: '{$item['class']}'},
                    ";

		            // ...gather the ids of selected items...
		            if ($item['selected']) {
		                $selected_item_ids []= $id;
		            }
		        }

		        $html .= "
                        ],
                ";

		        // ...and if there's any selected item render the initialization code to mark them as selected
		        if (!empty($selected_item_ids)) {
		            $html .= "
                        items: ['" . implode("', '", $selected_item_ids) . "'],
                    ";
		        }
		    }
		}

		$html .= "
                    });
                });
            </script>
        ";

		return $html;
        
	}
}
?>