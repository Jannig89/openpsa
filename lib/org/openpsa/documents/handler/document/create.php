<?php
/**
 * @package org.openpsa.documents
 * @author Nemein Oy http://www.nemein.com/
 * @version $Id: create.php 26168 2010-05-24 12:51:58Z flack $
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.documents document handler and viewer class.
 *
 * @package org.openpsa.documents
 *
 */
class org_openpsa_documents_handler_document_create extends midcom_baseclasses_components_handler
{

    /**
     * The document we're working with (if any).
     *
     * @var org_openpsa_documents_documen_dba
     * @access private
     */
    private $_document = null;

    /**
     * The Controller of the document used for creating or editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     * @access private
     */
    private $_controller = null;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var Array
     * @access private
     */
    private $_schemadb = null;

    /**
     * The schema to use for the new document.
     *
     * @var string
     * @access private
     */
    private $_schema = 'default';

    /**
     * The defaults to use for the new document.
     *
     * @var Array
     * @access private
     */
    private $_defaults = array();

    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        midcom::load_library('midcom.helper.datamanager2');
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_document'));
    }

    /**
     * Internal helper, fires up the creation mode controller. Any error triggers a 500.
     *
     * @access private
     */
    private function _load_create_controller()
    {
        $this->_controller = midcom_helper_datamanager2_controller::create('create');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->schemaname = $this->_schema;
        $this->_controller->callback_object =& $this;
        $this->_controller->defaults =& $this->_defaults;
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 create controller.");
            // This will exit.
        }
    }

    /**
     * This is what Datamanager calls to actually create a document
     */
    function & dm2_create_callback(&$datamanager)
    {
        $document = new org_openpsa_documents_document_dba();
        $document->topic = $this->_request_data['directory']->id;
        $document->orgOpenpsaAccesstype = ORG_OPENPSA_ACCESSTYPE_WGPRIVATE;

        if (! $document->create())
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('We operated on this object:', $document);
            debug_pop();
            midcom::generate_error(MIDCOM_ERRCRIT,
                "Failed to create a new document, cannot continue. Error: " . midcom_connection::get_error_string());
            // This will exit.
        }

        $this->_document = $document;

        return $document;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_create($handler_id, $args, &$data)
    {
        midcom::auth->require_do('midgard:create', $this->_request_data['directory']);

        $this->_defaults = array
        (
            'topic' => $this->_request_data['directory']->id,
            'author' => midcom_connection::get_user(),
            'orgOpenpsaAccesstype' => $this->_topic->get_parameter('org.openpsa.core', 'orgOpenpsaAccesstype'),
            'orgOpenpsaOwnerWg' => $this->_topic->get_parameter('org.openpsa.core', 'orgOpenpsaOwnerWg'),
        );

        $this->_load_create_controller();

        switch ($this->_controller->process_form())
        {
            case 'save':
                /* Index the document */
                $indexer = midcom::get_service('indexer');
                $indexer->index($this->_controller->datamanager);

                // Relocate to document view
                $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
                if ($this->_document->topic != $this->_topic->id)
                {
                    $nap = new midcom_helper_nav();
                    $node = $nap->get_node($this->_document->topic);
                    $prefix = $node[MIDCOM_NAV_ABSOLUTEURL];
                }

                midcom::relocate($prefix  . "document/" . $this->_document->guid . "/");
                // This will exit
            case 'cancel':
                midcom::relocate('');
                // This will exit
        }
        $this->_request_data['controller'] =& $this->_controller;

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this); 
        
        $tmp = Array();

        $tmp[] = Array
        (
            MIDCOM_NAV_URL => "",
            MIDCOM_NAV_NAME => $this->_l10n->get('create document'),
        );

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . '/org.openpsa.core/ui-elements.css',
            )
        );

        return true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_create($handler_id, &$data)
    {
        midcom_show_style("show-document-create");
    }

}
?>