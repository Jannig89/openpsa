<?php
/**
 * @package net.nehmer.account
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Component configuration screen.
 *
 * @package net.nehmer.account
 */
class net_nehmer_account_handler_configuration extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_edit
{
    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['node'] =& $this->_topic;
    }

    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_config'));
    }

    /**
     * Displays a config edit view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_configuration($handler_id, array $args, array &$data)
    {
        $this->_topic->require_do('midgard:update');

        $data['controller'] = $this->get_controller('simple', $this->_topic);

        switch ($data['controller']->process_form())
        {
            case 'save':
                $_MIDCOM->uimessages->add($this->_l10n->get('net.nehmer.account'), $this->_l10n->get('configuration saved'));
                $_MIDCOM->relocate('');
                break;

            case 'cancel':
                $_MIDCOM->uimessages->add($this->_l10n->get('net.nehmer.account'), $this->_l10n->get('cancelled'));
                $_MIDCOM->relocate('');
                // This will exit.
        }

        $this->_prepare_request_data();
        $_MIDCOM->set_26_request_metadata($this->_topic->metadata->revised, $this->_topic->guid);
        $_MIDCOM->set_pagetitle("{$this->_topic->extra}: " . $this->_l10n_midcom->get('component configuration'));
        $this->add_breadcrumb("config/", $this->_l10n_midcom->get('component configuration', 'midcom'));
    }

    /**
     * Shows the loaded photo.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_configuration($handler_id, array &$data)
    {
        midcom_show_style('admin-config');
    }
}
?>