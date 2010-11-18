<?php
/**
 * @package net.nehmer.blog
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: aerodrome.php 3630 2006-06-19 10:03:59Z bergius $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapper class for objects
 *
 * @package net.nehmer.blog
 */
class net_nehmer_blog_link_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'net_nehmer_blog_link';
    
    /**
     * Connect to the parent class constructor and give a possibility for
     * fetching a record by ID or GUID
     * 
     * @access public
     * @param mixed $id
     */
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
        
    /**
     * Check if all the fields contain required information upon creation
     * 
     * @access public
     * @return boolean Indicating success
     */
    function _on_creating()
    {
        return true;
    }
    
    /**
     * Check if all the fields contain required information upon update
     * 
     * @access public
     * @return boolean Indicating success
     */
    function _on_updating()
    {
        if (   !$this->topic
            || !$this->article)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add('Failed to update the link, either topic or article was undefined', MIDCOM_LOG_WARN);
            debug_pop();
            midcom_connection::set_error(MGD_ERR_ERROR);
            return false;
        }
        
        return true;
    }
}
?>
