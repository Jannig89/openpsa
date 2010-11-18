<?php
/**
 * @package org.openpsa.directmarketing
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: message.php 25716 2010-04-20 22:57:24Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Discussion forum index
 *
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_message_message extends midcom_baseclasses_components_handler
{
    /**
     * The message which has been created
     *
     * @var org_openpsa_directmarketing_campaign_message
     * @access private
     */
    var $_message = null;

    /**
     * Simple default constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Internal helper, loads the datamanager for the current message. Any error triggers a 500.
     *
     * @access private
     */
    function _load_datamanager()
    {
        $schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_message'));
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($schemadb);

        if (!$this->_datamanager)
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to create a DM2 instance for messages.");
            // This will exit.
        }
    }

    /**
     * Looks up an message to display.
     */
    function _handler_view ($handler_id, $args, &$data)
    {
        midcom::auth->require_valid_user();
        $this->_message = new org_openpsa_directmarketing_campaign_message_dba($args[0]);
        if (   !$this->_message
            || !$this->_message->guid)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "The message {$args[0]} was not found.");
            // This will exit.
        }

        $this->_campaign = new org_openpsa_directmarketing_campaign_dba($this->_message->campaign);
        if (   !$this->_campaign
            || $this->_campaign->node != $this->_topic->id)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "The campaign {$this->_message->campaign} was not found.");
            // This will exit.
        }

        $this->_component_data['active_leaf'] = "campaign_{$this->_campaign->id}";

        $this->_load_datamanager();
        $this->_datamanager->autoset_storage($this->_message);

        $tmp = array();
        $tmp[] = array
        (
            MIDCOM_NAV_URL => "message/{$this->_message->guid}/",
            MIDCOM_NAV_NAME => $this->_message->title,
        );
        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);

        $data['message'] =& $this->_message;
        $data['campaign'] =& $this->_campaign;
        $data['datamanager'] =& $this->_datamanager;

        // Populate the toolbar
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "message/edit/{$this->_message->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit message'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                MIDCOM_TOOLBAR_ENABLED => $this->_message->can_do('midgard:update')
            )
        );
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "message/delete/{$this->_message->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('delete message'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                MIDCOM_TOOLBAR_ACCESSKEY => 'd',
                MIDCOM_TOOLBAR_ENABLED => $this->_message->can_do('midgard:delete')
            )
        );
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "message/copy/{$this->_message->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('copy message'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/editcopy.png',
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                MIDCOM_TOOLBAR_ENABLED => $this->_message->can_do('midgard:update')
            )
        );

        if (   !empty(midcom::auth->user)
            && !empty(midcom::auth->user->guid))
        {
            $preview_url = "message/compose/{$this->_message->guid}/{midcom::auth->user->guid}/";
        }
        else
        {
            $preview_url = "message/compose/{$this->_message->guid}/";
        }
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => $preview_url,
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('preview message'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/view.png',
                MIDCOM_TOOLBAR_ACCESSKEY => 'p',
                MIDCOM_TOOLBAR_ENABLED => true,
                MIDCOM_TOOLBAR_OPTIONS => array('target' => '_BLANK'),
            )
        );
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "message/report/{$this->_request_data['message']->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("message report"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ACCESSKEY => 'r',
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/printer.png',
                MIDCOM_TOOLBAR_ENABLED => true,
            )
        );

        $this->_campaign->get_testers();
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "message/send_test/{$this->_request_data['message']->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("send message to testers"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_mail-send.png',
                MIDCOM_TOOLBAR_ENABLED => (count($this->_campaign->testers) > 0) ? true : false,
            )
        );

        $mc = org_openpsa_campaign_member::new_collector('campaign', $this->_campaign->id);
        $mc->set_key_property('campaign');
        $mc->execute();
        $keys = $mc->list_keys();

        // Show the message send if there are recipients
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "message/send/{$this->_request_data['message']->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("send message to whole campaign"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_mail-send.png',
                MIDCOM_TOOLBAR_ENABLED => (count($keys) > 0 && $this->_message->can_do('midgard:update')) ? true : false,
                MIDCOM_TOOLBAR_OPTIONS => array
                (
                    'onclick' => "return confirm('" . $this->_l10n->get('are you sure you wish to send this to the whole campaign') . "')",
                ),
            )
        );

        // Populate calendar events for the message
        $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

        midcom::bind_view_to_object($this->_message, $this->_datamanager->schema->name);
        midcom::set_26_request_metadata($this->_message->metadata->revised, $this->_message->guid);
        midcom::set_pagetitle($this->_message->title);

        return true;
    }

    /**
     * Shows the loaded message.
     */
    function _show_view ($handler_id, &$data)
    {
        $data['view_message'] = $this->_datamanager->get_content_html();
        midcom_show_style('show-message');
    }
}

?>