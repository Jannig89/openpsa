<?php
/**
 * @package midcom.db
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: virtual.php 23014 2009-07-27 15:44:43Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM DBA class for MidCOM virtual groups
 *
 * @package midcom.db
 */
class midcom_db_group_virtual extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'midcom_core_group_virtual_db';

    function __construct($id=null)
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
    
    static function &get_cached($src)
    {
        return midcom::dbfactory()->get_cached(__CLASS__, $src);
    }
}

?>