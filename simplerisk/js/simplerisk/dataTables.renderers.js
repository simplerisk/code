/******************************************************************
****************Renderers for the datatable widget*****************
*******************************************************************/
$.fn.dataTable.render.tags = function (tag_type) {
    return function (data, type, row) {
        // console.log(data, type, row);
        if (type === 'edit') {
            if (!(data instanceof Array)) {
                data = [data];
            }
            var id = row['id'];
            
            var result = `<select class='edited-field' readonly id='tags-${id}' name='tags[]' multiple placeholder='Select/Add tag'>`; 
            
            for (const tag of data) {
                result += `<option selected value='${tag}'>${tag}</option>`;
            }
            return result + `</select><script>
                    var tags_${id}_selectize = $('#tags-${id}').selectize({
                        plugins: ['remove_button', 'restore_on_backspace'],
                        delimiter: '|',
                        create: true,
                        valueField: 'label',
                        labelField: 'label',
                        searchField: 'label',
                        sortField: [{ field: 'label', direction: 'asc' }],
                        onChange: function() {$('#tags-${id}').data('changed', true);},
                    });
                    $.ajax({
                        url: BASE_URL + '/api/management/tag_options_of_type?type=${tag_type}',
                        type: 'GET',
                        dataType: 'json',
                        error: function() {
                            console.log('Error loading assets for selectize!');
                        },
                        success: function(res) {
                            tags_${id}_selectize[0].selectize.addOption(res.data);
                            tags_${id}_selectize[0].selectize.refreshOptions(true);
                        }
                    });
                </script>`;
        }
        
        if (type === 'display' && data) {
            if (!(data instanceof Array)) {
                data = [data];
            }
            var result = "";
            for (const tag of data) {
                result += `<button class='btn btn-secondary btn-sm' style='pointer-events: none; margin:1px; padding: 4px 12px;' role='button' aria-disabled='true'>${tag}</button>`;
            }
            return result;
        }            

        // Search, order and type can use the original data
        return data;
    };
};

$.fn.dataTable.render.short_text = function (name) {
    return function (data, type, row) {
        //console.log(data, type, row);
        if (type === 'edit') {
            var id = row['id'];
            return `<input type='text' class='edited-field' id='${name}-${id}' name='${name}' placeholder='' value='${data}' style='width: 100%' />`; 
        }

        // Search, order and type can use the original data
        return data;
    };
};

$.fn.dataTable.render.long_text = function (name) {
    return function (data, type, row) {
        //console.log(data, type, row);
        if (type === 'edit') {
            var id = row['id'];
            return `<textarea class='edited-field' id='${name}-${id}' name='${name}' placeholder='' rows='3' cols='50'>${data}</textarea>`; 
        }

        // Search, order and type can use the original data
        return data;
    };
};
