function closeSearchBox()
{
    document.getElementById("selections").style.display = "none";
}

/**
* Make filter dropdown options html
* 
* @param options : value, name
* @param columnName : Dynammick risk tables column name
* @param select : Select container
* @param hidden_location_filters
*/
function makeFitlerOptionHTML(options, columnName, select, hidden_location_filters)
{
    options.forEach( function ( option ) {
        if(option.class !== undefined)
        {
            var dataClass = option.class;
        }
        else
        {
            var dataClass = "";
        }

        if( columnName == "location" && hidden_location_filters )
        {
            if(hidden_location_filters.indexOf(option.value) > -1)
            {
                select.append( '<option selected value="'+option.value+'" data-class="' + dataClass + '">'+option.text+'</option>' )
            }
            else
            {
                select.append( '<option value="'+option.value+'" data-class="' + dataClass + '">'+option.text+'</option>' )
            }
        }
        else
        {
            select.append( '<option value="'+option.value+'" data-class="' + dataClass + '">'+option.text+'</option>' )
        }
    });
}

/**
* Make HTML for filter else dropdown
* 
* @param index
* @param columnName
*/
function makeFilterNonDropdownHTML(index, columnName, fieldType)
{
    var HTML = '<div style="min-width: 150px; text-align: center">';
    var date_format = $("#date_format").val();
    if(typeof(date_format) == "undefined") date_format = "YYYY-MM-DD";

    if(fieldType == "date" || columnName == "submission_date" || columnName == "review_date" || columnName == "planning_date" || columnName == "closure_date" || columnName == "mitigation_date")
    {
        HTML += '<input type="text" data-index="'+ index +'" class="dynamic-column-filter dynamic-column-text-filter" placeholder="'+date_format+'" data-name="'+columnName+'">'
    }
    else if(columnName.indexOf("calculated_risk") !== -1 || columnName.indexOf("residual_risk") !== -1 || columnName.indexOf("contributing_likelihood") !== -1 || columnName.indexOf("contributing_impact") !== -1 || columnName == "classic_likelihood" || columnName == "classic_impact" || columnName == "days_open")
    {
        /**
        * >  : 0
        * >= : 1
        * =  : 2
        * <= : 3
        * <  : 4
        */
        HTML += '<SELECT class="sub-filter-box-1 dynamic-column-filter dynamic-column-operator-filter" data-index="'+ index +'" data-name="'+ columnName + "_operator" +'"><option value="0">></option><option value="1">>=</option><option value="2">=</option><option value="3"><=</option><option value="4"><</option></SELECT>&nbsp;&nbsp;<input type="text" data-index="'+ index +'" class="sub-filter-box-2 dynamic-column-filter dynamic-column-text-filter" data-name="'+columnName+'">';
    }
    else
    {
        HTML += '<input type="text" data-index="'+ index +'" class="dynamic-column-filter dynamic-column-text-filter" data-name="'+columnName+'">';
    }
    
    HTML += '</div>';
    
    return HTML;
}

$(document).ready(function(){
    if($(".risk-datatable").length){
        var sortColumns = [["calculated_risk", "desc"], ["id", "asc"], ["subject", "asc"], ["residual_risk", "desc"]];
        var defaultSortColumnIndex = 0;
        var defaultSortColumn = sortColumns[$("#sort").val()];
        if(defaultSortColumn == undefined){
            defaultSortColumn = sortColumns[defaultSortColumnIndex];
        }
        var risk_columns = $("#risk_columns").val();
        var mitigation_columns = $("#mitigation_columns").val();
        var review_columns = $("#review_columns").val();
        var scoring_columns = $("#scoring_columns").val();
        var unassigned_columns = $("#unassigned_columns").val();
        var risk_mapping_columns = $("#risk_mapping_columns").val();
        var selected_columns = risk_columns.concat(mitigation_columns, review_columns, scoring_columns, unassigned_columns, risk_mapping_columns);
        var columnOptions = [];
        var columnNames = [];
        $(".risk-datatable tr.main th").each(function(index){
            var name = $(this).data('name');
            if(columnNames.indexOf(name) > -1){
                return;
            }
            columnNames.push(name);
            if(selected_columns.indexOf(name) == -1) {
                columnOptions.push(index);
            }
            if(defaultSortColumn != undefined && name == defaultSortColumn[0]) {
                defaultSortColumnIndex = index;
            }
        });
        
        // Save filter dropdown was changed or not
        var changedFitler = false;

        // Create multiselect of table column filter dropdowns
        var createMultiSelectColumnFilter = function(selfTable, filterContainer){
            filterContainer || (filterContainer = $(selfTable.table().header()).find('tr.filter'));
            $('.dynamic-column-dropdown-filter', filterContainer).multiselect({
                enableFiltering: true,
                buttonWidth: '100%',
                maxHeight: 150,
                numberDisplayed: 1,
                enableCaseInsensitiveFiltering: true,
                includeSelectAllOption: true,
                onSelectAll : function(){
                    changedFitler = true;
                },
                onDeselectAll : function(){
                    changedFitler = true;
                },
                onChange: function(){
                    changedFitler = true;
                },
                optionClass: function(element) {
                    return $(element).data('class');
                },

                onDropdownShown: function(){
                },
                onDropdownHide: function(){
                    if(changedFitler){
                        selfTable.draw()
                        changedFitler = false;
                    }
                }
            })
            selfTable.columns.adjust()
        }
        
        // Set initial_load to true
        var initial_load = true;
        
        // Set hidden_location_filters param
        if($("#hidden_location_filters").val())
        {
            var hidden_location_filters = $("#hidden_location_filters").val().split(",");
            initial_load = false;
        }
        else
        {
            var hidden_location_filters = false;
        }
        
        var riskDataTables = [];
        var unique = [];
        var unassigned_option = $("#unassigned_option").val();
        if($("#custom_column_filters").val()){
            var column_filters = JSON.parse($("#custom_column_filters").val());
            var field_values = [];
            for (var i = 0; i < column_filters.length; i++) {
                field_values[column_filters[i][0]] = column_filters[i][1];
            }
        } else column_filters = [];
        var columnFilters = [];
        var orderColumnName = "";
        var orderDir = "";
        $(".risk-datatable").each(function(index){
            var $this = $(this);
            var table_columns = [];
            var risk_cell_indexs = [];
            $('tr.main th', $this).each(function(index){
                var column_name = $(this).data('name');
                table_columns[index] = column_name;
                if(column_name.indexOf("calculated_risk") !== -1 || column_name.indexOf("residual_risk") !== -1) risk_cell_indexs.push(index);
            });

            // Attaching to the event that's fired BEFORE the xhr
            $this.on('preXhr.dt', function(e, settings, data) {
            	// to go through the column data being sent
            	$.each( data['columns'], function( index, column ){
            		// and remove those that aren't used on the server side
            		// to be able to stay below PHP's default `max_input_vars` setting(1000)
            		delete column['data'];
            		delete column['searchable'];
            		delete column['orderable'];
            		delete column['search'];
            	});
            });

            var riskDatatable = $this.DataTable({
                scrollX: true,
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                deferLoading: initial_load ? null : 0, // if initial load is false, prevent initial load by setting deferloadding to 0
//                ordering: false,
                pagingType: "full_numbers",
                dom : "flrti<'.download-by-group'><'.print-by-group'><'#view-all-"+ index +".view-all'>p",
                ajax: {
                    url: BASE_URL + '/api/reports/dynamic',
                    type: "post",
                    data: function(d) {
                        d.status        = $("#status").val();
                        d.group         = $("#group").val();
                        d.sort          = $("#sort").val();
                        d.group_value   = $this.data('group');
                        d.table_columns = table_columns;
                        
                        // Set params in risks_by_teams page
                        if ($("#teams").length) {
                            d.risks_by_team     = 1;
                            d.teams             = $("#teams").val();
                            d.owners            = $("#owners").val();
                            d.ownersmanagers    = $("#ownersmanagers").val();
                        } 
                        
                        d.columnFilters = {};
                        if($('.dynamic-column-filter', $this).length)
                        {
                            $('tr.filter .dynamic-column-filter', riskDatatable.table().header()).each(function(){
                                var name = $(this).data('name');
                                d.columnFilters[name] = $(this).val();
                            })
                        }

//                        if($('.dynamic-column-text-filter', $this).length)
//                        {
//                            $('tr.filter .dynamic-column-text-filter', riskDatatable.table().header()).each(function(){
//                                var name = $(this).data('name');
//                                d.columnFilters[name] = $(this).val();
//                            })
//                        }
                        
                    },
                    error: function(xhr,status,error){
                        retryCSRF(xhr, this);
                    }
                },
                order: [[defaultSortColumnIndex, defaultSortColumn[1]]],
                columnDefs : [
                    {
                        "targets" : columnOptions,
                        "visible" : false
                    },
                    {
                        "targets" : risk_cell_indexs,
                        "className" : "risk-cell",
                    },
                ],
                initComplete: function(){
                    var params = {};
                        params.status        = $("#status").val();
                        params.group         = $("#group").val();
                        params.sort          = $("#sort").val();
                        params.group_value   = $this.data('group');
                        
                        // Set params in risks_by_teams page
                        if ($("#teams").length) {
                            params.risks_by_team     = 1;
                            params.teams             = $("#teams").val();
                            params.owners            = $("#owners").val();
                            params.ownersmanagers    = $("#ownersmanagers").val();
                        } 

                    var self = this;
                        
                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/reports/dynamic_unique_column_data",
                        data: params,
                        dataType: 'json',
                        success: function(data){
                            
                            // For visible columns on datatable
                            $("tr.filter th", self.api().table().header()).each(function(){
                                var column = $(this);

                                var columnName = column.data('name').toLowerCase();
                                var options = data[columnName];
                                column.html("");
                                if(options === undefined){
                                    $(makeFilterNonDropdownHTML(index, columnName)).appendTo( column );
                                }
                                else if(options.field_type){
                                    $(makeFilterNonDropdownHTML(index, columnName, options.field_type)).appendTo( column );
                                }
                                else{
                                    var select = $('<select class="dynamic-column-dropdown-filter dynamic-column-filter" data-index="'+ index +'" data-name="'+columnName+'" multiple><option value="_empty">'+unassigned_option+'</option></select>').appendTo( column );
                                    makeFitlerOptionHTML(options, columnName, select, hidden_location_filters);

                                }
                            })
                            $("tr.filter", self.api().table().header()).show()

                            
                            // For hidden columns on datatable
                            self.api().columns().every( function () {
                                var column = this;
                                // If this is hidden column, save dropdown html in hidden tag
                                if(!column.visible())
                                {
                                    var columnName = $(column.header()).data('name').toLowerCase();
                                    var options = data[columnName];
                                    var $hiddenContainerObj = $('<div class="hidden-container"></div>').appendTo( $(column.header()) );
                                    if(options === undefined){
                                        $hiddenContainerObj.html(makeFilterNonDropdownHTML(index, columnName));
                                    }
                                    else if(options.field_type){
                                        $hiddenContainerObj.html(makeFilterNonDropdownHTML(index, columnName, options.field_type));
                                    }
                                    else{
                                        $hiddenContainerObj.html('<select class="dynamic-column-filter dynamic-column-dropdown-filter" data-index="'+ index +'" data-name="'+columnName+'" multiple></select>');
                                        makeFitlerOptionHTML(options, columnName, $("select", $hiddenContainerObj), hidden_location_filters);
                                    }
                                }
                            });
                            if(column_filters.length > 0){
                                $("tr.filter .dynamic-column-filter", self.api().table().header()).each(function(i){
                                    var data_name = $(this).attr('data-name');
                                    $(this).val(field_values[data_name]);
                                });
                                setTimeout(function(){self.api().draw();},1);
                            }

                            createMultiSelectColumnFilter(self.api());
                            
                            if(!initial_load){
                                self.api().draw();
                            }
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        }
                    });
                    
                }
            });

            riskDatatable.on('draw', function(e, settings){
                if(settings._iDisplayLength == -1){
                    $("#" + settings.sTableId + "_wrapper").find(".paginate_button.current").removeClass("current");
                }
                $('.paginate_button.first').html('<i class="fa fa-chevron-left"></i><i class="fa fa-chevron-left"></i>');
                $('.paginate_button.previous').html('<i class="fa fa-chevron-left"></i>');

                $('.paginate_button.last').html('<i class="fa fa-chevron-right"></i><i class="fa fa-chevron-right"></i>');
                $('.paginate_button.next').html('<i class="fa fa-chevron-right"></i>');
            })
            riskDataTables.push(riskDatatable);
            riskDatatable.on( 'xhr', function () {
                columnFilters = riskDatatable.ajax.params().columnFilters;
                var orderColumnIndex = riskDatatable.ajax.params().order[0].column;
                orderColumnName = riskDatatable.ajax.params().columns[orderColumnIndex].name;
                orderDir = riskDatatable.ajax.params().order[0].dir;
            });
        });

        $('.view-all').html("All");
        $('.download-by-group').html("<i class=\"fa fa-download\" aria-hidden=\"true\"></i>");
        $('.print-by-group').html("<i class=\"fa fa-print\" aria-hidden=\"true\"></i>");
        
        $(".expand-all").click(function(e){
            e.preventDefault();
            $(".view-all").click();

        })
        
        $(".view-all").click(function(){
            var $this = $(this);
            var index = $(this).attr('id').replace("view-all-", "");
            var oSettings =  riskDataTables[index].settings();
            oSettings[0]._iDisplayLength = -1;
            riskDataTables[index].draw();
            $this.addClass("current");
        })
        
        // Event by text column filter
        $("body").on("change", '.dynamic-column-text-filter', function(){
            var tableIndex = $(this).data("index");
            riskDataTables[tableIndex].draw();
        })
        
        // Event by operator column filter
        $("body").on("change", '.dynamic-column-operator-filter', function(){
            // If Column to have operator has text value, redraw table by change event
            if($(this).parent().find(".dynamic-column-text-filter").val())
            {
                var tableIndex = $(this).data("index");
                riskDataTables[tableIndex].draw();
            }
        })

        $("body").on("click", "span > .paginate_button", function(){
            var index = $(this).attr('aria-controls').replace("DataTables_Table_", "");

            riskDataTables[index] || (index = 0);
            
            if(riskDataTables[index]){
                var oSettings =  riskDataTables[index].settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = 10;
                    riskDataTables[index].draw()
                }
            }
        })
            
        $("body").on("click", '.download-by-group', function(){
            // $("#get_risks_by").attr('target', '_blank');
            var filter_params = {
                column_filters : columnFilters
            }
            var filter_uri = $.param( filter_params);
            var group_value = encodeURIComponent($(this).closest('.dataTables_wrapper').find(".risk-datatable").data('group'));
            document.get_risks_by.action += "?option=download-by-group&group_value=" + group_value + "&order_column=" + orderColumnName + "&order_dir=" + orderDir + "&" + filter_uri;
            document.get_risks_by.submit();
            document.get_risks_by.action = "";
            // $("#get_risks_by").attr('target', '');
        })
        $("body").on("click", '.print-by-group', function(){
            // $("#get_risks_by").attr('target', '_blank');
            var group_value = encodeURIComponent($(this).closest('.dataTables_wrapper').find(".risk-datatable").data('group'));
            var status = $("#status").val();
            var group = $("#group").val();
            var sort = $("#sort").val();
            var filter_params = {
                column_filters : columnFilters
            }
            var filter_uri = $.param( filter_params);
            var url = "print_by_group.php?group=" + group + "&status=" + status + "&sort=" + sort + "&group_value=" + group_value + "&order_column=" + orderColumnName + "&order_dir=" + orderDir + "&" + filter_uri;
            window.open(url,'_blank');
        });
        var selected_all = false;
        $('#column-selections-container .multiselect').multiselect({
            enableFiltering: true,
            buttonWidth: '100%',
            maxHeight: 250,
            numberDisplayed: 1,
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
            onSelectAll : function(){
                selected_all = true;
            },
            onDeselectAll : function(){
                selected_all = true;
            },
            onChange: function(option, checked, select){
                var option_value = $(option).val();
                for(var key in riskDataTables){
                    var column = riskDataTables[key].column("th[data-name='"+ option_value +"']");
                    if(checked == true){
                        column.visible(true);
                        // The TH element to show filter html
                        var targetTH = $("tr.filter th[data-name='"+ option_value +"']", riskDataTables[key].table().header());

                        // If this element was hidden on loading, add filter content to the TH element and create multi dropdown
                        if($(".hidden-container", column.header()).length > 0)
                        {
                            targetTH.html($(".hidden-container", column.header()).html());
                            createMultiSelectColumnFilter(riskDataTables[key], targetTH);
                            $(".hidden-container", column.header()).remove();
                        }
                    }else{
                        column.visible(false);
                    }
                }
                return true;
            },
            onDropdownHide: function(){
                $('#selections').block({
                    message: 'Processing',
                    css: { border: '1px solid black', background: '#ffffff', zIndex:100001 }
                });
                setTimeout(function(){
                    var risk_columns = $("#risk_columns").val()?$("#risk_columns").val():[];
                    var mitigation_columns = $("#mitigation_columns").val()?$("#mitigation_columns").val():[];
                    var review_columns = $("#review_columns").val()?$("#review_columns").val():[];
                    var scoring_columns = $("#scoring_columns").val()?$("#scoring_columns").val():[];
                    var unassigned_columns = $("#unassigned_columns").val()?$("#unassigned_columns").val():[];
                    var risk_mapping_columns = $("#risk_mapping_columns").val()?$("#risk_mapping_columns").val():[];
                    var selected_columns = risk_columns.concat(mitigation_columns, review_columns, scoring_columns, unassigned_columns, risk_mapping_columns);
                    if(selected_all == true) {
                        visible_column("risk_columns", selected_columns);
                        visible_column("mitigation_columns", selected_columns);
                        visible_column("review_columns", selected_columns);
                        visible_column("scoring_columns", selected_columns);
                        visible_column("unassigned_columns", selected_columns);
                        visible_column("risk_mapping_columns", risk_mapping_columns);
                    }
                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/set_custom_display",
                        data: {
                            columns: selected_columns,
                        },
                        success: function(data){
                            $("#selections").unblock();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        }
                    });
                    selected_all = false;
                },200);

                return true;
            },
       });
    }
    
    $("#export-dynamic-risk-report").click(function(e){
        // $("#get_risks_by").attr('target', '_blank');
        var filter_params = {
            column_filters : columnFilters
        }
        var filter_uri = $.param( filter_params);
        document.get_risks_by.action += (document.get_risks_by.action.indexOf('?') !== -1  ? "&" : "?") + "option=download&order_column=" + orderColumnName + "&order_dir=" + orderDir + "&" + filter_uri;
        document.get_risks_by.submit();
        document.get_risks_by.action = "";
        // $("#get_risks_by").attr('target', '');
    });
    $("#export-risks-and-assets-report, #export-risks-and-controls-report").click(function(e){
        document.select_report.action += (document.select_report.action.indexOf('?') !== -1  ? "&" : "?") + "option=download";
        document.select_report.submit();
        document.select_report.action = "";
    });
    function visible_column(selectID, selected_columns){
        $("#"+selectID).find("option").each(function(index){
            var option_value = $(this).val();
            for(var key in riskDataTables){
                var column = riskDataTables[key].column("th[data-name='"+ option_value +"']");
                if(selected_columns.indexOf(option_value) > -1) {
                    column.visible(true);
                    var targetTH = $("tr.filter th[data-name='"+ option_value +"']", riskDataTables[key].table().header());

                    // If this element was hidden on loading, add filter content to the TH element and create multi dropdown
                    if($(".hidden-container", column.header()).length > 0)
                    {
                        targetTH.html($(".hidden-container", column.header()).html());
                        createMultiSelectColumnFilter(riskDataTables[key], targetTH);
                        $(".hidden-container", column.header()).remove();
                    }
                } else {
                    column.visible(false);
                }
            }
        });

    }
    
})