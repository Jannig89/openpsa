<?php
/**
 * @package org.openpsa.documents
 * @author Nemein Oy http://www.nemein.com/
 * @version $Id: view.php 26305 2010-06-09 14:19:07Z gudd $
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.documents document handler and viewer class.
 *
 * @package org.openpsa.documents
 *
 */
class org_openpsa_documents_handler_directory_view extends midcom_baseclasses_components_handler
{
    
    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var Array
     * @access private
     */
    private $_schemadb = null;

    private $_datamanager = null;

    /**
     * The documents of the directory we're working with.
     *
     * @var Array
     * @access private
     */
    private $_documents = array();
    /**
     * The directories of the directory we're working with.
     *
     * @var Array
     * @access private
     */
    private $_directories = array();
    /**
     * The wanted output mode.
     *
     * @var String
     * @access private
     */
    private $_output_mode = 'html';


    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        midcom::load_library('midcom.helper.datamanager2');
        $schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_document_listview'));
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($schemadb);
    }

    /**
     * Loads and prepares the schema database.
     *
     * The operations are done on all available schemas within the DB.
     */
    private function _load_schemadb()
    {
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_directory'));
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_view($handler_id, $args, &$data)
    {
        midcom::auth->require_valid_user();

        $qb = org_openpsa_documents_document_dba::new_query_builder();

        // Workgroup filtering
        if ($GLOBALS['org_openpsa_core_workgroup_filter'] != 'all')
        {
            debug_push('_show_directory');
            debug_add("Filtering documents by workgroup {$GLOBALS['org_openpsa_core_workgroup_filter']}");
            $qb->add_constraint('orgOpenpsaOwnerWg', '=', $GLOBALS['org_openpsa_core_workgroup_filter']);
            debug_pop();
        }
        //check if there is another output-mode wanted
        if(isset($args[0]))
        {
            $this->_output_mode = $args[0];
        }

        $current_topic = midcom::get_context_data(MIDCOM_CONTEXT_CONTENTTOPIC);

        switch($this->_output_mode)
        {
            case 'xml':
                $current_component = midcom::get_context_data(MIDCOM_CONTEXT_CONTENTTOPIC)->component;
                $parent_link = "";
                $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
                //check if id of a topic is passed
                if(isset($_POST['nodeid']))
                {
                    $node_id = $_POST['nodeid'];
                    $root_topic = new midcom_db_topic((int)$_POST['nodeid']);
                    while (($root_topic->get_parent()->component == $current_component)
                        && ($root_topic->id != $current_topic->id))
                    {
                        $parent_link = $root_topic->name . "/" . $parent_link;
                        $root_topic = $root_topic->get_parent();
                    }
                    $root_topic = new midcom_db_topic((int)$_POST['nodeid']);
                    $this->_request_data['parent_link'] = $parent_link;
                }
                else
                {
                    $root_topic = $current_topic;
                    $current_topic = $current_topic->get_parent();
                    if($current_topic->get_parent())
                    {
                        $this->_request_data['parent_directory'] = $current_topic;
                        $parent_link = substr($prefix , 0 , strlen($prefix) - (strlen($root_topic->name) + 1));
                    }
                    $this->_request_data['parent_up_link'] = $parent_link;
                }

                //show only documents of the right topic
                $qb->add_constraint('topic' , '=' , $root_topic->id);

                //get needed directories
                $this->_prepare_directories($root_topic , $current_component);
                //set header & style for xml
                midcom::header("Content-type: text/xml; charset=UTF-8");
                midcom::skip_page_style = true;

                break;
            //html
            default:
                $qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_DOCUMENT);
                $qb->add_constraint('topic', '=', $this->_request_data['directory']->id);
                $this->_prepare_output();
                break;
        }

        $this->_request_data['current_guid'] = $current_topic->guid;

        $qb->add_constraint('nextVersion', '=', 0);
        $qb->add_order('title');
        $this->_documents = $qb->execute();

        return true;
    }

    /**
     * Helper that adds toolbar items
     */
    private function _populate_toolbar()
    {
        if (midcom::auth->can_do('midgard:create', $this->_request_data['directory']))
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => 'document/create/',
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('new document'),
                    MIDCOM_TOOLBAR_HELPTEXT => '',
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-text.png',
                    MIDCOM_TOOLBAR_ENABLED => true,
                )
            );
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => 'create/',
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('new directory'),
                    MIDCOM_TOOLBAR_HELPTEXT => '',
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-dir.png',
                    MIDCOM_TOOLBAR_ENABLED => true,
                )
            );
        }
        if (midcom::auth->can_do('midgard:update', $this->_request_data['directory']))
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => 'edit/',
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit directory'),
                    MIDCOM_TOOLBAR_HELPTEXT => '',
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ENABLED => true,
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
        }
                
        midcom::bind_view_to_object($this->_request_data['directory']);
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_view($handler_id, &$data)
    {
        switch($this->_output_mode)
        {
            case 'html':
                midcom_show_style("show-directory-header");
                midcom_show_style("show-directory-footer");
                break;
            case 'xml':
                $this->_request_data['documents'] = $this->_documents;
                $this->_request_data['directories'] = $this->_directories;
                $this->_request_data['datamanager'] = $this->_datamanager;
                midcom_show_style("show-directory-xml");
                break;
        }
    }

    /**
     * Helper function to add needed css & js files
     */
    function _prepare_output()
    {
        $this->_request_data['prefix'] = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

        //load js/css for jqgrid
        org_openpsa_core_ui::enable_jqgrid();

        midcom::add_link_head
        (
            array
            (
                'rel'   => 'stylesheet',
                'type'  => 'text/css',
                'media' => 'all',
                'href'  => MIDCOM_STATIC_URL . "/org.openpsa.documents/layout.css",
            )
        );

        midcom::add_link_head
        (
            array
            (
                'rel'   => 'stylesheet',
                'type'  => 'text/css',
                'media' => 'all',
                'href'  => MIDCOM_STATIC_URL . "/org.openpsa.contactwidget/hcard.css",
            )
        );

        $this->_populate_toolbar();
    }

    /**
     * Helper function to get directories for passed topic of the passed component
     *
     * @param root_topic - the topic to search the directories for
     * @param current_component - component of the topic/directories
     */
    function _prepare_directories(&$root_topic , &$current_component)
    {

        $qb = midcom_db_topic::new_query_builder();
        $qb->add_constraint("component", "=", $current_component);
        $qb->add_constraint("up", "=", $root_topic->id);
        $this->_directories = $qb->execute();

    }

}
?>