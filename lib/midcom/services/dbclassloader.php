<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: dbclassloader.php 26711 2010-10-21 21:11:09Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * <b>How to write database class definitions:</b>
 *
 * The general idea is to provide MidCOM with a way to hook into every database interaction
 * between the component and the Midgard core.
 *
 * Since PHP does not allow for multiple inheritance (which would be really useful here),
 * a decorator pattern is used, which connects the class you actually use in your component
 * and the original MgdSchema class while at the same time routing all function calls through
 * midcom_core_dbaobject.
 *
 * The class loader does not require much information when registering classes:
 * An example declaration looks like this:
 *
 * <code>
 * Array
 * (
 *     'mgdschema_class_name' => 'midgard_article',
 *     'midcom_class_name' => 'midcom_db_article'
 * )
 * </code>
 *
 * As for the parameters:
 *
 * <i>mgdschema_class_name</i> is the MgdSchema class name from that you want to use. This argument
 * is mandatory, and the class specified must exist.
 *
 * <i>midcom_class_name</i> this is the name of the MidCOM base class you intend to create.
 * It is checked for basic validity against the PHP restrictions on symbol naming, but the
 * class itself is not checked for existence. You <i>must</i> declare the class as listed at
 * all times, as typecasting and -detection is done using this metadata property in the core.
 *
 * It is possible to specify more than one class in a single class definition file, and it
 * is recommended that you take advantage of this feature for performance reasons:
 *
 * <code>
 * Array
 * (
 *     //...
 * ),
 * Array
 * (
 *     //...
 * ),
 * </code>
 *
 * Place a simple text file with exactly the declarations into the config directory of your
 * component or shared library.
 *
 * <b>Inherited class requirements</b>
 *
 * The classes you inherit from the intermediate stub classes must at this time satisfy two
 * requirements: The constructor must call the base class constructor and you have to override
 * the get_parent method where applicable:
 *
 * The <i>constructor</i> part is relatively trivial. Consider the article example above, the
 * subclass constructor looks like this:
 *
 * <code>
 * class midcom_db_article
 *     extends midcom_core_dbaobject
 * {
 *     function __construct($id = null)
 *     {
 *         parent::__construct($id);
 *     }
 *
 *     // ...
 * }
 * </code>
 *
 * Be sure to take and pass the $id parameter to the parent class, it will automatically load
 * the object identified by the id <i>or</i> GUID passed.
 *
 * Then there is the (optional) <i>get_parent()</i> method: It is used in various places (for
 * example the ACL system) in MidCOM to find the logical parent of an object. By default this
 * method directly returns null indicating that there is no parent. You should override it
 * wherever you have a tree-like content structure so that MidCOM can correctly climb upwards.
 * If you have a parent only conditionally (e.g. there are root level objects), return NULL to
 * indicate no available parent.
 *
 * For example:
 *
 * <code>
 * class midcom_db_article
 *     extends midcom_core_dbaobject
 * {
 *     // ...
 *
 *     function get_parent()
 *     {
 *         if ($this->up != 0)
 *         {
 *             $parent = new midcom_db_article($this->up);
 *             if (! $parent)
 *             {
 *                 // Handle Error
 *             }
 *         }
 *         else
 *         {
 *             $parent = new midcom_db_topic($this->topic);
 *             if (! $parent)
 *             {
 *                 // Handle Error
 *             }
 *         }
 *         return $parent;
 *     }
 * }
 * </code>
 *
 * As you can see, this is not that hard. The only rule is that you always have to return either
 * null (no parent) or a MidCOM DB type.
 *
 * The recommended way of handling inconsistencies as the ones shown above is to log an error with
 * at least MIDCOM_LOG_INFO and then return null. Depending on your application you could also
 * call generate_error instead, halting execution.
 *
 * @package midcom.services
 */
class midcom_services_dbclassloader extends midcom_baseclasses_core_object
{
    /**
     * Temporary variable during class construction, stores the
     * constructed code.
     *
     * @var string
     * @access private
     */
    var $_class_string = '';

    /**
     * The filename of the class definition currently being read.
     *
     * @var string
     * @access private
     */
    var $_class_definition_filename = '';

    /**
     * Temporary variable during class construction, stores the
     * class definition that is currently processed.
     *
     * @var Array
     * @access private
     */
    var $_class_definition = null;

    /**
     * List of all midgard classes which have been loaded.
     *
     * This list only contains the class definitions that have been used to
     * construct  the actual helper classes.
     *
     * @var Array
     * @access private
     */
    private $_midgard_classes = Array();

    /**
     * A mapping storing which component handles which class.
     *
     * This is used to ensure that all MidCOM DBA main classes are loaded when
     * casting  MgdSchema objects to DBA objects. Especially important for the
     * generic by-GUID object getter.
     *
     * @var Array
     * @access private
     */
    private $_mgdschema_class_handler = Array();

    /**
     * Initializes the class for usage.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * This is the main class loader function. It takes a component/filename pair as
     * arguments, the first specifying the place to look for the latter.
     *
     * For example, if you call load_classes('net.nehmer.static', 'my_classes.inc'), it will
     * look in the directory MIDCOM_ROOT/net/nehmer/static/config/my_classes.inc. The magic
     * component 'midcom' goes for the MIDCOM_ROOT/midcom/config directory and is reserved
     * for MidCOM core classes and compatibility classes.
     *
     * If the class definition file is invalid, false is returned.
     *
     * If this function completes successfully, all __xxx classes are loaded and present.
     *
     * @return boolean Indicating success
     */
    function load_classes($component, $filename, $definition_list = null)
    {
        $this->_create_class_definition_filename($component, $filename);

        if (is_null($definition_list))
        {
            $contents = $this->_read_class_definition_file();
            $result = eval ("\$definition_list = Array ( {$contents} \n );");
            if ($result === false)
            {
                midcom::generate_error(MIDCOM_ERRCRIT,
                                         "Failed to parse the class definition file '{$this->_class_definition_filename}', see above for PHP errors.");
                // This will exit.
            }
        }

        if (!$this->_register_loaded_classes($definition_list, $component))
        {
            midcom::generate_error(MIDCOM_ERRCRIT,
                "DB Class Loader: Classes from file {$this->_class_definition_filename} couldn't be registered.");
            // This will exit.
            return false;
        }

        return true;
    }

    /**
     * This helper function validates a class definition list for correctness.
     *
     * Any error will be logged and false is returned.
     *
     * Where possible, missing elements are completed with sensible defaults.
     *
     * @param Array $definition_list A reference to the definition list to verify.
     * @return boolean Indicating success
     */
    function _validate_class_definition_list(&$definition_list)
    {
        if (! is_array ($definition_list))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('Validation failed: It was no Array.', MIDCOM_LOG_INFO);
            debug_pop();
            return false;
        }

        foreach ($definition_list as $key => $copy)
        {
            // Convenience Reference
            $definition =& $definition_list[$key];

            if (! is_array($definition))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Validation failed: Key {$key} was no array.", MIDCOM_LOG_INFO);
                debug_pop();
                return false;
            }

            // Validate element count upper limit first, lower limits and defaults are caught by
            // The array_key_exists checks below.
            if (count($definition) > 4)
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Validation failed: Key {$key} had too much elements.", MIDCOM_LOG_INFO);
                debug_pop();
                return false;
            }

            if (! array_key_exists('mgdschema_class_name', $definition))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Validation failed: Key {$key} had no mgdschema_class_name element.", MIDCOM_LOG_INFO);
                debug_pop();
                return false;
            }
            if (! class_exists($definition['mgdschema_class_name']))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Validation failed: Key {$key} had an invalid mgdschema_class_name element: {$definition['mgdschema_class_name']}. Probably the required MgdSchema is not loaded.", MIDCOM_LOG_INFO);
                debug_pop();
                return false;
            }

            if (! array_key_exists('midcom_class_name', $definition))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Validation failed: Key {$key} had no midcom_class_name element.", MIDCOM_LOG_INFO);
                debug_pop();
                return false;
            }
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $definition['midcom_class_name']) == 0)
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Validation failed: Key {$key} had an invalid mgdschema_class_name element.", MIDCOM_LOG_INFO);
                debug_pop();
                return false;
            }
        }

        return true;
    }

    /**
     * Little helper which converts a component / filename combination into a fully
     * qualified path/filename.
     *
     * The filename is assigned to the $_class_definition_filename member variable of this class.
     *
     * @param string $component The name of the component for which the class file has to be loaded. The path must
     *     resolve with the component loader unless you use 'midcom' to load MidCOM core class definition files.
     */
    function _create_class_definition_filename($component, $filename)
    {
        if ($component == 'midcom')
        {
            $this->_class_definition_filename = MIDCOM_ROOT . "/midcom/config/{$filename}";
        }
        else
        {
            $this->_class_definition_filename = MIDCOM_ROOT . midcom::componentloader->path_to_snippetpath($component) . "/config/{$filename}";
        }

    }

    /**
     * This helper function loads a class definition file from the disk and
     * returns its contents.
     *
     * The source must be stored in the $_class_definition_filename
     * member.
     *
     * It will translate component and filename into a full path and delivers
     * the contents verbatim.
     *
     * @return string The contents of the file.
     */
    function _read_class_definition_file()
    {
        if (! file_exists($this->_class_definition_filename))
        {
            midcom::generate_error(MIDCOM_ERRCRIT,
                "DB Class Loader: Failed to access the file {$this->_class_definition_filename}: File does not exist.");
            // This will exit.
        }

        return file_get_contents($this->_class_definition_filename);
    }

    /**
     * Simple helper that adds a list of classes to the loaded classes listing.
     *
     * This creates a mapping of which class is handled by which component.
     * The generic by-GUID loader and the class conversion tools in the dbfactory
     * require this information to be able to load the required components on-demand.
     *
     * @param array &$definitions The list of classes which have been loaded along with the meta information.
     * @param string $component The component name of the classes to add
     */
    private function _register_loaded_classes(&$definitions, $component)
    {
        if (! $this->_validate_class_definition_list($definitions))
        {
            return false;
        }

        foreach($definitions as $entry)
        {
            $this->_mgdschema_class_handler[$entry['midcom_class_name']] = $component;

            if (   substr($entry['mgdschema_class_name'], 0, 8) == 'midgard_'
                || substr($entry['mgdschema_class_name'], 0, 12) == 'midcom_core_'
                || $entry['mgdschema_class_name'] == $GLOBALS['midcom_config']['person_class'])
            {
                $this->_midgard_classes[] = $entry;
            }
        }

        return true;
    }

    /**
     * Simple helper to check whether we are dealing with a MgdSchema or MidCOM DBA
     * object or a subclass thereof.
     *
     * @param object $object The object to check
     * @return boolean true if this is a MgdSchema object, false otherwise.
     */
    function is_mgdschema_object($object)
    {
        // Sometimes we might get class string instead of an object
        if (is_string($object))
        {
            $classname = $object;
            $object = new $classname();
        }
        if ($this->is_midcom_db_object($object))
        {
            return true;
        }

        // First, try the quick way
        if (in_array(get_class($object), midcom_connection::get_schema_types()))
        {
            return true;
        }

        // Then, do a thorough scan
        foreach (midcom_connection::get_schema_types() as $mgdschema_class)
        {
            if (is_a($object, $mgdschema_class))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Get component name associated with a class name to get its DBA classes defined
     *
     * @param string $classname Class name to load a component for
     * @return string component name if found for the class, false otherwise
     */
    function get_component_for_class($classname)
    {
        $class_parts = explode('_', $classname);
        $component = '';
        $parts = 0;
        foreach ($class_parts as $part)
        {
            $parts++;
            if (empty($component))
            {
                $component = $part;
            }
            else
            {
                $component .= ".{$part}";
            }

            // Fix for incorrectly named classes
            switch ($component)
            {
                // Handle MidCOM's own classes
                case 'midcom.baseclasses':
                case 'midcom.db':
                case 'midcom.core':
                case 'midgard':
                    return 'midcom';

                case 'net.nehmer.accounts':
                    $component = 'net.nehmer.account';
                    break;
                case 'org.openpsa.campaign':
                case 'org.openpsa.link':
                    $component = 'org.openpsa.directmarketing';
                    break;
                case 'org.openpsa.document':
                    $component = 'org.openpsa.documents';
                    break;
                case 'org.openpsa.organization':
                case 'org.openpsa.person':
                    $component = 'org.openpsa.contacts';
                    break;
                case 'org.openpsa.salesproject':
                    $component = 'org.openpsa.sales';
                    break;
                case 'org.openpsa.event':
                    $component = 'org.openpsa.calendar';
                    break;
                case 'org.openpsa.invoice':
                    $component = 'org.openpsa.invoices';
                    break;
                case 'org.openpsa.query':
                    $component = 'org.openpsa.reports';
                    break;
                case 'org.openpsa.task':
                case 'org.openpsa.expense':
                case 'org.openpsa.hour':
                case 'org.openpsa.deliverable':
                    $component = 'org.openpsa.projects';
                    break;
            }

            if (   !empty($component)
                && midcom::componentloader->is_installed($component))
            {
                return $component;
            }
        }
        return false;
    }

    /**
     * Load a component associated with a class name to get its DBA classes defined
     *
     * @param string $classname Class name to load a component for
     * @return boolean true if a component was found for the class, false otherwise
     */
    function load_component_for_class($classname)
    {
        $component = $this->get_component_for_class($classname);
        if (!$component)
        {
            return false;
        }

        if (midcom::componentloader->is_loaded($component))
        {
            return true;
        }

        return midcom::componentloader->load_graceful($component);
    }

    /**
     * Get a MidCOM DB class name for a MgdSchema Object.
     *
     * @param object &$object The object to check
     * @return string The corresponding MidCOM DB class name, false otherwise.
     */
    function get_midcom_class_name_for_mgdschema_object(&$object)
    {
        static $dba_classes_by_mgdschema = array();

        if (is_string($object))
        {
            // In some cases we get a class name instead
            $classname = $object;
        }
        elseif (is_object($object))
        {
            $classname = get_class($object);
        }
        else
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("{$object} is not an MgdSchema object, not resolving to MidCOM DBA class", MIDCOM_LOG_WARN);
            debug_pop();
            return false;
        }

        if (isset($dba_classes_by_mgdschema[$classname]))
        {
            return $dba_classes_by_mgdschema[$classname];
        }

        if (!$this->is_mgdschema_object($object))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("{$classname} is not an MgdSchema object, not resolving to MidCOM DBA class", MIDCOM_LOG_WARN);
            debug_pop();
            $dba_classes_by_mgdschema[$classname] = false;
            return false;
        }

        $component = false;
        if ($classname == $GLOBALS['midcom_config']['person_class'])
        {
            $component = 'midcom';
        }
        else
        {
            $component = $this->get_component_for_class($classname);
        }

        if (!$component)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Component for class {$classname} cannot be found", MIDCOM_LOG_WARN);
            debug_pop();
            $dba_classes_by_mgdschema[$classname] = false;
            return false;
        }

        if ($component == 'midcom')
        {
            $definitions = $this->get_midgard_classes();
        }
        else
        {
            $definitions = $this->get_component_classes($component);
        }

        foreach ($definitions as $definition)
        {
            if ($classname == $definition['mgdschema_class_name'])
            {
                $dba_classes_by_mgdschema[$classname] = $definition['midcom_class_name'];
                return $dba_classes_by_mgdschema[$classname];
            }
        }

        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add("{$classname} cannot be resolved to any DBA class name", MIDCOM_LOG_DEBUG);
        debug_pop();
        $dba_classes_by_mgdschema[$classname] = false;
        return false;
    }

    /**
     * Get an MgdSchema class name for a MidCOM DBA class name
     *
     * @param string $classname The MidCOM DBA classname to check
     * @return string The corresponding MidCOM DBA class name, false otherwise.
     */
    function get_mgdschema_class_name_for_midcom_class($classname)
    {
        static $mapping = array();

        if (array_key_exists($classname, $mapping))
        {
            return $mapping[$classname];
        }

        $mapping[$classname] = false;

        if (class_exists($classname))
        {
            $dummy_object = new $classname();
            if (!$this->is_midcom_db_object($dummy_object))
            {
                return false;
            }
            $mapping[$classname] = $dummy_object->__mgdschema_class_name__;
        }

        return $mapping[$classname];
    }

    /**
     * This function is required by the DBA interface layer and should normally not be used
     * outside of it.
     *
     * Its purpose is to ensure that the component providing a certain DBA class instance is
     * actually loaded. This is necessary, as the intermediate classes along with the class
     * descriptions are loaded during system startup now, but the full-blown DBA class
     * is not available at that point (for performance reasons). It will load the components
     * in question when requested by any operation in the system that might have to convert
     * to a yet unloaded class, mainly this covers the type conversion of arbitrary objects
     * retrieved by the GUID object getter.
     *
     * @param string $classname The name of the MidCOM DBA class that must be available.
     * @return boolean Indicating success. False is returned only if you are requesting unknown
     *        classes and the like. Component loading failure will result in an HTTP 500, as
     *     always.
     */
    function load_mgdschema_class_handler($classname)
    {
        if (!is_string($classname))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Requested to load the classhandler for class name which is not a string.", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        if (! array_key_exists($classname, $this->_mgdschema_class_handler))
        {
            $component = $this->get_component_for_class($classname);
            midcom::componentloader->load($component);
            if (! array_key_exists($classname, $this->_mgdschema_class_handler))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Requested to load the classhandler for {$classname} which is not known.", MIDCOM_LOG_ERROR);
                debug_pop();
                return false;
            }
        }
        $component = $this->_mgdschema_class_handler[$classname];

        if ($component == 'midcom')
        {
            // This is always loaded.
            return true;
        }

        if (midcom::componentloader->is_loaded($component))
        {
            // Already loaded, so we're fine too.
            return true;
        }

        // This generate_error's on any problems.
        midcom::componentloader->load($component);

        return true;
    }

    /**
     * Simple helper to check whether we are dealing with a MidCOM Database object
     * or a subclass thereof.
     *
     * @param object &$object The object to check
     * @return boolean true if this is a MidCOM Database object, false otherwise.
     */
    function is_midcom_db_object(&$object)
    {
        if (is_object($object))
        {
            return (is_a($object, 'midcom_core_dbaobject') || is_a($object, 'midcom_core_dbaproxy'));
        }
        else if (   is_string($object)
                 && class_exists($object))
        {
            $dummy = new $object();
            return is_a($dummy, 'midcom_core_dbaobject');
        }

        return false;
    }

    function get_component_classes($component)
    {
        $classes = array();

        if ($component == 'midcom')
        {
            $classes = $this->get_midgard_classes();
            return $classes;
        }

        foreach (midcom::componentloader->manifests[$component]->class_definitions as $list)
        {
            $classes = array_merge($classes, $list);
        }

        return $classes;
    }

    function get_midgard_classes()
    {
        return $this->_midgard_classes;
    }
}

?>
