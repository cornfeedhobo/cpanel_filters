if (window.rcmail) {
    rcmail.addEventListener('init', function(evt) {
        // Add "Filters" tab
        var tab = $('<span>')
            .attr('id', 'settingstabplugincpanel_filters')
            .addClass('tablink');
        var button = $('<a>')
            .attr('title', rcmail.gettext('cpanel_filters.filterManage'))
            .attr('href', rcmail.env.comm_path+'&_action=plugin.cpanel_filters')
            .html(rcmail.gettext('cpanel_filters.filter'))
            .appendTo(tab);
        rcmail.add_element(tab, 'tabs');
    });
};