<?php
/**
 * @package net.nemein.wiki
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package net.nemein.wiki
 */
class net_nemein_wiki_link_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'net_nemein_wiki_link';
    
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

    function get_parent_guid_uncached()
    {
        // FIXME: Midgard Core should do this
        if ($this->frompage != 0)
        {
            $parent = new net_nemein_wiki_wikipage($this->frompage);
            return $parent->guid;
        }
        else
        {
            return null;
        }
    }
}
?>