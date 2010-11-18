<?php
/**
 * @package org.openpsa.projects
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: frontpage.php 25728 2010-04-21 23:58:29Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Projects index handler
 *
 * @package org.openpsa.projects
 */
class org_openpsa_projects_handler_frontpage extends midcom_baseclasses_components_handler
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_frontpage($handler_id, $args, &$data)
    {
        midcom::auth->require_valid_user();

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'project/new/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("create project"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-dir.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_user_do('midgard:create', null, 'org_openpsa_projects_project'),
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'task/new/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("create task"),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new_task.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::auth->can_user_do('midgard:create', null, 'org_openpsa_projects_task_dba'),
            )
        );

        // List current projects, sort by customer
        $data['customers'] = array();
        $project_qb = org_openpsa_projects_project::new_query_builder();
        $project_qb->add_constraint('status', '<', ORG_OPENPSA_TASKSTATUS_CLOSED);
        $project_qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_PROJECT);
        $project_qb->add_order('customer.official');
        $project_qb->add_order('end');
        $projects = $project_qb->execute();
        foreach ($projects as $project)
        {
            if (!isset($data['customers'][$project->customer]))
            {
                $data['customers'][$project->customer] = array();
            }

            $data['customers'][$project->customer][] = $project;
        }

        // Projects without customer have to be queried separately, see #97
        $nocustomer_qb = org_openpsa_projects_project::new_query_builder();
        $nocustomer_qb->add_constraint('status', '<', ORG_OPENPSA_TASKSTATUS_CLOSED);
        $nocustomer_qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_PROJECT);
        $nocustomer_qb->add_constraint('customer', '=', 0);
        $nocustomer_qb->add_order('end');
        if ($nocustomer_qb->count() > 0)
        {
            $data['customers'][0] = $nocustomer_qb->execute();
        }

        $closed_qb = org_openpsa_projects_project::new_query_builder();
        $closed_qb->add_constraint('status', '=', ORG_OPENPSA_TASKSTATUS_CLOSED);
        $closed_qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_PROJECT);
        $data['closed_count'] = $closed_qb->count();

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/list.css",
            )
        );

        midcom::add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.projects/frontpage.js');

        midcom::set_pagetitle($this->_l10n->get('current projects'));

        return true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_frontpage($handler_id, &$data)
    {
        midcom_show_style("show-frontpage");
    }
}
?>