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
            rcmail.env.action == 'plugin.cpanel_filters-add' ||
            rcmail.env.action == 'plugin.cpanel_filters-edit') {
            
             if (rcmail.gui_objects.filterlist) {
                var p = rcmail;
                rcmail.filterlist = new rcube_list_widget(
                    rcmail.gui_objects.filterlist, {
                        multiselect:false, draggable:false, keyboard:false} );
                rcmail.filterlist.addEventListener('select', function(o)
                {
                    p.cpf_select(o);
                } );
                rcmail.filterlist.init();
                rcmail.filterlist.focus();
            }
            
            // Register and control the Add Filter button
            rcmail.register_command('plugin.cpanel_filters-add', function() {
                rcmail.filterlist.clear_selection();
                rcmail.cpf_frame(null,'plugin.cpanel_filters-add');
            }, true);
            
            if (rcmail.env.action == 'plugin.cpanel_filters-add' ||
                rcmail.env.action == 'plugin.cpanel_filters-edit') {
                // Register and control the Save button
                rcmail.register_command('plugin.cpanel_filters-save', function() {
                    if (parent.rcmail && parent.rcmail.filterlist)
                        $('form#filterform').validate();
                        $('form#filterform').submit();
                }, true);
            }
            
            
            if ( rcmail.env.action == 'plugin.cpanel_filters-edit' ) {
                // Register and control the Delete button
                rcmail.register_command('plugin.cpanel_filters-delete', function() {
                    var id = parent.rcmail.filterlist.get_single_selection();
                    if (confirm(rcmail.get_label('cpanel_filters.filterDeleteconfirm')))
                        rcmail.http_request('plugin.cpanel_filters','_act=delete&_fid='+
                            parent.rcmail.filterlist.rows[id].uid, true);
                }, true);
            }
            
        }
    });
};
/*********************************************************/
/*********       cPanel Filters UI methods       *********/
/*********************************************************/
// Select list item handler
rcube_webmail.prototype.cpf_select = function(list)
{
    var id = list.get_single_selection();
    if (id != null)
        this.cpf_frame(list.rows[id].uid, 'plugin.cpanel_filters-edit');
};
// Load the framed filteredit
rcube_webmail.prototype.cpf_frame = function(fid, action)
{
    if ( this.env.contentframe && window.frames && window.frames[this.env.contentframe] ) {
        target = window.frames[this.env.contentframe];
        var msgid = this.set_busy(true, 'loading');
        target.location.href = this.env.comm_path+'&_action='+action+'&_framed=1&_fid='+fid+'&_unlock='+msgid;
    }
}
rcube_webmail.prototype.cpf_ruleadd = function(bttnrow)
{
    this.http_post('plugin.cpanel_filters', '_act=ruleadd&_rid='+bttnrow);
};
rcube_webmail.prototype.cpf_actionadd = function(bttnrow)
{
    this.http_post('plugin.cpanel_filters', '_act=actionadd&_aid='+bttnrow);
};
rcube_webmail.prototype.cpf_ruledel = function(rid)
{
    if (confirm(this.get_label('cpanel_filters.filterRuledelete'))) {
        $('div#rulerow'+rid).remove();
    }
    rcmail.cpf_formbuttons('rulerow');
};
rcube_webmail.prototype.cpf_actiondel = function(aid)
{
    if (confirm(this.get_label('cpanel_filters.filterActiondelete'))) {
        $('div#actionrow'+aid).remove();
    }
    rcmail.cpf_formbuttons('actionrow');
};
rcube_webmail.prototype.cpf_insertrow = function(content, bttnrow, field)
{
    if ( content != '' ) {
        $('div#'+field+bttnrow).after(content);
    }
    rcmail.cpf_formbuttons(field);
};
rcube_webmail.prototype.cpf_formbuttons = function(field)
{
    // Rename form elements
    if ( field != null ) {
        if ( field == 'rulerow' || field == 'both' ) {
            $('div.rulerow').each( function(i) { $(this).attr('id', 'rulerow'+i); } );
            $('select[name$="[part]"]').each( function(i)
            {
                $(this).change(function()
                {
                    if ( ( $(this).val() == '$header_from:' ||
                           $(this).val() == '$header_to:' ||
                           $(this).val() == '$header_from:' ||
                           $(this).val() == '$reply_address:' ||
                           $(this).val() == 'foranyaddress $h_to:,$h_cc:,$h_bcc:' ) &&
                           $('select#match'+i).val() == 'is' ) {
                        
                        $('input#value'+i).addClass('email');
                    }
                    else if ( $(this).val() != 'is' ) {
                        $('input#value'+i).removeClass('email');
                    }
                } );
                $(this).attr('name', '_rules['+i+'][part]');
                $(this).attr('id', 'header'+i);
            });
            $('select[name$="[match]"]').each( function(i)
            {
                $(this).change(function()
                {
                    if ( $(this).val() == 'is' && (
                        $('select#header'+i).val() == '$header_from:' ||
                        $('select#header'+i).val() == '$header_to:' ||
                        $('select#header'+i).val() == '$header_from:' ||
                        $('select#header'+i).val() == '$reply_address:' ||
                        $('select#header'+i).val() == 'foranyaddress $h_to:,$h_cc:,$h_bcc:' ) ) {
                        
                        $('input#value'+i).addClass('email');
                    }
                    else if ( $(this).val() != 'is' ) {
                        $('input#value'+i).removeClass('email');
                    }
                } );
                $(this).attr('name', '_rules['+i+'][match]');
                $(this).attr('id', 'match'+i);
            } );
            $('input[name$="[val]"]').each( function(i)
            {
                $(this).attr('name', '_rules['+i+'][val]');
                $(this).attr('id', 'value'+i);
            } );
            $('select[name$="[opt]"]').each( function(i)
            {
                $(this).attr('name', '_rules['+i+'][opt]');
                $(this).attr('id', 'opt'+i);
            } );
            $('input[id^="ruleadd"]').each( function(i)
            {
                $(this)[0].onclick = function() { rcmail.cpf_ruleadd(i); };
                $(this).attr('id', 'ruleadd'+i);
            } );
            $('input[id^="ruledel"]').each( function(i)
            {
                $(this)[0].onclick = function() { rcmail.cpf_ruledel(i); };
                $(this).attr('id', 'ruledel'+i);
                if ( $('div.rulerow').length < 2 ) {
                    $(this).addClass('disabled');
                    $(this).attr('disabled','disabled');
                }
                else if ( $('div.rulerow').length > 1 ) {
                    $(this).removeClass('disabled');
                    $(this).removeAttr('disabled');
                }
            } );
            $('select[name$="[opt]"]').each( function(i)
            {
                if ( ( $('div.rulerow').length - 1 ) > i )
                    $(this).show();
                else
                    $(this).hide();
            } );
        }
        if ( field == 'actionrow' || field == 'both' ) {
            $('div.actionrow').each( function(i) { $(this).attr('id', 'actionrow'+i); } );
            $('select[name$="[action]"]').each( function(i)
            {
                $(this).change( function()
                {
                    if ( $(this).val() == 'deliver' || $(this).val() == 'fail' ) {
                        $('select#mailbox'+i).hide();
                        $('input#dest'+i).show();
                        $('input#dest'+i).addClass('required');
                        if ( $(this).val() == 'deliver' )
                            $('input#dest'+i).addClass('email');
                        else if ( $(this).val() == 'fail' )
                            $('input#dest'+i).removeClass('email');
                    }
                    else if ( $(this).val() == 'save' ) {
                        $('input#dest'+i).hide();
                        $('select#mailbox'+i).show();
                        $('input#dest'+i).removeClass('required email');
                    }
                } );
                $(this).attr('name', '_actions['+i+'][action]');
                $(this).attr('id', 'action'+i);
            } );
            $('input[name$="[dest]"]').each( function(i)
            {
                $(this).attr('name', '_actions['+i+'][dest]');
                $(this).attr('id', 'dest'+i);
            } );
            $('select[name$="[folder]"]').each( function(i)
            {
                $(this).attr('name', '_actions['+i+'][folder]');
                $(this).attr('id', 'mailbox'+i);
            } );
            $('input[id^="actionadd"]').each( function(i)
            {
                $(this)[0].onclick = function(){rcmail.cpf_actionadd(i);};
                $(this).attr('id', 'actionadd'+i);
            } );
            $('input[id^="actiondel"]').each( function(i)
            {
                $(this)[0].onclick = function() { rcmail.cpf_actiondel(i); };
                $(this).attr('id', 'actiondel'+i);
                if ( $('div.actionrow').length < 2 ) {
                    $(this).addClass('disabled');
                    $(this).attr('disabled','disabled');
                }
                else if ( $('div.actionrow').length > 1 ) {
                    $(this).removeClass('disabled');
                    $(this).removeAttr('disabled');
                }
            } );
        }
    }
}
rcube_webmail.prototype.cpf_reload = function(url)
{
    parent.rcmail.set_busy(true);
    parent.rcmail.goto_url(url);
    parent.rcmail.set_busy(false);
}
rcube_webmail.prototype.cpf_rowid = function(id)
{
    var i, rows = parent.rcmail.filterlist.rows;
    for (i=0; i<rows.length; i++)
        if (rows[i] != null && rows[i].uid == id)
            return i;
};