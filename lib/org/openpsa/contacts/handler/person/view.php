<?php
/**
 * @package org.openpsa.contacts
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: view.php 26536 2010-07-09 14:55:05Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Person display class
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_person_view extends midcom_baseclasses_components_handler
{
    /**
     * The contact to display
     *
     * @var org_openpsa_contacts_person_dba
     * @access private
     */
    private $_contact = null;

    /**
     * Schema to use for contact display
     *
     * @var string
     * @access private
     */
    private $_schema = null;

    /**
     * The user object for the current person, if any
     *
     * @var midcom_core_user
     */
    private $_person_user;

    /**
     * The Controller of the contact used for editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     * @access private
     */
    private $_controller = null;

    /**
     * Simple default constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        midcom::load_library('midcom.helper.datamanager2');
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['person'] =& $this->_contact;
        $this->_request_data['controller'] =& $this->_controller;
        $this->_request_data['person_user'] =& $this->_person_user;
    }

    private function _load_controller()
    {
        $schemadb_person = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_person'));

        $this->_modify_schema();

        $this->_controller = midcom_helper_datamanager2_controller::create('ajax');
        $this->_controller->schemadb =& $schemadb_person;
        $this->_controller->set_storage($this->_contact, $this->_schema);
        $this->_controller->process_ajax();
    }

    private function _modify_schema()
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $owner_guid = $siteconfig->get_my_company_guid();
        if ($owner_guid)
        {
            // Figure out if user is from own organization or other org
            $this->_person_user = new midcom_core_user($this->_contact->id);

            if (   is_object($this->_person_user)
                && method_exists($this->_person_user, 'is_in_group')
                && $this->_person_user->is_in_group("group:{$owner_guid}"))
            {
                $this->_schema = 'employee';
            }
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

        $tmp[] = array
        (
            MIDCOM_NAV_URL => "person/{$this->_contact->guid}/",
            MIDCOM_NAV_NAME => $this->_contact->name,
        );

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     * Looks up a contact to display.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_view($handler_id, $args, &$data)
    {
        $this->_contact = new org_openpsa_contacts_person_dba($args[0]);
        if (!$this->_contact)
        {
            return false;
        }

        $this->_prepare_request_data();
        $this->_load_controller();

        $data['person_rss_url'] = $this->_contact->get_parameter('net.nemein.rss', 'url');
        if ($data['person_rss_url'])
        {
            // We've autoprobed that this contact has a RSS feed available, link it
            midcom::add_link_head
            (
                array
                (
                    'rel'   => 'alternate',
                    'type'  => 'application/rss+xml',
                    'title' => sprintf($this->_l10n->get('rss feed of person %s'), $this->_contact->name),
                    'href'  => $data['person_rss_url'],
                )
            );
        }
        // This handler uses Ajax, include the javascript
        midcom::add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.helpers/ajaxutils.js");
        //enable ui_tab
        org_openpsa_core_ui::enable_ui_tab();

        $this->_populate_toolbar($handler_id);
        midcom::bind_view_to_object($this->_contact, $this->_controller->datamanager->schema->name);
        $this->_update_breadcrumb_line();
        midcom::set_pagetitle($this->_contact->name);

        return true;
    }

    /**
     * Helper function that populates the toolbar with the necessary items
     *
     * @param string $handler_id the ID of the current handler
     */
    private function _populate_toolbar($handler_id)
    {
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "person/edit/{$this->_contact->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_contact->can_do('midgard:update'),
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
            )
        );
        if (   midcom::componentloader->is_installed('org.openpsa.invoices')
            && midcom::auth->can_user_do('midgard:create', null, 'org_openpsa_invoices_invoice_dba'))
        {
            $billing_data_url = "create/" . $this->_contact->guid ."/";
            $qb_billing_data = org_openpsa_invoices_billing_data_dba::new_query_builder();
            $qb_billing_data->add_constraint('linkGuid' , '=' , $this->_contact->guid);
            $billing_data = $qb_billing_data->execute();
            if(count($billing_data) > 0)
            {
                $billing_data_url = $billing_data[0]->guid . "/";
            }
            $siteconfig = org_openpsa_core_siteconfig::get_instance();
            $invoices_url = $siteconfig->get_node_full_url('org.openpsa.invoices');
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $invoices_url."billingdata/" . $billing_data_url,
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit billingdata'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ENABLED => $this->_contact->can_do('midgard:update'),
                )
            );
        }

        if ($this->_contact->username)
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "account/edit/{$this->_contact->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit account'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
                    MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:update', $this->_contact),
                )
            );
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "person/privileges/{$this->_contact->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("permissions"),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'midgard.admin.asgard/permissions-16.png',
                    MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:privileges', $this->_contact),
                )
            );
        }
        else
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "account/create/{$this->_contact->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create account'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
                    MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:update', $this->_contact),
                )
            );
        }

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "person/delete/{$this->_contact->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('delete'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_contact->can_do('midgard:delete'),
                MIDCOM_TOOLBAR_ACCESSKEY => 'd',
            )
        );

        $qb = org_openpsa_contacts_buddy_dba::new_query_builder();
        $user = midcom::auth->user->get_storage();
        $qb->add_constraint('account', '=', $user->guid);
        $qb->add_constraint('buddy', '=', $this->_contact->guid);
        $qb->add_constraint('blacklisted', '=', false);
        $buddies = $qb->count();
        if ($buddies > 0)
        {
            // We're buddies, show remove button
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "buddylist/remove/{$this->_contact->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('remove buddy'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                    MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:delete', $buddies[0]),
                )
            );
        }
        else
        {
            // We're not buddies, show add button
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "buddylist/add/{$this->_contact->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('add buddy'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_person.png',
                    MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_do('midgard:create', $user),
                )
            );
        }
    }

    /**
     * Shows the loaded contact.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_view($handler_id, &$data)
    {
        // For AJAX handling it is the controller that renders everything
        $data['contact_view'] =& $this->_controller->get_content_html();
        $data['datamanager'] =& $this->_controller->datamanager;

        midcom_show_style('show-person');
    }
}
?>