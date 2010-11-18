<?php
/**
 * @package net.nemein.rss
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package net.nemein.rss
 */
class net_nemein_rss_feed_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'net_nemein_rss_feed';
    
    function __construct($id = null)
    {
        return parent::__construct($id);
    }
        
    static function new_query_builder()
    {
        return midcom::dbfactory()->new_query_builder(__CLASS__);
    }

    static function new_collector($domain, $value)
    {
        return midcom::dbfactory()->new_collector(__CLASS__, $domain, $value);
    }

    function _on_loaded()
    {
        if (   $this->title == ''
            && $this->id)
        {
            $this->title = "Feed #{$this->id}";
        }

        return parent::_on_loaded();
    }
}
?>