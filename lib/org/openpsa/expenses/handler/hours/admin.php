<?php
/**
 * @package org.openpsa.expenses
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: admin.php 26659 2010-09-23 18:15:23Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Hour report create handler
 *
 * @package org.openpsa.expenses
 */
class org_openpsa_expenses_handler_hours_admin extends midcom_baseclasses_components_handler
{
    /**
     * The hour report
     *
     * @var org_openpsa_projects_hour_report_dba
     * @access private
     */
    var $_hour_report = null;

    /**
     * The Controller of the report used for editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     * @access private
     */
    var $_controller = null;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var Array
     * @access private
     */
    var $_schemadb = null;

    /**
     * The schema to use for the new article.
     *
     * @var string
     * @access private
     */
    var $_schema = 'default';

    /**
     * The defaults to use for the new report.
     *
     * @var Array
     * @access private
     */
    var $_defaults = Array();

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
    function _prepare_request_data()
    {
        $this->_request_data['controller'] =& $this->_controller;
        $this->_request_data['schema'] =& $this->_schema;
        $this->_request_data['schemadb'] =& $this->_schemadb;
    }

    /**
     * Loads and prepares the schema database.
     *
     * The operations are done on all available schemas within the DB.
     */
    function _load_schemadb()
    {
        $this->_schemadb =& midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_hours'));
    }

    /**
     * Internal helper, fires up the creation mode controller. Any error triggers a 500.
     *
     * @access private
     */
    function _load_create_controller()
    {
        $this->_defaults['task'] = $this->_request_data['task'];
        $this->_defaults['person'] = midcom_connection::get_user();
        $this->_defaults['date'] = time();

        $this->_controller = midcom_helper_datamanager2_controller::create('create');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->schemaname = $this->_schema;
        $this->_controller->defaults = $this->_defaults;
        $this->_controller->callback_object =& $this;
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 create controller.");
            // This will exit.
        }
    }

    /**
     * DM2 creation callback
     */
    function & dm2_create_callback (&$controller)
    {
        $this->_hour_report = new org_openpsa_projects_hour_report_dba();
        if ($this->_request_data['task'])
        {
            $this->_hour_report->task = $this->_request_data['task'];
        }
        else
        {
            $this->_hour_report->task = (int) $_POST['task'];
        }
        if (! $this->_hour_report->create())
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('We operated on this object:', $this->_hour_report);
            debug_pop();
            midcom::generate_error(MIDCOM_ERRCRIT,
                "Failed to create a new hour_report under hour_report group #{$this->_request_data['task']}, cannot continue. Error: " . midcom_connection::get_error_string());
            // This will exit.
        }

        return $this->_hour_report;
    }

    /**
     * Displays the report creation view.
     *
     * If create privileges apply, we relocate to the edit view
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_create($handler_id, $args, &$data)
    {
        //load component here to be able to access its constants
        midcom::componentloader->load('org.openpsa.projects');

        $this->_load_schemadb();
        $data['selected_schema'] = $args[0];
        if (!array_key_exists($data['selected_schema'], $this->_schemadb))
        {
            return false;
        }
        $this->_schema =& $data['selected_schema'];

        if (count($args) > 1)
        {
            $parent = new org_openpsa_projects_task_dba($args[1]);

            $data['task'] = $parent->id;

            if (!$parent)
            {
                return false;
            }
            $parent->require_do('midgard:create');

            $this->_add_toolbar_items($parent);
        }
        else
        {
            midcom::auth->require_valid_user();
            $data['task'] = 0;
        }

        $this->_load_create_controller();

        switch ($this->_controller->process_form())
        {
            case 'save':
                $this->_hour_report->modify_hours_by_time_slot();
                if (count($args) > 1)
                {
                    midcom::relocate("hours/task/" . $parent->guid . "/");
                }
                else
                {
                    midcom::relocate("hours/edit/{$this->_hour_report->guid}/");
                }
                // This will exit.

            case 'cancel':
                if (count($args) > 1)
                {
                    midcom::relocate("hours/task/" . $parent->guid . "/");
                }
                else
                {
                    midcom::relocate('');
                }
                // This will exit.
        }

        $this->_prepare_request_data();

        if ($this->_hour_report)
        {
            midcom::set_26_request_metadata($this->_hour_report->metadata->revised, $this->_hour_report->guid);
        }

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );

        $data['view_title'] = sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get($this->_schemadb[$this->_schema]->description));
        midcom::set_pagetitle($data['view_title']);
        $this->_update_breadcrumb_line($data['view_title']);

        return true;
    }

    /**
     * Helper to populate the toolbar
     *
     * @param mixed &$parent The parent object or false
     */
    private function _add_toolbar_items(&$parent)
    {
        if (empty($parent->guid))
        {
            return;
        }

        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $projects_url = $siteconfig->get_node_full_url('org.openpsa.projects');

        if ($projects_url)
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $projects_url . "task/{$parent->guid}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n->get('show task %s'), $parent->title),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/jump-to.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'g',
                )
            );
        }
    }

    /**
     * Shows the create form.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_create($handler_id, &$data)
    {
        midcom_show_style('hours_create');
    }

    /**
     * Looks up an hour_report to edit.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_edit($handler_id, $args, &$data)
    {
        $this->_hour_report = new org_openpsa_projects_hour_report_dba($args[0]);
        if (   !$this->_hour_report
            || !$this->_hour_report->guid)
        {
            org_openpsa_core_ui::object_inaccessible($args[0]);
        }

        $this->_load_schemadb();
        $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->set_storage($this->_hour_report);
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 controller instance for hour_report {$this->_hour_report->id}.");
            // This will exit.
        }

        switch ($this->_controller->process_form())
        {
            case 'save':
                $this->_hour_report->modify_hours_by_time_slot();
                // Reindex the article
                //$indexer = midcom::get_service('indexer');
                //net_nemein_wiki_viewer::index($this->_request_data['controller']->datamanager, $indexer, $this->_topic);
                // *** FALL-THROUGH ***
            case 'cancel':
                $task = new org_openpsa_projects_task_dba($this->_hour_report->task);
                midcom::relocate("hours/task/" . $task->guid . "/");
                // This will exit.
        }


        $this->_prepare_request_data();

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "hours/delete/{$this->_hour_report->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('delete'),
                MIDCOM_TOOLBAR_HELPTEXT => null,
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                MIDCOM_TOOLBAR_ACCESSKEY => 'd',

            )
        );

        $parent = $this->_hour_report->get_parent();
        $this->_add_toolbar_items($parent);

        $this->_view_toolbar->bind_to($this->_hour_report);

        midcom::set_26_request_metadata($this->_hour_report->metadata->revised, $this->_hour_report->guid);

        midcom::set_pagetitle($this->_l10n->get($handler_id));

        $this->_update_breadcrumb_line($handler_id);

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . "/org.openpsa.core/ui-elements.css",
            )
        );
        return true;
    }


    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     * @param string $handler_id The handler ID
     */
    private function _update_breadcrumb_line($handler_id)
    {
        $task = false;

        if (isset($this->_hour_report->task))
        {
            $task = new org_openpsa_projects_task_dba($this->_hour_report->task);
        }
        if (isset($this->_request_data['task']))
        {
            $task = new org_openpsa_projects_task_dba($this->_request_data['task']);
        }

        $tmp = Array();

        if ($task)
        {

            $tmp[] = array
            (
               MIDCOM_NAV_URL => "hours/task/" . $task->guid,
               MIDCOM_NAV_NAME => $task->get_label(),
            );

            $tmp[] = array
            (
               MIDCOM_NAV_URL => "",
               MIDCOM_NAV_NAME => $this->_l10n->get($handler_id),
            );
        }
        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }


    /**
     * Shows the hour_report edit form.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_edit($handler_id, &$data)
    {
        midcom_show_style('hours_edit');
    }

    /**
     * The delete handler.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_delete($handler_id, $args, &$data)
    {
        $this->_hour_report = new org_openpsa_projects_hour_report_dba($args[0]);
        if (!$this->_hour_report)
        {
            return false;
        }

        $this->_hour_report->require_do('midgard:delete');

        if (array_key_exists('org_openpsa_expenses_deleteok', $_REQUEST))
        {
            // Deletion confirmed.
            if (! $this->_hour_report->delete())
            {
                midcom::generate_error(MIDCOM_ERRCRIT, "Failed to delete hour report {$args[0]}, last Midgard error was: " . midcom_connection::get_error_string());
                // This will exit.
            }

            // Delete ok, relocating to welcome.
            midcom::relocate('');
            // This will exit.
        }

        if (array_key_exists('org_openpsa_expenses_deletecancel', $_REQUEST))
        {
            // Redirect to view page.
            midcom::relocate();
            // This will exit()
        }

        $this->_load_schemadb();
        $dm = new midcom_helper_datamanager2_datamanager($this->_schemadb);
        $dm->autoset_storage($this->_hour_report);
        $data['datamanager'] =& $dm;

        $this->_update_breadcrumb_line($handler_id);

        $this->_view_toolbar->bind_to($this->_hour_report);

        midcom::set_26_request_metadata($this->_hour_report->metadata->revised, $this->_hour_report->guid);

        return true;
    }

    /**
     * Shows the delete hour_report form
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_delete($handler_id, &$data)
    {
        midcom_show_style('hours_delete');
    }

    /**
     * executes passed action for passed reports & relocates to passed url
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    function _handler_batch($handler_id, $args, &$data)
    {
        //get url to relocate
        $relocate = "/";
        if(isset($_POST['relocate_url']))
        {
            $relocate = $_POST['relocate_url'];
        }
        else
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('no relocate url was passed ' , $_POST);
            debug_pop();
        }
        //check if reports are passed
        if(isset($_POST['report']))
        {
            //iterate through reports
            foreach($_POST['report'] as $report_id => $void)
            {
                $hour_report = new org_openpsa_projects_hour_report_dba($report_id);
                switch($_POST['action'])
                {
                    case 'mark_invoiceable':
                        $hour_report->invoiceable = true;
                        break;
                    case 'mark_uninvoiceable':
                        $hour_report->invoiceable = false;
                        break;
                    case 'change_invoice':
                        if(is_array($_POST['org_openpsa_expenses_invoice_chooser_widget_selections']))
                        {
                            foreach($_POST['org_openpsa_expenses_invoice_chooser_widget_selections'] as $id)
                            {
                                if($id != 0)
                                {
                                    $hour_report->invoice = $id;
                                    break;
                                }
                            }
                        }
                        break;
                    case 'change_task':
                        if(is_array($_POST['org_openpsa_expenses_task_chooser_widget_selections']))
                        {
                            foreach($_POST['org_openpsa_expenses_task_chooser_widget_selections'] as $id_key => $id)
                            {
                                if($id != 0)
                                {
                                    $hour_report->task = $id;
                                    break;
                                }
                            }
                        }
                        break;
                    default:
                        debug_push_class(__CLASS__, __FUNCTION__);
                        debug_print_r('passed Action is unknown ' , $_POST['action']);
                        debug_pop();
                        return false;
                }
                $hour_report->update();
            }
        }
        else
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('No Reports passed to action handler' , $_POST);
            debug_pop();
        }

        midcom::relocate($relocate);
    }

}
?>