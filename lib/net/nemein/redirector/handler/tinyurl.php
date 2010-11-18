<?php
/**
 * @package net.nemein.redirector
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: viewer.php 17797 2008-09-30 08:32:08Z adrenalin $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class net_nemein_redirector_handler_tinyurl extends midcom_baseclasses_components_handler
{
    /**
     * TinyURL object
     *
     * @access private
     * @var net_nemein_redirector_tinyurl
     */
    var $_tinyurl = null;

    /**
     * TinyURL object array
     *
     * @access private
     * @var array
     */
    var $_tinyurls = array();

    /**
     * Datamanager2 instance
     *
     * @access private
     * @var midcom_helper_datamanager2_datamanager
     */
    var $_datamanager = null;

    /**
     * Datamanager2 schemadb
     *
     * @access private
     * @var midcom_helper_datamanager2_schema
     */
    var $_schema = null;

    /**
     * Controller for creating or editing
     *
     * @access private
     * @var mixed
     */
    var $_controller = null;

    /**
     * Constructor
     *
     * @access public
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Initialization scripts
     *
     * @access public
     */
    function _on_initialize()
    {
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_tinyurl'));
    }

    /**
     * Populate request data
     *
     * @access private
     * @param String $handler_id
     */
    function _populate_request_data($handler_id)
    {
        $tmp = array();

        if ($this->_tinyurl)
        {
            $tmp[] = array
            (
                MIDCOM_NAV_URL => "{$this->_tinyurl->name}/",
                MIDCOM_NAV_NAME => $this->_tinyurl->title,
            );
            $this->_view_toolbar->bind_to($this->_tinyurl);
        }

        switch ($handler_id)
        {
            case 'edit':
            case 'delete':
                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "{$this->_tinyurl->name}/{$handler_id}",
                    MIDCOM_NAV_NAME => $this->_l10n->get($this->_l10n_midcom->get($handler_id)),
                );
                break;

            case 'create':
                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "create/",
                    MIDCOM_NAV_NAME => sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('tinyurl')),
                );
                break;
        }

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     * Get the item according to the given rule
     *
     * @access private
     * @param mixed $rule
     * @return false on failure or net_nemein_redirector_tinyurl_dba on success
     */
    function _get_item($rule)
    {
        $mc = net_nemein_redirector_tinyurl_dba::new_collector('node', $this->_topic->guid);

        // Set the rules
        $mc->begin_group('OR');
            $mc->add_constraint('guid', '=', $rule);
            $mc->add_constraint('name', '=', $rule);
        $mc->end_group();
        $mc->execute();

        $keys = $mc->list_keys();

        if (count($keys) === 0)
        {
            return false;
        }

        foreach ($keys as $guid => $array)
        {
            break;
        }

        $item = new net_nemein_redirector_tinyurl_dba($guid);
        return $item;
    }

    /**
     * DM2 creation callback, binds to the current content topic.
     */
    function &dm2_create_callback (&$controller)
    {
        $this->_tinyurl = new net_nemein_redirector_tinyurl_dba();
        $this->_tinyurl->node = $this->_topic->guid;

        if (!$this->_tinyurl->create())
        {
            midcom::generate_error(MIDCOM_ERRCRIT,
                'Failed to create a new TinyURL object, cannot continue. Last Midgard error was: '. midcom_connection::get_error_string());
            // This will exit.
        }

        return $this->_tinyurl;
    }

    /**
     * Create a new TinyURL
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean True if the request can be handled, false otherwise.
     */
    function _handler_create($handler_id, $args, &$data)
    {
        $this->_topic->require_do('midgard:create');

        // Ensure that datamanager is available
        midcom::load_library('midcom.helper.datamanager2');

        // Load the controller
        $this->_controller = midcom_helper_datamanager2_controller::create('create');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->callback_object =& $this;

        // Initialize
        if (!$this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 create controller.");
            // This will exit.
        }

        switch ($this->_controller->process_form())
        {
            case 'save':
                midcom::relocate("edit/{$this->_tinyurl->name}");
                // This will exit
        }

        // Set the request data
        $this->_populate_request_data($handler_id);

        return true;
    }

    /**
     * Show the creation form
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array &$data The local request data.
     */
    function _show_create($handler_id, &$data)
    {
        $data['controller'] =& $this->_controller;
        midcom_show_style('tinyurl-create');
    }

    /**
     * Edit an existing TinyURL
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean True if the request can be handled, false otherwise.
     */
    function _handler_edit($handler_id, $args, &$data)
    {
        $this->_tinyurl = $this->_get_item($args[0]);

        // Show error page on failure
        if (   !$this->_tinyurl
            || !$this->_tinyurl->guid)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, 'Item not found');
            // This will exit
        }

        $this->_tinyurl->require_do('midgard:update');

        // Ensure that datamanager is available
        midcom::load_library('midcom.helper.datamanager2');

        // Edit controller
        $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->set_storage($this->_tinyurl);
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 controller instance for article {$this->_article->id}.");
            // This will exit.
        }
        $data['controller'] =& $this->_controller;
        $data['tinyurl'] =& $this->_tinyurl;

        switch ($this->_controller->process_form())
        {
            case 'save':
                midcom::uimessages()->add($this->_l10n->get('net.nemein.redirector'), $this->_l10n_midcom->get('saved'));
                // Fall through

            case 'cancel':
                midcom::relocate('');
        }

        // Set the request data
        $this->_populate_request_data($handler_id);

        return true;
    }

    /**
     * Show the creation form
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array &$data The local request data.
     */
    function _show_edit($handler_id, &$data)
    {
        midcom_show_style('tinyurl-edit');
    }

    /**
     * List TinyURLs
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean True if the request can be handled, false otherwise.
     */
    function _handler_list($handler_id, $args, &$data)
    {
        // Get the topic link and relocate accordingly
        $data['url'] = net_nemein_redirector_viewer::topic_links_to($data);

        $qb = net_nemein_redirector_tinyurl_dba::new_query_builder();
        $qb->add_constraint('node', '=', $this->_topic->guid);

        $this->_tinyurls = $qb->execute();

        // Initialize the datamanager instance
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_schemadb);

        // Set the request data
        $this->_populate_request_data($handler_id);

        return true;
    }

    /**
     * Show the list of TinyURL's
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array &$data The local request data.
     */
    function _show_list($handler_id, &$data)
    {
        midcom_show_style('tinyurl-list-start');

        $data['datamanager'] =& $this->_datamanager;

        foreach ($this->_tinyurls as $tinyurl)
        {
            $data['tinyurl'] =& $tinyurl;
            $data['datamanager']->autoset_storage($tinyurl);
            $data['view_tinyurl'] = $data['datamanager']->get_content_html();

            midcom_show_style('tinyurl-list-item');
        }

        midcom_show_style('tinyurl-list-end');
    }
}
?>