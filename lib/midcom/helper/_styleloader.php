<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: _styleloader.php 26510 2010-07-06 13:42:58Z indeyets $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class is responsible for all style management and replaces
 * the old <[...]> syntax. It is instantiated by the MidCOM framework
 * and accessible through the $midcom->style object.
 *
 * The method show ($style) returns the style element $style for the current
 * component:
 *
 * It checks whether a style path is defined for the current component.
 *
 * - If there is a user defined style path, the element named $style in
 *   this path is returned,
 * - otherwise the element "$style" is taken from the default style of the
 *   current component (/path/to/component/_style/$path).
 *
 * (The default fallback is always the default style, e.g. if $style
 * is not in the user defined style path)
 *
 * To enable cross-style referencing and provide the opportunity to access
 * any style element (not only the style that is set
 * in the current page), "show" can be called with a full qualified style
 * path (like "/mystyle/element1", while the current page's style may be set
 * to "/yourstyle").
 *
 * Note: To make sure sub-styles and elements included in styles are handled
 * correctly, the old style tag <[...]> should not be used anymore,
 * but should be replaced by something like this:
 *
 * <code>
 * <?php midcom_show_style ("elementname"); ?>
 * </code>
 *
 * Style Inheritance
 *
 * The basic path the styleloader follows to find a style element is:
 * 1. Topic style -> if the current topic has a style set
 * 2. Inherited topic style -> if the topic inherits a style from another topic.
 * 3. Site-wide per-component default style -> if defined in MidCOM configuration key styleengine_default_styles
 * 4. Midgard style -> the style of the MidCOM component.
 * 5. The file style. This is usually the elements found in the components style directory.
 *
 * Regarding nr. 4:
 * It is possible to add extra file styles if so is needed for example by a portal component.
 * This is done either using the append/prepend component_style functions or by setting it
 * to another directory by calling (append|prepend)_styledir directly.
 *
 * NB: This cannot happen after the midcom::content() stage in midcom is called,
 * i.e. you cannot change this in another style element or in a _show() function in a component.
 *
 * @todo Document Style Inheritance
 *
 * @package midcom
 */
class midcom_helper__styleloader
{
    /**
     * Current style scope
     *
     * @var array
     * @access private
     */
    var $_scope;

    /**
     * Current topic
     *
     * @var midcom_db_topic
     * @access private
     */
    var $_topic;

    /**
     * Default style path
     *
     * @var string
     * @access private
     */
    var $_snippetdir;

    /**
     * Path to file styles.
     * @var array
     */
    var $_filedirs = array();

    /**
     * Current context
     *
     * @var id
     * @access private
     */
    var $_context;

    /**
     * Style element cache
     *
     * @var array
     * @access private
     */
    var $_styles;

    /**
     * Default style element cache
     *
     * @todo Is this still in use?
     * @var array
     * @access private
     */
    var $_snippets;

    /**
     * List of styledirs to handle after componentstyle
     * @var array
     * @access private
     */
    var $_styledirs_append = array();

    /**
     * List of styledirs to handle before componentstyle
     * @var array
     * @access private
     */
    var $_styledirs_prepend = array();

    /**
     * The stack of directories to check for styles.
     */
    var $_styledirs = array();

    /**
     * The actual Midgard style object
     */
    var $object = null;

    /**
     * Simple initialization
     */
    function __construct()
    {
        $this->_context = array ();
        $this->_scope = array ();
        $this->_topic = false;
        $this->_styles = array ();
        $this->_snippets = array ();
    }

    /**
     * Returns the path of the style described by $id.
     *
     * @param int $id    Style id to look up path for
     * @return    string Style path
     * @access public
     */
    function get_style_path_from_id($id)
    {
        static $path_cache = array();
        if (!isset($path_cache[$id]))
        {
            // Construct the path
            $path_parts = array();
            $original_id = $id;

            while (($style = new midcom_db_style($id)))
            {
                if (!$style->guid)
                {
                    break;
                }

                $path_parts[] = $style->name;
                $id = $style->up;

                if ($style->up == 0)
                {
                    // Toplevel style
                    break;
                }

                if (   $GLOBALS['midcom_config']['styleengine_relative_paths']
                    && $style->up == $_MIDGARD['style'])
                {
                    // Relative path, stop before going to main Midgard style
                    break;
                }
            }

            $path_parts = array_reverse($path_parts);

            $path_cache[$original_id] = '/' . implode('/', $path_parts);
        }

        return $path_cache[$original_id];
    }

    /**
     * Returns the id of the style described by $path.
     *
     * Note: $path already includes the element name, so $path looks like
     * "/rootstyle/style/style/element".
     *
     * @todo complete documentation
     * @param string $path        The path to retrieve
     * @param int $rootstyle_id    ???
     * @return    int ID of the matching style or FALSE
     * @access public
     */
    function get_style_id_from_path($path, $rootstyle = 0)
    {
        static $cached = array();

        if (   $GLOBALS['midcom_config']['styleengine_relative_paths']
            && $rootstyle == 0)
        {
            // Relative paths in use, start seeking from under the style used for the Midgard host
            $rootstyle = $_MIDGARD['style'];
        }

        if (!isset($cached[$rootstyle]))
        {
            $cached[$rootstyle] = array();
        }
        if (array_key_exists($path, $cached[$rootstyle]))
        {
            return $cached[$rootstyle][$path];
        }

        $path = preg_replace("/^\/(.*)/", "$1", $path); // leading "/"
        $path_array = explode('/', $path);

        $current_style = $rootstyle;

        if (count($path_array) == 0)
        {
            $cached[$rootstyle][$path] = false;
            return false;
        }

        foreach ($path_array as $path_item)
        {
            if ($path_item == '')
            {
                // Skip
                continue;
            }

            $mc = midgard_style::new_collector('up', $current_style);
            $mc->set_key_property('guid');
            $mc->add_value_property('id');
            $mc->add_constraint('name', '=', $path_item);
            $mc->execute();
            $styles = $mc->list_keys();

            if (!$styles)
            {
                $cached[$rootstyle][$path] = false;
                return false;
            }

            foreach ($styles as $style_guid => $value)
            {
                $current_style = $mc->get_subkey($style_guid, 'id');
                midcom::cache()->content->register($style_guid);
            }
        }

        if ($current_style != 0)
        {
            $cached[$rootstyle][$path] = $current_style;
            return $current_style;
        }

        $cached[$rootstyle][$path] = false;
        return false;
    }

    function _get_nodes_inheriting_style($node)
    {
        $nodes = array();
        $child_qb = midcom_db_topic::new_query_builder();
        $child_qb->add_constraint('up', '=', $node->id);
        $child_qb->add_constraint('style', '=', '');
        $children = $child_qb->execute();

        foreach ($children as $child_node)
        {
            $nodes[] = $child_node;
            $subnodes = $this->_get_nodes_inheriting_style($child_node);
            $nodes = array_merge($nodes, $subnodes);
        }

        return $nodes;
    }

    /**
     * Get list of topics using a particular style
     *
     * @param string $style Style path
     * @return array List of folders
     */
    function get_nodes_using_style($style)
    {
        $style_nodes = array();
        // Get topics directly using the style
        $qb = midcom_db_topic::new_query_builder();
        $qb->add_constraint('style', '=', $style);
        $nodes = $qb->execute();

        foreach ($nodes as $node)
        {
            $style_nodes[] = $node;

            if ($node->styleInherit)
            {
                $child_nodes = $this->_get_nodes_inheriting_style($node);
                $style_nodes = array_merge($style_nodes, $child_nodes);
            }
        }

        return $style_nodes;
    }

    /**
     * List the default template elements shipped with a component
     * @param string $component Component to look elements for
     * @return array List of elements found indexed by the element name
     */
    function get_component_default_elements($component)
    {
        $elements = array();

        // Path to the file system
        $path = MIDCOM_ROOT . '/' . str_replace('.', '/', $component) . '/style';

        if (!is_dir($path))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Directory {$path} not found.");
            debug_pop();
            return $elements;
        }

        $directory = dir($path);

        if (!$directory)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Failed to read directory {$path}");
            debug_pop();
            return $elements;
        }

        while (($file = $directory->read()) !== false)
        {
            if (!preg_match('/\.php$/i', $file))
            {
                continue;
            }

            $elements[str_replace('.php', '', $file)] = "{$path}/{$file}";
        }

        $directory->close();

        return $elements;
    }

    /**
     * Returns a style element that matches $name and is in style $id.
     * Unlike mgd_get_element_by_name2 it also returns an element if it is not in
     * the given style, but in one of its parent styles.
     *
     * @param int $id        The style id to search in.
     * @param string $name    The element to locate.
     * @return string    Value of the found element, or false on failure.
     * @access private
     */
    function _get_element_in_styletree($id, $name)
    {
        static $cached = array();
        if (!isset($cached[$id]))
        {
            $cached[$id] = array();
        }
        if (array_key_exists($name, $cached[$id]))
        {
            return $cached[$id][$name];
        }

        $element_mc = midgard_element::new_collector('style', $id);
        $element_mc->set_key_property('guid');
        $element_mc->add_value_property('value');
        $element_mc->add_constraint('name', '=', $name);
        $element_mc->execute();
        $elements = $element_mc->list_keys();
        if ($elements)
        {
            foreach ($elements as $element_guid => $value)
            {
                $value = $element_mc->get_subkey($element_guid, 'value');
                midcom::cache()->content->register($element_guid);
                $cached[$id][$name] = $value;
                if ($GLOBALS['midcom_debugger']->firephp)
                {
                    $GLOBALS['midcom_debugger']->firephp->log("Loading template '{$name}' from style #{$id}");
                }
                return $value;
            }
        }

        // No such element on this level, check parents
        $style_mc = midgard_style::new_collector('id', $id);
        $style_mc->set_key_property('guid');
        $style_mc->add_value_property('up');
        $style_mc->execute();
        $styles = $style_mc->list_keys();

        foreach ($styles as $style_guid => $value)
        {
            // FIXME: Should we register this also in the other case
            midcom::cache()->content->register($style_guid);

            $up = $style_mc->get_subkey($style_guid, 'up');
            if (   $up
                && $up != 0)
            {
                $value = $this->_get_element_in_styletree($up, $name);
                $cached[$id][$name] = $value;
                return $value;
            }
        }

        $cached[$id][$name] = false;
        return $cached[$id][$name];
    }

    function get_style_elements_and_nodes($style)
    {
        $results = array
        (
            'elements' => array(),
            'nodes' => array(),
        );

        $style_id = $this->get_style_id_from_path($style);
        if (!$style_id)
        {
            return $results;
        }

        $style_nodes = midcom::style()->get_nodes_using_style($style);

        foreach ($style_nodes as $node)
        {
            if (!isset($results['nodes'][$node->component]))
            {
                $results['nodes'][$node->component] = array();
            }

            $results['nodes'][$node->component][] = $node;
        }

        foreach ($results['nodes'] as $component => $nodes)
        {
            // Get the list of style elements for the component
            $results['elements'][$component] = midcom::style()->get_component_default_elements($component);

            // Arrange elements in alphabetical order
            ksort($results['elements'][$component]);
        }

        $results['elements']['midcom'] = array
        (
            'style-init' => '',
            'style-finish' => '',
        );

        if ($style_id == $_MIDGARD['style'])
        {
            // We're in site main style, append elements from there to the list of "common elements"
            $mc = midcom_db_element::new_collector('style', $_MIDGARD['style']);
            $mc->add_value_property('name');
            $mc->execute();
            $element_guids = $mc->list_keys();
            foreach ($element_guids as $guid => $array)
            {
                $results['elements']['midcom'][$mc->get_subkey($guid, 'name')] = '';
            }

            if (!isset($results['elements']['midcom']['ROOT']))
            {
                // There should always be the ROOT element available
                $results['elements']['midcom']['ROOT'] = '';
            }
        }

        return $results;
    }

    /**
     * Looks for a style element matching $path (either in a user defined style
     * or the default style snippetdir) and displays/evaluates it.
     *
     * @param string $path    The style element to show.
     * @param array $guids  List of GUIDs included in this element display, if set will enable caching
     * @return boolean            True on success, false otherwise.
     */
    function show($path, $guids = null)
    {
        if ($this->_context === array())
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Trying to show '{$path}' but there is no context set", MIDCOM_LOG_INFO);
            debug_pop();
            return false;
        }

        $_element = $path;

        // we have full qualified path to element

        if (preg_match("|(.*)/(.*)|", $path, $matches))
        {
            $_stylepath = $matches[1];
            $_element = $matches[2];
        }

        if (   isset ($_stylepath)
            && $_styleid = $this->get_style_id_from_path($_stylepath))
        {
            array_unshift($this->_scope, $_styleid);
        }

        $_style = null;

        // try to find element in current / given scope
        if (count($this->_scope) > 0)
        {
            $src = "{$this->_scope[0]}/{$_element}";
            if (array_key_exists($src, $this->_styles))
            {
                $_style = $this->_styles[$src];
            }
            else if ($this->_scope[0] != '')
            {
                if ($_result = $this->_get_element_in_styletree($this->_scope[0], $_element))
                {
                    $this->_styles[$src] = $_result;
                    $_style = $this->_styles[$src];
                }
            }
        }

        // fallback: try to get element from default style snippet
        if (! isset($_style))
        {
            $src = "{$this->_snippetdir}/{$_element}";
            if (   $GLOBALS['midcom_debugger']->firephp
                && !_midcom_headers_sent())
            {
                $GLOBALS['midcom_debugger']->firephp->log("Loading template '{$_element}' from {$this->_snippetdir}");
            }
            if (array_key_exists($src, $this->_snippets))
            {
                $_style = $this->_snippets[$src];
            }
            else
            {
                if (array_key_exists('theme', $GLOBALS['midcom_config']))
                {
                    $filename = preg_replace('/lib$/', 'themes', MIDCOM_ROOT) . '/' . $GLOBALS['midcom_config']['theme'] .  "/style/{$_element}.php";
                    if (file_exists($filename))
                    {
                        $_style = file_get_contents($filename);
                        $src = $filename;
                        $this->_snippets[$src] = $_style;
                    }
                }

                if (!isset($_style))
                {
                    for ($i = 0; ! isset($_style) && $i < $this->_styledirs_count[midcom::get_current_context()]; $i++)
                    {
                        $filename = MIDCOM_ROOT . $this->_styledirs[midcom::get_current_context()][$i] .  "/{$_element}.php";
                        if (file_exists($filename))
                        {
                            $_style = file_get_contents($filename);
                            $src = $filename;
                            $this->_snippets[$src] = $_style;
                        }
                    }
                }
            }
        }

        if (isset($_style))
        {
            // This is a bit of a hack to allow &(); tags
            $data =& midcom::get_custom_context_data('request_data');
            $instance_id = false;

            if (   $guids
                && in_array('style', $GLOBALS['midcom_config']['cache_module_memcache_data_groups']))
            {
                // Cache style elements
                $instance_id = $path . '-' . md5(serialize($guids));

                if (midcom::cache()->memcache->exists('style', $instance_id))
                {
                    echo midcom::cache()->memcache->get('style', $instance_id);
                }
            }

            if ($GLOBALS['midcom_config']['wrap_style_show_with_name'])
            {
                $_style = "\n<!-- Start of style '{$path}' -->\n" . $_style;
                $_style .= "\n<!-- End of style '{$path}' -->\n";
            }

            if ($instance_id)
            {
                // This element will be cached after display
                ob_start();
                $result = eval('?>' . mgd_preparse($_style));
                $contents = ob_get_contents();
                midcom::cache()->memcache->put('style', $instance_id, $result);
                ob_end_flush();
            }
            else
            {
                $result = eval('?>' . mgd_preparse($_style));
            }

            if ($result === false)
            {
                // Note that src detection will be semi-reliable, as it depends on all errors being
                // found before caching kicks in.
                midcom::generate_error(MIDCOM_ERRCRIT,
                    "Failed to parse style element '{$path}', content was loaded from '{$src}', see above for PHP errors.");
                // This will exit.
            }
        }
        else
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("The element '{$path}' could not be found.", MIDCOM_LOG_INFO);
            debug_pop();
            return false;
        }

        if (isset($_stylepath))
        {
            array_shift($this->_scope);
        }

        return true;
    }

    /**
     * Gets the component style.
     *
     * @todo Document
     *
     * @param midcom_db_topic $topic    Current topic
     * @return int Database ID if the style to use in current view or FALSE
     */
    function _getComponentStyle($topic)
    {

        // get user defined style for component
        // style inheritance
        // should this be cached somehow?
        if ($topic->style)
        {
            $_st = $this->get_style_id_from_path($topic->style);
        }
        elseif (   isset($GLOBALS['midcom_style_inherited'])
                 && ($GLOBALS['midcom_style_inherited']))
        {
            // FIXME: This GLOBALS is set by urlparser. Should be removed
            // get user defined style inherited from topic tree
            $_st = $this->get_style_id_from_path($GLOBALS['midcom_style_inherited']);
        }
        else
        {
            // Get style from sitewide per-component defaults.
            $component = $topic->component;
            if (array_key_exists($component, $GLOBALS['midcom_config']['styleengine_default_styles']))
            {
                $_st = $this->get_style_id_from_path($GLOBALS['midcom_config']['styleengine_default_styles'][$component]);
            }
            elseif ($GLOBALS['midcom_config']['styleengine_relative_paths'])
            {
                $_st = $_MIDGARD['style'];
            }
        }

        if (isset($_st))
        {
            $substyle = midcom::get_context_data(MIDCOM_CONTEXT_SUBSTYLE);

            if (   isset($substyle)
                && is_string($substyle))
            {
                $chain = explode('/', $substyle);
                foreach ($chain as $stylename)
                {
                    $_subst_id = $this->get_style_id_from_path($stylename, $_st);
                    if ($_subst_id)
                    {
                        $_st = $_subst_id;
                    }
                }
            }
        }

        if (isset($_st))
        {
            return $_st;
        }

        return false;
    }


    /**
     * Gets the component styledir associated with the topics
     * component.
     *
     * @param MidgardTopic $topic the current component topic.
     * @return mixed the path to the components style directory.
     */
    function _getComponentSnippetdir($topic)
    {
        // get component's snippetdir (for default styles)
        $loader = midcom::get_component_loader();
        if (   !$topic
            || !$topic->guid)
        {
            return null;
        }
        return $loader->path_to_snippetpath($topic->component) . "/style";
    }

    /**
     * Function append styledir
     *
     * Adds an extra style directory to check for style elements at
     * the end of the styledir queue.
     *
     * @param dirname path of style directory within midcom.
     * @return boolean true if directory appended
     * @throws midcom exception if directory does not exist.
     */
    function append_styledir ($dirname)
    {
        if (!file_exists(MIDCOM_ROOT . $dirname)) {
            midcom::generate_error(MIDCOM_ERRCRIT, "Style directory $dirname does not exist!");
        }
        $this->_styledirs_append[midcom::get_current_context()][] = $dirname;
        return true;
    }

    /**
     * append the styledir of a component to the queue of styledirs.
     *
     * @param string componentname
     * @return void
     * @throws midcom exception if directory does not exist.
     */
    function append_component_styledir ($component)
    {
        $loader = midcom::get_component_loader();
        $path = $loader->path_to_snippetpath($component ) . "/style";
        $this->append_styledir($path);
        return;
    }
    /**
     * prepend the styledir of a component
     * @param string componentname
     * @return void
     * @throws midcom exception if directory does not exist.
     */
    function prepend_component_styledir ($component) {
        $loader = midcom::get_component_loader();
        $path = $loader->path_to_snippetpath($component ) . "/style";
        $this->prepend_styledir($path);
        return;
    }

    /**
     * Function prepend styledir
     * @param dirname path of styledirectory within midcom.
     * @return boolean true if directory appended
     * @throws midcom exception if directory does not exist.
     */
    function prepend_styledir ($dirname)
    {
        if (!file_exists(MIDCOM_ROOT . $dirname))
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Style directory {$dirname} does not exist.");
        }
        $this->_styledirs_prepend[midcom::get_current_context()][] = $dirname;
        return true;
    }

    /**
     * This function merges the prepend and append styles with the
     * componentstyle. This happens when the enter_context function is called.
     * You cannot change the style call stack after that (unless you call enter_context again of course).
     * @param string component style
     * @return void
     */
    function _merge_styledirs ($component_style)
    {
        /* first the prepend styles */
        $this->_styledirs[midcom::get_current_context()] = $this->_styledirs_prepend[midcom::get_current_context()];
        /* then the contextstyle */
        $this->_styledirs[midcom::get_current_context()][count($this->_styledirs[midcom::get_current_context()])] = $component_style;

        $this->_styledirs[midcom::get_current_context()] =  array_merge($this->_styledirs[midcom::get_current_context()], $this->_styledirs_append[midcom::get_current_context()]);
        $this->_styledirs_count[midcom::get_current_context()] = count($this->_styledirs[midcom::get_current_context()]);
    }

    /**
     * Switches the context (see dynamic load). Private variables $_context, $_topic
     * and $_snippetdir are adjusted.
     *
     * @todo check documentation
     * @param int $context    The context to enter
     * @return boolean            True on success, false on failure.
     */
    function enter_context($context)
    {
        // set new context and topic
        array_unshift($this->_context, $context); // push into context stack

        $this->_topic = midcom::get_context_data(MIDCOM_CONTEXT_CONTENTTOPIC);

        // Prepare styledir stacks
        if (!isset($this->_styledirs[$context]))
        {
            $this->_styledirs[$context] = array();
        }
        if (!isset($this->_styledirs_prepend[$context]))
        {
            $this->_styledirs_prepend[$context] = array();
        }
        if (!isset($this->_styledirs_append[$context]))
        {
            $this->_styledirs_append[$context] = array();
        }
        if (!isset($this->_styledirs_count[$context]))
        {
            $this->_styledirs_count[$context] = 0;
        }

        $_st = $this->_getComponentStyle($this->_topic);
        if (isset($_st))
        {
            array_unshift($this->_scope, $_st);
        }

        $this->_snippetdir = $this->_getComponentSnippetdir($this->_topic);

        $this->_merge_styledirs($this->_snippetdir);
        return true;
    }

    /**
     * Switches the context (see dynamic load). Private variables $_context, $_topic
     * and $_snippetdir are adjusted.
     *
     * @todo check documentation
     * @return boolean            True on success, false on failure.
     */
    function leave_context()
    {
        /* does this cause an extra, not needed call to ->parameter ? */
        $_st = $this->_getComponentStyle($this->_topic);
        if (isset($_st))
        {
            array_shift($this->_scope);
        }

        array_shift($this->_context);

        // get our topic again
        // FIXME: does this have to be above _getComponentStyle($this->_topic) ??
        $this->_topic = midcom::get_context_data(MIDCOM_CONTEXT_CONTENTTOPIC);

        $this->_snippetdir = $this->_getComponentSnippetdir($this->_topic);
        return true;
    }

    function get_style()
    {
        if (is_null($this->object))
        {
            $this->object = new midcom_db_style($_MIDGARD['style']);
        }
        return $this->object;
    }

    /**
     * Include all text/css attachments of current style to MidCOM headers
     */
    function add_database_head_elements()
    {
        static $called = false;
        if ($called)
        {
            return;
        }
        $style = $this->get_style();
        $mc = midcom_db_attachment::new_collector('parentguid', $style->guid);
        $mc->add_constraint('mimetype', '=', 'text/css');
        $mc->add_value_property('name');
        $mc->execute();
        $attachments = $mc->list_keys();

        foreach ($attachments as $guid => $values)
        {
            // TODO: Support media types
            $filename = $mc->get_subkey($guid, 'name');
            midcom::add_link_head
            (
                array
                (
                    'rel' => 'stylesheet',
                    'type' => 'text/css',
                    'href' => midcom_connection::get_url('self') . "midcom-serveattachment-{$guid}/{$filename}",
                )
            );
        }

        $called = true;
    }
}

/**
 * Global shortcut.
 *
 * @see midcom_helper__styleloader::show()
 */
function midcom_show_style($param, $guids = null)
{
    return midcom::style()->show($param, $guids);
}

?>
