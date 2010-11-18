<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: deleted.php 22990 2009-07-23 15:46:03Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Simple object deleted page
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_deleted extends midcom_baseclasses_components_handler
{
    /**
     * Object requested
     *
     * @access private
     * @var mixed Object
     */
    var $_object = null;

    /**
     * Constructor, call for the class parent constructor method.
     *
     * @access public
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
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    function _prepare_request_data()
    {
        $this->_request_data['object'] =& $this->_object;
        $this->_request_data['l10n'] =& $this->_l10n;
        $this->_request_data['view_title'] = $this->_l10n->get('object deleted');
        $this->_request_data['asgard_toolbar'] = new midcom_helper_toolbar();
    }

    /**
     * Handler for deleted objects
     *
     * @access private
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success
     */
    function _handler_deleted($handler_id, $args, &$data)
    {
        $data['guid'] = $args[0];
        /*
         * TODO: It would be nice to be able to load the object to show undelete/purge links, but for
         * this we'd have to loop through all schema types and qb until we find something ...
         */
        $this->_prepare_request_data();

        if (midcom::auth->admin)
        {
            $data['asgard_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => '__mfa/asgard/trash/',
                    MIDCOM_TOOLBAR_LABEL => midcom::i18n()->get_string('trash', 'midgard.admin.asgard'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash-full.png',
                )
            );
        }

        // Set the breadcrumb data
        $tmp = array();
        $tmp[] = array
        (
            MIDCOM_NAV_URL => '__mfa/asgard/',
            MIDCOM_NAV_NAME => midcom::i18n()->get_string('midgard.admin.asgard', 'midgard.admin.asgard'),
        );
        $tmp[] = array
        (
            MIDCOM_NAV_URL => "",
            MIDCOM_NAV_NAME => $this->_l10n->get('object deleted')
        );
        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);

        return true;
    }

    /**
     * Output the style element for deleted objects
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     * @access private
     */
    function _show_deleted($handler_id, &$data)
    {
        midgard_admin_asgard_plugin::asgard_header();
        midcom_show_style('midgard_admin_asgard_object_deleted');
        midgard_admin_asgard_plugin::asgard_footer();
    }

}
?>