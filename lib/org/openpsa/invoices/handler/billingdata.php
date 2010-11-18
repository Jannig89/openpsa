<?php
/**
 * @package org.openpsa.invoices
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: action.php 26143 2010-05-18 15:07:48Z gudd $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * billing_data handlers
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_handler_billingdata extends midcom_baseclasses_components_handler
{
    /**
     * contains the object the billing data is linked to
     *
     * @var object
     */
    private $_linked_object = null;

    /**
     * contains the billing_data
     *
     * @var object
     */
    private $_billing_data = null;

    /**
     * contains datamanager-controller
     */
     private $_controller = null;

     /**
     * contains datamanager object
     */
     private $_datamanager = null;
     /**
     * contains schema for datamanager
     */
     private $_schemadb = null;

     /**
     * contains schema-name
     */
     private $_schema = 'default';

    function _handler_billingdata($handler_id, $args, &$data)
    {
        //get billing_data
        $this->_billing_data = org_openpsa_invoices_billing_data_dba::get_cached($args[0]);
        $this->_linked_object = midcom::dbfactory()->get_object_by_guid($this->_billing_data->linkGuid);

        midcom::set_pagetitle(midcom::i18n()->get_string('edit' , 'midcom') . " " . $this->_l10n->get("billing data"));

        $this->_prepare_datamanager();
        $this->_load_controller();
        $this->_process_billing_form();

        midcom::enable_jquery();
        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );

        $this->_update_breadcrumb();

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);
        midcom::bind_view_to_object($this->_billing_data);

        $this->_request_data['datamanager'] =& $this->_datamanager;
        $this->_request_data['controller'] =& $this->_controller;

        return true;
    }

    function _show_billingdata($handler_id, &$data)
    {
        midcom_show_style('show-billingdata');
    }
    /**
     * function to load libraries/schemas for datamanager
     */
    private function _prepare_datamanager()
    {
        midcom::load_library('midcom.helper.datamanager2');
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_billing_data'));

        $fields =& $this->_schemadb[$this->_schema]->fields;
        // Fill VAT select
        $vat_array = explode(',', $this->_config->get('vat_percentages'));
        if (   is_array($vat_array)
            && count($vat_array) > 0)
        {
            $vat_values = array();
            foreach ($vat_array as $vat)
            {
                $vat_values[$vat] = "{$vat}%";
            }
            $fields['vat']['type_config']['options'] = $vat_values;
        }
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_schemadb);

        if (!$this->_datamanager)
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Datamanager could not be instantiated.");
            // This will exit.
        }
    }

    /**
     * load controller for datamanager
     *
     * @access private
     */
    private function _load_controller()
    {
        if($this->_billing_data)
        {
            $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        }
        else
        {
            $this->_controller = midcom_helper_datamanager2_controller::create('create');
        }
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->schemaname = $this->_schema;
        $this->_controller->callback_object =& $this;
        if($this->_billing_data)
        {
            $this->_controller->set_storage($this->_billing_data, $this->_schema);
        }
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 create controller.");
            // This will exit.
        }

        $dummy_invoice = new org_openpsa_invoices_invoice_dba();
        //set the defaults for vat & due to the schema
        $this->_controller->schemadb[$this->_schema]->fields['due']['default'] = $dummy_invoice->get_default_due();
        $this->_controller->schemadb[$this->_schema]->fields['vat']['default'] = $dummy_invoice->get_default_vat();
        unset($dummy_invoice);
    }

    /**
     * Datamanager callback
     */
    function & dm2_create_callback(&$datamanager)
    {
        $billing_data = new org_openpsa_invoices_billing_data_dba();
        $billing_data->linkGuid = $this->_linked_object->guid;
        if (! $billing_data->create())
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('We operated on this object:', $billing_data);
            debug_pop();
            midcom::generate_error(MIDCOM_ERRCRIT,
                "Failed to create a new billing_data, cannot continue. Error: " . midcom_connection::get_error_string());
            // This will exit.
        }

        return $billing_data;
    }

    /**
     * helper to update the breadcrumb
     */
    private function _update_breadcrumb()
    {
        $ref = midcom_helper_reflector::get($this->_linked_object);
        $object_label = $ref->get_object_label($this->_linked_object);

        $tmp[] = array
        (
            MIDCOM_NAV_URL => midcom::permalinks->create_permalink($this->_linked_object->guid),
            MIDCOM_NAV_NAME => $object_label,
        );
        $tmp[] = array
        (
            MIDCOM_NAV_URL => '#',
            MIDCOM_NAV_NAME => $this->_l10n->get('billing data') . " : " . $object_label,
        );
        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    function _handler_create($handler_id, $args, &$data)
    {
        midcom::auth->require_valid_user();

        $this->_linked_object = midcom::dbfactory()->get_object_by_guid($args[0]);
        if(empty($this->_linked_object->guid))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('Passed guid does not exists. GUID :', $args[0]);
            debug_pop();
        }

        midcom::set_pagetitle((midcom::i18n()->get_string('create' , 'midcom') . " " . $this->_l10n->get("billing data")));

        $this->_prepare_datamanager();
        $this->_load_controller();

        $this->_process_billing_form();

        midcom::enable_jquery();
        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );

        $this->_update_breadcrumb();

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);


        $this->_request_data['datamanager'] =& $this->_datamanager;
        $this->_request_data['controller'] =& $this->_controller;

        return true;
    }

    function _show_create($handler_id, &$data)
    {
        midcom_show_style('show-billingdata');
    }

    /**
     * helper function to process the form of the controller
     */
    private function _process_billing_form()
    {
        switch ($this->_controller->process_form())
        {
            case 'save':
            case 'cancel':
                $siteconfig = org_openpsa_core_siteconfig::get_instance();
                $relocate = $siteconfig->get_node_full_url('org.openpsa.contacts');
                switch(true)
                {
                    case is_a($this->_linked_object , 'org_openpsa_contacts_person_dba'):
                        $relocate .= 'person/' . $this->_linked_object->guid . '/';
                        break;
                    case is_a($this->_linked_object , 'org_openpsa_contacts_group_dba'):
                        $relocate .= 'group/' . $this->_linked_object->guid . '/';
                        break;
                    default:
                        $relocate = midcom::permalinks->create_permalink($this->_linked_object->guid);
                        break;
                }
                midcom::relocate($relocate);
                // This will exit.
        }
    }
}
?>