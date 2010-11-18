<?php

/**
 * @package net.nehmer.static
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: interfaces.php 25987 2010-05-04 14:10:09Z bergie $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * n.n.static MidCOM interface class.
 *
 * @package net.nehmer.static
 */
class net_nehmer_static_interface extends midcom_baseclasses_components_interface
{
    /**
     * Constructor.
     *
     * Nothing fancy, loads all script files and the datamanager library.
     */
    function __construct()
    {
        parent::__construct();

        $this->_component = 'net.nehmer.static';
        $this->_autoload_libraries = Array
        (
            'midcom.helper.datamanager2',
        );
    }

    /**
     * Iterate over all articles and create index record using the datamanager indexer
     * method.
     */
    function _on_reindex($topic, $config, &$indexer)
    {
        if (is_null($config->get('symlink_topic')))
        {
            $qb = midcom::dbfactory()->new_query_builder('midcom_db_article');
            $qb->add_constraint('topic', '=', $topic->id);
            $result = midcom::dbfactory()->exec_query_builder($qb);

            if ($result)
            {
                $schemadb = midcom_helper_datamanager2_schema::load_database($config->get('schemadb'));
                $datamanager = new midcom_helper_datamanager2_datamanager($schemadb);
                if (! $datamanager)
                {
                    debug_add('Warning, failed to create a datamanager instance with this schemapath:' . $config->get('schemadb'),
                        MIDCOM_LOG_WARN);
                    continue;
                }

                foreach ($result as $article)
                {
                    if (! $datamanager->autoset_storage($article))
                    {
                        debug_add("Warning, failed to initialize datamanager for Article {$article->id}. Skipping it.", MIDCOM_LOG_WARN);
                        continue;
                    }

                    net_nehmer_static_viewer::index($datamanager, $indexer, $topic);
                }
            }
        }
        else
        {
            debug_add("The topic {$topic->id} is symlinked to another topic, skipping indexing.");
        }

        debug_pop();
        return true;
    }

    /**
     * Simple lookup method which tries to map the guid to an article of out topic.
     */
    function _on_resolve_permalink($topic, $config, $guid)
    {
        $topic_guid = $config->get('symlink_topic');
        if (   !empty($topic_guid)
            && mgd_is_guid($topic_guid))
        {
            $new_topic = new midcom_db_topic($topic_guid);
            // Validate topic.

            if (   is_object($new_topic)
                && isset($new_topic->guid)
                && !empty($new_topic->guid))
            {
                $topic = $new_topic;
            }
        }

        $article = new midcom_db_article($guid);
        if (   ! $article
            || $article->topic != $topic->id)
        {
            return null;
        }
        if (   $article->name == 'index'
            && ! $config->get('autoindex'))
        {
            return '';
        }

        return "{$article->name}/";
    }

    public function get_opengraph_default($object)
    {
        return 'article';
    }
}
?>
