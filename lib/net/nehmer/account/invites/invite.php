<?php
/**
 * @package net.nehmer.account
 * @author Henri Bergius, http://bergie.iki.fi
 * @copyright Nemein Oy, http://www.nemein.com
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapper class for invite objects
 *
 * @package net.nehmer.account
 */
class net_nehmer_account_invites_invite_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'net_nehmer_accounts_invites_invite';
    
    function __construct($src = null)
    {
        parent::__construct($src);
    }
    
    static function new_query_builder()
    {
        return midcom::dbfactory()->new_query_builder(__CLASS__);
    }

    static function new_collector($domain, $value)
    {
        return midcom::dbfactory()->new_collector(__CLASS__, $domain, $value);
    }
    
    function get_class_magic_default_privileges()
    {
        return Array (
            'EVERYONE' => Array(),
            'ANONYMOUS' => Array(),
            'USERS' => Array('midgard:create' => MIDCOM_PRIVILEGE_ALLOW),
        );
    }
}

?>