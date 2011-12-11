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
        $this->register_action('plugin.cpanel_filters',array($this,'cpf_actions'));
        $this->register_action('plugin.cpanel_filters-add',array($this,'cpf_actions'));
        
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
    
    function cpf_actions() {
        $this->_start();
        $this->cpf_send();
    } // end cpf_actions()
    
    function cpf_send() {
        $this->rcmail->output->set_pagetitle( Q( $this->gettext('filterManage') ) );
        $this->rcmail->output->send('cpanel_filters.cpanel_filters');
    } // end cpf_send
    
    function html_filterlist($attr) {
        $out = '';
        return $out;
    }
    
    function html_filterframe($attr) {
        $out = '';
        return $out;
    }
    
    function html_filterform($attr) {
        $out = '';
        return $out;
    }
    
} // cpanel_filters class
?>