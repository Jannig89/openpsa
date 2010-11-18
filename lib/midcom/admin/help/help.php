<?php
/**
 * @package midcom.admin.help
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: help.php 25331 2010-03-18 23:02:08Z solt $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Online help display
 *
 * @package midcom.admin.help
 */
class midcom_admin_help_help extends midcom_baseclasses_components_handler
{

    var $mgdtypes = array
    (
        MGD_TYPE_STRING => "string",
        MGD_TYPE_INT => "integer",
        MGD_TYPE_UINT => "unsigned integer",
        MGD_TYPE_FLOAT => "float",
        //MGD_TYPE_DOUBLE => "double",
        MGD_TYPE_BOOLEAN => "boolean",
        MGD_TYPE_TIMESTAMP => "datetime",
        MGD_TYPE_LONGTEXT => "longtext",
        MGD_TYPE_GUID => "guid",
    );

    var $special_ids = array('handlers', 'dependencies', 'urlmethods', 'mgdschemas');


    function __construct()
    {
        parent::__construct();
        midcom::add_link_head
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . '/midcom.admin.help/style-editor.css',
            )
        );

        midcom::add_jsfile(MIDCOM_STATIC_URL.'/midcom.admin.help/twisty.js');
        if (defined('MGD_TYPE_NONE'))
        {
            $this->mgdtypes[MGD_TYPE_NONE] = 'none';
        }
    }

    function get_plugin_handlers()
    {
        return array
        (
            // Handle / MidCOM help system welcome screen
            'welcome' => array
            (
                'handler' => array('midcom_admin_help_help', 'welcome'),
            ),
            // Handle /<component> component documentation ToC
            'component' => array
            (
                'handler' => array('midcom_admin_help_help', 'component'),
                'variable_args' => 1,
            ),
            // Handle /<component>/<help id> display help page from a component
            'help' => array
            (
                'handler' => array('midcom_admin_help_help', 'help'),
                'variable_args' => 2,
            ),
        );
    }

    function _on_initialize()
    {
        // doing this here as this component most probably will not be called by itself.
        midcom::style()->prepend_component_styledir('midcom.admin.help');
        midcom::load_library('net.nehmer.markdown');
    }

    static function check_component($component)
    {
        if (empty($component))
        {
            $component = 'midcom.core.nullcomponent';
        }
        if (   !midcom::componentloader()->is_installed($component)
            && $component != 'midcom')
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to generate documentation path for component {$component} as it is not installed.");
            // This will exit
        }
    }

    /**
     * Static method for getting component's documentation directory path
     *
     * @param string $component Component name
     * @return string Component documentation directory path
     */
    static function get_documentation_dir($component)
    {
        if ($component == 'midcom')
        {
            return MIDCOM_ROOT . "/midcom/documentation/";
        }
        self::check_component($component);
        $component_dir = str_replace('.', '/', $component);
        return MIDCOM_ROOT . "/{$component_dir}/documentation/";
    }

    /**
     * Static method for checikng if help file exists
     *
     * @param string $help_id Help name ID
     * @param string $component Component name
     * @return bool True of false
     */
    static function help_exists($help_id, $component)
    {
        $file = self::generate_file_path($help_id, $component);

        if (!$file)
        {
            return false;
        }

        if (file_exists($file))
        {
            return true;
        }

        return false;
    }

    static function generate_file_path($help_id, $component, $language = null)
    {

        if ($language === null)
        {
            $language = midcom::i18n()->get_current_language();
        }

        $file = self::get_documentation_dir($component) . "{$help_id}.{$language}.txt";
        if (!file_exists($file))
        {
            if ($language != $GLOBALS['midcom_config']['i18n_fallback_language'])
            {
                // Try MidCOM's default fallback language
                $file = self::generate_file_path($help_id, $component, $GLOBALS['midcom_config']['i18n_fallback_language']);
            }
            else
            {
                return null;
            }
        }

        return $file;
    }

    static function get_help_title($help_id, $component)
    {

        $subject = midcom::i18n()->get_string("help_" . $help_id, 'midcom.admin.help');
        $path = self::generate_file_path($help_id, $component);
        if (!$path)
        {
            return $subject;
        }
        $file_contents = file($path);
        if (trim($file_contents[0]))
        {
            $subject = trim($file_contents[0]);
        }

        return $subject;
    }

    /**
     * Load the file from the component's documentation directory.
     */
    function _load_file($help_id, $component)
    {
        // Try loading the file
        $file = self::generate_file_path($help_id, $component);
        if (!$file)
        {
            return false;
        }

        // Load the contents
        $help_contents = file_get_contents($file);

        // Replace static URLs (URLs for screenshots etc)
        $help_contents = str_replace('MIDCOM_STATIC_URL', MIDCOM_STATIC_URL, $help_contents);

        return $help_contents;
    }

    /**
     * Load a help file and markdownize it
     */
    function get_help_contents($help_id, $component)
    {
        $text = $this->_load_file($help_id, $component);
        if (!$text)
        {
            return false;
        }

        $marker = new net_nehmer_markdown_markdown();

        // Finding [callback:some_method_of_viewer]
        if (preg_match_all('/(\[callback:(.+?)\])/', $text, $regs))
        {
            foreach ($regs[1] as $i => $value)
            {
                if ($component != midcom::get_context_data(MIDCOM_CONTEXT_COMPONENT))
                {
                    $text = str_replace($value, "\n\n    __Note:__ documentation part _{$regs[2][$i]}_ from _{$component}_ is unavailable in this MidCOM context.\n\n", $text);
                }
                else
                {
                    $method_name = "help_{$regs[2][$i]}";
                    if (method_exists($this->_master, $method_name))
                    {
                        $text = str_replace($value, $this->_master->$method_name(), $text);
                    }
                }
            }
        }

        return $marker->render($text);
    }

    function list_files($component, $with_index = false)
    {
        $files = array();

        $path = midcom_admin_help_help::get_documentation_dir($component);
        if (!file_exists($path))
        {
            return $files;
        }

        $directory = dir($path);
        while (false !== ($entry = $directory->read()))
        {
            if (substr($entry, 0, 1) == '.' ||
                substr($entry, 0, 5) == 'index' ||
                substr($entry, 0, 7) == 'handler' ||
                substr($entry, 0, 9) == 'urlmethod'
               )
            {
                // Ignore dotfiles, handlers & index.lang.txt
                continue;
            }

            $filename_parts = explode('.', $entry);
            if (count($filename_parts) < 3)
            {
                continue;
            }

            if ($filename_parts[2] != 'txt')
            {
                // Not text file, skip
                continue;
            }

            if (   $filename_parts[1] != midcom::i18n()->get_current_language()
                && $filename_parts[1] != $GLOBALS['midcom_config']['i18n_fallback_language'])
            {
                // Wrong language
                continue;
            }

            $files[$filename_parts[0]] = array
            (
                'path' => "{$path}{$entry}",
                'subject' => self::get_help_title($filename_parts[0], $component),
                'lang' => $filename_parts[1],
            );
        }
        $directory->close();

        //Artificial help files.
        // Schemas
        $this->_request_data['mgdschemas'] = midcom::dbclassloader()->get_component_classes($component);
        if (count($this->_request_data['mgdschemas']))
        {
            $files['mgdschemas'] = array
            (
                'path' => '/mgdschemas',
                'subject' => midcom::i18n()->get_string('help_mgdschemas','midcom.admin.help'),
                'lang' => 'en',
            );
        }

        // URL Methods
        $this->_request_data['urlmethods'] = $this->read_url_methods($component);
        if (count($this->_request_data['urlmethods']))
        {
            $files['urlmethods'] = array
            (
                'path' => '/urlmethods',
                'subject' => midcom::i18n()->get_string('help_urlmethods','midcom.admin.help'),
                'lang' => 'en',
            );
        }

        // Break if dealing with MidCOM Core docs
        if ($component == 'midcom')
        {
            ksort($files);
            return $files;
        }

        // handlers
        $this->_request_data['request_switch_info'] = $this->read_component_handlers($component);
        if (count($this->_request_data['request_switch_info']))
        {
            $files['handlers'] = array
            (
                'path' => '/handlers',
                'subject' => midcom::i18n()->get_string('help_handlers','midcom.admin.help'),
                'lang' => 'en',
            );
        }

        // Dependencies
        $this->_request_data['dependencies'] = midcom::componentloader()->get_component_dependencies($component);
        if (count($this->_request_data['dependencies']))
        {
            $files['dependencies'] = array
            (
                'path' => '/dependencies',
                'subject' => midcom::i18n()->get_string('help_dependencies','midcom.admin.help'),
                'lang' => 'en',
            );
        }
        ksort($files);
        // prepend 'index' URL if required
        if ($with_index)
        {
            $files = array_merge
            (
                array
                (
                    'index' => array
                    (
                        'path' => '/',
                        'subject' => midcom::i18n()->get_string('help_index','midcom.admin.help'),
                        'lang' => 'en',
                    ),
                ),
                $files
            );
        }
        return $files;
    }


    function read_component_handlers($component)
    {
        $data = array();

        // TODO: We're using "private" members here, better expose them through a method
        $handler = midcom::componentloader()->get_interface_class($component);
        $request =& $handler->_context_data[midcom::get_current_context()]['handler'];
        if (!isset($request->_request_switch))
        {
            // No request switch available, skip loading it
            return $data;
        }

        foreach ($request->_request_switch as $request_handler_id => $request_data)
        {
            if (substr($request_handler_id, 0, 12) == '____ais-help')
            {
                // Skip self
                continue;
            }

            $data[$request_handler_id] = array();

            // Build the dynamic_loadable URI, starting from topic path
            $data[$request_handler_id]['route'] = str_replace(midcom_connection::get_url('prefix'), '', midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX));
            // Add fixed arguments
            $data[$request_handler_id]['route'] .= implode('/', $request_data['fixed_args']);
            // Add variable_arguments
            $i = 0;
            while ($i < $request_data['variable_args'])
            {
                if (substr($data[$request_handler_id]['route'], strlen($data[$request_handler_id]['route']) - 1) != '/')
                {
                    $data[$request_handler_id]['route'] .= '/';
                }
                $data[$request_handler_id]['route'] .= '{$args[' . $i . ']}';
                $i++;
            }

            if (substr($data[$request_handler_id]['route'], strlen($data[$request_handler_id]['route']) - 1) != '/')
            {
                $data[$request_handler_id]['route'] .= '/';
            }

            if (is_array($request_data['handler']))
            {
                $data[$request_handler_id]['controller'] = $request_data['handler'][0];

                if (is_object($data[$request_handler_id]['controller']))
                {
                    $data[$request_handler_id]['controller'] = get_class($data[$request_handler_id]['controller']);
                }

                $data[$request_handler_id]['action'] = $request_data['handler'][1];
            }

            if (midcom_admin_help_help::help_exists('handlers_' . $request_handler_id,$component))
            {
                $data[$request_handler_id]['info'] = midcom_admin_help_help::get_help_contents('handlers_' . $request_handler_id,$component);
                $data[$request_handler_id]['handler_help_url'] = 'handlers_' . $request_handler_id;
            }
        }

        return $data;
    }

    function read_url_methods($component)
    {
        $data = array();

        if ($component == 'midcom')
        {
            $exec_path = MIDCOM_ROOT . '/midcom/exec';
        }
        else
        {
            $component_path = str_replace('.', '/', $component);
            $exec_path = MIDCOM_ROOT . '/' . $component_path . '/exec';
        }

        if (   !is_dir($exec_path)
            || !is_readable($exec_path))
        {
            // Directory not accessible, skip loading it
            return $data;
        }

        $dir_handle = opendir($exec_path);

        while (false !== ($file = readdir($dir_handle)))
        {
            if (preg_match('/^\./', $file))
            {
                //Skip hidden files
                continue;
            }
            $data[$file] = array();

            $info_id = "urlmethod_" . str_replace('.php', '', $file);

            $data[$file]['url'] = '/midcom-exec-' . $component . '/' . $file;
            $data[$file]['description'] = midcom_admin_help_help::get_help_contents($info_id, $component);

            if (midcom_admin_help_help::help_exists($info_id, $component))
            {
                $data[$file]['handler_help_url'] = $info_id;
            }
        }

        return $data;
    }

    function read_schema_properties()
    {
        foreach ($this->_request_data['mgdschemas'] as $classdef)
        {
            $classname = $classdef['mgdschema_class_name'];
            $mrp = new midgard_reflection_property($classname);
            $dummy = new $classname();
            $class_props = get_object_vars($dummy);
            unset($class_props['metadata']);
            $default_properties = array();
            $additional_properties = array();

            foreach ($class_props as $prop => $vanity)
            {
                $descr = $mrp->description($prop);
                switch ($prop)
                {
                    case 'action':
                    case 'sid':
                        // Midgard-internal properties, skip
                        break;
                    case 'guid':
                    case 'id':
                        $default_properties[$prop] = $this->_get_property_data($mrp, $prop);
                        break;
                    default:
                        $additional_properties[$prop] = $this->_get_property_data($mrp, $prop);
                        break;
                }
            }
            ksort($default_properties);
            ksort($additional_properties);

            $this->_request_data['properties'][$classname] = array_merge($default_properties, $additional_properties);
        }
        return true;
    }

    function _get_property_data($mrp,$prop)
    {
        $ret = array();

        $ret['value'] = $mrp->description($prop);

        $ret['link'] = $mrp->is_link($prop);
        $ret['link_name'] = $mrp->get_link_name($prop);
        $ret['link_target'] = $mrp->get_link_target($prop);

        $ret['midgard_type'] = $this->mgdtypes[$mrp->get_midgard_type($prop)];
        return $ret;
    }

    function _load_component_data($name)
    {
        $component_array = array();
        $component_array['name'] = $name;
        $component_array['title'] = midcom::i18n()->get_string($name, $name);
        $component_array['icon'] = midcom::componentloader()->get_component_icon($name);

        if (!isset(midcom::componentloader()->manifests[$name]))
        {
            return $component_array;
        }

        $manifest = midcom::componentloader()->manifests[$name];
        $component_array['purecode'] = $manifest->purecode;

        if (isset($manifest->_raw_data['package.xml']['description']))
        {
            $component_array['description'] = $manifest->_raw_data['package.xml']['description'];
        }
        else
        {
            $component_array['description'] = '';
        }

        $component_array['version'] = $manifest->_raw_data['version'];

        $component_array['maintainers'] = array();
        if (isset($manifest->_raw_data['package.xml']['maintainers']))
        {
            $component_array['maintainers'] = $manifest->_raw_data['package.xml']['maintainers'];
        }

        return $component_array;
    }

    function _list_components()
    {
        $this->_request_data['core_components'] = array();
        $this->_request_data['components'] = array();
        $this->_request_data['libraries'] = array();
        $this->_request_data['core_libraries'] = array();

        $this->_request_data['core_components']['midcom'] = $this->_load_component_data('midcom');

        foreach (midcom::componentloader()->manifests as $name => $manifest)
        {
            if (!array_key_exists('package.xml', $manifest->_raw_data))
            {
                // This component is not yet packaged, skip
                continue;
            }

            $type = 'components';
            if ($manifest->purecode)
            {
                $type = 'libraries';
            }

            if (midcom::componentloader()->is_core_component($name))
            {
                $type = 'core_' . $type;
            }

            $component_array = $this->_load_component_data($name);

            $this->_request_data[$type][$name] = $component_array;
        }

        asort($this->_request_data['core_components']);
        asort($this->_request_data['components']);
        asort($this->_request_data['libraries']);
        asort($this->_request_data['core_libraries']);
    }

    function _prepare_breadcrumb($handler_id)
    {
        $breadcrumb = array();

        if ($handler_id == '____ais-help-help')
        {
            if ($this->_request_data['help_id'] == 'handlers'
                || $this->_request_data['help_id'] == 'dependencies'
                || $this->_request_data['help_id'] == 'urlmethods'
                || $this->_request_data['help_id'] == 'mgdschemas')
            {
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => "__ais/help/{$this->_request_data['component']}/{$this->_request_data['help_id']}",
                    MIDCOM_NAV_NAME => midcom::i18n()->get_string($this->_request_data['help_id'], 'midcom.admin.help'),
                );
            }
            else
            {
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => "__ais/help/{$this->_request_data['component']}/{$this->_request_data['help_id']}",
                    MIDCOM_NAV_NAME => self::get_help_title($this->_request_data['help_id'], $this->_request_data['component']),
                );
            }
        }

        if ($handler_id == '____ais-help-help'
            || $handler_id == '____ais-help-component')
        {
            $breadcrumb[] = array
            (
                MIDCOM_NAV_URL => "__ais/help/{$this->_request_data['component']}/",
                MIDCOM_NAV_NAME => sprintf(midcom::i18n()->get_string('help for %s', 'midcom.admin.help'), midcom::i18n()->get_string($this->_request_data['component'], $this->_request_data['component'])),
            );
        }

        $breadcrumb[] = array
        (
            MIDCOM_NAV_URL => "__ais/help/",
            MIDCOM_NAV_NAME => midcom::i18n()->get_string('midcom.admin.help', 'midcom.admin.help'),
        );

        $breadcrumb = array_reverse($breadcrumb);

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $breadcrumb);
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_welcome($handler_id, $args, &$data)
    {
        midcom::auth()->require_valid_user();
        midcom::skip_page_style = true;

        $data['view_title'] = midcom::i18n()->get_string('midcom.admin.help', 'midcom.admin.help');
        midcom::set_pagetitle($data['view_title']);

        $this->_list_components();

        $this->_prepare_breadcrumb($handler_id);

        return true;
    }

    /**
     * Shows the help system main screen
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_welcome($handler_id, &$data)
    {
        midcom_show_style('midcom_admin_help_header');
        $list_types = array('core_components','core_libraries','components','libraries');

        foreach ($list_types as $list_type)
        {
            $data['list_type'] = $list_type;
            midcom_show_style('midcom_admin_help_list_header');
            foreach ($data[$list_type] as $component => $component_data)
            {
                $data['component_data'] = $component_data;
                midcom_show_style('midcom_admin_help_list_item');
            }
            midcom_show_style('midcom_admin_help_list_footer');
        }

        midcom_show_style('midcom_admin_help_footer');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_component($handler_id, $args, &$data)
    {
        midcom::auth()->require_valid_user();

        $data['component'] = $args[0];

        if (!midcom::componentloader()->is_installed($data['component']) && $data['component'] != 'midcom')
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "Component {$data['component']} is not installed.");
            // This will exit
        }

        if ($data['component'] != 'midcom') midcom::componentloader()->load($data['component']);
        midcom::skip_page_style = true;

        $data['view_title'] = sprintf(midcom::i18n()->get_string('help for %s', 'midcom.admin.help'), midcom::i18n()->get_string($data['component'], $data['component']));
        midcom::set_pagetitle($data['view_title']);

        $data['help_files'] = $this->list_files($data['component']);
        $data['html'] = $this->get_help_contents('index', $data['component']);
        $this->_prepare_breadcrumb($handler_id);

        return true;
    }

    /**
     * Shows the component help ToC.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_component($handler_id, &$data)
    {
        midcom_show_style('midcom_admin_help_header');

        midcom_show_style('midcom_admin_help_show');
        midcom_show_style('midcom_admin_help_component');

        midcom_show_style('midcom_admin_help_footer');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_help($handler_id, $args, &$data)
    {
        midcom::auth()->require_valid_user();

        $data['help_id'] = $args[1];
        $data['component'] = $args[0];
        if (!midcom::componentloader()->is_installed($data['component']) && $data['component'] != 'midcom')
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "Component {$data['component']} is not installed.");
            // This will exit
        }

        if ($data['component'] != 'midcom') midcom::componentloader()->load($data['component']);
        midcom::skip_page_style = true;

        $data['help_files'] = $this->list_files($data['component']);

        switch ($data['help_id'])
        {
            case 'mgdschemas':
                $this->read_schema_properties();
                // Fall through
            default:
                $data['html'] = $this->get_help_contents($data['help_id'], $data['component']);
        }

        // Table of contents navi
        $data['view_title'] = sprintf
        (
            midcom::i18n()->get_string('help for %s in %s', 'midcom.admin.help'),
            self::get_help_title($data['help_id'], $data['component']),
            midcom::i18n()->get_string($data['component'], $data['component'])
        );
        midcom::set_pagetitle($data['view_title']);
        $this->_prepare_breadcrumb($handler_id);

        return true;
    }

    /**
     * Shows the help page.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_help($handler_id, &$data)
    {
        midcom_show_style('midcom_admin_help_header');
        switch($this->_request_data['help_id'])
        {
            case 'handlers':
                midcom_show_style('midcom_admin_help_handlers');
                break;
            case 'mgdschemas':
                midcom_show_style('midcom_admin_help_show');
                midcom_show_style('midcom_admin_help_mgdschemas');
                break;
            case 'urlmethods':
                midcom_show_style('midcom_admin_help_show');
                midcom_show_style('midcom_admin_help_urlmethods');
                break;
            case 'dependencies':
                midcom_show_style('midcom_admin_help_show');
                $data['list_type'] = 'dependencies';
                midcom_show_style('midcom_admin_help_list_header');
                foreach ($data['dependencies'] as $component)
                {
                    $data['component_data'] = $this->_load_component_data($component);
                    midcom_show_style('midcom_admin_help_list_item');
                }
                midcom_show_style('midcom_admin_help_list_footer');
                break;
            default:
                midcom_show_style('midcom_admin_help_show');

                if (!$this->_request_data['html'])
                {
                    $this->_request_data['html'] = $this->get_help_contents('notfound', 'midcom.admin.help');
                    midcom_show_style('midcom_admin_help_show');
                    midcom_show_style('midcom_admin_help_component');
                }
        }
        midcom_show_style('midcom_admin_help_footer');
    }
}
?>