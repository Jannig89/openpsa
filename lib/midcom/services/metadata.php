<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: metadata.php 26603 2010-08-12 16:26:56Z jval $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Metadata service.
 *
 * This service utilizes MidCOM's metadata system to provide meaningful, auto-generated
 * meta and link tags into documents. It is also entry point site builders can use to
 * retrieve metadata about current page.
 *
 * @package midcom.services
 */
class midcom_services_metadata extends midcom_baseclasses_core_object
{
    /**
     * The metadata currently available. This array is indexed by context id; each
     * value consists of a flat array of two metadata objects, the first object being
     * the Node metadata, the second View metadata. The metadata objects are created
     * on-demand.
     *
     * @var Array
     * @access private
     */
    var $_metadata = Array();

    /**
     * Class of the current page per each context.
     * Typically these are the same as the schema name of the current object's Datamanager schema.
     * This can be used for changing site styling based on body class="" etc.
     *
     * @var Array
     * @access private
     */
    var $_page_classes = Array();

    /**
     * Simple constructor, calls base class.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a reference to the node metadata of the specified context. The metadata
     * will be created if this is the first request.
     *
     * @param int $context_id The context to retrieve the node metadata for, this
     *     defaults to the current context.
     */
    function & get_node_metadata ($context_id = null)
    {
        if ($context_id === null)
        {
            $context_id = midcom::get_current_context();
        }

        if (! array_key_exists($context_id, $this->_metadata))
        {
            $this->_create_metadata($context_id);
        }

        return $this->_metadata[$context_id][MIDCOM_METADATA_NODE];
    }

    /**
     * Returns a reference to the view metadata of the specified context. The metadata
     * will be created if this is the first request.
     *
     * @param int $context_id The context to retrieve the view metadata for, this
     *     defaults to the current context.
     */
    function & get_view_metadata ($context_id = null)
    {
        if ($context_id === null)
        {
            $context_id = midcom::get_current_context();
        }

        if (! array_key_exists($context_id, $this->_metadata))
        {
            $this->_create_metadata($context_id);
        }

        return $this->_metadata[$context_id][MIDCOM_METADATA_VIEW];
    }

    /**
     * Creates the node and view metadata for a given context ID.
     *
     * @param int $context_id The context ID for which the metadata should be created.
     */
    function _create_metadata ($context_id)
    {
        if ($context_id === null)
        {
            $topic = midcom::get_context_data(MIDCOM_CONTEXT_CONTENTTOPIC);
        }
        else
        {
            $topic = midcom::get_context_data($context_id, MIDCOM_CONTEXT_CONTENTTOPIC);
        }

        if (   !is_object($topic)
            || !isset($topic->id)
            || empty($topic->id))
        {
            $this->_metadata[$context_id] = Array();
            $this->_metadata[$context_id][MIDCOM_METADATA_NODE] = null;
            $this->_metadata[$context_id][MIDCOM_METADATA_VIEW] = null;
            return;
        }

        $this->_metadata[$context_id] = Array();

        $this->_metadata[$context_id][MIDCOM_METADATA_NODE] = midcom_helper_metadata::retrieve($topic);
        $this->_metadata[$context_id][MIDCOM_METADATA_VIEW] = null;
    }

    /**
     * Sets the class of the current page for a context
     *
     * @param string $page_class The class that should be used for the page
     * @param int $context_id The context ID for which the page class should be set.
     */
    function set_page_class($page_class, $context_id = null)
    {
        if ($context_id === null)
        {
            $context_id = midcom::get_current_context();
        }

        // Append current topic to page class if enabled
        if ($GLOBALS['midcom_config']['page_class_include_component'])
        {
            $page_class .= ' ' . str_replace('.', '_', midcom::get_context_data(MIDCOM_CONTEXT_COMPONENT));
        }

        // Append a custom class from topic to page class
        $topic_class = midcom::get_context_data(MIDCOM_CONTEXT_CONTENTTOPIC)->get_parameter('midcom.services.metadata', 'page_class');
        if (!empty($topic_class))
        {
            $page_class .= " {$topic_class}";
        }

        $this->_page_classes[$context_id] = trim($page_class);
    }

    /**
     * Gets the CSS class of the current page of a context
     *
     * @param int $context_id The context ID for the page class.
     * @return string The page class
     */
    public function get_page_class($context_id = null)
    {
        if ($context_id === null)
        {
            $context_id = midcom::get_current_context();
        }

        if (array_key_exists($context_id, $this->_page_classes))
        {
            return $this->_page_classes[$context_id];
        }
        else
        {
            return 'default';
        }
    }

    /**
     * Get CSS classes for an object. This will include two new CSS classes for the object's class string
     * if appropriate:
     *
     * - unapproved: approvals are enabled for the site but the object is not translated
     * - hidden: object is hidden via metadata settings or scheduling
     *
     * @param DBAobject &$object The DBA class instance to get CSS classes for
     * @param string Existing CSS classes to append to
     * @return string CSS classes for that object
     */
    public function get_object_classes(&$object, $existing_classes = null)
    {
        $css_classes = array();
        if (!is_null($existing_classes))
        {
            $css_classes[] = $existing_classes;
        }

        // Approval attributes
        if (   $GLOBALS['midcom_config']['metadata_approval']
            && !$object->metadata->is_approved())
        {
            $css_classes[] = 'unapproved';
        }

        // Hiding and scheduling attributes
        if (   (   !$GLOBALS['midcom_config']['show_hidden_objects']
                || $GLOBALS['midcom_config']['metadata_scheduling'])
            && !$object->metadata->is_visible())
        {
            $css_classes[] = 'hidden';
        }

        // Folder's class
        if ($page_class = $object->get_parameter('midcom.services.metadata', 'page_class'))
        {
            $css_classes[] = $page_class;
        }

        if (empty($css_classes))
        {
            return '';
        }

        return implode(' ', $css_classes);
    }

    /**
     * Binds view metadata to a DBA content object
     *
     * @param DBAObject &$object The DBA class instance to bind to.
     */
    function bind_to(&$object)
    {
        $this->bind_metadata_to_object(MIDCOM_METADATA_VIEW, $object);
    }

    /**
     * Binds object to given metadata type.
     */
    function bind_metadata_to_object($metadata_type, &$object, $context_id = null)
    {
        if ($context_id === null)
        {
            $context_id = midcom::get_current_context();
        }

        $this->_metadata[$context_id][$metadata_type] = midcom_helper_metadata::retrieve($object);
        if (!$this->_metadata[$context_id][$metadata_type])
        {
            return;
        }

        // Update MidCOM 2.6 request metadata if appropriate
        $request_metadata = midcom::get_26_request_metadata($context_id);
        $edited = $this->_metadata[$context_id][$metadata_type]->get('edited');
        if ($edited > $request_metadata['lastmodified'])
        {
            midcom::set_26_request_metadata($edited, $request_metadata['permalinkguid']);
        }
    }

    /**
     * Populates appropriate metadata into XHTML documents based on metadata information
     * available to MidCOM for the request.
     */
    function populate_meta_head()
    {
        // Populate the 2.6 request metadata into view
        $request_metadata = midcom::get_26_request_metadata();

        // HTML generator information
        midcom::add_meta_head
        (
            array
            (
                'name' => 'generator',
                'content' => 'Midgard/' . mgd_version() . ' MidCOM/' . $GLOBALS['midcom_version'] . ' PHP/' . phpversion()
            )
        );

        // PermaLink into machine-detectable format
        midcom::add_link_head
        (
            array
            (
                'rel' => 'bookmark',
                'href' => $request_metadata['permalink']
            )
        );

        // Last revision time for the entire page
        if ($request_metadata['lastmodified'])
        {
            midcom::add_meta_head
            (
                array
                (
                    'name' => 'lastupdated',
                    'content' => @gmdate('Y-m-d H:i:s\Z', $request_metadata['lastmodified'])
                )
            );
        }

        // If an object has been bound we have more information available
        $view_metadata = $this->get_view_metadata();
        if ($view_metadata)
        {
            foreach ($GLOBALS['midcom_config']['metadata_head_elements'] as $property => $metatag)
            {
                $content = $view_metadata->get($property);
                if ($content)
                {
                    // Handle date fields
                    switch ($property)
                    {
                        case 'published':
                        case 'created':
                        case 'revised':
                        case 'approved':
                        case 'locked':
                            $content = gmdate('Y-m-d', (int) $content);
                            break;
                    }

                    midcom::add_meta_head
                    (
                        array
                        (
                            'name' => $metatag,
                            'content' => $content,
                        )
                    );
                }
            }
            // TODO: Add support for tags here

            if ($GLOBALS['midcom_config']['metadata_opengraph'])
            {
                $opengraph_type = $view_metadata->object->get_parameter('midcom.helper.metadata', 'opengraph_type');
                if (   $opengraph_type
                    && $opengraph_type != 'none')
                {
                    midcom::add_meta_head
                    (
                        array
                        (
                            'property' => 'og:type',
                            'content' => $opengraph_type,
                        )
                    );
                    midcom::add_meta_head
                    (
                        array
                        (
                            'property' => 'og:title',
                            'content' => midcom::get_context_data(MIDCOM_CONTEXT_PAGETITLE),
                        )
                    );
                    midcom::add_meta_head
                    (
                        array
                        (
                            'property' => 'og:url',
                            'content' => $request_metadata['permalink'],
                        )
                    );
                    midcom::add_meta_head
                    (
                        array
                        (
                            'property' => 'og:image',
                            'content' => '',
                        )
                    );
                    midcom::add_meta_head
                    (
                        array
                        (
                            'property' => 'og:description',
                            'content' => $view_metadata->get('description'),
                        )
                    );
                }
            }

            if ($GLOBALS['midcom_config']['positioning_enable'])
            {
                // Load the positioning library
                midcom::load_library('org.routamc.positioning');

                // Display position metadata
                $object_position = new org_routamc_positioning_object($view_metadata->object);
                $object_position->set_metadata();
            }
        }
    }

    /**
     * Return a list of Open Graph Protocol types
     *
     * @see http://opengraphprotocol.org/
     * @return Array
     */
    public function get_opengraph_types()
    {
        if (!$GLOBALS['midcom_config']['metadata_opengraph'])
        {
            return array();
        }

        return array
        (
            'none' => 'opengraph type select',
            'activity' => 'opengraph activity activity',
            'sport' => 'opengraph activity sport',
            'bar' => 'opengraph business bar',
            'company' => 'opengraph business company',
            'hotel' => 'opengraph business hotel',
            'restaurant' => 'opengraph business restaurant',
            'cause' => 'opengraph group cause',
            'sports_league' => 'opengraph group sports_league',
            'sports_team' => 'opengraph group sports_team',
            'band' => 'opengraph organization band',
            'government' => 'opengraph organization government',
            'non_profit' => 'opengraph organization non_profit',
            'school' => 'opengraph organization school',
            'university' => 'opengraph organization university',
            'actor' => 'opengraph person actor',
            'athlete' => 'opengraph person athlete',
            'author' => 'opengraph person author',
            'director' => 'opengraph person director',
            'musician' => 'opengraph person musician',
            'politician' => 'opengraph person politician',
            'public_figure' => 'opengraph person public_figure',
            'city' => 'opengraph place city',
            'country' => 'opengraph place country',
            'landmark' => 'opengraph place landmark',
            'state_province' => 'opengraph place state_province',
            'album' => 'opengraph product album',
            'book' => 'opengraph product book',
            'drink' => 'opengraph product drink',
            'food' => 'opengraph product food',
            'game' => 'opengraph product game',
            'movie' => 'opengraph product movie',
            'product' => 'opengraph product product',
            'song' => 'opengraph product song',
            'tv_show' => 'opengraph product tv_show',
            'article' => 'opengraph website article',
            'blog' => 'opengraph website blog',
            'website' => 'opengraph website website',
        );
    }

    /**
     * Get the default Open Graph Protocol type for an object
     *
     * @return string Open Graph Protocol type
     */
    public function get_opengraph_type_default($object = null)
    {
        if (!$GLOBALS['midcom_config']['metadata_opengraph'])
        {
            return '';
        }

        if (!$object)
        {
            // No object given, use object bound to view
            $context_id = midcom::get_current_context();
            if (   !isset($this->_metadata[$context_id][MIDCOM_METADATA_VIEW])
                || !$this->_metadata[$context_id][MIDCOM_METADATA_VIEW])
            {
                return '';
            }
            $object = $this->_metadata[$context_id][MIDCOM_METADATA_VIEW]->object;
        }

        if (   !isset($object->guid)
            || !$object->guid)
        {
            // Non-persistent or non-Midgard object
            return '';
        }

        $component = midcom::get_context_data(MIDCOM_CONTEXT_COMPONENT);
        if (   !$component
            || !midcom::componentloader->is_installed($component)
            || !midcom::componentloader->load_graceful($component))
        {
            return '';
        }

        $interface = midcom::componentloader->get_interface_class($component);
        if (!method_exists($interface, 'get_opengraph_default'))
        {
            return '';
        }

        return $interface->get_opengraph_default($object);
    }
}
?>
