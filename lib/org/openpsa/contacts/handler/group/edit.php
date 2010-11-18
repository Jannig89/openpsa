<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @version $Id: edit.php 26257 2010-06-01 15:19:12Z gudd $
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.contacts group handler and viewer class.
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_group_edit extends midcom_baseclasses_components_handler
{
    /**
     * The group we're working on
     *
     * @var org_openpsa_contacts_group_dba
     */
    private $_group = null;

    /**
     * The Datamanager of the contact to display
     *
     * @var midcom_helper_datamanager2_datamanager
     * @access private
     */
    private $_datamanager = null;

    /**
     * The Controller of the organization used for editing
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
     * Schema to use for organization display
     *
     * @var string
     * @access private
     */
    private $_schema = null;

    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        midcom::load_library('midcom.helper.datamanager2');
        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/midcom.helper.datamanager2/legacy.css",
            )
        );
    }


    /**
     * Internal helper, loads the controller for the current contact. Any error triggers a 500.
     *
     * @access private
     */
    private function _load_controller()
    {
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_group'));
        $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        $this->_controller->schemadb =& $this->_schemadb;

        $this->_controller->set_storage($this->_group, $this->_schema);
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 controller instance for contact {$this->_contact->id}.");
            // This will exit.
        }
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_edit($handler_id, $args, &$data)
    {
        midcom::auth->require_valid_user();
        // Check if we get the group
        $this->_group = new org_openpsa_contacts_group_dba($args[0]);
        if (!$this->_group)
        {
            return false;
        }

        $this->_group->require_do('midgard:update');

        $this->_load_controller();

        switch ($this->_controller->process_form())
        {
            case 'save':
                // Index the organization
                $indexer = midcom::get_service('indexer');
                org_openpsa_contacts_viewer::index_group($this->_controller->datamanager, $indexer, $this->_content_topic);

                // *** FALL-THROUGH ***

            case 'cancel':
                $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
                midcom::relocate($prefix . "group/" . $this->_group->guid . "/");
                // This will exit.
        }

        $root_group = org_openpsa_contacts_interface::find_root_group($this->_config);

        if ($this->_group->owner
            && $this->_group->owner != $root_group->id)
        {
            $this->_request_data['parent_group'] = new org_openpsa_contacts_group_dba($this->_group->owner);
        }
        else
        {
            $this->_request_data['parent_group'] = false;
        }

        $this->_request_data['controller'] =& $this->_controller;
        $this->_request_data['group'] =& $this->_group;

        org_openpsa_helpers::dm2_savecancel($this);
        midcom::bind_view_to_object($this->_group);

        midcom::set_pagetitle(sprintf($this->_l10n_midcom->get('edit %s'), $this->_group->official));

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );

        $this->_update_breadcrumb_line();
        
        return true;
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     */
    function _update_breadcrumb_line()
    {
        $tmp = Array();

        org_openpsa_contacts_viewer::get_breadcrumb_path_for_group($this->_group, $tmp);

        $tmp[] = array
        (
            MIDCOM_NAV_URL => "",
            MIDCOM_NAV_NAME => sprintf($this->_l10n_midcom->get('edit %s'), $this->_l10n->get('organization')),
        );

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_edit($handler_id, &$data)
    {
        midcom_show_style("show-group-edit");
    }

}
?>