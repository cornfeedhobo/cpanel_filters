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
        
        if ( rcmail.env.action == 'plugin.cpanel_filters' ||
            rcmail.env.action == 'plugin.cpanel_filters-add' ) {
            
             if (rcmail.gui_objects.filterlist) {
                var p = rcmail;
                rcmail.filterlist = new rcube_list_widget( rcmail.gui_objects.filterlist, { multiselect:false, draggable:false, keyboard:false } );
                rcmail.filterlist.addEventListener('select', function(o) { p.cpf_select(o); } );
                rcmail.filterlist.init();
                rcmail.filterlist.focus();
            }
            
            // Register and control the Add Filter button
            rcmail.register_command('plugin.cpanel_filters-add', function() {
                rcmail.filterlist.clear_selection();
                rcmail.cpf_frame(null,'plugin.cpanel_filters-add');
            }, true);
        }
    });
};

/*********************************************************/
/*********       cPanel Filters UI methods       *********/
/*********************************************************/
// Select list item handler
rcube_webmail.prototype.cpf_select = function(list) {
//    var id = list.get_single_selection();
//    if (id != null)
//        this.load_cpf_frame(list.rows[id].uid, 'plugin.cpanel_filters-edit');
};
// Load the framed filteredit
rcube_webmail.prototype.cpf_frame = function(fid, action) {
    if ( this.env.contentframe && window.frames && window.frames[this.env.contentframe] ) {
        target = window.frames[this.env.contentframe];
        var msgid = this.set_busy(true, 'loading');
        target.location.href = this.env.comm_path+'&_action='+action+'&_framed=1&_fid='+fid+'&_unlock='+msgid;
    }
}