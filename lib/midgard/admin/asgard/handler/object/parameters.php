<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: parameters.php 23025 2009-07-28 10:03:50Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Object parameters interface
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_parameters extends midcom_baseclasses_components_handler
{
    /**
     * The hour report
     *
     * @var org_openpsa_projectsobject
     * @access private
     */
    var $object = null;

    /**
     * The Controller of the object used for editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     * @access private
     */
    var $_controller = null;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var array
     * @access private
     */
    var $_schemadb = null;

    /**
     * Simple default constructor.
     */
    function __construct()
    {
        $this->_component = 'midgard.admin.asgard';
        parent::__construct();
    }

    function _on_initialize()
    {
        // Ensure we get the correct styles
        midcom::style->prepend_component_styledir('midgard.admin.asgard');
        midcom::skip_page_style = true;

        midcom::load_library('midcom.helper.datamanager2');
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    function _prepare_request_data()
    {
        $this->_request_data['object'] =& $this->_object;
    }

    /**
     * Object editing view
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_edit($handler_id, $args, &$data)
    {
        $this->_object = midcom::dbfactory()->get_object_by_guid($args[0]);
        if (   !$this->_object
            || !$this->_object->guid)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "The GUID '{$args[0]}' was not found.");
            // This will exit
        }
        $this->_object->require_do('midgard:update');
        $this->_object->require_do('midgard:parameters');
        midcom::auth->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');

        // List all parameters for display
        $qb = midcom_db_parameter::new_query_builder();
        $qb->add_constraint('parentguid', '=', $this->_object->guid);
        $qb->add_order('domain');
        $qb->add_order('name');
        $data['parameters'] = $qb->execute();

        $this->_prepare_request_data();
        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        return true;
    }

    /**
     * Shows the loaded object in editor.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_edit($handler_id, &$data)
    {
        midcom_show_style('midgard_admin_asgard_header');
        midcom_show_style('midgard_admin_asgard_middle');
        midcom_show_style('midgard_admin_asgard_object_parameters');
        midcom_show_style('midgard_admin_asgard_footer');
    }
}
?>