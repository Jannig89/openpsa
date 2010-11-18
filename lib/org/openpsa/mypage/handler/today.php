<?php
/**
 * @package org.openpsa.mypage
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: today.php 26470 2010-06-28 16:13:16Z gudd $
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
    var $user = null;

    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        midcom::auth->require_valid_user();
    }

    function _calculate_day()
    {
        require_once 'Calendar/Day.php';

        // Get start and end times
        $this->_request_data['this_day'] = new Calendar_Day(date('Y', $this->_request_data['requested_time']), date('m', $this->_request_data['requested_time']), date('d', $this->_request_data['requested_time']));
        $this->_request_data['prev_day'] = $this->_request_data['this_day']->prevDay('object');
        $this->_request_data['day_start'] = $this->_request_data['prev_day']->getTimestamp() + 1;
        $this->_request_data['next_day'] = $this->_request_data['this_day']->nextDay('object');
        $this->_request_data['day_end'] = $this->_request_data['next_day']->getTimestamp() - 1;
    }

    function _populate_toolbar()
    {
        $prev_day = date('Y-m-d', $this->_request_data['prev_day']->getTimestamp());
        $next_day = date('Y-m-d', $this->_request_data['next_day']->getTimestamp());
        $this_day = date('Y-m-d', $this->_request_data['this_day']->getTimestamp());
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "day/{$prev_day}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('previous'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/up.png',
                MIDCOM_TOOLBAR_ENABLED => true,
            )
        );
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "day/{$next_day}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('next'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/down.png',
                MIDCOM_TOOLBAR_ENABLED => true,
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "weekreview/{$this_day}",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('week review'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
                MIDCOM_TOOLBAR_ENABLED => true,
            )
        );
    }

    /**
     * Helper function that sets the request data for hour reports
     *
     * @param &$array The array returned by collector
     */
    private function _add_hour_data(&$array)
    {
        static $customer_cache = array();
        if (!isset($customer_cache[$array['task']]))
        {
            $customer = 0;
            $customer_label = $this->_l10n->get('no customer');
            if ($array['task'] != 0)
            {
                $mc = new midgard_collector('org_openpsa_task', 'id', $array['task']);
                $mc->set_key_property('id');
                $mc->add_value_property('customer');
                $mc->execute();
                $customer_id = $mc->get_subkey($array['task'], 'customer');
                if ($customer_id)
                {
                    $customer = new org_openpsa_contacts_group_dba($customer_id);
                    if ($customer->guid != "")
                    {
                        $customer_label = $customer->official;
                        $customer = $customer_id;
                    }
               }
            }
            $customer_cache[$array['task']] = $customer;
            if (!isset($this->_request_data['customers'][$customer]))
            {
                $this->_request_data['customers'][$customer] = $customer_label;
            }
        }

        $customer = $customer_cache[$array['task']];

        $category = 'uninvoiceable';
        if ($array['invoiceable'])
        {
            $category = 'invoiceable';
        }

        if (!isset($this->_request_data['hours'][$category][$customer]))
        {
            $this->_request_data['hours'][$category][$customer] = $array['hours'];
        }
        else
        {
            $this->_request_data['hours'][$category][$customer] += $array['hours'];
        }
        $this->_request_data['hours']['total_' . $category] += $array['hours'];
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_today($handler_id, $args, &$data)
    {
        $this->user = midcom::auth->user->get_storage();

        if ($handler_id != 'today')
        {
            // TODO: Check format as YYYY-MM-DD via regexp
            $requested_time = @strtotime($args[0]);
            if ($requested_time)
            {
                $data['requested_time'] = $requested_time;
            }
            else
            {
                // We couldn't generate a date
                return false;
            }
        }

        org_openpsa_helpers::calculate_week($data);
        $this->_calculate_day();

        // List work hours this week
        $this->_list_work_hours();

        $this->_populate_toolbar();

        $data['title'] = strftime('%a %x', $data['requested_time']);
        midcom::set_pagetitle($data['title']);

        // Add the JS file for "now working on" calculator
        midcom::add_jsfile(MIDCOM_STATIC_URL . "/jQuery/jquery.epiclock.min.js");

        // Add the JS file for dynamic switching tasks without reloading the whole window
        midcom::add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.mypage/mypage.js");

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.mypage/mypage.css",
            )
        );

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/list.css",
            )
        );

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );
        //needed js/css-files for jqgrid
        org_openpsa_core_ui::enable_jqgrid();

        //set the start-constraints for journal-entries
        $time_span = 7 * 24 * 60 *60 ; //7 days
        $today = mktime(0, 0, 0, $this->_request_data['this_day']->month, $this->_request_data['this_day']->day, $this->_request_data['this_day']->year);
        $this->_request_data['journal_constraints'] = array();
        //just show entries of current_user
        $this->_request_data['journal_constraints'][] = array(
                        'property' => 'metadata.creator',
                        'operator' => '=',
                        'value' => midcom::auth->user->guid,
                        );
        $this->_request_data['journal_constraints'][] = array(
                        'property' => 'followUp',
                        'operator' => '<',
                        'value' => $today + $time_span,
                        );
        $this->_request_data['journal_constraints'][] = array(
                        'property' => 'closed',
                        'operator' => '=',
                        'value' => false,
                        );

        return true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_today($handler_id, &$data)
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $data['calendar_url'] = $siteconfig->get_node_relative_url('org.openpsa.calendar');
        $data['projects_url'] = $siteconfig->get_node_full_url('org.openpsa.projects');
        $data['projects_relative_url'] = $siteconfig->get_node_relative_url('org.openpsa.projects');
        $data['expenses_url'] = $siteconfig->get_node_full_url('org.openpsa.expenses');
        $data['wiki_url'] = $siteconfig->get_node_relative_url('net.nemein.wiki');

        $data_url = midcom::get_host_name() . midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
        $data['journal_url'] = $data_url . '/__mfa/org.openpsa.relatedto/journalentry/list/xml/';

        midcom_show_style('show-today');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_expenses($handler_id, $args, &$data)
    {
        $data['requested_time'] = time();

        $this->_calculate_day();
        org_openpsa_helpers::calculate_week($data);
        // List work hours this week
        $this->_list_work_hours();

        midcom::skip_page_style = true;

        return true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_expenses($handler_id, &$data)
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $data['expenses_url'] = $siteconfig->get_node_full_url('org.openpsa.expenses');
        midcom_show_style('workingon_expenses');
    }

    /**
     * Function to list invoiceable and uninvoicable hours
     */
    function _list_work_hours()
    {
        $hours_mc = org_openpsa_projects_hour_report_dba::new_collector('person', midcom_connection::get_user());
        $hours_mc->add_value_property('task');
        $hours_mc->add_value_property('invoiceable');
        $hours_mc->add_value_property('hours');
        $hours_mc->add_constraint('date', '>=', $this->_request_data['week_start']);
        $hours_mc->add_constraint('date', '<=', $this->_request_data['week_end']);
        $hours_mc->execute();

        $hours = $hours_mc->list_keys();

        $this->_request_data['customers'] = array();
        $this->_request_data['hours'] = array
        (
            'invoiceable' => array(),
            'uninvoiceable' => array(),
            'total_invoiceable' => 0,
            'total_uninvoiceable' => 0,
        );

        foreach ($hours as $guid => $values)
        {
            $this->_add_hour_data($hours_mc->get($guid));
        }

        return true;
    }
}
?>