<?php
/**
 * @package midcom.db
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: host.php 24475 2009-12-16 12:05:15Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM level replacement for the Midgard Host record with framework support.
 * 
 * Hosts do not have a parent object.
 * 
 * Note, as with all MidCOM DB layer objects, you should not use the get_by*
 * operations directly, instead, you have to use the constructor's $id parameter.
 * 
 * Also, all QueryBuilder operations need to be done by the factory class 
 * obtainable through the statically callable new_query_builder() DBA methods.
 * 
 * @see midcom_services_dbclassloader
 * @package midcom.db
 */
class midcom_db_host extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'midgard_host';

    /**
     * The default constructor will create an empty object. Optionally, you can pass
     * an object ID or GUID to the object which will then initialize the object with
     * the corresponding DB instance.
     *
     * @param mixed $id A valid object ID or GUID, omit for an empty object.
     */
    function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * Overwrite the query builder getter with a version retrieving the right type.
     * We need a better solution here in DBA core actually, but it will be difficult to
     * do this as we cannot determine the current class in a polymorphic environment without
     * having a this (this call is static).
     * 
     * @static
     */
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

    function get_label()
    {
        if (   $this->port == 0
            || $this->port == 80)
        {
            return "{$this->name}{$this->prefix}";
        }
        return "{$this->name}:{$this->port}{$this->prefix}";
    }

}


?>