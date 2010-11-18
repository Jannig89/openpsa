<?php
/**
 * @package midcom.helper.reflector
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: main.php 26123 2010-05-16 19:03:49Z bergie $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * The Grand Unified Reflector
 * @package midcom.helper.reflector
 */
class midcom_helper_reflector extends midcom_baseclasses_components_purecode
{

    var $mgdschema_class = false;
    var $_mgd_reflector = false;
    var $_dummy_object = false;
    var $_original_class = false;
    var $get_class_label_l10n_ok = false;


    function _sanity_check_config()
    {
        if (   !isset($this->_config)
            || !is_a($this->_config, 'midcom_helper_configuration'))
        {
            // 1. option, make sure the component is actually loaded
            midcom::componentloader()->load('midcom.helper.reflector');
        }
        // Then for a second pass
        if (   !isset($this->_config)
            || !is_a($this->_config, 'midcom_helper_configuration'))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('Cannot read configuration, something is SERIOUSLY wrong here', MIDCOM_LOG_ERROR);
            debug_add("\$this->mgdschema_class={$this->mgdschema_class}",  MIDCOM_LOG_INFO);
            debug_print_r('$this->_config', $this->_config);
            debug_pop();
            return false;
        }
        return true;
    }

    /**
     * Constructor, takes classname or object, resolved MgdSchema root class automagically
     *
     * @param string/midgard_object $src classname or object
     */
    function __construct($src)
    {
        $this->_component = 'midcom.helper.reflector';
        parent::__construct();
        // Handle object vs string
        if (is_object($src))
        {
            $this->_original_class = get_class($src);

            // TODO: This should be redundant, it's only used to the the mgdschema_class, which is overwritten later by the resolve_baseclass -method
            if (!isset($src->__mgdschema_class_name__))
            {
                $converted = midcom::dbfactory()->convert_midgard_to_midcom($src);
                if (is_object($converted))
                {
                    $src = $converted;
                    $this->mgdschema_class = $src->__mgdschema_class_name__;
                }
                else
                {
                    debug_push_class(__CLASS__, __FUNCTION__);
                    debug_add("Failed to convert '{$this->_original_class}' to midcom", MIDCOM_LOG_ERROR);
                    debug_print_r('$src', $src);
                    debug_pop();
                }
                unset($converted);
            }
        }
        else
        {
            $this->_original_class = $src;
        }

        // Resolve root class name
        $this->mgdschema_class = midcom_helper_reflector::resolve_baseclass($this->_original_class);

        // Could not resolve root class name
        if (empty($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not determine MgdSchema baseclass for '{$this->_original_class}'", MIDCOM_LOG_ERROR);
            debug_pop();
            $x = false;
            return $x;
        }

        // Instantiate midgard reflector
        if (!class_exists($this->mgdschema_class))
        {
            $x = false;
            return $x;
        }
        $this->_mgd_reflector = new midgard_reflection_property($this->mgdschema_class);
        if (!$this->_mgd_reflector)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not instantiate midgard_mgd_reflection_property for {$this->mgdschema_class}", MIDCOM_LOG_ERROR);
            debug_pop();
            $x = false;
            return $x;
        }

        // Instantiate dummy object
        $this->_dummy_object = new $this->mgdschema_class();
        if (!$this->_dummy_object)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not instantiate dummy object for {$this->mgdschema_class}", MIDCOM_LOG_ERROR);
            debug_pop();
            $x = false;
            return $x;
        }
    }

    function &get($src)
    {
        if (is_object($src))
        {
            $classname = get_class($src);
        }
        else
        {
            $classname = $src;
        }
        if (!isset($GLOBALS['midcom_helper_reflector_singletons'][$classname]))
        {
            $GLOBALS['midcom_helper_reflector_singletons'][$classname] =  new midcom_helper_reflector($src);
        }
        return $GLOBALS['midcom_helper_reflector_singletons'][$classname];
    }

    /**
     * Gets a midcom_helper_l10n instance for component governing the type
     *
     * @return midcom_services__i18n_l10n  Localization library for the reflector object class
     */
    function &get_component_l10n()
    {
        // Use cache if we have it
        if (!isset($GLOBALS['midcom_helper_reflector_get_component_l10n_cache']))
        {
            $GLOBALS['midcom_helper_reflector_get_component_l10n_cache'] = array();
        }
        if (isset($GLOBALS['midcom_helper_reflector_get_component_l10n_cache'][$this->mgdschema_class]))
        {
            return $GLOBALS['midcom_helper_reflector_get_component_l10n_cache'][$this->mgdschema_class];
        }
        $midcom_dba_classname = midcom::dbclassloader()->get_midcom_class_name_for_mgdschema_object($this->_dummy_object);
        if (empty($midcom_dba_classname))
        {
            // Could not resolve MidCOM DBA class name, fallback early to our own l10n
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not get MidCOM DBA classname for type {$this->mgdschema_class}, using our own l10n", MIDCOM_LOG_INFO);
            debug_pop();
            $GLOBALS['midcom_helper_reflector_get_component_l10n_cache'][$this->mgdschema_class] = $this->_l10n;
            return $this->_l10n;
        }

        $component = midcom::dbclassloader()->get_component_for_class($midcom_dba_classname);
        if (!$component)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not resolve component for DBA class {$midcom_dba_classname}, using our own l10n", MIDCOM_LOG_INFO);
            debug_pop();
            $GLOBALS['midcom_helper_reflector_get_component_l10n_cache'][$this->mgdschema_class] = $this->_l10n;
            return $this->_l10n;
        }
        // Got component, try to load the l10n helper for it
        $midcom_i18n = midcom::get_service('i18n');
        $component_l10n = $midcom_i18n->get_l10n($component);
        if (!empty($component_l10n))
        {
            $GLOBALS['midcom_helper_reflector_get_component_l10n_cache'][$this->mgdschema_class] =& $component_l10n;
            return $component_l10n;
        }

        // Could not get anything else, use our own l10n
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add("Everything else failed, using our own l10n for type {$this->mgdschema_class}", MIDCOM_LOG_WARN);
        debug_pop();

        $GLOBALS['midcom_helper_reflector_get_component_l10n_cache'][$this->mgdschema_class] = $this->_l10n;
        return $this->_l10n;
    }

    /**
     * Get the localized label of the class
     *
     * @access public
     * @return string Class label
     * @todo remove any hardcoded class names/prefixes
     */
    public function get_class_label()
    {

        static $component_l10n = false;
        $component_l10n = $this->get_component_l10n();
        $use_classname = $this->mgdschema_class;

        $midcom_dba_classname = midcom::dbclassloader()->get_midcom_class_name_for_mgdschema_object($this->_dummy_object);
        if (!empty($midcom_dba_classname))
        {
            $use_classname = $midcom_dba_classname;
        }

        $use_classname = preg_replace('/_(db|dba)$/', '', $use_classname);

        $this->get_class_label_l10n_ok = true;
        $label = $component_l10n->get($use_classname);
        if ($label == $use_classname)
        {
            // Class string not localized, try Bergies way to pretty-print
            $classname_parts = explode('_', $use_classname);
            if (count($classname_parts) >= 3)
            {
                // Drop first two parts of class name
                array_shift($classname_parts);
                array_shift($classname_parts);
            }
            // FIXME: Remove hardcoded class prefixes
            $use_label = preg_replace('/(openpsa|database|positioning|notifications|statusmessage)_/', '', implode('_', $classname_parts));

            $use_label = str_replace('_',' ',$use_label);
            $label = $component_l10n->get($use_label);
            if ($use_label == $label)
            {
                $this->get_class_label_l10n_ok = false;
                $label = ucwords($use_label);
            }
        }
        debug_pop();
        return $label;
    }

    /**
     * Get property name to use as label
     *
     * @return string name of property to use as label (or false on failure)
     * @todo remove any hardcoded class names/prefixes
     */
    function get_label_property()
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class)
            || !class_exists($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        debug_push_class(__CLASS__, __FUNCTION__);
        $midcom_class = midcom::dbclassloader()->get_midcom_class_name_for_mgdschema_object($this->mgdschema_class);
        if ($midcom_class)
        {
            $obj = new $midcom_class;
        }
        else
        {
            $obj = new $this->mgdschema_class;
        }
        $properties = get_object_vars($obj);

        if (isset($properties['__object']))
        {
            $tmp = get_object_vars($properties['__object']);

            if (!empty($tmp))
            {
                $properties = $tmp;
            }
        }

        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        // TODO: less trivial implementation
        // FIXME: Remove hardcoded class logic
        switch(true)
        {

            case (method_exists($obj,'get_label_property')):
                $property = $obj->get_label_property();
                break;
            // TODO: Switch to use the get_name/title_property helpers below
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_topic')):
                $property = 'extra';
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_person')):
                $property = array
                (
                    'rname',
                    'username',
                    'id',
                );
                break;
            // TODO: Switch to use the get_name/title_property helpers
            case (array_key_exists('title', $properties)):
                $property = 'title';
                break;
            case (array_key_exists('name', $properties)):
                $property = 'name';
                break;
            default:
                $property = 'guid';
        }

        debug_pop();
        return $property;
    }

    /**
     * Get the object label property value
     *
     * @access public
     * @param mixed $object    MgdSchema object
     * @return String       Label of the object
     * @todo remove any hardcoded class names/prefixes
     */
    function get_object_label(&$object)
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        debug_push_class(__CLASS__, __FUNCTION__);

        if (!isset($object->__mgdschema_class_name__))
        {
            // Not a MidCOM DBA object
            $obj = midcom::dbfactory()->convert_midgard_to_midcom($object);
            if ($obj === null)
            {
                debug_pop();
                return false;
            }
        }
        else
        {
            $obj = $object;
        }
        $properties = array_flip($obj->get_properties());
        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        // FIXME: Remove hardcoded class logic
        switch(true)
        {
            case (method_exists($obj, 'get_label')):
                $label = $obj->get_label();
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_person')):
                if ($obj->rname)
                {
                    $label = $obj->rname;
                }
                else
                {
                    $label = $obj->username;
                }
                break;

            case (midcom::dbfactory()->is_a($obj, 'midcom_db_topic')):
                if ($obj->extra)
                {
                    $label = $obj->extra;
                }
                else
                {
                    $label = $obj->name;
                }
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_event')):
            case (midcom::dbfactory()->is_a($obj, 'org_openpsa_calendar_event')):
                if ($obj->start == 0)
                {
                    $label = $obj->title;
                }
                else
                {
                    $label = strftime('%x', $obj->start) . " {$obj->title}";
                }
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_eventmember')):
                $person = new midcom_db_person($obj->uid);
                $event = new midcom_db_event($obj->eid);
                $label = sprintf(midcom::i18n()->get_string('%s in %s', 'midcom'), $person->name, $event->title);
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_member')):
                $person = new midcom_db_person($obj->uid);
                $grp = new midcom_db_group($obj->gid);
                $label = sprintf(midcom::i18n()->get_string('%s in %s', 'midcom'), $person->name, $grp->official);
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_host')):
                if (   $obj->port
                    && $obj->port != '80')
                {
                    $label = "{$obj->name}:{$obj->port}{$obj->prefix}";
                }
                else
                {
                    $label = "{$obj->name}{$obj->prefix}";
                }
                break;
            case (isset($properties['title'])):
                $label = $obj->title;
                break;
            case (isset($properties['name'])):
                $label = $obj->name;
                break;
            default:
                $label = $obj->guid;
        }
        debug_pop();
        return $label;
    }

    /**
     * Get the name of the create icon image
     *
     * @static
     * @access public
     * @param string $type  Name of the type
     * @return string       URL name of the image
     */
    static public function get_create_icon($type)
    {
        static $config = null;
        static $config_icon_map = array();

        // Get the component configuration
        if (is_null($config))
        {
            $config =& $GLOBALS['midcom_component_data']['midcom.helper.reflector']['config'];
        }

        if (empty($config_icon_map))
        {
            $icons2classes = $config->get('create_type_magic');
            //sanity
            if (!is_array($icons2classes))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add('Config key "create_type_magic" is not an array', MIDCOM_LOG_ERROR);
                debug_print_r("\$this->_config->get('create_type_magic')", $icons2classes, MIDCOM_LOG_INFO);
                debug_pop();
                unset($icons2classes);
            }
            else
            {
                foreach ($icons2classes as $icon => $classes)
                {
                    foreach ($classes as $class)
                    {
                        $config_icon_map[$class] = $icon;
                    }
                }
                unset($icons2classes, $classes, $class, $icon);
            }
        }

        $icon_callback = array($type, 'get_create_icon');
        switch (true)
        {
            // class has static method to tell us the answer ? great !
            case (is_callable($icon_callback)):
                $icon = call_user_func($icon_callback);
            // configuration icon
            case (isset($config_icon_map[$type])):
                $icon = $config_icon_map[$type];
                break;

            // heuristics magic (in stead of adding something here, take a look at config key "create_type_magic")
            case (strpos($type, 'member') !== false):
            case (strpos($type, 'organization') !== false):
                $icon = 'stock_people-new.png';
                break;
            case (strpos($type, 'person') !== false):
            case (strpos($type, 'member') !== false):
                $icon = 'stock_person-new.png';
                break;
            case (strpos($type, 'event') !== false):
                $icon = 'stock_event_new.png';
                break;

            // Config default value
            case (isset($config_icon_map['__default__'])):
                $icon = $config_icon_map['__default__'];
                break;
            // Fallback default value
            default:
                $icon = 'new-text.png';
                break;
        }
        return $icon;
    }

    /**
     * Get the name of the icon image
     *
     * @static
     * @access public
     * @param mixed $obj          MgdSchema object
     * @param boolean $url_only   Get only the URL location instead of full <img /> tag
     * @return string             URL name of the image
     */
    static public function get_object_icon(&$obj, $url_only = false)
    {
        static $config = null;
        static $config_icon_map = array();

        // Get the component configuration
        if (is_null($config))
        {
            $config =& $GLOBALS['midcom_component_data']['midcom.helper.reflector']['config'];
        }

        if (empty($config_icon_map))
        {
            $icons2classes = $config->get('object_icon_magic');
            //sanity
            if (!is_array($icons2classes))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add('Config key "object_icon_magic" is not an array', MIDCOM_LOG_ERROR);
                debug_print_r("\$config->get('object_icon_magic')", $icons2classes, MIDCOM_LOG_INFO);
                debug_pop();
                unset($icons2classes);
            }
            else
            {
                foreach ($icons2classes as $icon => $classes)
                {
                    foreach ($classes as $class)
                    {
                        $config_icon_map[$class] = $icon;
                    }
                }
                unset($icons2classes, $classes, $class, $icon);
            }
        }

        $object_class = get_class($obj);
        $object_baseclass = midcom_helper_reflector::resolve_baseclass($obj);
        switch(true)
        {
            // object knows it's icon, how handy!
            case (method_exists($obj,'get_icon')):
                $icon = $obj->get_icon();
                break;

            // configuration icon
            case (isset($config_icon_map[$object_class])):
                $icon = $config_icon_map[$object_class];
                break;
            case (isset($config_icon_map[$object_baseclass])):
                $icon = $config_icon_map[$object_baseclass];
                break;

            // heuristics magic (in stead of adding something here, take a look at config key "object_icon_magic")
            case (strpos($object_class, 'person') !== false):
                $icon = 'stock_person.png';
                break;
            case (strpos($object_class, 'event') !== false):
                $icon='stock_event.png';
                break;
            case (strpos($object_class, 'member') !== false):
            case (strpos($object_class, 'organization') !== false):
                $icon='stock_people.png';
                break;
            // FIXME: Remove hardcoded class logic
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_host')):
                $icon='stock_internet.png';
                break;
            case (midcom::dbfactory()->is_a($obj, 'midcom_db_snippet')):
                $icon='script.png';
                break;
            case (strpos($object_class, 'element') !== false):
                $icon = 'text-x-generic-template.png';
                break;

            // Config default value
            case (isset($config_icon_map['__default__'])):
                $icon = $config_icon_map['__default__'];
                break;
            // Fallback default value
            default:
                $icon = 'document.png';
                break;
        }

        // If the icon name has no slash then it's in stock-icons
        if (strpos($icon, '/') === false)
        {
            $icon_url = MIDCOM_STATIC_URL . "/stock-icons/16x16/{$icon}";
        }
        else
        {
            $icon_url = $icon;
        }
        if ($url_only)
        {
            debug_pop();
            return $icon_url;
        }
        debug_pop();
        return "<img src=\"{$icon_url}\" align=\"absmiddle\" border=\"0\" alt=\"{$object_class}\" /> ";
    }

    /**
     * Get headers to be used with chooser
     *
     * @access public
     * @return array
     * @todo this should not be here but part of the chooser widget
     */
    public function get_result_headers()
    {
        $headers = array();
        $properties = $this->get_search_properties();
        foreach ($properties as $property)
        {
            $headers[] = array
            (
                'name' => $property,
                'title' => ucfirst($this->_l10n->get($property)),
            );
        }
        return $headers;
    }

    /**
     * Get class properties to use as search fields in choosers or other direct DB searches
     *
     * @return array of property names
     */
    public function get_search_properties()
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        // Return cached results if we have them
        static $cache = array();
        if (isset($cache[$this->mgdschema_class]))
        {
            return $cache[$this->mgdschema_class];
        }
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add("Starting analysis for class {$this->mgdschema_class}");
        $obj =& $this->_dummy_object;

        // Get property list and start checking (or abort on error)
        if (midcom::dbclassloader()->is_midcom_db_object($obj))
        {
            $properties = $obj->get_object_vars();
        }
        else
        {
            $properties = array_keys(get_object_vars($obj));
        }
        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        $search_properties = array();

        foreach ($properties as $property)
        {
            switch(true)
            {
                case (strpos($property, 'name') !== false):
                    // property contains 'name'
                    $search_properties[] = $property;
                    break;
                case ($property == 'title'):
                    $search_properties[] = $property;
                    break;
                case ($property == 'tag'):
                    $search_properties[] = $property;
                    break;
                case ($property == 'firstname'):
                    $search_properties[] = $property;
                    break;
                case ($property == 'lastname'):
                    $search_properties[] = $property;
                    break;
                case ($property == 'official'):
                    $search_properties[] = $property;
                    break;
                case ($property == 'username'):
                    $search_properties[] = $property;
                    break;
                // TODO: More per property heuristics
            }
        }
        // TODO: parent and up heuristics

        $label_prop = $this->get_label_property();

        if (    is_string($label_prop)
             && $label_prop != 'guid'
             && property_exists($obj, $label_prop)
             && !in_array($label_prop, $search_properties))
        {
            $search_properties[] = $label_prop;
        }

        // Exceptions - always search these fields
        $this->_sanity_check_config();
        if (is_object($this->_config))
        {
            $always_search_all = $this->_config->get('always_search_fields');
            // safety against misconfiguration
            if (!is_array($always_search_all))
            {
                $always_search_all = array();
            }
        }
        else
        {
            $always_search_all = array();
        }
        $always_search = array();
        if (isset($always_search_all[$this->mgdschema_class]))
        {
            $always_search = $always_search_all[$this->mgdschema_class];
        }
        foreach ($always_search as $property)
        {
            if (!array_key_exists($property, $properties))
            {
                debug_add("Property '{$property}' is set as always search, but is not a property in class '{$this->mgdschema_class}'", MIDCOM_LOG_WARN);
                continue;
            }
            if (in_array($property, $search_properties))
            {
                // Already listed
                debug_add("Property '{$property}', already exists in \$search_properties");
                continue;
            }
            $search_properties[] = $property;
        }

        // Exceptions - never search these fields
        $this->_sanity_check_config();
        if (is_object($this->_config))
        {
            $never_search_all = $this->_config->get('never_search_fields');
            // safety against misconfiguration
            if (!is_array($never_search_all))
            {
                $never_search_all = array();
            }
        }
        else
        {
            $never_search_all = array();
        }
        $never_search = array();
        if (isset($never_search_all[$this->mgdschema_class]))
        {
            $never_search = $never_search_all[$this->mgdschema_class];
        }
        foreach ($never_search as $property)
        {
            if (!in_array($property, $search_properties))
            {
                continue;
            }
            debug_add("Removing '{$property}' from \$search_properties", MIDCOM_LOG_INFO);
            $key = array_search($property, $search_properties);
            if ($key === false)
            {
                debug_add("Cannot find key for '{$property}' in \$search_properties", MIDCOM_LOG_ERROR);
                continue;
            }
            unset($search_properties[$key]);
        }

        debug_print_r("Search properties for {$this->mgdschema_class}: ", $search_properties);
        debug_pop();
        $cache[$this->mgdschema_class] = $search_properties;
        return $search_properties;
    }


    /**
     * Gets a list of link properties and the links target info
     *
     * Link info key specification
     *     'class' string link target class name
     *     'target' string link target property (of target class)
     *     'parent' boolean link is link to "parent" in object tree
     *     'up' boolean link is link to "up" in object tree
     *
     * @return array multidimensional array keyed by property, values are arrays with link info (or false in case of failure)
     */
    public function get_link_properties()
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        // Return cached results if we have them
        static $cache = array();
        if (isset($cache[$this->mgdschema_class]))
        {
            return $cache[$this->mgdschema_class];
        }
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add("Starting analysis for class {$this->mgdschema_class}");

        // Shorthands
        $ref =& $this->_mgd_reflector;
        $obj =& $this->_dummy_object;

        // Get property list and start checking (or abort on error)
        $properties = get_object_vars($obj);
        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        $links = array();
        $parent_property = midgard_object_class::get_property_parent($obj);
        $up_property = midgard_object_class::get_property_up($obj);
        foreach ($properties as $property => $dummy)
        {
            if ($property == 'guid')
            {
                // GUID, even though of type MGD_TYPE_GUID, is never a link
                continue;
            }

            if (   !$ref->is_link($property)
                && $ref->get_midgard_type($property) != MGD_TYPE_GUID)
            {
                continue;
            }
            debug_add("Processing property '{$property}'");
            $linkinfo = array
            (
                'class' => null,
                'target' => null,
                'parent' => false,
                'up' => false,
                'type' => $ref->get_midgard_type($property),
            );
            if (   !empty($parent_property)
                && $parent_property === $property)
            {
                debug_add("Is 'parent' property");
                $linkinfo['parent'] = true;
            }
            if (   !empty($up_property)
                && $up_property === $property)
            {
                debug_add("Is 'up' property");
                $linkinfo['up'] = true;
            }

            $type = $ref->get_link_name($property);
            debug_add("get_link_name returned '{$type}'");
            if (!empty($type))
            {
                $linkinfo['class'] = $type;
            }
            unset($type);

            $target = $ref->get_link_target($property);

            debug_add("get_link_target returned '{$target}'");
            if (!empty($target))
            {
                $linkinfo['target'] = $target;
            }
            elseif ($linkinfo['type'] == MGD_TYPE_GUID)
            {
                $linkinfo['target'] = 'guid';
            }
            unset($target);

            $links[$property] = $linkinfo;
            unset($linkinfo);
        }

        debug_print_r("Links for {$this->mgdschema_class}: ", $links);
        debug_pop();
        $cache[$this->mgdschema_class] = $links;
        return $links;
    }

    /**
     * Statically callable method to map extended classes
     *
     * For example org.openpsa.* components often expand core objects,
     * in config we specify which classes we wish to substitute with which
     *
     * @param string $schema_type classname to check rewriting for
     * @return string new classname (or original in case no rewriting is to be done)
     */
    function class_rewrite($schema_type)
    {
        static $extends = false;
        if ($extends === false)
        {
            if (!isset($GLOBALS['midcom_component_data']['midcom.helper.reflector']['config']))
            {
                midcom::componentloader()->load_graceful('midcom.helper.reflector');
            }
            $extends = $GLOBALS['midcom_component_data']['midcom.helper.reflector']['config']->get('class_extends');
            // Safety against misconfiguration
            if (!is_array($extends))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("config->get('class_extends') did not return array, invalid configuration ??", MIDCOM_LOG_ERROR);
                debug_pop();
                return $schema_type;
            }
        }
        if (   isset($extends[$schema_type])
            && class_exists($extends[$schema_type]))
        {
            return $extends[$schema_type];
        }
        return $schema_type;
    }

    /**
     * Statically callable method to see if two MgdSchema classes are the same
     *
     * NOTE: also takes into account the various extended class scenarios
     *
     * @param string $class_one first class to compare
     * @param string $class_two second class to compare
     * @return boolean response
     */
    function is_same_class($class_one, $class_two)
    {
        $one = midcom_helper_reflector::resolve_baseclass($class_one);
        $two = midcom_helper_reflector::resolve_baseclass($class_two);
        if ($one == $two)
        {
            return true;
        }
        if (midcom_helper_reflector::class_rewrite($one) == $two)
        {
            return true;
        }
        if ($one == midcom_helper_reflector::class_rewrite($two))
        {
            return true;
        }

        return false;
    }

    /**
     * Get an object, deleted or not
     *
     * @static
     * @access public
     * @param string $guid    GUID of the object
     * @param string $type    MgdSchema type
     * @return mixed          MgdSchema object
     */
    static public function get_object($guid, $type)
    {
        static $objects = array();

        if (!isset($objects[$guid]))
        {
            $qb = new midgard_query_builder($type);
            $qb->add_constraint('guid', '=', $guid);
            // We know we want/need only one result
            $qb->set_limit(1);
            $qb->include_deleted();
            $results = $qb->execute();
            if (count($results) == 0)
            {
                $objects[$guid] = null;
            }
            else
            {
                $objects[$guid] = $results[0];
            }
        }

        return $objects[$guid];
    }

    /**
     * Get the MgdSchema classname for given class, statically callable
     *
     * @param mixed $classname either string (class name) or object
     * @return string the base class name
     */
    static public function resolve_baseclass($classname)
    {
        static $cached = array();
        if (   is_string($classname)
            && empty($classname))
        {
            return null;
        }

        if (is_null($classname))
        {
            return null;
        }

        // Quicker cache hit check for string arguments
        if (   is_string($classname)
            && isset($cached[$classname]))
        {
            return $cached[$classname];
        }

        if (is_object($classname))
        {
            $class_instance = $classname;
            $classname = get_class($classname);
        }
        else
        {
            $class_instance = new $classname();
        }

        // Check for cache hit
        if (isset($cached[$classname]))
        {
            return $cached[$classname];
        }

        // Check for decorators first
        $parent_class = false;
        if (   isset($class_instance->__object)
            && is_object($class_instance->__object))
        {
            // Decorated object instance
            $parent_class = get_class($class_instance->__object);
        }
        elseif (   isset($class_instance->__mgdschema_class_name__)
                && !empty($class_instance->__mgdschema_class_name__))
        {
            // Decorator without object
            $parent_class = $object->__mgdschema_class_name__;
        }
        else
        {
            $parent_class = $classname;
        }

        // Then traverse class tree
        do
        {
            $cached[$classname] = $parent_class;
            $parent_class = get_parent_class($cached[$classname]);
            if (   empty($parent_class)
                || $parent_class == 'midgard_object')
            {
                break;
            }
        }
        while ($parent_class !== false);

        // Avoid notice in case things went wrong
        if (isset($cached[$classname]))
        {
            return $cached[$classname];
        }

        return false;
    }

    /**
     * Copy an object. Both source and parent may be liberally filled. Source can be either
     * MgdSchema or MidCOM db object or GUID of the object and parent can be
     *
     * - MgdSchema object
     * - MidCOM db object
     * - predefined target array (@see get_target_properties())
     * - ID or GUID of the object
     * - left empty to copy as a parentless object
     *
     * @static
     * @access public
     * @param mixed $source        GUID or MgdSchema object that will be copied
     * @param mixed $parent        MgdSchema or MidCOM db object or ID of the parent object
     * @param boolean $parameters  Switch to determine if the parameters should be copied
     * @param boolean $metadata    Switch to determine if the metadata should be copied (excluding created and published)
     * @param boolean $attachments Switch to determine if the attachments should be copied (creates only a new link, doesn't duplicate the content)
     * @return mixed               False on failure, newly created MgdSchema object on success
     */
    static public function copy_object($source, $parent, $parameters = true, $metadata = true, $attachments = true)
    {
        $copy = new midcom_helper_reflector_copy();
        $copy->source =& $source;
        $copy->target =& $parent;
        $copy->parameters = $parameters;
        $copy->metadata = $metadata;
        $copy->attachments = $attachments;
        $copy->copy_tree = false;

        if (!$copy->copy())
        {
            return false;
        }

        return $copy->get_object();
    }

    /**
     * Get the target properties and return an array that is used e.g. in copying
     *
     * @static
     * @access public
     * @param mixed $object     MgdSchema object or MidCOM db object
     * @return array            id, parent property, class and label of the object
     */
    static public function get_target_properties($object)
    {
        $mgdschema_class = midcom_helper_reflector::resolve_baseclass($object);
        $mgdschema_object = new $mgdschema_class($object->guid);

        static $targets = array();

        // Return the cached results
        if (isset($targets[$mgdschema_class]))
        {
            return $targets[$mgdschema_class];
        }

        // Empty result set for the current class
        $target = array
        (
            'id' => null,
            'parent' => '',
            'class' => $mgdschema_class,
            'label' => '',
            'reflector' => new midcom_helper_reflector($object),
        );

        // Try to get the parent property for determining, which property should be
        // used to point the parent of the new object. Attachments are a special case.
        if (!midcom::dbfactory()->is_a($object, 'midcom_db_attachment'))
        {
            $parent_property = midgard_object_class::get_property_parent($mgdschema_object);
        }
        else
        {
            $parent_property = 'parentobject';
        }

        // Get the class label
        $target['label'] = $target['reflector']->get_label_property();

        // Try once more to get the parent property, but now try up as a backup
        if (!$parent_property)
        {
            $up_property = midgard_object_class::get_property_up($mgdschema_object);

            if (!$up_property)
            {
                midcom::generate_error(MIDCOM_ERRCRIT, 'Failed to get the parent property for copying');
            }

            $target['parent'] = $up_property;
        }
        else
        {
            $target['parent'] = $parent_property;
        }

        // Cache the results
        $targets[$mgdschema_class] = $target;
        return $targets[$mgdschema_class];
    }

    /**
     * Statically callable method to check for "clean" URL name
     *
     * @see http://trac.midgard-project.org/ticket/809
     * @param $object reference to object to check the name for
     * @param $name_property property to use as "name", if left to default (null), will be reflected
     * @return boolean indicating cleanlines (on missing property or other failure returns false)
     */
    function name_is_clean(&$object, $name_property = null)
    {
        $name_copy = midcom_helper_reflector::get_object_name($object, $name_property);
        if ($name_copy === false)
        {
            //get_object_name failed
            return false;
        }
        if (empty($name_copy))
        {
            // empty name is not "clean"
            return false;
        }
        if ($name_copy !== midcom_generate_urlname_from_string($name_copy))
        {
            unset($name_copy);
            return false;
        }
        unset($name_copy);
        return true;
    }

    /**
     * Statically callable method to check for URL-safe name
     *
     * @see http://trac.midgard-project.org/ticket/809
     * @param $object reference to object to check the name for
     * @param $name_property property to use as "name", if left to default (null), will be reflected
     * @return boolean indicating safety (on missing property or other failure returns false)
     */
    function name_is_safe(&$object, $name_property = null)
    {
        $name_copy = midcom_helper_reflector::get_object_name($object, $name_property);
        if ($name_copy === false)
        {
            //get_object_name failed
            return false;
        }
        if (empty($name_copy))
        {
            // empty name is not url-safe
            return false;
        }
        if ($name_copy !== rawurlencode($name_copy))
        {
            unset($name_copy);
            return false;
        }
        unset($name_copy);
        return true;
    }

    /**
     * Statically callable method to check for URL-safe name, this variant accepts empty name
     *
     * @see http://trac.midgard-project.org/ticket/809
     * @param $object reference to object to check the name for
     * @param $name_property property to use as "name", if left to default (null), will be reflected
     * @return boolean indicating safety (on missing property or other failure returns false)
     */
    function name_is_safe_or_empty(&$object, $name_property = null)
    {
        $name_copy = midcom_helper_reflector::get_object_name($object, $name_property);
        if ($name_copy === false)
        {
            //get_object_name failed
            return false;
        }
        if (empty($name_copy))
        {
            return true;
        }
        return midcom_helper_reflector::name_is_safe($object, $name_property);
    }

    /**
     * Statically callable method to check for "clean" URL name, this variant accepts empty name
     *
     * @see http://trac.midgard-project.org/ticket/809
     * @param $object reference to object to check the name for
     * @param $name_property property to use as "name", if left to default (null), will be reflected
     * @return boolean indicating cleanlines (on missing property or other failure returns false)
     */
    function name_is_clean_or_empty(&$object, $name_property = null)
    {
        $name_copy = midcom_helper_reflector::get_object_name($object, $name_property);
        if ($name_copy === false)
        {
            //get_object_name failed
            return false;
        }
        if (empty($name_copy))
        {
            return true;
        }
        return midcom_helper_reflector::name_is_clean($object, $name_property);
    }

    /**
     * statically callable method to resolve the "name" of given object
     *
     * @see midcom_helper_reflector::name_is_safe()
     * @param $object the object to get the name property for
     * @param $name_property property to use as "name", if left to default (null), will be reflected
     * @return string value of name property or boolean false on failure
     */
    function get_object_name($object, $name_property = null)
    {
        if (is_null($name_property))
        {
            $name_property = midcom_helper_reflector::get_name_property($object);
        }
        if (   empty($name_property)
            || !midcom::dbfactory()->property_exists($object, $name_property))
        {
            // Could not resolve valid property
            return false;
        }
        // Make copy via typecast, very important or we might accidentally manipulate the given object
        $name_copy = (string)$object->{$name_property};
        unset($name_property);
        return $name_copy;
    }

    /**
     * Method to resolve the "name" property of given object
     *
     * @see midcom_helper_reflector::get_name_property()
     * @see midcom_helper_reflector::get_object_name()
     * @param $object the object to get the name property for
     * @return string name of property or boolean false on failure
     * @todo when midgard_reflection_property supports flagging name fields use that in stead of heuristics
     */
    function get_name_property_nonstatic(&$object)
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class)
            || !class_exists($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        // Cache results per class within request
        static $cache = array();
        $key = get_class($object);
        if (isset($cache[$key]))
        {
            return $cache[$key];
        }

        // Configured properties
        if (!$this->_sanity_check_config())
        {
            return false;
        }
        $name_exceptions = $this->_config->get('name_exceptions');
        foreach ($name_exceptions as $class => $property)
        {
            if (midcom::dbfactory()->is_a($object, $class))
            {
                if (   $property !== false
                    && !midcom::dbfactory()->property_exists($object, $property))
                {
                    debug_push_class(__CLASS__, __FUNCTION__);
                    debug_add("Matched class '{$key}' to '{$class}' via is_a but property '{$property}' does not exist", MIDCOM_LOG_ERROR);
                    debug_pop();
                    $cache[$key] = false;
                    return $cache[$key];
                }
                $cache[$key] = $property;
                return $cache[$key];
            }
        }

        // The simple heuristic
        if (midcom::dbfactory()->property_exists($object, 'name'))
        {
            $cache[$key] = 'name';
            return $cache[$key];
        }
        /**
         * Noise, useful when something is going wrong in *weird* way
         *
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add("Could not resolve name property for object " . get_class($object) . " #{$object->id}", MIDCOM_LOG_WARN);
        debug_pop();
        */
        $cache[$key] = false;
        return $cache[$key];
    }

    /**
     * statically callable method to resolve the "name" property of given object
     *
     * @see midcom_helper_reflector::get_name_property_nonstatic()
     * @see midcom_helper_reflector::get_object_name()
     * @param $object the object to get the name property for
     * @return string name of property or boolean false on failure
     */
    function get_name_property(&$object)
    {
        // Cache results per class within request
        static $cache = array();
        $key = get_class($object);
        if (isset($cache[$key]))
        {
            return $cache[$key];
        }
        $resolver =& midcom_helper_reflector::get($object);
        $cache[$key] = $resolver->get_name_property_nonstatic($object);
        return $cache[$key];
    }

    /**
     * statically callable method to resolve the "title" of given object
     *
     * NOTE: This is distinctly different from get_object_label, which will always return something
     * even if it's just the class name and GUID, also it will for some classes include extra info (like datetimes)
     * which we do not want here.
     *
     * @see http://trac.midgard-project.org/ticket/809
     * @param $object the object to get the name property for
     * @param $title_property property to use as "name", if left to default (null), will be reflected
     * @return string value of name property or boolean false on failure
     */
    function get_object_title($object, $title_property = null)
    {
        if (is_null($title_property))
        {
            $title_property = midcom_helper_reflector::get_title_property($object);
        }
        if (   empty($title_property)
            || !midcom::dbfactory()->property_exists($object, $title_property))
        {
            // Could not resolve valid property
            return false;
        }
        // Make copy via typecast, very important or we might accidentally manipulate the given object
        $title_copy = (string)$object->{$title_property};
        unset($title_property);
        return $title_copy;
    }

    /**
     * statically callable method to resolve the "title" property of given object
     *
     * NOTE: This is distinctly different from get_label_property, which will always return something
     * even if it's just the guid
     *
     * @see midcom_helper_reflector::get_object_title()
     * @param $object the object to get the title property for
     * @return string name of property or boolean false on failure
     * @todo when midgard_reflection_property supports flagging name fields use that in stead of heuristics
     */
    function get_title_property(&$object)
    {
        // Cache results per class within request
        static $cache = array();
        $key = get_class($object);
        if (isset($cache[$key]))
        {
            return $cache[$key];
        }
        $resolver =& midcom_helper_reflector::get($object);
        $cache[$key] = $resolver->get_title_property_nonstatic($object);
        return $cache[$key];
    }

    /**
     * Resolve the "title" property of given object
     *
     * NOTE: This is distinctly different from get_label_property, which will always return something
     * even if it's just the guid
     *
     * @see midcom_helper_reflector::get_object_title()
     * @param $object the object to get the title property for
     * @return string name of property or boolean false on failure
     * @todo when midgard_reflection_property supports flagging name fields use that in stead of heuristics
     */
    function get_title_property_nonstatic(&$object)
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class)
            || !class_exists($this->mgdschema_class))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        // Cache results per class within request
        static $cache = array();
        $key = get_class($object);
        if (isset($cache[$key]))
        {
            return $cache[$key];
        }

        // Configured properties
        if (!$this->_sanity_check_config())
        {
            return false;
        }
        $title_exceptions = $this->_config->get('title_exceptions');
        foreach ($title_exceptions as $class => $property)
        {
            if (midcom::dbfactory()->is_a($object, $class))
            {
                if (   $property !== false
                    && !midcom::dbfactory()->property_exists($object, $property))
                {
                    debug_push_class(__CLASS__, __FUNCTION__);
                    debug_add("Matched class '{$key}' to '{$class}' via is_a but property '{$property}' does not exist", MIDCOM_LOG_ERROR);
                    debug_pop();
                    $cache[$key] = false;
                    return $cache[$key];
                }
                $cache[$key] = $property;
                return $cache[$key];
            }
        }

        // The easy check
        if (midcom::dbfactory()->property_exists($object, 'title'))
        {
            $cache[$key] = 'title';
            return $cache[$key];
        }

        /**
         * Noise, useful when something is going wrong in *weird* way
         *
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add("Could not resolve title property for object " . get_class($object) . " #{$object->id}", MIDCOM_LOG_WARN);
        debug_pop();
         */
        $cache[$key] = false;
        return $cache[$key];
    }
}

/**
 * I hope we don't need these workarounds but in case we do, keep them handy
function midcom_helper_reflector_get_property_parent(&$src)
{
    return midgard_object_class::get_property_parent($src);
}

function midcom_helper_reflector_get_property_up(&$src)
{
    return midgard_object_class::get_property_up($src);
}
*/
?>
