<?php
/**
 * @package org.openpsa.relatedto
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped base class, keep logic here
 *
 * @package org.openpsa.relatedto
 */
class org_openpsa_relatedto_journal_entry_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'org_openpsa_relatedto_journal_entry';

    function __construct($id = null)
    {
        $this->_use_rcs = false;
        $this->_use_activitystream = false;
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

    function get_parent_guid_uncached()
    {
        return $this->linkGuid;
    }

}
?>
