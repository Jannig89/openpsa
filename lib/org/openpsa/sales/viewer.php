<?php
/**
 * @package org.openpsa.sales
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: viewer.php 26544 2010-07-11 14:42:47Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Sales viewer interface class.
 *
 * @package org.openpsa.sales
 */
class org_openpsa_sales_viewer extends midcom_baseclasses_components_request
{

    function __construct($topic, $config)
    {
        parent::__construct($topic, $config);

        // Match /list/<status>
        $this->_request_switch['list_status'] = array
        (
            'handler' => array('org_openpsa_sales_handler_list', 'list'),
            'fixed_args' => array('list'),
            'variable_args' => 1,
        );

        // Match /salesproject/edit/<salesproject>
        $this->_request_switch['salesproject_edit'] = array
        (
            'handler' => array('org_openpsa_sales_handler_edit', 'edit'),
            'fixed_args' => array('salesproject', 'edit'),
            'variable_args' => 1,
        );

        // Match /salesproject/new
        $this->_request_switch['salesproject_new'] = array
        (
            'handler' => array('org_openpsa_sales_handler_edit', 'new'),
            'fixed_args' => array('salesproject', 'new'),
        );

        // Match /salesproject/<salesproject>
        $this->_request_switch['salesproject_view'] = array
        (
            'handler' => array('org_openpsa_sales_handler_view', 'view'),
            'fixed_args' => array('salesproject'),
            'variable_args' => 1,
        );

        // Match /deliverable/add/<salesproject>/
        $this->_request_switch['deliverable_add'] = array
        (
            'handler' => array('org_openpsa_sales_handler_deliverable_add', 'add'),
            'fixed_args' => array('deliverable', 'add'),
            'variable_args' => 1,
        );

        // Match /deliverable/process/<deliverable>/
        $this->_request_switch['deliverable_process'] = array
        (
            'handler' => array('org_openpsa_sales_handler_deliverable_process', 'process'),
            'fixed_args' => array('deliverable', 'process'),
            'variable_args' => 1,
        );

        // Match /deliverable/edit/<deliverable>
        $this->_request_switch['deliverable_edit'] = array
        (
            'handler' => array('org_openpsa_sales_handler_deliverable_admin', 'edit'),
            'fixed_args' => array('deliverable', 'edit'),
            'variable_args' => 1,
        );

        // Match /deliverable/<deliverable>
        $this->_request_switch['deliverable_view'] = array
        (
            'handler' => array('org_openpsa_sales_handler_deliverable_view', 'view'),
            'fixed_args' => array('deliverable'),
            'variable_args' => 1,
        );

        // Match /
        $this->_request_switch['frontpage'] = array
        (
            'handler' => array('org_openpsa_sales_handler_frontpage', 'frontpage'),
        );
    }

    /**
     * Generic request startup work:
     *
     * - Load the Schema Database
     * - Add the LINK HTML HEAD elements
     */
    function _on_handle($handler, $args)
    {
        midcom::load_library('org.openpsa.contactwidget');

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.projects/projects.css",
            )
        );

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.invoices/invoices.css",
            )
        );

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.sales/sales.css",
            )
        );

        midcom::auth->require_valid_user();

        return true;
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     * @param mixed $object
     */
    function update_breadcrumb_line($object)
    {
        $tmp = array();

        while ($object)
        {
            if (midcom::dbfactory()->is_a($object, 'org_openpsa_sales_salesproject_deliverable_dba'))
            {
                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "deliverable/{$object->guid}/",
                    MIDCOM_NAV_NAME => $object->title,
                );
            }
            else
            {
                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "salesproject/{$object->guid}/",
                    MIDCOM_NAV_NAME => $object->title,
                );
            }
            $object = $object->get_parent();
        }
        $tmp = array_reverse($tmp);
        return $tmp;
    }
}

?>