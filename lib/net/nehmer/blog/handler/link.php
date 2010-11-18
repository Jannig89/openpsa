<?php
/**
 * @package net.nehmer.blog
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: create.php 21013 2009-03-11 11:18:37Z rambo $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * n.n.blog create page handler
 *
 * @package net.nehmer.blog
 */
class net_nehmer_blog_handler_link extends midcom_baseclasses_components_handler
{
    /**
     * The content topic to use
     *
     * @var midcom_db_topic
     * @access private
     */
    var $_content_topic = null;

    /**
     * The article which has been created
     *
     * @var midcom_db_article
     * @access private
     */
    var $_article = null;

    /**
     * The article link which has been created
     *
     * @var net_nehmer_blog_link_dba
     * @access private
     */
    var $_link = null;

    /**
     * The Controller of the article used for editing
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
     * The defaults to use for the new article.
     *
     * @var Array
     * @access private
     */
    var $_defaults = Array();

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    function _prepare_request_data()
    {
        $this->_request_data['controller'] =& $this->_controller;
        $this->_request_data['indexmode'] =& $this->_indexmode;
        $this->_request_data['schema'] =& $this->_schema;
        $this->_request_data['schemadb'] =& $this->_schemadb;
    }
    /**
     * Simple default constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Maps the content topic from the request data to local member variables.
     */
    function _on_initialize()
    {
        $this->_content_topic =& $this->_request_data['content_topic'];
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_link'));
    }

    /**
     * Internal helper, fires up the creation mode controller. Any error triggers a 500.
     *
     * @access private
     */
    function _load_controller()
    {
        if (isset($_GET['article']))
        {
            $this->_defaults['article'] = $_GET['article'];
        }
        else
        {
            $this->_defaults['topic'] = $this->_topic->id;
        }
        
        $this->_controller = midcom_helper_datamanager2_controller::create('create');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->defaults = $this->_defaults;
        $this->_controller->callback_object =& $this;

        if (! $this->_controller->initialize())
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to initialize a DM2 create controller.");
            // This will exit.
        }
    }

    /**
     * DM2 creation callback, binds to the current content topic.
     */
    function &dm2_create_callback (&$controller)
    {
        $this->_link = new net_nehmer_blog_link_dba();
        $this->_link->topic = $this->_topic->id;
        
        if (!$this->_link->create())
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_print_r('We operated on this object:', $this->_link);
            debug_pop();
            midcom::generate_error(MIDCOM_ERRCRIT,
                'Failed to create a new article, cannot continue. Last Midgard error was: '. midcom_connection::get_error_string());
            // This will exit.
        }
        
        return $this->_link;
    }
    
    /**
     * Displays an article edit view.
     *
     * Note, that the article for non-index mode operation is automatically determined in the can_handle
     * phase.
     *
     * If create privileges apply, we relocate to the index creation article
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_create($handler_id, $args, &$data)
    {
        $this->_content_topic->require_do('midgard:create');

        if (!$this->_config->get('enable_article_links'))
        {
            midcom::generate_error(MIDCOM_ERRNOTFOUND, 'Article linking disabled');
        }

        $this->_load_controller();

        switch ($this->_controller->process_form())
        {
            case 'save':
                $this->_article = new midcom_db_article($this->_link->article);
                midcom::relocate("{$this->_article->name}/");
                // This will exit
            
            case 'cancel':
                if (isset($_GET['article']))
                {
                    $article = new midcom_db_article($_GET['article']);
                    
                    if ($this->_config->get('view_in_url'))
                    {
                        $prefix = 'view/';
                    }
                    else
                    {
                        $prefix = '';
                    }
                    
                    if (   $article
                        && $article->guid)
                    {
                        midcom::relocate("{$prefix}{$article->name}/");
                    }
                }
                
                midcom::relocate('');
                // This will exit
        }
        
        $this->_prepare_request_data();
        $title = sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('article link'));
        midcom::set_pagetitle("{$this->_topic->extra}: {$title}");
        $this->_update_breadcrumb_line($handler_id);
        return true;
    }
    

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     */
    function _update_breadcrumb_line()
    {
        $tmp = Array();

        $tmp[] = Array
        (
            MIDCOM_NAV_URL => "create/link/",
            MIDCOM_NAV_NAME => sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('article link')),
        );

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     * Shows the loaded article.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_create ($handler_id, &$data)
    {
        midcom_show_style('admin-create-link');
    }
}
?>
