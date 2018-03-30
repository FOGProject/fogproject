(function($) {
    // DEFINE: CUSTOM DATA ADAPTER; FOR FETCHING THE GROUPS

    $.fn.select2.amd.define('select2/data/fetchAdapter', [
      'select2/data/array',
      'select2/utils'
    ], function(ArrayAdapter, Utils){
        function FetchDataAdapter ($element, options) {
            FetchDataAdapter.__super__.constructor.call(this, $element, options);
        }

        Utils.Extend(FetchDataAdapter, ArrayAdapter);

        FetchDataAdapter.prototype.postQuery = function(params, callback, dataStore) {
          var response = [];

          if(dataStore != undefined){
            var data = dataStore.results;

            data.forEach(function(element, index){
              if(params.term == undefined || element.text.toLowerCase().indexOf(params.term.toLowerCase()) !== -1){
                response.push(element);
              }
            });

            callback({
              results: response
            });
          }

          return;
        }

        FetchDataAdapter.prototype.query = function (params, callback) {
          if(this.options.options.url == undefined){
            throw new DOMException("Invalid URL supplied!");
          }

          if(this.dataStore == undefined){
            var self = this;

            $.ajax({
              url: this.options.options.url,
              dataType: 'json',
              quietMillis: 200,
              data: function(params){
                return params;
              },
              success: function(data){
                var mappedData = $.map(data, function (obj) {
                    obj.text = obj.name;
                    return obj;
                });

                self.dataStore = {
                  results: mappedData
                }

                self.postQuery(params, callback, self.dataStore);
                return;
              }
            });
          }

          this.postQuery(params, callback, this.dataStore);
        };

        return FetchDataAdapter;
    });

    // END: CUSTOM DATA ADAPTER


    var addToGroup = $('#addSelectedToGroup'),
        deleteSelected = $('#deleteSelected'),
        groupModal = $('#addToGroupModal'),
        groupModalSelect = $('#groupSelect');
    var groupList = [];

    function disableButtons(disable) {
        addToGroup.prop('disabled', disable);
        deleteSelected.prop('disabled', disable);
    }
    disableButtons(true);

    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }

    function loadGroupSelect(){
      var fetchAdapter = $.fn.select2.amd.require('select2/data/fetchAdapter');

      groupModalSelect.select2({
        url: '../fog/group/names',
        dataAdapter: fetchAdapter,
        width: '100%',
        tags: true,
        placeholder: 'Select or create group',
        createTag: function (params) {
            return {
                id: params.term,
                text: params.term,
                newOption: true
            }
        },
        templateResult: function (data) {
            if (!data.text.length) {
                return;
            }
            var $result = $("<span></span>");

            $result.text(data.text);
            if (data.newOption) {
                $result.append(" <em><b>(new)</b></em>");
            }
            return $result;
        }
      });

      groupModalSelect.val(null).trigger("change");
    }

    var table = Common.registerTable($('#dataTable'), onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'primac'},
            {data: 'pingstatus'},
            {data: 'deployed'},
            {data: 'imagename'},
            {data: 'description'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function (data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id=' + row.id + '">' + data + '</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 0,
                targets: 1
            },
            {
                render: function (data, type, row) {
                    return (data === '0000-00-00 00:00:00') ? '' : data;
                },
                targets: 3
            },
            {
                render: function (data, type, row) {
                    if (data === null) {
                        return '';
                    }
                    return '<a href="../management/index.php?node=image&sub=edit&id=' + row.imageID + '">' + data + '</a>';
                },
                targets: 4
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node=host&sub=list',
            type: 'POST'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

    deleteSelected.on('click', function() {
        disableButtons(true);
        Common.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            //   as the rows still exist and are selected
            if (err) {
                disableButtons(false);
            }
        });
    });

    Common.registerModal(groupModal,
        // On show
        null,
        // On close
        function(e) {
            // Clear the group selector and data
            groupModalSelect.select2('destroy');
        }
    );

    groupModal.on('show.bs.modal', function(e) {
      Pace.track(function(){
        loadGroupSelect();
      });
    });

    addToGroup.on('click', function() {
        groupModal.modal('show');
    });
})(jQuery);
