<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: _basicnav.php 26601 2010-08-12 15:45:59Z jval $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class is the basic building stone of the Navigation Access Point
 * System of MidCOM.
 *
 * It is responsible for collecting the available
 * information and for building the navigational tree out of it. This
 * class is only the internal interface to the NAP System and is used by
 * midcom_helper_nav as a node cache. The framework should ensure that
 * only one class of this type is active at one time.
 *
 * Basicnav will give you a very abstract view of the content tree, modified
 * by the NAP classes of the components. You can retrieve a node/leaf tree
 * of the content, and for each element you can retrieve a URL name and a
 * long name for navigation display.
 *
 * Leaves and Nodes are both indexed by integer constants which are assigned
 * by the framework. The framework defines two starting points in this tree:
 * The root node and the "current" node. The current node defined through
 * the topic of the component that declared to be able to handle the request.
 *
 * The class will load the necessary information on demand to minimize
 * database traffic.
 *
 * The interface functions should enable you to build any navigation tree you
 * desire. The public nav class will give you some of those high-level
 * functions.
 *
 * <b>Node data interchange format</b>
 *
 * Node NAP data consists of a simple key => value array with the following
 * keys required by the component:
 *
 * - MIDCOM_NAV_NAME => The real (= displayable) name of the element
 *
 * Other keys delivered to NAP users include:
 *
 * - MIDCOM_NAV_URL  => The URL name of the element, which is automatically
 *   defined by NAP.
 *
 * <b>Leaf data interchange format</b>
 *
 * Basically for each leaf the usual meta information is returned:
 *
 * - MIDCOM_NAV_URL      => URL of the leaf element
 * - MIDCOM_NAV_NAME     => Name of the leaf element
 * - MIDCOM_NAV_GUID     => Optional argument denoting the GUID of the referred element
 * - MIDCOM_NAV_SORTABLE => Optional argument denoting whether the element is sortable
 *
 * The Datamanager will automatically transform (3) to the syntax described in
 * (1) by copying the values.
 *
 * @package midcom
 */
class midcom_helper__basicnav
{
    /**#@+
     * NAP data variable.
     *
     * @access private
     */

    /**
     * The GUID of the MidCOM Root Content Topic
     *
     * @var int
     */
    private $_root;

    /**
     * The GUID of the currently active Navigation Node, determined by the active
     * MidCOM Topic or one of its uplinks, if the subtree in question is invisible.
     *
     * @var int
     */
    private $_current;

    /**
     * The GUID of the currently active leaf.
     *
     * @var string
     */
    private $_currentleaf;

    /**
     * This is the leaf cache. It is an array which contains elements indexed by
     * their leaf ID. The data is again stored in an associative array:
     *
     * - MIDCOM_NAV_NODEID => ID of the parent node (int)
     * - MIDCOM_NAV_URL => URL name of the leaf (string)
     * - MIDCOM_NAV_NAME => Textual name of the leaf (string)
     *
     * @todo Update the data structure documentation
     * @var Array
     */
    private $_leaves;

    /**
     * This is the node cache. It is an array which contains elements indexed by
     * their node ID. The data is again stored in an associative array:
     *
     * - MIDCOM_NAV_NODEID => ID of the parent node (-1 for the root node) (int)
     * - MIDCOM_NAV_URL => URL name of the leaf (string)
     * - MIDCOM_NAV_NAME => Textual name of the leaf (string)
     *
     * @todo Update the data structure documentation
     * @var Array
     */
    private static $_nodes;

    /**
     * This map tracks all loaded GUIDs along with their NAP structures. This cache
     * is used by nav's resolve_guid function to short-circut already known GUIDs.
     *
     * @var Array
     */
    private $_guid_map = Array();

    /**
     * This array holds a list of all topics for which the leaves have been loaded.
     * If the id of the node is in this array, the leaves are available, otherwise,
     * the leaves have to be loaded.
     *
     * @var Array
     */
    private $_loaded_leaves = Array();

    /**#@-*/

    /**#@+
     * Internal runtime state variable.
     *
     * @access private
     */

    /**
     * This is a reference to the systemwide component loader class.
     *
     * @var midcom_helper__componentloader
     */
    private $_loader;

    /**
     * This is a temporary storage where _loadNode can return the last known good
     * node in case the current node not visible. It is evaluated by the
     * constructor.
     *
     * @var int
     */
    private $_lastgoodnode;

    /**
     * A reference to the NAP cache store
     *
     * @var midcom_services_cache_backend
     */
    private $_nap_cache = null;

    /**
     * This array holds the node path from the URL. First value at key 0 is
     * the root node ID, possible second value is the first subnode ID etc.
     * Contains only visible nodes (nodes which can be loaded).
     *
     * @var Array
     */
    private $_node_path = Array();

    /**
     * This private helper holds the user id for ACL checks. This is set when instantiating
     * to avoid unnecessary overhead
     *
     * @var string
     * @access private
     */
    private $_user_id = false;

    /**#@-*/


    /**
     * Constructor
     *
     * The only constructor of the Basicnav class. It will initialize Root-Topic,
     * Current-Topic and all cache arrays. The function will load all nodes
     * between root and current node.
     *
     * If the current node is behind an invisible or undescendable node, the last
     * known good node will be used instead for the current node.
     *
     * The constructor retrieves all initialization data from the component context.
     * A special process is used, if the context in question is of the type
     * MIDCOM_REQUEST_CONTENTADM: The system then goes into Administration Mode,
     * querying the components for the administrative data instead of their regular
     * data. In addition, the root topic is set to the administrated topic instead
     * of the regular root topic. This way you can build up Admin Interface
     * Navigation for "external" trees.
     *
     * @param int $context    The Context ID for which to create NAP data for, defaults to 0
     */
    function __construct($context = 0)
    {
        $tmp = midcom::get_context_data($context, MIDCOM_CONTEXT_ROOTTOPIC);
        $this->_root = $tmp->id;

        $this->_nap_cache = midcom::cache()->nap;

        $this->_leaves = array();

        $this->_loader = midcom::get_component_loader();

        $this->_currentleaf = false;

        $this->_lastgoodnode = -1;

        if (!midcom::auth()->admin)
        {
            $this->_user_id = midcom::auth()->acl->get_user_id();
        }

        $up = null;
        $node_path_candidates = array($this->_root);
        $this->_current = $this->_root;
        foreach (midcom::get_context_data($context, MIDCOM_CONTEXT_URLTOPICS) as $topic)
        {
            $id = $this->_nodeid($topic->id, $up);
            $node_path_candidates[] = $id;
            $this->_current = $id;
            if ($up || !empty($topic->symlink))
            {
                $up = $id;
            }
        }

        $root_set = false;

        foreach ($node_path_candidates as $node_id)
        {
            switch ($this->_loadNode($node_id))
            {
                case MIDCOM_ERROK:
                    if (!$root_set)
                    {
                        // Reset the Root node's URL Parameter to an empty string.
                        self::$_nodes[$this->_root][MIDCOM_NAV_URL] = '';
                        $root_set = true;
                    }
                    $this->_node_path[] = $node_id;
                    $this->_lastgoodnode = $node_id;
                    break;

                case MIDCOM_ERRFORBIDDEN:
                    // Node is hidden behind an undescendable one, activate the last known good node as current
                    $this->_current = $this->_lastgoodnode;
                    break;

                default:
                    debug_push_class(__CLASS__, __FUNCTION__);
                    debug_add("_loadNode failed, see above error for details.", MIDCOM_LOG_ERROR);
                    debug_pop();
                    return false;
            }
        }
    }


    /**
     * This function is the controlling instance of the loading mechanism. It
     * is able to load the navigation data of any topic within MidCOM's topic
     * tree into memory. Any uplink nodes that are not loaded into memory will
     * be loaded until any other known topic is encountered. After the
     * necessary data has been loaded with calls to _loadNodeData.
     *
     * If all load calls were successful, MIDCOM_ERROK is returned. Any error
     * will be indicated with a corresponding return value.
     *
     * @param mixed $node_id    The node ID of the node to be loaded
     * @param mixed $up    The node ID of the parent node.    Optional and not normally needed.
     * @return int            MIDCOM_ERROK on success, one of the MIDCOM_ERR... constants upon an error
     * @access private
     */
    private function _loadNode($node_id, $up = null)
    {
        // Check if we have a cached version of the node already
        if ($up)
        {
            if (isset(self::$_nodes[$this->_nodeid($node_id, $up)]))
            {
                return MIDCOM_ERROK;
            }
        }
        else
        {
            if (isset(self::$_nodes[$node_id]))
            {
                return MIDCOM_ERROK;
            }
        }

        if (!$up)
        {
            $up = $this->_up($node_id);
        }
        $topic_id = (int) $node_id;

        // Load parent nodes also to cache
        $parent_id = 0;
        $up_ids = array();
        if ($up)
        {
            $parent_id = $up;

            $up_ids = explode("_", $up);
            $up_ids = array_reverse($up_ids);
            array_pop($up_ids);
        }
        else
        {
            $parent_id = $this->_get_parent_id($topic_id);
        }

        $lastgoodnode = null;

        while (   $parent_id
               && !isset(self::$_nodes[$parent_id]))
        {
            // Pass the full topic so _loadNodeData doesn't have to reload it
            $result = $this->_loadNodeData($parent_id);
            switch ($result)
            {
                case MIDCOM_ERRFORBIDDEN:
                    debug_push_class(__CLASS__, __FUNCTION__);
                    $log_level = MIDCOM_LOG_WARN;
                    if (!$this->_get_parent_id($topic_id))
                    {
                        // This can happen only when a target for a symlink pointing outside the tree is tried to be accessed.
                        // It is normal then, so use info as log level in that case.
                        $log_level = MIDCOM_LOG_INFO;
                    }
                    debug_add("The Node {$parent_id} is invisible, could not satisfy the dependency chain to Node #{$node_id}", $log_level);
                    debug_pop();
                    return MIDCOM_ERRFORBIDDEN;

                case MIDCOM_ERRCRIT:
                    return MIDCOM_ERRCRIT;
            }

            if (null === $lastgoodnode)
            {
                $lastgoodnode = $parent_id;
            }

            $parent_id = $this->_get_parent_id($topic_id);

            if ($up)
            {
                if ($up_id = array_pop($up_ids))
                {
                    if ($up_id != $parent_id)
                    {
                        $parent_id = $up_id;
                    }
                }
            }
        }

        if (   !is_null($lastgoodnode)
            && (!isset($this->_lastgoodnode) || !$this->_lastgoodnode || $this->_lastgoodnode <= 0))
        {
            $this->_lastgoodnode = $lastgoodnode;
        }

        return $this->_loadNodeData($topic_id, $up);
    }

    /**
     * Load the navigational information associated with the topic $param, which
     * can be passed as an ID or as a MidgardTopic object. This is differentiated
     * by the flag $idmode (true for id, false for MidgardTopic).
     *
     * This method does query the topic for all information and completes it to
     * build up a full NAP data structure
     *
     * It determines the URL_NAME of the topic automatically using the name of the
     * topic in question.
     *
     * The currently active leaf is only queried if and only if the currently
     * processed topic is equal to the current context's content topic. This should
     * prevent dynamically loaded components from disrupting active leaf information,
     * as this can happen if dynamic_load is called before showing the navigation.
     *
     * @param mixed $topic_id Topic ID to be processed
     * @return int            One of the MGD_ERR constants
     */
    private function _loadNodeData($topic_id, $up = null)
    {
        // Load the node data and check visibility.
        $nodedata = $this->_get_node($topic_id, $up);

        if (    !$this->_is_object_visible($nodedata)
             || (   $this->_user_id
                 && !midcom::auth()->acl->can_do_byguid('midgard:read', $nodedata[MIDCOM_NAV_GUID], 'midcom_db_topic', $this->_user_id)))
        {
            return MIDCOM_ERRFORBIDDEN;
        }

        // The node is visible, add it to the list.
        self::$_nodes[$nodedata[MIDCOM_NAV_ID]] = $nodedata;

        $this->_guid_map[$nodedata[MIDCOM_NAV_GUID]] =& self::$_nodes[$nodedata[MIDCOM_NAV_ID]];

        // Load the current leaf, this does *not* load the leaves from the DB, this is done
        // during get_leaf now.
        if ($nodedata[MIDCOM_NAV_ID] === $this->_current)
        {
            $interface = $this->_loader->get_interface_class($nodedata[MIDCOM_NAV_COMPONENT]);
            if (!$interface)
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Could not get interface class of '{$nodedata[MIDCOM_NAV_COMPONENT]}' to the topic {$topic_id}, cannot add it to the NAP list.",
                    MIDCOM_LOG_ERROR);
                debug_pop();
                return null;
            }
            $currentleaf = $interface->get_current_leaf();
            if ($currentleaf !== false)
            {
                $this->_currentleaf = "{$nodedata[MIDCOM_NAV_ID]}-{$currentleaf}";
            }
        }

        return MIDCOM_ERROK;
    }

    /**
     * This helper object will construct a complete node data structure for a given topic,
     * without any dependant objects like subtopics or leaves. It does not do any visibility
     * checks, it just prepares the object for later processing.
     *
     * This code is NAP cache aware, if the resulting information is already in the NAP
     * cache, it is retrieved from there. (NOTE: Cache has been disabled. See #252 at Tigris.org.)
     *
     * @param mixed $topic_id The node ID for which the NAP information is requested.
     * @param mixed $up    The node ID of the parent node.    Optional and not normally needed.
     * @return Array NAP node data structure or NULL in case no NAP information is available for this topic.
     * @access private
     */
    private function _get_node($topic_id, $up = null)
    {
        $nodedata = false;

        if (!$up)
        {
            $nodedata = $this->_nap_cache->get_node($topic_id);
        }

        if (!$nodedata)
        {
            midcom::auth()->request_sudo('midcom.helper.nav');
            $nodedata = $this->_get_node_from_database($topic_id, $up);
            midcom::auth()->drop_sudo();

            if (is_null($nodedata))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add('We got NULL for this node, so we do not have any NAP information, returning null directly.');
                debug_pop();
                return null;
            }

            $this->_nap_cache->put_node($nodedata[MIDCOM_NAV_ID], $nodedata);
            debug_add("Added the ID {$nodedata[MIDCOM_NAV_ID]} to the cache.");
        }

        // Rewrite all host dependant URLs based on the relative URL within our topic tree.
        $nodedata[MIDCOM_NAV_FULLURL] = "{$GLOBALS['midcom_config']['midcom_site_url']}{$nodedata[MIDCOM_NAV_RELATIVEURL]}";
        $nodedata[MIDCOM_NAV_ABSOLUTEURL] = substr($GLOBALS['midcom_config']['midcom_site_url'], strlen(midcom::get_host_name()))
            . "{$nodedata[MIDCOM_NAV_RELATIVEURL]}";
        $nodedata[MIDCOM_NAV_PERMALINK] = midcom::permalinks()->create_permalink($nodedata[MIDCOM_NAV_GUID]);

        return $nodedata;
    }

    /**
     * Reads a node data structure from the database, completes all defaults and
     * derived properties (like ViewerGroups).
     *
     * If the topic is missing a component, it will set the component to midcom.core.nullcomponent.
     *
     * @param mixed $topic_id The ID of the node for which the NAP information is requested.
     * @param mixed $up    The node ID of the parent node.    Optional and not normally needed.
     * @return Array Node data structure or NULL in case no NAP information is available for this topic.
     * @access private
     */
    private function _get_node_from_database($topic_id, $up = null)
    {
        $topic = new midcom_core_dbaproxy($topic_id, 'midcom_db_topic');

        if (   !$topic
            || !$topic->guid)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not load Topic #{$topic_id}: " . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            debug_pop();
            return null;
        }

        $urltopic = $topic;
        $id = $this->_nodeid($urltopic->id, $up);

        if (   $GLOBALS['midcom_config']['symlinks']
            && !empty($urltopic->symlink))
        {
            $topic = new midcom_core_dbaproxy($urltopic->symlink, 'midcom_db_topic');

            if (!$topic || !$topic->guid)
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Could not load target for symlinked topic {$urltopic->id}: " . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
                debug_pop();
                $topic = $urltopic;
            }
        }

        // Retrieve a NAP instance
        // if we are missing the component, use the nullcomponent.
        if (   !$topic->component
            || !array_key_exists($topic->component, midcom::componentloader()->manifests))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("The topic {$topic->id} has no component assigned to it, using 'midcom.core.nullcomponent'.",
                MIDCOM_LOG_INFO);
            debug_pop();
            $topic->component = 'midcom.core.nullcomponent';
        }
        $path = $topic->component;

        $interface = $this->_loader->get_interface_class($path);
        if (!$interface)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not get interface class of '{$path}' to the topic {$topic->id}, cannot add it to the NAP list.",
                MIDCOM_LOG_ERROR);
            debug_pop();
            return null;
        }

        if (! $interface->set_object($topic))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not set the NAP instance of '{$path}' to the topic {$topic->id}, cannot add it to the NAP list.",
                MIDCOM_LOG_ERROR);
            debug_pop();
            return null;
        }

        // Get the node data and verify this is a node that actually has any relevant NAP
        // information. Internal components like the L10n editor, which don't have
        // a NAP interface yet return null here, to be exempt from any NAP processing.
        $nodedata = $interface->get_node();
        if (is_null($nodedata))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("The component '{$path}' did return null for the topic {$topic->id}, indicating no NAP information is available.");
            debug_pop();
            return null;
        }
        // Now complete the node data structure, we need a metadata object for this:
        $metadata = $urltopic->metadata;

        $nodedata[MIDCOM_NAV_URL] = $urltopic->name . '/';
        $nodedata[MIDCOM_NAV_NAME] = trim($nodedata[MIDCOM_NAV_NAME]) == '' ? $topic->name : $nodedata[MIDCOM_NAV_NAME];
        $nodedata[MIDCOM_NAV_GUID] = $urltopic->guid;
        $nodedata[MIDCOM_NAV_ID] = $id;
        $nodedata[MIDCOM_NAV_TYPE] = 'node';
        $nodedata[MIDCOM_NAV_SCORE] = $metadata->score;
        $nodedata[MIDCOM_NAV_COMPONENT] = $path;
        $nodedata[MIDCOM_NAV_SORTABLE] = true;

        if (!isset($nodedata[MIDCOM_NAV_CONFIGURATION]))
        {
            $nodedata[MIDCOM_NAV_CONFIGURATION] = null;
        }

        if (   ! array_key_exists(MIDCOM_NAV_NOENTRY, $nodedata)
            || $nodedata[MIDCOM_NAV_NOENTRY] == false)
        {
            $nodedata[MIDCOM_NAV_NOENTRY] = (bool) $metadata->get('navnoentry');
        }

        if ($urltopic->id == $this->_root)
        {
            $nodedata[MIDCOM_NAV_NODEID] = -1;
            $nodedata[MIDCOM_NAV_RELATIVEURL] = '';
        }
        else
        {
            if (!$up || $this->_loadNode($up) !== MIDCOM_ERROK)
            {
                $up = $urltopic->up;
            }
            $nodedata[MIDCOM_NAV_NODEID] = $up;

            if (   !$nodedata[MIDCOM_NAV_NODEID]
                || !array_key_exists($nodedata[MIDCOM_NAV_NODEID], self::$_nodes))
            {
                return null;
            }
            if (!array_key_exists(MIDCOM_NAV_RELATIVEURL, self::$_nodes[$nodedata[MIDCOM_NAV_NODEID]]))
            {
                return null;
            }

            $nodedata[MIDCOM_NAV_RELATIVEURL] = self::$_nodes[$nodedata[MIDCOM_NAV_NODEID]][MIDCOM_NAV_RELATIVEURL] . $nodedata[MIDCOM_NAV_URL];
        }

        $nodedata[MIDCOM_NAV_OBJECT] = $topic;

        return $nodedata;
    }

    /**
     * Loads the leaves for a given node from the cache or database.
     * It will relay the code to _get_leaves() and check the object visibility upon
     * return.
     *
     * @param Array $node The NAP node data structure to load the nodes for.
     */
    private function _load_leaves($node)
    {
        debug_push_class(__CLASS__, __FUNCTION__);

        if (array_key_exists($node[MIDCOM_NAV_ID], $this->_loaded_leaves))
        {
            debug_add("Warning, tried to load the leaves of node {$node[MIDCOM_NAV_ID]} more then once.", MIDCOM_LOG_INFO);
            debug_pop();
            return;
        }

        $this->_loaded_leaves[$node[MIDCOM_NAV_ID]] = array();

        debug_add("Loading leaves for node {$node[MIDCOM_NAV_ID]}");

        $leaves = $this->_get_leaves($node);
        foreach ($leaves as $id => $leaf)
        {
            if ($this->_is_object_visible($leaf))
            {
                // The leaf is visible, add it to the list.
                $this->_leaves[$id] = $leaf;
                $this->_guid_map[$leaf[MIDCOM_NAV_GUID]] =& $this->_leaves[$id];
                $this->_loaded_leaves[$node[MIDCOM_NAV_ID]][$id] =& $this->_leaves[$id];
            }
        }

        debug_pop();
    }

    /**
     * Return the list of leaves for a given node. This helper will construct complete leaf
     * data structures for each leaf found. It will first check the cache for the leaf structures,
     * and query the database only if the corresponding objects have not been found there.
     *
     * No visibility checks are made at this point.
     *
     * @param Array $node The node data structure for which to retrieve the leaves.
     * @return Array All leaves found for that node, in complete post processed leave data structures.
     * @access private
     */
    private function _get_leaves($node)
    {
        $entry_name = "{$node[MIDCOM_NAV_ID]}-leaves";

        $leaves = $this->_nap_cache->get_leaves($entry_name);

        if (false === $leaves)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('The leaves have not yet been loaded from the database, we do this now.');
            debug_pop();

            //we always write all the leaves to cache and filter for ACLs after the fact
            midcom::auth()->request_sudo('midcom.helper.nav');
            $leaves = $this->_get_leaves_from_database($node);
            midcom::auth()->drop_sudo();

            $this->_write_leaves_to_cache($node, $leaves);
        }

        $result = array();
        foreach ($leaves as $id => $data)
        {
            if (   isset($data[MIDCOM_NAV_OBJECT])
                && is_object($data[MIDCOM_NAV_OBJECT])
                && $data[MIDCOM_NAV_GUID])
            {
                if (    $this->_user_id
                     && !midcom::auth()->acl->can_do_byguid('midgard:read', $data[MIDCOM_NAV_GUID], $data[MIDCOM_NAV_OBJECT]->__midcom_class_name__, $this->_user_id))
                {
                    continue;
                }
            }
            $result[$id] = $data;
        }


        // Post process the leaves for URLs and the like.
        // Rewrite all host dependant URLs based on the relative URL within our topic tree.
        $this->_update_leaflist_urls($result);

        return $result;
    }

    /**
     * This helper is responsible for loading the leaves for a given node out of the
     * database. It will complete all default fields to provide full blown nap structures.
     * It will also build the base relative URLs which will later be completed by the
     * _get_leaves() interface functions.
     *
     * Important notes:
     * - The ViewerGroups property is copied from the parent topic, to ensure the same level of visibility.
     * - The IDs constructed for the leaves are the concatenation of the ID delivered by the component
     *   and the topics' GUID.
     *
     * @param Array $node The node data structure for which to retrieve the leaves.
     * @return Array All leaves found for that node, in complete post processed leave data structures.
     * @access private
     */
    private function _get_leaves_from_database($node)
    {
        $topic = $node[MIDCOM_NAV_OBJECT];

        // Retrieve a NAP instance
        $interface = $this->_loader->get_interface_class($node[MIDCOM_NAV_COMPONENT]);
        if (!$interface)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Could not get interface class of '{$node[MIDCOM_NAV_COMPONENT]}' to the topic {$topic->id}, cannot add it to the NAP list.",
                MIDCOM_LOG_ERROR);
            debug_pop();
            return null;
        }
        if (! $interface->set_object($topic))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('Topic object dump:', $topic);
            debug_pop();
            midcom::generate_error(MIDCOM_ERRCRIT,
                "Cannot load NAP information, aborting: Could not set the nap instance of {$node[MIDCOM_NAV_COMPONENT]} to the topic {$topic->id}.");
            // This will exit().
        }

        $leafdata = $interface->get_leaves();
        $leaves = Array();

        foreach ($leafdata as $id => $leaf)
        {
            // First, try to somehow gain both a GUID and a Leaf.
            if (   !isset($leaf[MIDCOM_NAV_GUID])
                && !isset($leaf[MIDCOM_NAV_OBJECT]))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Warning: The leaf {$id} of topic {$topic->id} does set neither a GUID nor an object.", MIDCOM_LOG_DEBUG);
                debug_pop();
                $leaf[MIDCOM_NAV_GUID] = null;
                $leaf[MIDCOM_NAV_OBJECT] = null;

                // Get the pseudo leaf score from the topic
                if (($score = $topic->get_parameter('midcom.helper.nav.score', "{$topic->id}-{$id}")))
                {
                    $leaf[MIDCOM_NAV_SCORE] = (int) $score;
                }
            }
            else if (!isset($leaf[MIDCOM_NAV_GUID]))
            {
                $leaf[MIDCOM_NAV_GUID] = $leaf[MIDCOM_NAV_OBJECT]->guid;
            }
            else if (!isset($leaf[MIDCOM_NAV_OBJECT]))
            {
                $leaf[MIDCOM_NAV_OBJECT] = midcom::dbfactory()->get_object_by_guid($leaf[MIDCOM_NAV_GUID]);
            }

            if (!isset($leaf[MIDCOM_NAV_SORTABLE]))
            {
                $leaf[MIDCOM_NAV_SORTABLE] = true;
            }

            // Now complete the actual leaf information

            // Score
            if (!isset($leaf[MIDCOM_NAV_SCORE]))
            {
                if (   $leaf[MIDCOM_NAV_OBJECT]
                    && isset($leaf[MIDCOM_NAV_OBJECT]->metadata->score))
                {
                    $leaf[MIDCOM_NAV_SCORE] = $leaf[MIDCOM_NAV_OBJECT]->metadata->score;
                }
                else
                {
                    $leaf[MIDCOM_NAV_SCORE] = 0;
                }
            }

            // NAV_NOENTRY Flag
            if (!isset($leaf[MIDCOM_NAV_NOENTRY]))
            {
                $leaf[MIDCOM_NAV_NOENTRY] = false;
            }

            //TODO: I don't see how this can work, $leaf is an array, so is_object should return false
            if (   $leaf[MIDCOM_NAV_NOENTRY] == false
                && is_object($leaf))
            {
                $metadata = $leaf->metadata;
                if ($metadata)
                {
                    $leaf[MIDCOM_NAV_NOENTRY] = (bool) $metadata->get('navnoentry');
                }
            }

            // complete NAV_NAMES where necessary
            if ( trim($leaf[MIDCOM_NAV_NAME]) == '')
            {
                $leaf[MIDCOM_NAV_NAME] = midcom::i18n()->get_string('unknown', 'midcom');
            }

            // Some basic information
            $leaf[MIDCOM_NAV_TYPE] = 'leaf';
            $leaf[MIDCOM_NAV_ID] = "{$node[MIDCOM_NAV_ID]}-{$id}";
            $leaf[MIDCOM_NAV_NODEID] = $node[MIDCOM_NAV_ID];
            $leaf[MIDCOM_NAV_RELATIVEURL] = $node[MIDCOM_NAV_RELATIVEURL] . $leaf[MIDCOM_NAV_URL];
            if (!array_key_exists(MIDCOM_NAV_ICON, $leaf))
            {
                $leaf[MIDCOM_NAV_ICON] = null;
            }

            // Save the original Leaf ID so that it is easier to query in topic-specific NAP code
            $leaf[MIDCOM_NAV_LEAFID] = $id;

            // The leaf is complete, add it.
            $leaves[$leaf[MIDCOM_NAV_ID]] = $leaf;
        }

        return $leaves;
    }

    /**
     * This helper updates the URLs in the reference-passed leaf list.
     * FULLURL, ABSOLUTEURL and PERMALINK are built upon RELATIVEURL, NAV_NAME
     * and NAV_URL are populated based on the administration mode with NAV_SITE values
     *
     * @param Array $leaves A reference to the list of leaves which has to be processed.
     * @access private
     */
    private function _update_leaflist_urls(&$leaves)
    {
        $fullprefix = "{$GLOBALS['midcom_config']['midcom_site_url']}";
        $absoluteprefix = substr($GLOBALS['midcom_config']['midcom_site_url'], strlen(midcom::get_host_name()));

        if (! is_array($leaves))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r("Wrong type", $leaves, MIDCOM_LOG_ERROR);
            debug_pop();

            midcom::generate_error(MIDCOM_ERRCRIT, 'Wrong type passed for navigation, see error level log for details');
            // This will exit
        }

        foreach ($leaves as $id => $copy)
        {
            $leaves[$id][MIDCOM_NAV_FULLURL] = $fullprefix . $leaves[$id][MIDCOM_NAV_RELATIVEURL];
            $leaves[$id][MIDCOM_NAV_ABSOLUTEURL] = $absoluteprefix . $leaves[$id][MIDCOM_NAV_RELATIVEURL];

            if (is_null($leaves[$id][MIDCOM_NAV_GUID]))
            {
                $leaves[$id][MIDCOM_NAV_PERMALINK] = $leaves[$id][MIDCOM_NAV_FULLURL];
            }
            else
            {
                $leaves[$id][MIDCOM_NAV_PERMALINK] = midcom::permalinks()->create_permalink($leaves[$id][MIDCOM_NAV_GUID]);
            }
        }
    }

    /**
     * Writes the leaves passed to this function to the cache, assigning them to the
     * specified node.
     *
     * The function will bail out on any critical error. Data inconsistencies will be
     * logged and overwritten silently otherwise.
     *
     * @param Array $node The node data structure to which the leaves should be assigned.
     * @param Array $leaves The leaves to store in the cache.
     * @access private
     */
    private function _write_leaves_to_cache($node, $leaves)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add('Writing ' . count ($leaves) . ' leaves to the cache.');

        $cached_node = $this->_nap_cache->get_node($node[MIDCOM_NAV_ID]);

        if (!$cached_node)
        {
            debug_add("NAP Caching Engine: Tried to update the topic {$node[MIDCOM_NAV_NAME]} (#{$node[MIDCOM_NAV_OBJECT]->id}) "
                . 'which was supposed to be in the cache already, but failed to load the object from the database. '
                . 'Aborting write_to_cache, this is a critical cache inconsistency.', MIDCOM_LOG_WARN);
            debug_pop();
            return;
        }

        foreach ($leaves as $id => $leaf)
        {
            if (is_object($leaf[MIDCOM_NAV_OBJECT]))
            {
                $leaves[$id][MIDCOM_NAV_OBJECT] = new midcom_core_dbaproxy($leaf[MIDCOM_NAV_OBJECT]->guid, get_class($leaf[MIDCOM_NAV_OBJECT]));
            }
        }

        $this->_nap_cache->put_leaves("{$node[MIDCOM_NAV_ID]}-leaves", $leaves);

        debug_pop();
    }

    private function _get_subnodes($parent_node)
    {
        if (isset(self::$_nodes[$parent_node][MIDCOM_NAV_SUBNODES]))
        {
            return self::$_nodes[$parent_node][MIDCOM_NAV_SUBNODES];
        }

        $subnodes = array();

        // Use midgard_collector to get the subnodes
        $id = (int) $parent_node;
        if ($GLOBALS['midcom_config']['symlinks'])
        {
            $id = self::$_nodes[$parent_node][MIDCOM_NAV_OBJECT]->id;
        }
        $mc = midcom_db_topic::new_collector('up', $id);
        $mc->add_value_property('id');

        $mc->add_constraint('name', '<>', '');

        $mc->add_order('metadata.score', 'DESC');
        $mc->add_order('metadata.created');

        $mc->execute();

        //we always write all the subnodes to cache and filter for ACLs after the fact
        midcom::auth()->request_sudo('midcom.helper.nav');
        $result = $mc->list_keys();

        foreach ($result as $guid => $empty)
        {
            $subnodes[] = $mc->get_subkey($guid, 'id');
        }
        midcom::auth()->drop_sudo();

        $cachedata = $this->_nap_cache->get_node($parent_node);

	if (false === $cachedata)
        {
            /* It seems that the parent node is not in cache. This may happen when list_nodes is called from cache_invalidate
             * We load it again from db (which automatically puts it in the cache if it's active)
             */
            $cachedata = $this->_get_node($parent_node);
        }

        $cachedata[MIDCOM_NAV_SUBNODES] = $subnodes;
        self::$_nodes[$parent_node][MIDCOM_NAV_SUBNODES] = $subnodes;
        $this->_nap_cache->put_node($parent_node, $cachedata);

        return $subnodes;
    }

    /**
     * Lists all Sub-nodes of $parent_node. If there are no subnodes you will get
     * an empty array, if there was an error (for instance an unknown parent node
     * ID) you will get FALSE.
     *
     * @param mixed $parent_node    The ID of the node of which the subnodes are searched.
     * @param boolean $show_noentry Show all objects on-site which have the noentry flag set.
     * @return Array            An array of node IDs or false on failure.
     */
    // Keep this doc in sync with midcom_helper_nav
    function list_nodes($parent_node, $show_noentry)
    {
        static $listed = array();
        $up = null;

        if ($this->_loadNode($parent_node) !== MIDCOM_ERROK)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Unable to load parent node $parent_node", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        if (isset($listed[$parent_node]))
        {
            return $listed[$parent_node];
        }

        $subnodes = $this->_get_subnodes($parent_node);

        // No results, return an empty array
        if (count($subnodes) === 0)
        {
            $listed[$parent_node] = array();
            return $listed[$parent_node];
        }

        $up = $this->_up($parent_node);
        $node = (int) $parent_node;

        if (   $up
            || (   $GLOBALS['midcom_config']['symlinks']
                && $node != self::$_nodes[$parent_node][MIDCOM_NAV_OBJECT]->id)
           )
        {
            $up = $this->_nodeid($node, $up);
        }

        $result = array();

        foreach ($subnodes as $id)
        {
            $subnode_id = $this->_nodeid($id, $up);

            if ($this->_loadNode($id, $up) !== MIDCOM_ERROK)
            {
                continue;
            }

            if (   !$show_noentry
                && self::$_nodes[$subnode_id][MIDCOM_NAV_NOENTRY])
            {
                // Hide "noentry" items
                continue;
            }

            $result[] = $subnode_id;
        }

        $listed[$parent_node] = $result;
        return $listed[$parent_node];
    }

    /**
     * Lists all leaves of $parent_node. If there are no leaves you will get an
     * empty array, if there was an error (for instance an unknown parent node ID)
     * you will get FALSE.
     *
     * @param mixed $parent_node    The ID of the node of which the leaves are searched.
     * @param boolean $show_noentry Show all objects on-site which have the noentry flag set.
     * @return Array             A list of leaves found, or false on failure.
     */
    // Keep this doc in sync with midcom_helper_nav
    function list_leaves($parent_node, $show_noentry)
    {
        static $listed = array();

        if ($this->_loadNode($parent_node) !== MIDCOM_ERROK)
        {
            return false;
        }

        if (!array_key_exists($parent_node, $this->_loaded_leaves))
        {
            $this->_load_leaves(self::$_nodes[$parent_node]);
        }

        if (isset($listed[$parent_node]))
        {
            return $listed[$parent_node];
        }

        $result = array();
        foreach ($this->_loaded_leaves[self::$_nodes[$parent_node][MIDCOM_NAV_ID]] as $id => $leaf)
        {
            if ($show_noentry || !$leaf[MIDCOM_NAV_NOENTRY])
            {
                $result[] = $id;
            }
        }

        $listed[$parent_node] = $result;
        return $result;
    }

    /**
     * This is a helper function used by midcom_helper_nav::resolve_guid(). It
     * checks if the object denoted by the passed GUID is already loaded into
     * memory and returns it, if available. This should speed up GUID lookup heavy
     * code.
     *
     * Access is restricted to midcom_helper_nav::resolve_guid().
     *
     * @access protected
     * @param GUID $guid The GUID to look up in the in-memory cache.
     * @return Array A NAP structure if the GUID is known, null otherwise.
     */
    function get_loaded_object_by_guid($guid)
    {
        if (! array_key_exists($guid, $this->_guid_map))
        {
            return null;
        }
        return $this->_guid_map[$guid];
    }

    /**
     * This will give you a key-value pair describing the node with the ID
     * $node_id. The defined keys are described above in Node data interchange
     * format. You will get false if the node ID is invalid.
     *
     * @param mixed $node_id    The node ID to be retrieved.
     * @return Array        The node data as outlined in the class introduction, false on failure
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_node($node_id)
    {
        $node = $node_id;
        if (is_object($node) && $node->guid)
        {
            $node_id = $node->id;
        }
        if ($this->_loadNode($node_id) != MIDCOM_ERROK)
        {
            return false;
        }

        if (!isset(self::$_nodes[$node_id]))
        {
            return false;
        }

        return self::$_nodes[$node_id];
    }

    /**
     * This will give you a key-value pair describing the leaf with the ID
     * $node_id. The defined keys are described above in leaf data interchange
     * format. You will get false if the leaf ID is invalid.
     *
     * @param string $leaf_id    The leaf-id to be retrieved.
     * @return Array        The leaf-data as outlined in the class introduction, false on failure
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_leaf ($leaf_id)
    {
        if (! $this->_check_leaf_id($leaf_id))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("This leaf is unknown, aborting.", MIDCOM_LOG_INFO);
            debug_pop();
            return false;
        }

        return $this->_leaves[$leaf_id];
    }

    /**
     * Retrieve the ID of the currently displayed node. Defined by the topic of
     * the component that declared able to handle the request.
     *
     * @return mixed    The ID of the node in question.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_current_node()
    {
        return $this->_current;
    }

    /**
     * Retrieve the ID of the currently displayed leaf. This is a leaf that is
     * displayed by the handling topic. If no leaf is active, this function
     * returns FALSE. (Remember to make a type sensitive check, e.g.
     * nav::get_current_leaf() !== false to distinguish "0" and "false".)
     *
     * @return string    The ID of the leaf in question or false on failure.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_current_leaf()
    {
        return $this->_currentleaf;
    }

    /**
     * Retrieve the ID of the upper node of the currently displayed node.
     *
     * @return mixed    The ID of the node in question.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_current_upper_node()
    {
        static $upper_node = null;

        if (!$upper_node)
        {
            $node = null;
            foreach ($this->_node_path as $node_path_component)
            {
                $upper_node = $node;
                $node = $node_path_component;
            }
            if (!$upper_node)
            {
                $upper_node = $node;
            }
        }

        return $upper_node;
    }

    /**
     * Retrieve the ID of the root node. Note that this ID is dependent from the
     * ID of the MidCOM Root topic and therefore will change as easily as the
     * root topic ID might. The MIDCOM_NAV_URL entry of the root node's data will
     * always be empty.
     *
     * @return int    The ID of the root node.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_root_node()
    {
        return $this->_root;
    }

    /**
     * Retrieve the IDs of the nodes from the URL. First value at key 0 is
     * the root node ID, possible second value is the first subnode ID etc.
     * Contains only visible nodes (nodes which can be loaded).
     *
     * @return Array    The node path array.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_node_path()
    {
        return $this->_node_path;
    }

    /**
     * Returns the ID of the node to which $leaf_id is associated to, false
     * on failure.
     *
     * @param string $leaf_id    The Leaf-ID to search an uplink for.
     * @return mixed             The ID of the Node for which we have a match, or false on failure.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_leaf_uplink($leaf_id)
    {
        if (! $this->_check_leaf_id($leaf_id))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("This leaf is unknown, aborting.", MIDCOM_LOG_ERROR);
            debug_pop();
            return false;
        }

        return $this->_leaves[$leaf_id][MIDCOM_NAV_NODEID];
    }

    /**
     * Returns the ID of the node to which $node_id is associated to, false
     * on failure. The root node's uplink is -1.
     *
     * @param mixed $node_id    The node ID to search an uplink for.
     * @return mixed             The ID of the node for which we have a match, -1 for the root node, or false on failure.
     */
    // Keep this doc in sync with midcom_helper_nav
    function get_node_uplink($node_id)
    {
        if ($this->_loadNode($node_id) !== MIDCOM_ERROK)
        {
            return false;
        }

        return self::$_nodes[$node_id][MIDCOM_NAV_NODEID];
    }

    /**
     * Helper function to read a parameter without loading the corresponding object.
     * This is primarily for improving performance, so the function does not check
     * for privileges.
     *
     * @param string $objectguid The object's GUID
     * @param string $name The parameter to look for
     */
    public static function get_parameter($objectguid, $name)
    {
        static $parameter_cache = array();

        if (!isset($parameter_cache[$objectguid]))
        {
            $parameter_cache[$objectguid] = array();
        }

        if (isset($parameter_cache[$objectguid][$name]))
        {
            return $parameter_cache[$objectguid][$name];
        }

        $mc = midgard_parameter::new_collector('parentguid', $objectguid);
        $mc->set_key_property('value');
        $mc->add_constraint('name', '=', $name);
        $mc->add_constraint('domain', '=', 'midcom.helper.nav');
        $mc->set_limit(1);
        $mc->execute();
        $parameters = $mc->list_keys();

        if (count($parameters) == 0)
        {
            $parameter_cache[$objectguid][$name] = null;
            return $parameter_cache[$objectguid][$name];
        }

        $parameter_cache[$objectguid][$name] = key($parameters);

        unset($mc);

        return $parameter_cache[$objectguid][$name];
    }

    /**
     * Retrieve the up part from the given node ID.
     * (To get the topic ID part, just cast the node ID to int with (int).
     *  That's why there's no method for that. :))
     *
     * @param mixed $nodeid    The node ID.
     * @return mixed    The up part.
     */
    private function _up($nodeid)
    {
        static $cache = array();

        if (!isset($cache[$nodeid]))
        {
            $up = null;
            $ids = explode("_", $nodeid);
            $id = array_shift($ids);
            foreach ($ids as $id)
            {
                if ($up)
                {
                    $up .= "_" . $id;
                }
                else
                {
                    $up = (int) $id;
                }
            }
            $cache[$nodeid] = $up;
        }

        return $cache[$nodeid];
    }

    /**
     * Generate node ID from topic ID and up value.
     *
     * @param int $topic_id    Topic ID.
     * @param mixed $up    The up part.
     * @return mixed    The generated node ID.
     */
    private function _nodeid($topic_id, $up)
    {
        $nodeid = $topic_id;
        if ($up)
        {
            $nodeid .= "_" . $up;
        }
        return $nodeid;
    }

    /**
     * Verifies the existence of a given leaf. Call this before getting a leaf from the
     * $_leaves cache. It will load all necessary nodes/leaves as necessary.
     *
     * @param string $leaf_id A valid NAP leaf id ($nodeid-$leafid pattern).
     * @return boolean true if the leaf exists, false otherwise.
     */
    private function _check_leaf_id($leaf_id)
    {
         if (! $leaf_id)
         {
            debug_add("Tried to load a suspicious leaf id, probably a FALSE from get_current_leaf.");
            return false;
        }

        if (array_key_exists($leaf_id, $this->_leaves))
        {
            return true;
        }

        $id_elements = explode('-', $leaf_id);

        $node_id = $id_elements[0];

        if ($this->_loadNode($node_id) !== MIDCOM_ERROK)
        {
            debug_add("Tried to verify the leaf id {$leaf_id}, which should belong to node {$node_id}, but this node cannot be loaded, see debug level log for details.",
                MIDCOM_LOG_INFO);
            return false;
        }

        $this->_load_leaves(self::$_nodes[$node_id]);

        return (array_key_exists($leaf_id, $this->_leaves));
    }

    /**
     * Small helper to determine a topic's parent id without loading the full object
     *
     * @param integer $topic_id The topic ID
     * @return integer The parent ID or false
     */
    private function _get_parent_id($topic_id)
    {
        $mc = midcom_db_topic::new_collector('id', $topic_id);
        $mc->add_value_property('up');
        $mc->execute();
        $result = $mc->list_keys();
        if (empty($result))
        {
            return false;
        }
        return $mc->get_subkey(key($result), 'up');
    }

    /**
     * Checks, if the NAP object indicated by $napdata is visible within the current
     * runtime environment. It will work with both nodes and leaves.
     * This includes checks for:
     *
     * - Nonexistent NAP information (null values)
     * - Viewergroups
     * - Scheduling/Hiding (only on-site)
     * - Approval (only on-site)
     *
     * @param Array $napdata The NAP data structure for the object to check (supports NULL values).
     * @return boolean Indicating visibility.
     * @access private
     * @todo Integrate with midcom_helper_metadata::is_object_visible_onsite()
     */
    private function _is_object_visible($napdata)
    {
        if (is_null($napdata))
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('Got a null value as napdata, so this object does not have any NAP info, so we cannot display it.');
            debug_pop();
            return false;
        }

        // Check the Metadata if and only if we are configured to do so.
        if (   is_object($napdata[MIDCOM_NAV_OBJECT])
            && (   $GLOBALS['midcom_config']['show_hidden_objects'] == false
                || $GLOBALS['midcom_config']['show_unapproved_objects'] == false))
        {
            // Check Hiding, Scheduling and Approval
            $metadata = $napdata[MIDCOM_NAV_OBJECT]->metadata;

            if (! $metadata)
            {
                // For some reason, the metadata for this object could not be retrieved. so we skip
                // Approval/Visibility checks.
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Warning, no Metadata available for the {$napdata[MIDCOM_NAV_TYPE]} {$napdata[MIDCOM_NAV_GUID]}.", MIDCOM_LOG_INFO);
                debug_pop();
                return true;
            }

            if (! $metadata->is_object_visible_onsite())
            {
                return false;
            }
        }

        return true;
    }
}
?>
