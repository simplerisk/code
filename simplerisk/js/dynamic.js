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
                dom : "flrti<'.download-by-group'><'#view-all-"+ index +".view-all'>p",
                ajax: {
                    url: BASE_URL + '/api/reports/dynamic',
                    type: "post",
                    data: function(d){
                        d.status                    = $("#status").val();
                        d.group                     = $("#group").val();
                        d.sort                      = $("#sort").val();
                        d.affected_assets_filter    = $("#affected_assets_filter").val();
                        d.group_value               = $this.data('group');
                        
                        // Set params in risks_by_teams page
                        if($("#teams").length){
                            d.risks_by_team     = 1;
                            d.teams             = $("#teams").val();
                            d.owners            = $("#owners").val();
                            d.ownersmanagers    = $("#ownersmanagers").val();
                        } else {
                            d.tags_filter    = $("#tags_filter").val();
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
                        // Calculated risk
                        "targets" : 16,
                        "className" : "risk-cell",
                    },
                    {
                        // Residulat risk
                        "targets" : 17,
                        "className" : "risk-cell",
                    },
                    {
                        /**
                        * 21: mitigation_planned
                        * 22: managment_review
                        * 23: days_open
                        * 26: affected_assets
                        * 27: risk_assessment
                        * 28: additional_notes
                        * 29: current_solution
                        * 30: security_recommendations
                        * 40: risk_tags
                        */
                        "targets" : [21, 22, 23, 26, 27, 28, 29, 30, 40],
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
        });

        // This is needed to refresh with a new magic token if the previous
        // token expired
        $(document.body).on('xhr.dt', function (e, settings, json, xhr){
            if(json === null && xhr.status === 403)
                retryDatatableCSRF(xhr, new $.fn.dataTable.Api(settings));
        });

        $('.view-all').html("All");
        $('.download-by-group').html("<i class=\"fa fa-download\" aria-hidden=\"true\"></i>");
        
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
            
        $("body").on("click", '.download-by-group', function(){
//            $("#get_risks_by").attr('target', '_blank');
            var group_value = $(this).closest('.dataTables_wrapper').find(".risk-datatable").data('group');
            document.get_risks_by.action += "?option=download-by-group&group_value=" + group_value;
            document.get_risks_by.submit();
            document.get_risks_by.action = "";
//            $("#get_risks_by").attr('target', '');
        })
    }
    
    $("#export-dynamic-risk-report").click(function(e){
//        $("#get_risks_by").attr('target', '_blank');
        document.get_risks_by.action += "?option=download";
        document.get_risks_by.submit();
        document.get_risks_by.action = "";
//        $("#get_risks_by").attr('target', '');
    })
    
})
