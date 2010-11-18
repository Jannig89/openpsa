<?php
/**
 * @package midcom.admin.settings
 */

/**
 * @package midcom.admin.settings
 */
class midcom_admin_settings_editor extends midcom_baseclasses_components_handler
{
    /**
     * The config storage to use
     *
     * @var midcom_db_topic
     * @access private
     */
    var $_codeinit = null;
    var $_config_storage = null;

    /**
     * The Datamanager of the article to display (for delete mode)
     *
     * @var midcom_helper_datamanager2_datamanager
     * @access private
     */
    var $_datamanager = null;

    /**
     * The controller of the article used for editing
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
     * Defaults for the schema database
     *
     * @var array
     * @access private
     */
    var $_defaults = array();

    var $hostconfig = null;

    /**
     * Simple default constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    function _on_initialize()
    {
        $this->_config_storage = new midcom_db_page((int) $_MIDGARD['page']);

        require_once MIDCOM_ROOT . '/midcom/helper/hostconfig.php';
        require_once MIDCOM_ROOT . '/midcom/admin/folder/folder_management.php';
        midcom::load_library('midgard.admin.asgard');
        midcom::load_library('midcom.admin.folder');

        $this->_l10n = midcom::i18n()->get_l10n('midcom.admin.settings');
        $this->_debug_prefix = "midcom_admin_settings::";

        $this->_request_data['l10n'] = $this->_l10n;
        midcom::cache()->content->no_cache();

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . '/midcom.admin.settings/style.css',
            )
        );


        // Initialize Asgard plugin
        midgard_admin_asgard_plugin::prepare_plugin($this->_l10n->get('midcom.admin.settings'), $this->_request_data);

    }


    function get_plugin_handlers()
    {
        midcom::auth()->require_user_do('midcom.admin.settings:access', null, 'midcom_admin_settings_editor');

        return array
        (
            'index' => array
            (
                'handler' => array('midcom_admin_settings_editor', 'edit'),
            ),
            'edit' => array
            (
                'handler' => array('midcom_admin_settings_editor', 'edit'),
                'variable_args' => 1,
            ),
        );
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    function _prepare_request_data(&$data)
    {
        $this->_request_data['datamanager'] =& $this->_datamanager;
        $this->_request_data['controller'] =& $this->_controller;
        midgard_admin_asgard_plugin::get_common_toolbar($data);
    }

    /**
     * Loads and prepares the schema database.
     *
     * Special treatment is done for the name field, which is set readonly for non-admins
     * if the simple_name_handling config option is set. (using an auto-generated urlname based
     * on the title, if it is missing.)
     *
     * The operations are done on all available schemas within the DB.
     */
    function _load_schemadb()
    {
        foreach ($GLOBALS['midcom_config_local'] as $key => $value)
        {
           $this->_defaults[$key] = $value;
        }

        $this->_schemadb = midcom_helper_datamanager2_schema::load_database('file:/midcom/admin/settings/config/schemadb_config.inc');
    }

    /**
     * Internal helper, loads the controller for the current article. Any error triggers a 500.
     *
     * @access private
     */
    function _load_controller()
    {
        $this->_load_schemadb();
        $this->_controller = & midcom_helper_datamanager2_controller::create('nullstorage');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->defaults = $this->hostconfig->config;
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 controller instance.");
            // This will exit.
        }
    }

    /**
     * Displays a config edit view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_edit($handler_id, $args, &$data)
    {
        midcom::auth()->require_admin_user();
        $data['hostname'] = $_SERVER['SERVER_NAME'] . midcom_connection::get_url('prefix');

        if (   isset($args[0])
            && $args[0])
        {
            $host = new midcom_db_host($args[0]);
            if ($host->root)
            {
                $data['hostname'] = $host->name . $host->prefix;
                $this->_config_storage = new midcom_db_page($host->root);
            }
            else
            {
                $this->_prepare_request_data($data);
                $this->_config_storage = null;
                $data['hostname'] = $args[0];
                return true;
            }
        }


        $qb = midcom_db_pageelement::new_query_builder();
        $qb->add_constraint('page', '=', $this->_config_storage->id);
        $qb->add_constraint('name', '=', 'code-init');
        $codeinits = $qb->execute();

        if (count($codeinits) == 0)
        {
            $data['code-init-warning'] = "No code init. MidCOM config will be created but be sure you really want it.";
            $this->_codeinit = new midcom_db_pageelement();
            $this->_codeinit->page = $this->_config_storage->id;
            $this->_codeinit->name = 'code-init';
        }
        else
        {
            $this->_codeinit = $codeinits[0];
        }

        $this->hostconfig = new midcom_helper_hostconfig($this->_config_storage);
        $this->_load_controller();

        switch ($this->_controller->process_form())
        {
            case 'save':
                $this->_codeinit->value = $this->_get_code_init();

                if (   $this->_codeinit->value == ''
                    || !$this->_codeinit->value)
                {
                    midcom::uimessages()->add
                    (
                        midcom::i18n()->get_string('host configuration', 'midcom.admin.settings'),
                        midcom::i18n()->get_string('failed to create settings', 'midcom.admin.settings'),
                        'error'
                    );
                    break;
                }

                if ($this->_codeinit->id)
                {
                    $rst = $this->_codeinit->update();
                }
                else
                {
                    $rst = $this->_codeinit->create();
                }

                if ($rst)
                {
                    mgd_cache_invalidate();
                    midcom::uimessages()->add
                    (
                        midcom::i18n()->get_string('host configuration', 'midcom.admin.settings'),
                        midcom::i18n()->get_string('settings saved successfully', 'midcom.admin.settings'),
                        'ok'
                    );
                }
                else
                {
                    midcom::uimessages()->add
                    (
                        midcom::i18n()->get_string('host configuration', 'midcom.admin.settings'),
                        sprintf(midcom::i18n()->get_string('failed to save settings, reason %s', 'midcom.admin.settings'), midcom_connection::get_error_string()),
                        'error'
                    );
                }

                midcom::relocate('__mfa/asgard_midcom.admin.settings/'.$host->guid.'/');

            case 'cancel':
                midcom::relocate('__mfa/asgard_midcom.admin.settings/'.$host->guid.'/');
                // This will exit.
        }

        $this->_prepare_request_data($data);

        // Add the view to breadcrumb trail
        $tmp = array();
        $tmp[] = array
        (
            MIDCOM_NAV_URL => '__mfa/asgard_midcom.admin.settings/',
            MIDCOM_NAV_NAME => $this->_l10n->get('host configuration'),
        );
        $tmp[] = array
        (
            MIDCOM_NAV_URL => '',
            MIDCOM_NAV_NAME => $data['hostname'],
        );

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);

        // Set page title
        midcom::set_pagetitle($this->_l10n->get('host configuration')." : ". $data['hostname']);

        return true;
    }


    /**
     * Shows the loaded article.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_edit ($handler_id, &$data)
    {
        midgard_admin_asgard_plugin::asgard_header();
        if(is_null($this->_config_storage))
        {
            midcom_show_style('midcom-admin-settings-empty');
        }
        else
        {
            midcom_show_style('midcom-admin-settings-edit');
        }
        midgard_admin_asgard_plugin::asgard_footer();
    }

    function _get_code_init()
    {
        foreach ($this->_controller->formmanager->form->_submitValues as $key => $val)
        {
            if (!array_key_exists($key, $GLOBALS['midcom_config']))
            {
                continue;
            }
            
            // Read correct value from DM2 type
            $val = $this->_controller->datamanager->types[$key]->convert_to_storage();

            if ($GLOBALS['midcom_config'][$key] != $val)
            {
                $this->hostconfig->set($key, $val);
            }
        }

        return $this->hostconfig->get_code_init('midcom.admin.settings');
    }

    /**
     * Static helper for listing hours of a day for purposes of pulldowns in the schema
     */
    function get_day_hours()
    {
        $hours = array();
        $i = 0;
        while ($i <= 23)
        {
            $hours[$i] = $i;
            $i++;
        }
        return $hours;
    }

    /**
     * Static helper for listing minutes of hour for purposes of pulldowns in the schema
     */
    function get_hour_minutes()
    {
        $minutes = array();
        $i = 0;
        while ($i <= 59)
        {
            $minutes[$i] = $i;
            $i++;
        }
        return $minutes;
    }

    function get_default_bool($key)
    {

        return sprintf(midcom::i18n()->get_string('default (%s)', 'midcom.admin.settings'), $GLOBALS['midcom_config_default'][$key] ? midcom::i18n()->get_string('yes', 'midcom') : midcom::i18n()->get_string('no', 'midcom'));
    }

    function get_default_val($key, $isprefix = false)
    {
        $prefix = '';
        if ($isprefix)
        {
            $prefix = "{$key}_";
        }

        return sprintf(midcom::i18n()->get_string('default (%s)', 'midcom.admin.settings'), midcom::i18n()->get_string("{$prefix}{$GLOBALS['midcom_config_default'][$key]}", 'midcom.admin.settings'));
    }

    function navigation()
    {
        $qb = midcom_db_host::new_query_builder();
        $qb->add_order('name');
        $rst = $qb->execute();

        $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

        echo '<ul class="midgard_admin_asgard_navigation">';

        foreach ($rst as $host)
        {
            if ($host->can_do("midgard::update"))
            {
                echo "            <li class=\"status\"><a href=\"{$prefix}__mfa/asgard_midcom.admin.settings/{$host->guid}/\">{$host->name}{$host->prefix}/</a></li>";
            }
        }

        echo '</ul>';

    }

}

?>