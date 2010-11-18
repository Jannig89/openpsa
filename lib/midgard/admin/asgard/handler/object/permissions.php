<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: permissions.php 25902 2010-04-30 10:31:29Z adrenalin $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Permissions interface
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_permissions extends midcom_baseclasses_components_handler
{
    /**
     * The object which permissions we handle
     *
     * @var mixed
     * @access private
     */
    var $object = null;

    /**
     * The Datamanager of the object
     *
     * @var midcom_helper_datamanager2_datamanager
     * @access private
     */
    var $_datamanager = null;

    /**
     * The Controller of the object used for editing
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
     * Privileges we're managing here
     *
     * @var Array
     * @access private
     */
    var $_privileges = array();

    /**
     * Table header
     *
     * @var String
     * @access private
     */
    var $_header = '';

    /**
     * Available row labels
     *
     * @var Array
     * @access private
     */
    var $_row_labels = array();

    /**
     * Rendered row labels
     *
     * @var Array
     * @access private
     */
    var $_rendered_row_labels = array();

    /**
     * Rendered row actions
     *
     * @var Array
     * @access private
     */
    var $_rendered_row_actions = array();

    /**
     * Simple default constructor.
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

        midcom::load_library('midcom.helper.datamanager2');

        $this->_privileges[] = 'midgard:read';
        $this->_privileges[] = 'midgard:create';
        $this->_privileges[] = 'midgard:update';
        $this->_privileges[] = 'midgard:delete';
        $this->_privileges[] = 'midgard:attachments';
        $this->_privileges[] = 'midgard:parameters';
        $this->_privileges[] = 'midgard:owner';

        if ($GLOBALS['midcom_config']['metadata_approval'])
        {
            $this->_privileges[] = 'midcom:approve';
        }

        midcom::enable_jquery();
        $script = "function submit_privileges(form){jQuery('#submit_action',form).attr({name: 'midcom_helper_datamanager2_add', value: 'add'});form.submit();};function applyRowClasses(){jQuery('.maa_permissions_items tr.maa_permissions_rows_row:odd').addClass('odd');jQuery('.maa_permissions_items tr.maa_permissions_rows_row:even').addClass('even');};";
        midcom::add_jscript($script);

        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . '/midgard.admin.asgard/permissions/layout.css'
            )
        );

    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['object'] =& $this->_object;
        $this->_request_data['controller'] =& $this->_controller;
        $this->_request_data['schemadb'] =& $this->_schemadb;
    }

    /**
     * Load component-defined additional privileges
     */
    private function _load_component_privileges()
    {
        $component_loader = midcom::get_component_loader();

        // Store temporarily the requested object
        $tmp = $this->_object;

        $i = 0;
        while (   $tmp
               && $tmp->guid
               && !midcom::dbfactory()->is_a($tmp, 'midgard_topic')
               && $i < 100)
        {
            // Get the parent; wishing eventually to get a topic
            $tmp = $tmp->get_parent();
            $i++;
        }

        // If the temporary object eventually reached a topic, fetch its manifest
        if (midcom::dbfactory()->is_a($tmp, 'midgard_topic'))
        {
            $current_manifest = $component_loader->manifests[$tmp->component];
        }
        else
        {
            $current_manifest = $component_loader->manifests[midcom::get_context_data(MIDCOM_CONTEXT_COMPONENT)];
        }

        foreach ($current_manifest->privileges as $privilege => $default_value)
        {
            $this->_privileges[] = $privilege;
        }

        if (   isset($current_manifest->customdata['midgard.admin.asgard.acl'])
            && isset($current_manifest->customdata['midgard.admin.asgard.acl']['extra_privileges']))
        {
            foreach ($current_manifest->customdata['midgard.admin.asgard.acl']['extra_privileges'] as $privilege)
            {
                if (!strpos($privilege, ':'))
                {
                    // Only component specified
                    // TODO: load components manifest and add privileges from there
                    continue;
                }
                $this->_privileges[] = $privilege;
            }
        }

        // In addition, give component configuration privileges if we're in topic
        if (midcom::dbfactory()->is_a($this->_object, 'midgard_topic'))
        {
            $this->_privileges[] = 'midcom.admin.folder:topic_management';
            $this->_privileges[] = 'midcom.admin.folder:template_management';
            $this->_privileges[] = 'midcom:component_config';
            $this->_privileges[] = 'midcom:urlname';
            if ($GLOBALS['midcom_config']['symlinks'])
            {
                $this->_privileges[] = 'midcom.admin.folder:symlinks';
            }
        }
    }

    /**
     * Static helper
     */
    function resolve_object_title($object)
    {
        $this->_request_data['object_reflector'] = midcom_helper_reflector::get($object);
        return $this->_request_data['object_reflector']->get_object_label($object);
    }

    /**
     * Generates, loads and prepares the schema database.
     *
     * The operations are done on all available schemas within the DB.
     */
    private function _load_schemadb()
    {
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_permissions'));

        // Populate additional assignee selector
        $additional_assignees = array
        (
            '' => '',
        );

        // Populate the magic assignees
        $additional_assignees['EVERYONE'] = midcom::i18n()->get_string('EVERYONE', 'midgard.admin.asgard');
        $additional_assignees['USERS'] = midcom::i18n()->get_string('USERS', 'midgard.admin.asgard');
        $additional_assignees['ANONYMOUS'] = midcom::i18n()->get_string('ANONYMOUS', 'midgard.admin.asgard');

        // List groups as potential assignees
        $qb = midcom_db_group::new_query_builder();

        $groups = $qb->execute();
        foreach ($groups as $group)
        {
            $label = $group->official;
            if (empty($group->official))
            {
                $label = $group->name;
                if (empty($group->name))
                {
                    $label = sprintf(midcom::i18n()->get_string('group %s', 'midgard.admin.asgard'), "#{$group->id}");
                }
            }

            $additional_assignees["group:{$group->guid}"] = $label;
        }

        $assignees = array();

        // Populate all resources having existing privileges
        $existing_privileges = $this->_object->get_privileges();

        foreach ($existing_privileges as $privilege)
        {
            if ($privilege->is_magic_assignee())
            {
                // This is a magic assignee
                $label = midcom::i18n()->get_string($privilege->assignee, 'midgard.admin.asgard');
            }
            else
            {
                //Inconsistent privilige base will mess here. Let's give a chance to remove ghosts
                $assignee = midcom::auth->get_assignee($privilege->assignee);

                if (is_object($assignee))
                {
                    $label = $assignee->name;
                }
                else
                {
                    $label = midcom::i18n()->get_string('ghost assignee for '. $privilege->assignee, 'midgard.admin.asgard');
                }
            }

            $assignees[$privilege->assignee] = $label;

            $key = str_replace(':', '_', $privilege->assignee);
            if (!isset($this->_row_labels[$key]))
            {
                $this->_row_labels[$key] = $label;
            }

            // This one is already an assignee, remove from 'Add assignee' options
            if (array_key_exists($privilege->assignee, $additional_assignees))
            {
                unset($additional_assignees[$privilege->assignee]);
            }
        }

        asort($additional_assignees);

        // Add the 'Add assignees' choices to schema
        $this->_schemadb['privileges']->fields['add_assignee']['type_config']['options'] = $additional_assignees;

        $header = '';

        $header_start = '';
        $header_end = '';
        $header_items = array();

        foreach ($assignees as $assignee => $label)
        {
            foreach ($this->_privileges as $privilege)
            {
                $privilege_components = explode(':', $privilege);
                if (   $privilege_components[0] == 'midcom'
                    || $privilege_components[0] == 'midgard')
                {
                    // This is one of the core privileges, we handle it
                    $privilege_label = $privilege;
                }
                else
                {
                    // This is a component-specific privilege, call component to localize it
                    $privilege_label = midcom::i18n()->get_string("privilege {$privilege_components[1]}", $privilege_components[0]);
                }

                if (! isset($header_items[$privilege_label]))
                {
                    $header_items[$privilege_label] = "        <th scope=\"col\" class=\"{$privilege_components[1]}\"><span>" . str_replace(' ', "\n", midcom::i18n()->get_string($privilege_label, 'midgard.admin.asgard')) . "</span></th>\n";
                }

                $this->_schemadb['privileges']->append_field(str_replace(':', '_', $assignee) . '_' . str_replace(':', '_', str_replace('.', '_', $privilege)), Array
                    (
                        'title' => $privilege_label,
                        'helptext'    => sprintf(midcom::i18n()->get_string('sets privilege %s', 'midgard.admin.asgard'), $privilege),
                        'storage' => null,
                        'type' => 'privilege',
                        'type_config' => Array
                        (
                            'privilege_name' => $privilege,
                            'assignee'       => $assignee,
                        ),
                        'widget' => 'privilegeselection',
                    )
                );
            }
        }

        $header .= "        <th align=\"left\" scope=\"col\" class=\"assignee_name\"><span>&nbsp;</span></th>\n";
        foreach ($header_items as $key => $item)
        {
            $header .= $item;
        }
        $header .= "        <th scope=\"col\" class=\"row_actions\"><span>&nbsp;</span></th>\n";

        $this->_header = $header;
    }

    /**
     * Internal helper, loads the controller for the current object. Any error triggers a 500.
     *
     * @access private
     */
    private function _load_controller()
    {
        $this->_load_schemadb();
        $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->set_storage($this->_object, 'privileges');
        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 controller instance for object {$this->_object->id}.");
            // This will exit.
        }
    }

    /**
     * Object editing view
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_edit($handler_id, $args, &$data)
    {
        $this->_object = midcom::dbfactory()->get_object_by_guid($args[0]);
        if (   !$this->_object
            || !$this->_object->guid)
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "The GUID '{$args[0]}' was not found.");
        }
        $this->_object->require_do('midgard:privileges');
        midcom::auth->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');

        $script = "
            applyRowClasses();
        ";

        // jQuery('.maa_permissions_rows ul').sortable({
        //     handle: 'div.draghandle',
        //     start: function(e, ui) {
        //         console.log(e);
        //     },
        //     stop: function(e, ui) {
        //         applyRowClasses();
        //     }
        // });

        // var clear_storage = jQuery('#maa_permissions_clear_storage');
        //
        // if (clear_storage.attr('class') != 'done') {
        //     clear_storage.attr('class','done');
        //     var data = {
        //         midcom_helper_datamanager2_save: 'Save',
        //         _qf__net_nehmer_static: ''
        //     };
        //     jQuery('ul.items li').each(function(i,n){
        //         jQuery(this).find('select').each(function(i,n){
        //             data[jQuery(n).attr('name')] = '" . MIDCOM_PRIVILEGE_INHERIT . "';
        //             //jQuery('<input type=\"hidden\" name=\"'+jQuery(n).attr('name')+'\" value=\"'+jQuery(n).val()+'\" />').appendTo(clear_storage);
        //         });
        //     });
        //     console.log(data);
        //     //jQuery.post('/__mfa/asgard/object/permissions/{$this->_object->guid}/', data, function(){jQuery.get('/__mfa/asgard/object/permissions/{$this->_object->guid}/');});
        // }

        midcom::add_jquery_state_script($script);

        // Load possible additional component privileges
        $this->_load_component_privileges();

        // Load the datamanager controller
        $this->_load_controller();

        if (   isset($_POST['midcom_helper_datamanager2_add'])
            && isset($_POST['add_assignee'])
            && $_POST['add_assignee'])
        {
            $this->_object->set_privilege('midgard:read', $_POST['add_assignee']);
            midcom::relocate("__mfa/asgard/object/permissions/{$this->_object->guid}/");
        }

        // Unset privileges so rearrangements work
        if (   isset($_POST['midcom_helper_datamanager2_save'])
            && $_POST['midcom_helper_datamanager2_save'])
        {
            $privs = $this->_object->get_privileges();
            foreach ($privs as $priv)
            {
                $priv->drop();
                $this->_object->unset_privilege($priv->name, $priv->assignee);
            }

            // Reread privilege types
            foreach ($this->_controller->datamanager->types as $field => $type)
            {
                if ($field != 'add_assignee')
                {
                    $this->_controller->datamanager->types[$field]->convert_from_storage('');
                }
            }

            // Reorder schema fields according to the POST vars
            $tmp_fields = array();
            foreach ($_POST as $key => $value)
            {
                if (! in_array($key, array('_qf__net_nehmer_static', 'midcom_helper_datamanager2_save')))
                {
                    if (isset($this->_controller->datamanager->storage->_schema->fields[$key]))
                    {
                        $tmp_fields[$key] = $this->_controller->datamanager->storage->_schema->fields[$key];
                    }
                }
            }
            if (!empty($tmp_fields))
            {
                $this->_controller->datamanager->storage->_schema->fields = $tmp_fields;
                $this->_controller->datamanager->schema->fields = $tmp_fields;
            }
        }

        switch ($this->_controller->process_form())
        {
            case 'save':
                // Handle populating additional assignees
                $new_assignee = $this->_object->parameter('midgard.admin.asgard.acl', 'add_assignee');
                if ($new_assignee)
                {
                    // We do this by adding a READ privilege so they show up on get_privileges()
                    // TODO: Would be nicer to register a priv that doesn't really count
                    if (!$this->_object->set_privilege('midgard:read', $new_assignee, MIDCOM_PRIVILEGE_ALLOW))
                    {
                        debug_push_class(__CLASS__, __FUNCTION__);
                        debug_add("Adding new privilege for assignee {$new_assignee} failed.", MIDCOM_LOG_WARN);
                        debug_pop();
                    }

                    // Then clear the parameter and relocate
                    $this->_object->parameter('midgard.admin.asgard.acl', 'add_assignee', '');

                    midcom::relocate("__mfa/asgard/object/permissions/{$this->_object->guid}/");
                    // This will exit.
                }
            case 'cancel':
                midcom::relocate("__mfa/asgard/object/view/{$this->_object->guid}/");
                // This will exit.
        }

        $this->_prepare_request_data();

        // Figure out label for the object's class
        switch (get_class($this->_object))
        {
            case 'midcom_db_topic':
                $type = midcom::i18n()->get_string('folder', 'midgard.admin.asgard');
                break;
            default:
                $type_parts = explode('_', get_class($this->_object));
                $type = $type_parts[count($type_parts)-1];
        }

        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        return true;
    }

    /**
     * Shows the loaded object in editor.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_edit($handler_id, &$data)
    {
        $this->_generate_editor($data);

        midcom_show_style('midgard_admin_asgard_header');
        midcom_show_style('midgard_admin_asgard_middle');

        midcom_show_style('midgard_admin_asgard_object_permissions');

        midcom_show_style('midgard_admin_asgard_footer');
    }

    private function _generate_editor(&$data)
    {
        $data['editor_rows'] = '';

        $form_start = "<form ";
        foreach ($this->_controller->formmanager->form->_attributes as $key => $value)
        {
            $form_start .= "{$key}=\"{$value}\" ";
        }
        $form_start .= "/>\n";

        $data['editor_header_form_start'] = $form_start;
        $data['editor_header_form_end'] = "</form>\n";

        $data['editor_header_titles'] = $this->_header;

        $data['editor_header'] = '';

        $priv_item_cnt = count($this->_privileges);

        $s = 0;
        foreach ($this->_controller->formmanager->form->_elements as $i => $row)
        {
            if (is_a($row, 'HTML_QuickForm_hidden'))
            {
                $html = "<input  ";
                foreach ($row->_attributes as $key => $value)
                {
                    $html .= "{$key}=\"{$value}\" ";
                }
                $html .= "/>\n";

                $data['editor_header_form_start'] .= $html;
            }

            if (is_a($row, 'HTML_QuickForm_select'))
            {
                $html = "  <div class=\"assignees\">\n";
                $html .= "    <label for=\"{$row->_attributes['id']}\">\n<span class=\"field_text\">{$row->_label}</span>\n";
                $html .= $this->_render_select($row);
                $html .= "    </label>\n";
                $html .= "  </div>\n";

                $data['editor_header_assignees'] = $html;

                $html = '';
            }

            if (is_a($row, 'HTML_QuickForm_group'))
            {
                if ($row->_name == 'form_toolbar')
                {
                    $form_toolbar_html = "  <div class=\"actions\">\n";
                    foreach ($row->_elements as $k => $element)
                    {
                        if (is_a($element, 'HTML_QuickForm_submit'))
                        {
                            $form_toolbar_html .= $this->_render_button($element);
                        }
                    }
                    $form_toolbar_html .= "  </div>\n";
                    continue;
                }

                $html = $this->_render_row_label($row->_name);

                foreach ($row->_elements as $k => $element)
                {
                    if (is_a($element, 'HTML_QuickForm_select'))
                    {
                        $html .= $this->_render_select($element);
                    }
                    if (is_a($element, 'HTML_QuickForm_static'))
                    {
                        if (strpos($element->_attributes['name'], 'holder_start') !== false)
                        {
                            $priv_class = $this->_get_row_value_class($row->_name);
                            $html .= "      <td class=\"row_value {$priv_class}\">\n";
                        }

                        $html .= $this->_render_static($element);
                        if (strpos($element->_attributes['name'], 'initscripts') !== false)
                        {
                            $html .= "      </td>\n";
                        }
                    }
                }

                $s++;

                if ($s == $priv_item_cnt)
                {
                    $s = 0;
                    $html .= $this->_render_row_actions($row->_name);
                    $html .= "    </tr>\n";
                }

                $data['editor_rows'] .= $html;
            }
        }

        $footer = "  <input type=\"hidden\" name=\"\" value=\"\" id=\"submit_action\"/>\n";
        $footer .= $form_toolbar_html;

        $data['editor_footer'] = $footer;

        return true;
    }

    private function _render_select($object)
    {
        $html = '';
        $element_name = '';

        $html .= "<select ";
        foreach ($object->_attributes as $key => $value)
        {
            $html .= "{$key}=\"{$value}\" ";
            if ($key == 'name')
            {
                $element_name = $value;
            }
        }
        $html .= ">\n";

        $selected_val = '';
        if (isset($this->_controller->formmanager->form->_defaultValues[$element_name]))
        {
            $selected_val = $this->_controller->formmanager->form->_defaultValues[$element_name];
        }
        if (isset($this->_controller->formmanager->form->_submitValues[$element_name]))
        {
            $selected_val = $this->_controller->formmanager->form->_submitValues[$element_name];
        }

        foreach ($object->_options as $k => $item)
        {
            $selected = '';
            if (   $selected_val != ''
                && $selected_val == $item['attr']['value'])
            {
                $selected = 'selected="selected"';
            }

            $html .= "<option value=\"{$item['attr']['value']}\" {$selected}>{$item['text']}</option>\n";
        }

        $html .= "</select>\n";

        return $html;
    }

    private function _render_button($object)
    {
        $html = "    <input ";
        foreach ($object->_attributes as $key => $value)
        {
            $html .= "{$key}=\"{$value}\" ";
            if ($key == 'name')
            {
                $element_name = $value;
            }
        }
        $html .= " />\n";

        return $html;
    }

    private function _render_static($object)
    {
        $html = $object->_text;

        return $html;
    }

    private function _render_row_label($row_name)
    {
        foreach ($this->_row_labels as $key => $label)
        {
            if (   strpos($row_name, $key) !== false
                && !isset($this->_rendered_row_labels[$key]))
            {
                $this->_rendered_row_labels[$key] = true;

                $html = "    <tr id=\"privilege_row_{$key}\" class=\"maa_permissions_rows_row\">\n";
                $html .= "      <td class=\"row_value assignee_name\"><span>{$label}</span></td>\n"; //<div class=\"draghandle\"></div>

                return $html;
            }
        }

        return '';
    }

    private function _render_row_actions($row_name)
    {
        foreach ($this->_row_labels as $key => $label)
        {
            if (strpos($row_name, $key) !== false)
            {
                $this->_rendered_row_actions[$key] = true;

                $actions = "<div class=\"actions\" id=\"privilege_row_actions_{$key}\">";
                $actions .= "</div>";

                midcom::add_jquery_state_script("jQuery('#privilege_row_{$key}').privilege_actions('{$key}');");

                $html = "      <td class=\"row_value row_actions\">{$actions}</td>\n";

                return $html;
            }
        }

        return '';
    }

    private function _get_row_value_class($row_name)
    {
        foreach ($this->_row_labels as $key => $label)
        {
            if (strpos($row_name, $key) !== false)
            {
                $tmp_priv = str_replace($key . '_', '', $row_name);
                $tmp_priv_arr = explode('_', $tmp_priv);
                $priv_class = "{$tmp_priv_arr[1]}";
                if (count($tmp_priv_arr) > 2)
                {
                    $priv_class = "{$tmp_priv_arr[1]}_{$tmp_priv_arr[2]}";
                }
                return $priv_class;
            }
        }
        return '';
    }
}
?>