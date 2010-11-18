<?php
/**
 * @package org.openpsa.documents
 * @author Nemein Oy, http://www.nemein.com/
 * @version $Id: navigation.php 22916 2009-07-15 09:53:28Z flack $
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.documents NAP interface class.
 * @package org.openpsa.documents
 */
class org_openpsa_documents_navigation extends midcom_baseclasses_components_navigation
{

    function get_leaves() 
    {

        $leaves = array ();
        return $leaves;

        // OLD STUFF:
        // List the documents
        $qb = org_openpsa_documents_document_dba::new_query_builder();
        $qb->add_constraint('topic', '=', $this->_topic->id);
        $qb->add_constraint('nextVersion', '=', 0);
        $qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_DOCUMENT);
        $ret = $qb->execute();
        if (   is_array($ret)
            && count($ret) > 0)
        {
            $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

            foreach ($ret as $document)
            {
                $leaves[$document->id] = array 
                (
                    MIDCOM_NAV_URL => 'document/' . $document->guid . '/',
                    MIDCOM_NAV_NAME => ($document->title != "") ? $document->title : "document #" . $document->id,
                    MIDCOM_NAV_OBJECT => $document,
                    MIDCOM_NAV_GUID => $document->guid,
                );
            }
        }
        return $leaves;
    }
    
}

?>