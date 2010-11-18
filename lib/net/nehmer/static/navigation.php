<?php
/**
 * @package net.nehmer.static
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: navigation.php 26058 2010-05-07 15:03:42Z jval $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * n.n.static NAP interface class
 *
 * This class has been rewritten for MidCOM 2.6 utilizing all of the currently
 * available state-of-the-art technology.
 *
 * See the individual member documentations about special NAP options in use.
 *
 * @package net.nehmer.static
 */

class net_nehmer_static_navigation extends midcom_baseclasses_components_navigation
{
    /**
     * The topic in which to look for articles. This defaults to the current content topic
     * unless overridden by the symlink topic feature.
     *
     * @var midcom_db_topic
     * @access private
     */
    private $_content_topic = null;

    /**
     * Simple constructor, calls base class.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns all leaves for the current content topic.
     *
     * It will hide the index leaf from the NAP information unless we are in Autoindex
     * mode. The leaves' title are used as a description within NAP, and the toolbar will
     * contain edit and delete links.
     */
    public function get_leaves()
    {
        $leaves = array();
        if ($this->_config->get('hide_navigation'))
        {
            return $leaves;
        }

        // Get the required information with midgard_collector
        $qb = midcom_db_article::new_query_builder();
        $qb->add_constraint('up', '=', 0);

        // Check whether to include the linked articles to navigation list
        if (!$this->_config->get('enable_article_links'))
        {
            $qb->add_constraint('topic', '=', $this->_content_topic->id);
        }
        else
        {
            // Get the linked articles as well
            $mc = net_nehmer_static_link_dba::new_collector('topic', $this->_content_topic->id);
            $mc->add_value_property('article');
            $mc->add_constraint('topic', '=', $this->_content_topic->id);
            $mc->execute();

            $links = $mc->list_keys();

            $qb->begin_group('OR');
                $qb->add_constraint('topic', '=', $this->_content_topic->id);
                foreach ($links as $guid => $array)
                {
                    $id = $mc->get_subkey($guid, 'article');
                    $qb->add_constraint('id', '=', $id);
                }
            $qb->end_group();
        }

        $qb->add_constraint('metadata.navnoentry', '=', 0);
        $qb->add_constraint('name', '<>', '');

        // Unless in Auto-Index mode or the index article is hidden, we skip the index article.
        if (   !$this->_config->get('autoindex')
            && !$this->_config->get('indexinnav'))
        {
            $qb->add_constraint('name', '<>', 'index');
        }

        $sort_order = 'ASC';
        $sort_property = $this->_config->get('sort_order');
        if (strpos($sort_property, 'reverse ') === 0)
        {
            $sort_order = 'DESC';
            $sort_property = substr($sort_property, strlen('reverse '));
        }
        if (strpos($sort_property, 'metadata.') === false)
        {
            $article = new midgard_article();
            if (!property_exists($article, $sort_property))
            {
                $sort_property = 'metadata.' . $sort_property;
            }
        }
        $qb->add_order($sort_property, $sort_order);

        // Sort items with the same primary sort key by title.
        $qb->add_order('title');

        $articles = $qb->execute();

        foreach ($articles as $article)
        {
            $article_url = "{$article->name}/";
            if ($article->name == 'index')
            {
                $article_url = '';
            }
            $leaves[$article->id] = array
            (
                MIDCOM_NAV_URL => $article_url,
                MIDCOM_NAV_NAME => ($article->title) ? $article->title : $article->name,
                MIDCOM_NAV_GUID => $article->guid,
                MIDCOM_NAV_OBJECT => $article,
            );
        }

        return $leaves;
    }

    /**
     * This event handler will determine the content topic, which might differ due to a
     * set content symlink.
     */
    function _on_set_object()
    {
        $this->_determine_content_topic();
        return true;
    }

    /**
     * Set the content topic to use. This will check against the configuration setting 'symlink_topic'.
     * We don't do sanity checking here for performance reasons, it is done when accessing the topic,
     * that should be enough.
     *
     * @access protected
     */
    function _determine_content_topic()
    {
        $guid = $this->_config->get('symlink_topic');
        if (is_null($guid))
        {
            // No symlink topic
            // Workaround, we should talk to a DBA object automatically here in fact.
            $this->_content_topic = midcom_db_topic::get_cached($this->_topic->id);
            debug_pop();
            return;
        }

        $this->_content_topic = midcom_db_topic::get_cached($guid);

        if (! $this->_content_topic)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('Failed to open symlink content topic, (might also be an invalid object) last Midgard Error: ' . midcom_connection::get_error_string(),
                MIDCOM_LOG_ERROR);
            debug_pop();
            midcom::generate_error(MIDCOM_ERRNOTFOUND, "Failed to open symlink content topic {$guid}.");
            // This will exit.
        }
    }
}
?>
