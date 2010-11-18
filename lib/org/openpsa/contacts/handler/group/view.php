<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @version $Id: view.php 26541 2010-07-10 05:48:54Z flack $
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.contacts group handler and viewer class.
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_group_view extends midcom_baseclasses_components_handler
{
    /**
     * The Controller of the group to display
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
    var $_schemadb = null;

    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        midcom::load_library('midcom.helper.datamanager2');
    }

    /**
     * Loads and prepares the schema database.
     *
     * The operations are done on all available schemas within the DB.
     */
    function _load_schemadb()
    {
        $this->_schemadb =& midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_group'));
    }

    function _load($identifier)
    {
        $this->_group = new org_openpsa_contacts_group_dba($identifier);

        if (!$this->_group)
        {
            return false;
        }

        $this->_load_schemadb();
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_schemadb);

        if (   ! $this->_datamanager
            || ! $this->_datamanager->autoset_storage($this->_group))
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to create a DM2 instance for group #{$this->_group->id}.");
            // This will exit.
        }
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

        // Get the requested group object
        $this->_load($args[0]);
        if (!$this->_group
            || !$this->_group->guid)
        {
            return false;
        }

        $this->_request_data['group'] =& $this->_group;
        $this->_request_data['view'] =& $this->_datamanager->get_content_html();

        $root_group = org_openpsa_contacts_interface::find_root_group();
        if ($this->_group->owner != $root_group->id)
        {
            $this->_request_data['parent_group'] = $this->_group->get_parent();
        }
        else
        {
            $this->_request_data['parent_group'] = false;
        }

        //pass billing-data if invoices is installed
        if (midcom::componentloader->is_installed('org.openpsa.invoices'))
        {
            $qb_billing_data = org_openpsa_invoices_billing_data_dba::new_query_builder();
            $qb_billing_data->add_constraint('linkGuid' , '=' , $this->_group->guid);
            $billing_data = $qb_billing_data->execute();
            if (count($billing_data) > 0)
            {
                $this->_request_data['billing_data'] = $billing_data[0];
            }
        }

        // Add toolbar items
        $this->_populate_toolbar();
        midcom::bind_view_to_object($this->_group);

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );
        // This handler uses Ajax, include the handler javascripts
        midcom::add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.helpers/ajaxutils.js");
        org_openpsa_core_ui::enable_ui_tab();

        midcom::set_pagetitle($this->_group->official);

        $this->_update_breadcrumb_line();

        return true;
    }

    private function _populate_toolbar()
    {
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "group/edit/{$this->_group->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get("edit"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:update', $this->_group),
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "group/create/{$this->_group->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create suborganization'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_people-new.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_group->can_do('midgard:update'),
            )
        );

        if (   midcom::auth->can_user_do('midgard:create', null, 'org_openpsa_contacts_person_dba')
            && midcom::auth->can_do('midgard:create', $this->_group))
        {
            $allow_person_create = true;
        }
        else
        {
            $allow_person_create = false;
        }

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "person/create/{$this->_group->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create person'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_person-new.png',
                MIDCOM_TOOLBAR_ENABLED => $allow_person_create,
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "group/notifications/{$this->_group->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("notification settings"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock-discussion.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:update', $this->_group),
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "group/privileges/{$this->_group->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("permissions"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'midgard.admin.asgard/permissions-16.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:privileges', $this->_group),
            )
        );

        $cal_node = midcom_helper_find_node_by_component('org.openpsa.calendar');
        if (!empty($cal_node))
        {
            //TODO: Check for privileges somehow
            $this->_node_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "#",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create event'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_new-event.png',
                    MIDCOM_TOOLBAR_ENABLED => true,
                    MIDCOM_TOOLBAR_OPTIONS  => array
                    (
                        'rel' => 'directlink',
                        'onclick' => org_openpsa_calendar_interface::calendar_newevent_js($cal_node, false, $this->_group->guid),
                    ),
                )
            );
        }
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     */
    private function _update_breadcrumb_line()
    {
        $tmp = Array();

        org_openpsa_contacts_viewer::get_breadcrumb_path_for_group($this->_group, $tmp);

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_view($handler_id, &$data)
    {
        midcom_show_style("show-group");
    }

}
?>