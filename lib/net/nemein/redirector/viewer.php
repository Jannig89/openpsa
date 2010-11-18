<?php
/**
 * @package net.nemein.redirector
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: viewer.php 25148 2010-02-22 14:31:05Z adrenalin $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Redirector interface class.
 *
 * @package net.nemein.redirector
 */
class net_nemein_redirector_viewer extends midcom_baseclasses_components_request
{
    /**
     * Constructor. Connect to the parent class constructor
     */
    function __construct($topic, $config)
    {
        parent::__construct($topic, $config);
    }

    /**
     * Initialization script, which sets the request switches
     */
    function _on_initialize()
    {
        // Match /
        if (   $this->_config->get('admin_redirection')
            || !$this->_topic->can_do('net.nemein.redirector:noredirect'))
        {
            $this->_request_switch['redirect'] = array
            (
                'handler' => 'redirect'
            );
        }
        else
        {
            $this->_request_switch['redirect'] = array
            (
                'handler' => array
                (
                    'net_nemein_redirector_handler_tinyurl',
                    'list',
                ),
            );
        }

        // Match /config/
        $this->_request_switch['config'] = array
        (
            'handler' => array
            (
                'midcom_core_handler_configdm2',
                'config',
            ),
            'schemadb' => 'file:/net/nemein/redirector/config/schemadb_config.inc',
            'fixed_args' => array
            (
                'config',
            ),
        );

        // Match /create/
        $this->_request_switch['create'] = array
        (
            'handler' => array
            (
                'net_nemein_redirector_handler_tinyurl',
                'create',
            ),
            'fixed_args' => array
            (
                'create',
            ),
        );

        // Match /edit/{$tinyurl}/
        $this->_request_switch['edit'] = array
        (
            'handler' => array
            (
                'net_nemein_redirector_handler_tinyurl',
                'edit',
            ),
            'fixed_args' => array
            (
                'edit',
            ),
            'variable_args' => 1,
        );

        // Match /delete/{$tinyurl}/
        $this->_request_switch['delete'] = array
        (
            'handler' => array
            (
                'net_nemein_redirector_handler_tinyurl',
                'delete',
            ),
            'fixed_args' => array
            (
                'delete',
            ),
            'variable_args' => 1,
        );

        // Match /{$tinyurl}/
        $this->_request_switch['tinyurl'] = array
        (
            'handler' => 'redirect',
            'variable_args' => 1,
        );
    }

    /**
     * Add creation link
     *
     * @access public
     */
    function _on_handle($handler_id, $args)
    {
        if ($this->_topic->can_do('midgard:create'))
        {
            // Add the creation link to toolbar
            $this->_node_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "create/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('tinyurl')),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_event.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'n',
                )
            );
        }

        return true;
    }

    /**
     * Check for hijacked URL space
     *
     * @access public
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _can_handle_redirect($handler_id, $args, &$data)
    {
        // Process the request immediately
        if (isset($args[0]))
        {
            $mc = net_nemein_redirector_tinyurl_dba::new_collector('node', $this->_topic->guid);
            $mc->add_constraint('name', '=', $args[0]);
            $mc->add_value_property('code');
            $mc->add_value_property('url');
            $mc->execute();

            $results = $mc->list_keys();

            // No results found
            if (count($results) === 0)
            {
                return false;
            }

            // Catch first the configuration option for showing editing interface instead
            // of redirecting administrators
            if (   $this->_topic->can_do('net.nemein.redirector:noredirect')
                && !$this->_config->get('admin_redirection'))
            {
                midcom::relocate("{$this->_topic->name}/edit/{$args[0]}/");
            }

            foreach ($results as $guid => $array)
            {
                $url = $mc->get_subkey($guid, 'url');
                $code = $mc->get_subkey($guid, 'code');
                break;
            }

            // Redirection HTTP code
            $code = $tinyurl->code;
            if (!$code)
            {
                $code = $this->_config->get('redirection_code');
            }

            midcom::relocate($url, $code);
            // This will exit
        }

        return true;
    }

    /**
     * Process the redirect request
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_redirect($handler_id, $args, &$data)
    {
        $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

        if (   is_null($this->_config->get('redirection_type'))
            || (   $this->_topic->can_do('net.nemein.redirector:noredirect')
                && !$this->_config->get('admin_redirection')))
        {
            // No type set, redirect to config
            midcom::relocate("{$prefix}config/");
            // This will exit
        }

        // Get the topic link and relocate accordingly
        $data['url'] = net_nemein_redirector_viewer::topic_links_to($data);

        // Metatag redirection
        if ($this->_config->get('redirection_metatag'))
        {
            $data['redirection_url'] = $data['url'];
            $data['redirection_speed'] = $this->_config->get('redirection_metatag_speed');

            midcom::add_meta_head
            (
                array
                (
                    'http-equiv' => 'refresh',
                    'content' => "{$data['redirection_speed']};url={$data['url']}",
                )
            );

            return true;
        }

        midcom::relocate($data['url'], $this->_config->get('redirection_code'));
        // This will exit
    }

    /**
     * Show redirection page.
     *
     * @access public
     * @param string $handler_id    Handler ID
     * @param array &$data          Pass-by-reference of request data
     */
    function _show_redirect($handler_id, &$data)
    {
        midcom_show_style('redirection-page');
    }

    /**
     * Get the URL where the topic links to
     *
     * @static
     * @access public
     * @param array &$data   Request data
     * @return String containing redirection URL
     */
    static function topic_links_to(&$data)
    {
        $config =& $data['config'];

        switch ($data['config']->get('redirection_type'))
        {
            case 'node':
                $nap = new midcom_helper_nav();
                $id = $data['config']->get('redirection_node');

                if (is_string($id))
                {
                    $topic = new midcom_db_topic($id);

                    if (   !$topic
                        || !$topic->guid)
                    {
                        break;
                    }

                    $id = $topic->id;
                }

                $node = $nap->get_node($id);

                // Node not found, fall through to configuration
                if (!$node)
                {
                    break;
                }

                return $node[MIDCOM_NAV_FULLURL];

            case 'subnode':
                $nap = new midcom_helper_nav();
                $nodes = $nap->list_nodes($nap->get_current_node());

                // Subnodes not found, fall through to configuration
                if (count($nodes) == 0)
                {
                    break;
                }

                // Redirect to first node
                $node = $nap->get_node($nodes[0]);
                return $node[MIDCOM_NAV_FULLURL];

            case 'permalink':
                $url = midcom::permalinks->resolve_permalink($data['config']->get('redirection_guid'));

                if ($url)
                {
                    return $url;
                }

            case 'url':
                if ($data['config']->get('redirection_url') != '')
                {
                    $url = $data['config']->get('redirection_url');

                    // Support varying host prefixes
                    if (strpos($url, '__PREFIX__') !== false)
                    {
                        $url = str_replace('__PREFIX__', midcom_connection::get_url('self'), $url);
                    }

                    return $url;
                }
                // Otherwise fall-through to config
        }

        $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
        return "{$prefix}config/";
    }
}
?>