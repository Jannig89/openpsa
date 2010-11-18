<?php
/**
 * @package org.openpsa.documents
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Wrapper for midcom_db_topic
 *
 * @package org.openpsa.documents
 *
 */
class org_openpsa_documents_directory extends midcom_db_topic
{
    function __construct($identifier = NULL)
    {
        return parent::__construct($identifier);
    }

    function _on_updated()
    {
        $this->_update_parent_timestamp();

        $ownerwg = $this->parameter('org.openpsa.core', 'orgOpenpsaOwnerWg');
        $accesstype = $this->parameter('org.openpsa.core', 'orgOpenpsaAccesstype');

        if (   $ownerwg
            && $accesstype)
        {
            // Sync the object's ACL properties into MidCOM ACL system
            $sync = new org_openpsa_core_acl_synchronizer();
            $sync->write_acls($this, $ownerwg, $accesstype);
            return true;
        }
    }

    function _on_created()
    {
        $this->_update_parent_timestamp();
    }

    function _on_deleted()
    {
        $this->_update_parent_timestamp();
    }

    private function _update_parent_timestamp()
    {
        $parent = $this->get_parent();
        if (   $parent 
            && $parent->component == 'org.openpsa.documents')
        {
            midcom::auth->request_sudo('org.openpsa.documents');

            $parent = new org_openpsa_documents_directory($parent);
            $parent->_use_rcs = false;
            $parent->_use_activtystream = false;
            $parent->update();

            midcom::auth->drop_sudo();
        }
    }


}
?>