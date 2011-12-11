<?php

/**
 * RoundCube cPanel Filters Plugin
 *
 * Plugin that adds a possibility to manage filters filters in Thunderbird's style.
 * It's clickable interface which operates on text scripts and communicates
 * with server using cpanel's xmlapi. Adds Filters tab in Settings.
 * 
 * @author cornfeed
 * @version 1.0a
 * @copyright 2008-2011, The Roundcube Dev Team
 */

class cpanel_filters extends rcube_plugin {
    public $task = 'settings';
    private $rcmail; // RC class
    private $xmlapi; //cpanel xmlapi class
    private $cuser; // cpanel user is needed in various places
    private $filters = array(); // Raw filter output from cpanel
    private $headers = array( //
        'filterFrom'        => '$header_from:',
        'filterSubject'     => '$header_subject:',
        'filterTo'          => '$header_to:',
        'filterReply'       => '$reply_address:',
        'filterBody'        => '$message_body',
        'filterHeader'      => '$message_headers',
        'filterRecipient'   => 'foranyaddress $h_to:,$h_cc:,$h_bcc:',
    );
    private $matches = array(
        'filterEquals'          => 'is',
        'filterRegex'           => 'matches',
        'filterContains'        => 'contains',
        'filterNotcontains'     => 'does not contain',
        'filterBeginwith'       => 'begins',
        'filterEndswith'        => 'ends',
        'filterNotbeginwith'    => 'does not begin',
        'filterNotendwith'      => 'does not end',
        'filterNotmatch'        => 'does not match',
    );
    private $actions = array(
        'filterDeliver'     => 'save',
        'filterRedirect'    => 'deliver',
        'filterFail'        => 'fail',
//        'filterStop'        => 'finish',  // might cause unexpected behaviour
    );
    
    /**
     * Roundcube init handler (mandatory)
     */
    function init() {
        // add Tab label/title
        $this->add_texts('localization/', true);
        
        // register javascript actions
        $this->register_action('plugin.cpanel_filters',array($this,'cpanel_filters_actions'));
        $this->register_action('plugin.cpanel_filters-add',array($this,'cpanel_filters_actions'));
        
        // include main js script
        $this->include_script('cpanel_filters.js');
    }
    
    /**
     * Sets up the xmlapi connection, sets $this->filters
     * Adds UI handlers
     */
    function _start() {
        $this->rcmail = rcmail::get_instance();
        $this->load_config(); // load our config

        // register UI objects that are in our template file
        // This will pass on $attrib to each function relative to the object
        $this->rcmail->output->add_handlers( array(
            'filterlist'    => array($this, 'html_filterlist'),
            'filterframe'   => array($this, 'html_filterframe'),
            'filterform'    => array($this, 'html_filterform'),
        ));
        
        require_once 'lib/xmlapi.inc.php';
        
        $chost = rcube_parse_host( $this->rcmail->config->get(
                'cpanel_filters_host', 'localhost') );
        $cport = $this->rcmail->config->get('cpanel_filters_port', 2083);
        $cpass = $this->rcmail->config->get('cpanel_filters_pass');
        $this->cuser = $this->rcmail->config->get('cpanel_filters_user');
        
        // Setup the xmlapi connection
        $this->xmlapi = new xmlapi($chost);
        $this->xmlapi->set_port($cport);
        $this->xmlapi->password_auth($this->cuser, $cpass);
        $this->xmlapi->set_output('json');
        $this->xmlapi->set_debug(1);
        
        $query = json_decode( $this->xmlapi->api2_query( $this->cuser, 'Email',
                'filterlist', array(account=>$this->rcmail->user->get_username())
                ), true );
        $this->filters = $query['cpanelresult']['data'];
    } // end _start()
    
    function cpanel_filters_actions() {
        $this->_start();
        $this->cpanel_filters_send();
    } // end cpanel_filters_actions()
    
    /**
     * Custom rcmail->output->send wrapper that handles the framed content
     */
    function cpanel_filters_send() {
        // Handle the frame
        if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
            $this->rcmail->output->send('cpanel_filters.filteredit');
        } else {
            $this->rcmail->output->set_pagetitle( Q( $this->gettext('filterManage') ) );
            $this->rcmail->output->send('cpanel_filters.cpanel_filters');
        }
    } // end cpanel_filters_send
    
    /**
     * Create the filter list on the side.
     * 
     * @param string $attrib Provided by RC's template engine
     * @return type XHTML output
     */
    function html_filterlist($attrib) {
        // Set the HTML ID attribute if not specified
        if (!$attrib['id'])
            $attrib['id'] = 'rcmcpflist';
        // Create one table column and give it a title
        $cols = array('cpanel_filters.filterName');
        foreach( $this->filters as $fid => $filter )
            $table[] = array(
                'cpanel_filters.filterName' => $filter['filtername'],
                'id'                        => $fid,
            );
        $out = rcube_table_output( $attrib, $table, $cols, 'id' );
        // Add the list object from the template
        $this->rcmail->output->add_gui_object( 'filterlist', $attrib['id'] );
        $this->rcmail->output->include_script( 'list.js' );
        return $out;
    } // end html_filterlist
    
    /**
     * Create a frame to load all filter edits in; starts with blank frame
     * 
     * @param type $attrib Provided by RC's template engine
     * @return type XHTML output
     */
    function html_filterframe($attrib) {
        // Set the HTML ID attribute if not specified
        if (!$attrib['id'])
            $attrib['id'] = 'rcmcpfframe';
        $attrib['name'] = $attrib['id'];
        $this->rcmail->output->set_env('contentframe', $attrib['name']);
        $this->rcmail->output->set_env('blankpage', $attrib['src'] ?
        $this->rcmail->output->abs_url($attrib['src']) : 'program/blank.gif');
        return html::tag('iframe', $attrib);
    } // end html_filterframe
    
    function html_filterform($attr) {
        // Set the HTML ID attribute if not specified
        if (!$attrib['id'])
            $attrib['id'] = 'rcmcpfform';
        
        // When given nothing, evalutes: string(4) "null"
        $fid = get_input_value('_fid', RCUBE_INPUT_GPC);
        // When given nothing, evalutes: NULL
        $flt = $this->filters[$fid];
        
        $out = '<form name="filterform" action="./" method="post">'."\n";
        
        // Begin the form w\hidden fields
        $hiddenfields = new html_hiddenfield( array(
                'name'  => '_task',
                'value' => $this->rcmail->task,
            ) );
        $hiddenfields->add( array( 
                'name'  => '_action',
                'value' => 'plugin.cpanel_filters-save',
            ) );
        $hiddenfields->add( array(
                'name'  => '_framed',
                'value' => ( ( $_POST['_framed'] || $_GET['_framed'] ) ? 1 : 0 ),
            ) );
        $hiddenfields->add( array(
                'name'  => '_fid',
                'value' => $fid,
            ) );
        $out .= $hiddenfields->show();
        
        // Create Filter Name <input>
        $input_name = new html_inputfield( array( 
                'name'  => '_name',
                'id'    => '_name',
                'size'  => 30,
                'class' => 'filtername',
            ) );
        
        // If we are given a filter, load it's name
        if ( $flt != null )
            $input_name = $input_name->show( $this->filters[$fid]['filtername'] );
        else
            $input_name = $input_name->show();
        
        $out .= sprintf("\n<label for=\"%s\"><b>%s:</b></label> %s<br /><br />\n",
                '_name', Q( $this->gettext('filterName') ), $input_name);
        
        // Process the Rules (accounts for $fid==null)
        $out .= '<fieldset><legend>'.Q($this->gettext('filterRules')).'</legend>'."\n";
        $rows = ( $flt != null ) ? sizeof( $flt['rules'] ) : 1;
        $out .= '<div id="rules">';
        for ( $i=0; $i<$rows; $i++ )
            $out .= $this->parse_rules($fid, $i);
        $out .= '</div>'."\n".'</fieldset>'."\n";
        
        // Process the Actions
        $out .= '<fieldset><legend>' . Q( $this->gettext('filterActions') ) . '</legend>'."\n";
        $rows = ( $flt != null ) ? sizeof($flt['actions']) : 1;
        $out .= '<div id="actions">';
        for ( $i=0; $i<$rows; $i++ )
            $out .= $this->parse_actions($fid, $i);
        $out .= '</div>'."\n".'</fieldset>'."\n";
        
        $this->rcmail->output->add_gui_object('filterform', $attrib['id']);
        
        return $out;
    }
    
    function parse_rules( $fid, $rid, $div = true ) {  // fid = NULL,rid = int(0)
        $rule = $this->filters[$fid]['rules'][$rid];  // returns NULL
        $rows = sizeof($this->filters[$fid]['rules']);  // returns int(0)
        
        // handle div wrapper for dynamic adding
        $out = $div ? '<div class="rulerow" id="rulerow'.$rid.'">'."\n" : '';
        
        // Create the <select> list for the header parts
        $out .= '<table><tr><td class="rowparts">';
        $select_header = new html_select( array(
                'name'      => '_rules['.$rid.'][part]',
                'id'        => 'header'.$rid,
            ) );
        foreach($this->headers as $name => $header)
            $select_header->add( Q($this->gettext($name)), Q($header) );
        if ( $rule['part'] != null )
            $out .= $select_header->show( Q( $this->gettext(
                    $this->headers[$rule['part']] ) ) );
        else
            $out .= $select_header->show();
        $out .= '</td>';
        
        // Create the <select> list for the match types
        $out .= '<td class="rowmatches">';
        $select_match = new html_select( array(
                'name'  => '_rules['.$rid.'][match]',
                'id'    => 'match'.$rid,
            ) );
        foreach($this->matches as $name => $match)
            $select_match->add( Q($this->gettext($name) ), Q($match) );
        if ( isset($rule['match']) )
            $out .= $select_match->show( Q( $this->gettext($this->matches[$rule['match']]) ) );
        else
            $out .= $select_match->show();
        
        $out .= '<input type="text" name="_rules['.$rid.'][val]" id="value' . $rid .
                '"value="' .Q($rule['val']). '" size="20" />'."\n".'</td>';
        
        // Create AND/OR <select> list
        $out .= '<td class="rowopts">';
        $select_opt = new html_select( array(
                'name'  => '_opts['.$rid.'][opt]',
                'id'    => 'opt'.$rid,
                'style' => 'display:'.( $rows<2 ? 'none' : 'inline' ),
            ) );
        $select_opt->add( Q($this->gettext('filterOr') ), Q('or') );
        $select_opt->add( Q($this->gettext('filterAnd') ), Q('and') );
//        if ( isset($rule['opt']) )
//            $out .= $select_opt->show( Q($rule['opt']) );
//        else
            $out .= $select_opt->show();
        $out .= '</td>';
        
        // Create Add/Delete buttons
        // MIGHT CHANGE TO USE JQUERY'S .click() HANDLER
        $out .= '<td class="rowbuttons">';
        $out .= '<input type="button" id="ruleadd' .$rid. '" value="' .
                Q($this->gettext('filterAdd')). '" onclick="rcmail.cpanel_filters_ruleadd(' .
                $rid. ')" class="button" /> ';
        $out .= '<input type="button" id="ruledel' .$rid. '" value="' .
                Q($this->gettext('filterDelete')). '" onclick="rcmail.cpanel_filters_ruledel(' .
                $rid. ')" class="button' . ($rows<2 ? ' disabled' : '') . '"' .
                ($rows<2 ? ' disabled="disabled"' : '') . ' />';
        $out .= '</td></tr></table>';

        // Close the div wrapper if set
        $out .= $div ? "</div>\n" : '';
        
        return $out;
    } // end parse_rules()
    
    function parse_actions( $fid, $aid, $div = true ) {  // fid = NULL,aid = int(0)
        $action = $this->filters[$fid]['actions'][$aid];  // returns NULL
        $rows = sizeof($this->filters[$fid]['actions']);  // returns int(0)
        
        // handle div wrapper for dynamic adding
        $out = $div ? '<div class="actionrow" id="actionrow'.$aid.'">'."\n" : '';
        $out .= '<table><tr>';
        
        // Create the <select> actions list
        $out .= '<td class="rowactions">';
        $select_action = new html_select( array(
                'name'      => '_actions['.$aid.'][action]',
                'id'        => 'action'.$aid,
//                'onchange'  => 'action_type_select(' .$aid. ')'
            ));
        foreach($this->actions as $name => $act)
            $select_action->add( Q($this->gettext($name) ), Q($act) );
        if ( isset($action['action']) )
            $out .= $select_action->show( 
                    Q( $this->gettext( $this->actions[$action['action']] ) ) );
        else
            $out .= $select_action->show();
        $out .= '</td>';
        
        // Create Action destination inputs
        $out .= '<td class="rowdest">';
        $out .= '<input type="text" name="_actions['.aid.'][dest]" id="dest'.$aid.
                '" value="'.
                ( ( $action['action']=='deliver' || $action['action']=='fail' ||
                !isset($action['action']) ) ? Q($action['dest'], 'strict', false) : '').
                '" size="40" style="display:' .
                ( ( $action['action']=='deliver' || $action['action']=='fail' ) ? 'inline' : 'none' ) . '" />';
        if ($action['action'] == 'save')
            $mailbox = $action['dest'];
        else
            $mailbox = '';
        $this->rcmail->imap_connect();
        $select_box = rcmail_mailbox_select(array(
	        'realnames' => false,
	        'maxlength' => 100,
	        'id' => 'action_mailbox' . $id,
	        'name' => '_action_mailbox[]',
	        'style' => 'display:'.
                ( ( $action['action']=='save' || !isset($action['action']) ) ? 'inline' : 'none' ),
	    ));
        $out .= $select_box->show($mailbox);
        $out .= '</td>';
        
        // add/del buttons
        $out .= '<td class="rowbuttons">';
        $out .= '<input type="button" id="actionadd'.$aid.'" value="'.Q($this->gettext('filterAdd')) .
                '" onclick="rcmail.cpanel_filters_actionadd('.$aid.')" class="button" /> ';
        $out .= '<input type="button" id="actiondel'.$aid.'" value="'.Q($this->gettext('filterDelete')) .
                '" onclick="rcmail.cpanel_filters_actiondel('.$aid.')" class="button' .
                ($rows<2 ? ' disabled' : '') .'"'. ($rows<2 ? ' disabled="disabled"' : '') .' />';
        $out .= '</td></tr></table>';
        
        // Close the div wrapper if set
        $out .= $div ? "</div>\n" : '';
        
        return $out;
    } // end parse_actions()
    
} // cpanel_filters class
?>