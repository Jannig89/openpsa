<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_buddy_dba extends midcom_core_dbaobject
{

    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'net_nehmer_buddylist_entry_db';

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
    
    static function &get_cached($src)
    {
        return midcom::dbfactory()->get_cached(__CLASS__, $src);
    }
    
    function get_parent_guid_uncached()
    {
        if ($this->account)
        {
            $person = new org_openpsa_contacts_person_dba($this->account);
            if ($person)
            {
                return $person->guid;
            }
        }
        else
        {
            // Not saved buddy, return user himself
            return midcom::auth->user->get_storage();
        }
        return null;
    }

    /**
     * Creation handler, grants owner permissions to the buddy user for this
     * buddy object, so that he can later approve / reject the request. For
     * safety reasons, the owner privilege towards the account user is also
     * created, so that there is no discrepancy later in case administrators
     * create the object.
     */
    function _on_created()
    {
    	if ($user = midcom::auth->get_user($this->buddy))
    	{
	        $this->set_privilege('midgard:owner', $user);
    	}
    	if ($user = midcom::auth->get_user($this->account))
    	{    	
	        $this->set_privilege('midgard:owner', $user);
    	}
    }

    /**
     * The pre-creation hook sets the added field to the current timestamp if and only if
     * it is unset.
     */
    function _on_creating()
    {
        if (! $this->added)
        {
            $this->added = time();
        }
        return true;
    }
}
?>