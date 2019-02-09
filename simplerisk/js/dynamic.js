function closeSearchBox()
{
    document.getElementById("selections").style.display = "none";
}

$(document).ready(function(){
    if($(".risk-datatable").length){
        var sortColumns = [["calculated_risk", "desc"], ["id", "asc"], ["subject", "asc"], ["residual_risk", "desc"]];
        var defaultSortColumnIndex = 0;
        var defaultSortColumn = sortColumns[$("#sort").val()];
        if(defaultSortColumn == undefined){
            defaultSortColumn = sortColumns[defaultSortColumnIndex];
        }
        var columnOptions = [];
        var columnNames = [];
        $(".risk-datatable tr.main th").each(function(index){
            var name = $(this).data('name');
            if(columnNames.indexOf(name) > -1){
                return;
            }
            columnNames.push(name);
//            if($("form[name='get_risks_by'] input.hidden-checkbox[name='"+ name +"']").length > 0 &&  !$("form[name='get_risks_by'] input.hidden-checkbox[name='"+ name +"']").is(':checked')){
            if(!$("form[name='get_risks_by'] input.hidden-checkbox[name='"+ name +"']").is(':checked')){
                columnOptions.push(index);
            }
            if(defaultSortColumn != undefined && name == defaultSortColumn[0]) {
                defaultSortColumnIndex = index;
            }
        })
        
        
        var riskDataTables = [];
        $(".risk-datatable").each(function(index){
            var $this = $(this);
            var riskDatatable = $(this).DataTable({
                scrollX: true,
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
//                ordering: false,
                pagingType: "full_numbers",
                dom : "flrti<'#view-all-"+ index +".view-all'>p",
                ajax: {
                    url: BASE_URL + '/api/reports/dynamic',
                    type: "post",
                    data: function(d){
                        d.status            = $("#status").val();
                        d.group             = $("#group").val();
                        d.sort              = $("#sort").val();
                        d.affected_asset    = $("#affected_asset").val();
                        d.group_value       = $this.data('group');
                        
                        // Set params in risks_by_teams page
                        if($("#teams").length){
                            d.risks_by_team     = 1;
                            d.teams             = $("#teams").val();
                            d.owners            = $("#owners").val();
                            d.ownersmanagers    = $("#ownersmanagers").val();
                        }
                    }
                },
                order: [[defaultSortColumnIndex, defaultSortColumn[1]]],
                columnDefs : [
                    {
                        "targets" : columnOptions,
                        "visible" : false
                    },
                    {
                        "targets" : 16,
                        "className" : "risk-cell",
                    },
                    {
                        "targets" : 17,
                        "className" : "risk-cell",
                    },
                    {
                        "targets" : [21, 22, 23, 26, 27, 28, 29, 30],
                        "orderable" : false,
                    },
                ]
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
        })
        $('.view-all').html("All");
        
        $("form[name='get_risks_by'] .hidden-checkbox").click(function(e){
            
            for(var key in riskDataTables){
                var column = riskDataTables[key].column("th[data-name='"+ $(this).attr('name') +"']");
                if($(this).is(':checked')){
                    column.visible(true);
                }else{
                    column.visible(false);
                }
            }
            
            var checkBoxes = $("form[name='get_risks_by'] .hidden-checkbox");
            var viewColumns = [];
            checkBoxes.each(function(){
                if($(this).is(':checked'))
                    viewColumns.push($(this).attr('name'));
            })
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/set_custom_display",
                data: {
                    columns: viewColumns,
                },
                success: function(data){
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                    }
                }
            });
        })
        
        $(".expand-all").click(function(e){
            e.preventDefault();
            $(".view-all").click();

        })
        
        $(".view-all").click(function(){
            var $this = $(this);
            var index = $(this).attr('id').replace("view-all-", "");
            var oSettings =  riskDataTables[index].settings();
            oSettings[0]._iDisplayLength = -1;
            riskDataTables[index].draw()
            $this.addClass("current");
        })
        
        $("body").on("click", "span > .paginate_button", function(){
            var index = $(this).attr('aria-controls').replace("DataTables_Table_", "");

            var oSettings =  riskDataTables[index].settings();
            if(oSettings[0]._iDisplayLength == -1){
                $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
                oSettings[0]._iDisplayLength = 10;
                riskDataTables[index].draw()
            }
            
        })
    }
    
    $("#export-dynamic-risk-report").click(function(e){
        document.get_risks_by.action += "?option=download";
        document.get_risks_by.submit();
        document.get_risks_by.action = "";
//        document.location.href = "dynamic_risk_report.php?option";
    })
})
