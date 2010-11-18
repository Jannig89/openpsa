<?php
/**
 * @package org.openpsa.sales
 * @author Nemein Oy, http://www.nemein.com/
 * @version $Id: interfaces.php 26662 2010-09-24 19:35:07Z flack $
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA Sales management component
 *
 * @package org.openpsa.sales
 */
class org_openpsa_sales_interface extends midcom_baseclasses_components_interface
{

    function __construct()
    {
        parent::__construct();

        $this->_component = 'org.openpsa.sales';
        $this->_autoload_files = array();
        $this->_autoload_libraries = array
        (
            'org.openpsa.core',
        );
    }

    function _on_initialize()
    {
        // Load needed data classes
        midcom::componentloader->load_graceful('org.openpsa.products');

        //TODO: Check that the loads actually succeeded

        //org.openpsa.sales object types
        define('ORG_OPENPSA_OBTYPE_SALESPROJECT', 10000);
        define('ORG_OPENPSA_OBTYPE_SALESPROJECT_MEMBER', 10500);
        //org.openpsa.sales salesproject statuses
        define('ORG_OPENPSA_SALESPROJECTSTATUS_LOST', 11000);
        define('ORG_OPENPSA_SALESPROJECTSTATUS_CANCELED', 11001);
        define('ORG_OPENPSA_SALESPROJECTSTATUS_ACTIVE', 11050);
        define('ORG_OPENPSA_SALESPROJECTSTATUS_WON', 11100);
        define('ORG_OPENPSA_SALESPROJECTSTATUS_DELIVERED', 11200);
        define('ORG_OPENPSA_SALESPROJECTSTATUS_INVOICED', 11300);
        //org.openpsa.sales salesproject deliverable statuses
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_NEW', 100);
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_PROPOSED', 200);
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_DECLINED', 300);
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_ORDERED', 400);
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_STARTED', 450);
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_DELIVERED', 500);
        define('ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_INVOICED', 600);

        return true;
    }


    function _on_resolve_permalink($topic, $config, $guid)
    {
        $salesproject = new org_openpsa_sales_salesproject_dba($guid);
        if ($salesproject->guid == $guid)
        {
            return "salesproject/{$salesproject->guid}/";
        }

        $deliverable = new org_openpsa_sales_salesproject_deliverable_dba($guid);
        if ($deliverable->guid == $guid)
        {
            return "deliverable/{$deliverable->guid}/";
        }
        return null;

    }

    /**
     * Used by org_openpsa_relatedto_suspect::find_links_object to find "related to" information
     *
     * Currently handles persons
     */
    function org_openpsa_relatedto_find_suspects($object, $defaults, &$links_array)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        if (   !is_array($links_array)
            || !is_object($object))
        {
            debug_add('$links_array is not array or $object is not object, make sure you call this correctly', MIDCOM_LOG_ERROR);
            debug_pop();
            return;
        }

        switch(true)
        {
            case midcom::dbfactory()->is_a($object, 'midcom_db_person'):
                //Fall-trough intentional
            case midcom::dbfactory()->is_a($object, 'org_openpsa_contacts_person_dba'):
                //List all projects and tasks given person is involved with
                $this->_org_openpsa_relatedto_find_suspects_person($object, $defaults, $links_array);
                break;
            case midcom::dbfactory()->is_a($object, 'midcom_db_event'):
            case midcom::dbfactory()->is_a($object, 'org_openpsa_calendar_event_dba'):
                $this->_org_openpsa_relatedto_find_suspects_event($object, $defaults, $links_array);
                break;
                //TODO: groups ? other objects ?
        }
        debug_pop();
        return;
    }

    /**
     * Used by org_openpsa_relatedto_find_suspects to in case the given object is a person
     *
     * Current rule: all participants of event must be either manager,contact or resource in task
     * that overlaps in time with the event.
     */
    function _org_openpsa_relatedto_find_suspects_event(&$object, &$defaults, &$links_array)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add('called');
        if (   !is_array($object->participants)
            || count($object->participants) < 2)
        {
            //We have invalid list or less than two participants, abort
            debug_pop();
            return;
        }
        $qb = new midgard_query_builder('org_openpsa_salesproject_member');

        // Target sales project starts or ends inside given events window or starts before and ends after
        $qb->begin_group('OR');
            $qb->begin_group('AND');
                $qb->add_constraint('salesproject.start', '>=', $object->start);
                $qb->add_constraint('salesproject.start', '<=', $object->end);
            $qb->end_group();
            $qb->begin_group('AND');
                $qb->add_constraint('salesproject.end', '<=', $object->end);
                $qb->add_constraint('salesproject.end', '>=', $object->start);
            $qb->end_group();
            $qb->begin_group('AND');
                $qb->add_constraint('salesproject.start', '<=', $object->start);
                $qb->begin_group('OR');
                    $qb->add_constraint('salesproject.end', '>=', $object->end);
                    $qb->add_constraint('salesproject.end', '=', 0);
                $qb->end_group();
            $qb->end_group();
        $qb->end_group();

        //Target sales project is active
        $qb->add_constraint('salesproject.status', '=', ORG_OPENPSA_SALESPROJECTSTATUS_ACTIVE);

        //Each event participant is either manager or member (resource/contact) in task
        foreach ($object->participants as $pid => $bool)
        {
            $qb->begin_group('OR');
                $qb->add_constraint('salesproject.owner', '=', $pid);
                $qb->add_constraint('person', '=', $pid);
            $qb->end_group();
        }
        $qbret = @$qb->execute();

        if (!is_array($qbret))
        {
            debug_add('QB returned with error, aborting, errstr: ' . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            debug_pop();
            return;
        }
        $seen_tasks = array();
        foreach ($qbret as $resource)
        {
            debug_add("processing resource #{$resource->id}");
            if (isset($seen_tasks[$resource->salesproject]))
            {
                //Only process one task once (someone might be both owner and contact for example)
                continue;
            }
            $seen_tasks[$resource->salesproject] = true;
            $to_array = array('other_obj' => false, 'link' => false);
            $task = new org_openpsa_sales_salesproject_dba($resource->salesproject);
            $link = new org_openpsa_relatedto_dba();
            org_openpsa_relatedto_suspect::defaults_helper($link, $defaults, $this->_component, $task);
            $to_array['other_obj'] = $task;
            $to_array['link'] = $link;

            $links_array[] = $to_array;
        }
        debug_add('done');
        debug_pop();
        return;
    }

    /**
     * Used by org_openpsa_relatedto_find_suspects to in case the given object is a person
     */
    function _org_openpsa_relatedto_find_suspects_person(&$object, &$defaults, &$links_array)
    {
        $qb = new midgard_query_builder('org_openpsa_salesproject_member');
        $qb->add_constraint('person', '=', $object->id);
        $qb->add_constraint('salesproject.status', '=', ORG_OPENPSA_SALESPROJECTSTATUS_ACTIVE);
        $qbret = @$qb->execute();
        $seen_sp = array();
        if (is_array($qbret))
        {
            foreach ($qbret as $member)
            {
                debug_add("processing resource #{$resource->id}");
                if (isset($seen_sp[$member->salesproject]))
                {
                    //Only process one salesproject once (someone might be both resource and contact for example)
                    continue;
                }
                $seen_sp[$resource->salesproject] = true;
                $to_array = array('other_obj' => false, 'link' => false);
                $sp = new org_openpsa_sales_salesproject_dba($member->salesproject);
                $link = new org_openpsa_relatedto_dba();
                org_openpsa_relatedto_suspect::defaults_helper($link, $defaults, $this->_component, $sp);
                $to_array['other_obj'] = $sp;
                $to_array['link'] = $link;

                $links_array[] = $to_array;
            }
        }
        $qb2 = org_openpsa_sales_salesproject_dba::new_query_builder();
        $qb2->add_constraint('owner', '=', $object->id);
        $qb2->add_constraint('status', '=', ORG_OPENPSA_SALESPROJECTSTATUS_ACTIVE);
        $qb2ret = @$qb2->execute();
        if (is_array($qb2ret))
        {
            foreach ($qb2ret as $sp)
            {
                debug_add("processing salesproject #{$sp->id}");
                if (isset($seen_sp[$sp->id]))
                {
                    //Only process one task once (someone might be both resource and contact for example)
                    continue;
                }
                $seen_sp[$sp->id] = true;
                $to_array = array('other_obj' => false, 'link' => false);
                $link = new org_openpsa_relatedto_dba();
                org_openpsa_relatedto_suspect::defaults_helper($link, $defaults, $this->_component, $sp);
                $to_array['other_obj'] = $sp;
                $to_array['link'] = $link;

                $links_array[] = $to_array;
            }
        }
    }

    /**
     * AT handler for handling subscription cycles.
     *
     * @param array $args handler arguments
     * @param object &$handler reference to the cron_handler object calling this method.
     * @return boolean indicating success/failure
     */
    function new_subscription_cycle($args, &$handler)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        if (   !isset($args['deliverable'])
            || !isset($args['cycle']))
        {
            $msg = 'deliverable GUID or cycle number not set, aborting';
            $handler->print_error($msg);
            debug_add($msg, MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        $deliverable = new org_openpsa_sales_salesproject_deliverable_dba($args['deliverable']);
        if (   !$deliverable
            || $deliverable->guid == "")
        {
            $msg = "Deliverable {$args['deliverable']} not found, error " . midcom_connection::get_error_string();
            $handler->print_error($msg);
            debug_add($msg, MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }
        $scheduler = new org_openpsa_invoices_scheduler($deliverable);

        return $scheduler->run_cycle($args['cycle']);
    }

    /**
     * function to send a notification to owner of the deliverable - guid of deliverable is passed
     */
    function new_notification_message($args , &$handler)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        if (!isset($args['deliverable']))
        {
            $msg = 'deliverable GUID not set, aborting';
            debug_add($msg, MIDCOM_LOG_ERROR);
            $handler->print_error($msg);
            debug_pop();
            return false;
        }
        $deliverable = new org_openpsa_sales_salesproject_deliverable_dba($args['deliverable']);
        if (empty($deliverable->guid))
        {
            $msg = 'no deliverable with passed GUID:' . $args['deliverable'] . ' , aborting';
            debug_add($msg, MIDCOM_LOG_ERROR);
            $handler->print_error($msg);
            debug_pop();
            return false;
        }

        $notify_msg = $deliverable->title;
        //get the owner of the sales-project the deliverable belongs to
        $project = new org_openpsa_sales_salesproject_dba($deliverable->salesproject);
        if(empty($project->guid))
        {
            $msg = 'no project(id:' . $deliverable->salesproject . ') found for deliverable with passed GUID:' . $args['deliverable'] . ' , aborting';
            debug_add($msg, MIDCOM_LOG_ERROR);
            $handler->print_error($msg);
            debug_pop();
            return false;
        }
        midcom::load_library('org.openpsa.notifications');

        return org_openpsa_notifications::notify('org.openpsa.sales:new_notification_message', $project->owner, $notify_msg);
    }
}
?>