<?php
/**
 * @package org.openpsa.projects
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: resourcing.php 25716 2010-04-20 22:57:24Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Projects task resourcing handler
 *
 * @package org.openpsa.projects
 */
class org_openpsa_projects_handler_task_resourcing extends midcom_baseclasses_components_handler
{
    /**
     * The task to operate on
     *
     * @var org_openpsa_projects_task_dba
     * @access private
     */
    private $_task = null;

    /**
     * Simple default constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data($handler_id)
    {
        $this->_request_data['task'] =& $this->_task;

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "task/edit/{$this->_task->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_task->can_do('midgard:update'),
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "task/delete/{$this->_task->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('delete'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_task->can_do('midgard:delete'),
                MIDCOM_TOOLBAR_ACCESSKEY => 'd',
            )
        );
    }

    /**
     * Maps the content topic from the request data to local member variables.
     */
    function _on_initialize()
    {
        midcom::load_library('org.openpsa.calendarwidget');
        midcom::load_library('org.openpsa.contactwidget');

        midcom::add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.projects/projectbroker.js");
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     */
    private function _update_breadcrumb_line()
    {
        $tmp = $breadcrumb = org_openpsa_projects_viewer::update_breadcrumb_line($this->_request_data['task']);

        $tmp[] = array
        (
            MIDCOM_NAV_URL => "task/resourcing/{$this->_task->guid}/",
            MIDCOM_NAV_NAME => $this->_l10n->get('resourcing'),
        );

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     * Display possible available resources
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_resourcing($handler_id, $args, &$data)
    {
        $this->_task = new org_openpsa_projects_task_dba($args[0]);
        if (! $this->_task)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "The task {$args[0]} was not found.");
            // This will exit.
        }
        $this->_task->require_do('midgard:create');

        if (   array_key_exists('org_openpsa_projects_prospects', $_POST)
            && $_POST['save'])
        {
            foreach ($_POST['org_openpsa_projects_prospects'] as $prospect_guid => $slots)
            {
                $prospect = new org_openpsa_projects_task_resource_dba($prospect_guid);
                if (!$prospect)
                {
                    // Could not fetch  prospect object
                    continue;
                }
                $update_prospect = false;
                foreach ($slots as $data)
                {
                    if (   !array_key_exists('used', $data)
                        || empty($data['used']))
                    {
                        // Slot not selected, skip
                        continue;
                    }
                    $prospect->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_PROJECTRESOURCE;
                    $update_prospect = true;
                    // Create event from slot
                    $event = new org_openpsa_calendar_event_dba();
                    $event->start = $data['start'];
                    $event->end = $data['end'];
                    $event->search_relatedtos = false;
                    $event->title = sprintf($this->_l10n->get('work for task %s'), $this->_task->title);
                    if (!$event->create())
                    {
                        // TODO: error reporting
                        continue;
                    }
                    $participant = new org_openpsa_calendar_event_member_dba();
                    $participant->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_EVENTPARTICIPANT;
                    $participant->uid = $prospect->person;
                    $participant->eid = $event->id;
                    $participant->create();
                    // create relatedto
                    if (!org_openpsa_relatedto_plugin::create($event, 'org.openpsa.calendar', $this->_task, 'org.openpsa.projects'))
                    {
                        // TODO: delete event ???
                    }
                }
            }
            if ($update_prospect)
            {
                if (!$prospect->update())
                {
                    // TODO: error handling
                }
            }
            midcom::relocate("task/{$this->_task->guid}/");
            // This will exit.
        }
        else if (   array_key_exists('cancel', $_POST)
                && $_POST['cancel'])
        {
            midcom::relocate("task/{$this->_task->guid}/");
            // This will exit.
        }

        $this->_prepare_request_data($handler_id);
        midcom::set_pagetitle($this->_task->title);
        midcom::bind_view_to_object($this->_task);
        $this->_update_breadcrumb_line();

        return true;
    }


    /**
     * Shows the loaded task.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_resourcing($handler_id, &$data)
    {
        midcom_show_style('show-task-resourcing');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_list_prospects($handler_id, $args, &$data)
    {
        $this->_task = new org_openpsa_projects_task_dba($args[0]);
        if (! $this->_task)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "The task {$args[0]} was not found.");
            // This will exit.
        }
        $this->_task->require_do('midgard:create');

        $qb = org_openpsa_projects_task_resource_dba::new_query_builder();
        $qb->add_constraint('task', '=', $this->_task->id);
        $qb->begin_group('OR');
            $qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_PROJECTPROSPECT);
            $qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_PROJECTRESOURCE);
        $qb->end_group('OR');
        $qb->add_order('orgOpenpsaObtype');
        $data['prospects'] = $qb->execute();

        midcom::skip_page_style = true;

        midcom::cache()->content->content_type("text/xml; charset=UTF-8");
        midcom::header("Content-type: text/xml; charset=UTF-8");

        return true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_list_prospects($handler_id, &$data)
    {
        midcom_show_style('show-prospects-xml');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_prospect_slots($handler_id, $args, &$data)
    {
        $data['prospect'] = new org_openpsa_projects_task_resource_dba($args[0]);
        if (!$data['prospect'])
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "Prospect {$args[0]} was not found.");
            // This will exit.
        }

        $data['person'] = new org_openpsa_contacts_person_dba($data['prospect']->person);
        if (! $data['person'])
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "Person #{$data['prospect']->person} was not found.");
            // This will exit.
        }

        $this->_task = new org_openpsa_projects_task_dba($data['prospect']->task);
        if (! $this->_task)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "Task #{$data['prospect']->task} was not found.");
            // This will exit.
        }
        $this->_task->require_do('midgard:create');

        $projectbroker = new org_openpsa_projects_projectbroker();
        $data['slots'] = $projectbroker->resolve_person_timeslots($data['person'], $this->_task);

        midcom::skip_page_style = true;

        return true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_prospect_slots($handler_id, &$data)
    {
        midcom_show_style('show-prospect');
    }
}

?>