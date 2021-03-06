<?php
/**
 * @package org.openpsa.mypage
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * My page today handler
 *
 * @package org.openpsa.mypage
 */
class org_openpsa_mypage_handler_today extends midcom_baseclasses_components_handler
{
    private function _populate_toolbar()
    {
        $buttons = array(
            array(
                MIDCOM_TOOLBAR_URL => 'weekreview/' . $this->_request_data['this_day'] . '/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('week review'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
            ),
            array(
                MIDCOM_TOOLBAR_URL => 'day/' . $this->_request_data['prev_day'] . '/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('previous'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/back.png',
            ),
            array(
                MIDCOM_TOOLBAR_URL => 'day/' . $this->_request_data['next_day'] . '/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('next'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/next.png',
            )
        );
        $this->_view_toolbar->add_items($buttons);
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_today($handler_id, array $args, array &$data)
    {
        if ($handler_id == 'today') {
            $data['requested_time'] = new DateTime;
        } else {
            // TODO: Check format as YYYY-MM-DD via regexp
            $data['requested_time'] = new DateTime($args[0]);
        }

        $this->_master->calculate_day($data['requested_time']);

        $this->_populate_toolbar();

        $data['title'] = $this->_l10n->get_formatter()->date($data['requested_time']);
        midcom::get()->head->set_pagetitle($data['title']);

        // Add the JS file for workingon widget
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.mypage/jquery.epiclock.min.js");
        midcom_helper_datamanager2_widget_autocomplete::add_head_elements();
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.mypage/mypage.js");

        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.mypage/mypage.css");
        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.core/list.css");

        //needed js/css-files for journal entries
        org_openpsa_widgets_grid::add_head_elements();
        midcom\workflow\datamanager2::add_head_elements();
        org_openpsa_widgets_calendar::add_head_elements();
        org_openpsa_widgets_ui::enable_ui_tab();
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_today($handler_id, array &$data)
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $data['calendar_url'] = $siteconfig->get_node_relative_url('org.openpsa.calendar');
        $data['projects_relative_url'] = $siteconfig->get_node_relative_url('org.openpsa.projects');
        $data['expenses_url'] = $siteconfig->get_node_full_url('org.openpsa.expenses');
        $data['wiki_url'] = $siteconfig->get_node_relative_url('net.nemein.wiki');
        $data['wiki_guid'] = $siteconfig->get_node_guid('net.nemein.wiki');
        $data['journal_url'] = '__mfa/org.openpsa.relatedto/journalentry/list/' . $data['day_start'] . '/';

        midcom_show_style('show-today');
    }
}
