(function($) {
    var addToGroup = $('#addSelectedToGroup'),
        deleteSelected = $('#deleteSelected'),
        groupModal = $('#addToGroupModal'),
        groupModalSelect = $('#groupSelect'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');
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
        var hostGroupUpdateBtn = $('#confirmGroupAdd');
        groupModalSelect.select2({
            tags: true,
            tokenSeparators: [',', ' '],
            ajax: {
                url: function(params) {
                    return '../group/names/name='
                        + encodeURIComponent(
                            '%'
                            + params.term
                            + '%'
                        );
                },
                dataType: 'json',
                processResults: function(data, params) {
                    return {
                        results: $.map(data, function(item) {
                            return {
                                id: item.id || item.name,
                                name: item.name,
                                text: item.name
                            };
                        }),
                        totals: data.length
                    };
                }
            },
            width: '100%',
            placeholder: 'Select or create group',
            createTag: function (params) {
                var term = $.trim(params.term);
                if (term === '') {
                    return;
                }
                return {
                    id: term,
                    text: term,
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

        hostGroupUpdateBtn.on('click', function(e) {
            e.preventDefault();
            var items = groupModalSelect.find('option').map(function() {return $(this).val()}).get(),
                hosts = $.getSelectedIds(table),
                groups = [],
                groups_new = [];
            $.map(items, function(item) {
                item = $.trim(item);
                if (item === '') {
                    return;
                }
                if ($.isNumeric(item)) {
                    groups.push(item);
                } else {
                    groups_new.push(item);
                }
            });
            var action = '../management/index.php?node='
                + Common.node
                + '&sub=saveGroup',
                method = 'post',
                opts = {
                    hosts: hosts,
                    groups: groups,
                    groups_new: groups_new
                };
            $.apiCall(method,action,opts,function(err) {
                if (err) {
                    return;
                }
                groupModalSelect.val(null);
                groupModal.modal('hide');
            });
        });
    }

    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'primac'},
            {data: 'pingstatus'},
            {data: 'deployed'},
            {data: 'imageLink'},
            {data: 'description'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
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

    createnewModal.registerModal(Common.createModalShow, Common.createModalHide);
    createnewBtn.on('click', function(e) {
        e.preventDefault();
        createnewModal.modal('show');
    });
    createnewSendBtn.on('click', function(e) {
        e.preventDefault();
        createForm.processForm(function(err) {
            if (err) {
                return;
            }
            table.draw(false);
            createnewModal.modal('hide');
        });
    });
    $('#mac').inputmask({mask: Common.masks.mac});
    $('#key').inputmask({mask: Common.masks.productKey});
    // ---------------------------------------------------------------
    // ACTIVE DIRECTORY TAB
    var ADJoinDomain = $('#adEnabled');

    ADJoinDomain.on('ifClicked', function(e) {
        e.preventDefault();
        $(this).prop('checked', !this.checked);
        if (!this.checked) {
            return;
        }
        var indomain = $('#adDomain'),
            inou = $('#adOU'),
            inuser = $('#adUsername'),
            inpass = $('#adPassword');
        if (indomain.val() && inou.val() && inuser.val() && inpass.val()) {
            return;
        }
        Pace.ignore(function() {
            $.get('../management/index.php?sub=adInfo', function(data) {
                if (!indomain.val()) {
                    indomain.val(data.domainname);
                }
                if (!inou.val()) {
                    inou.val(data.ou)
                }
                if (!inuser.val()) {
                    inuser.val(data.domainuser);
                }
                if (!inpass.val()) {
                    inpass.val(data.domainpass);
                }
            }, 'json');
        });
    });

    // Delete hosts.
    deleteSelected.on('click', function() {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            //   as the rows still exist and are selected
            if (err) {
                disableButtons(false);
            }
        });
    });

    // Add host(s) to group.
    groupModal.registerModal(
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
