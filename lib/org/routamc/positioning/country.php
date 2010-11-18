<?php
/**
 * @package org.routamc.positioning
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: country.php 6148 2007-06-02 20:49:53Z bergie $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapper class for country objects
 *
 * @package org.routamc.positioning
 */
class org_routamc_positioning_country_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'org_routamc_positioning_country';
    
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
     * Human-readable label for cases like Asgard navigation
     */
    function get_label()
    {
        return $this->name;
    }

    /**
     * Don't save country if another country with name exists
     */
    function _on_creating()
    {
        $qb = org_routamc_positioning_country_dba::new_query_builder();
        $qb->add_constraint('name', '=', (string)$this->name);
        $qb->set_limit(1);
        $matches = $qb->execute_unchecked();
        if (count($matches) > 0)
        {
            // We don't need to save duplicate entries
            midcom_connection::set_error(MGD_ERR_DUPLICATE);
            return false;
        }
        return parent::_on_creating();
    }

    static function get_by_name($name)
    {
        // Seek by strict city name first
        $qb = org_routamc_positioning_country_dba::new_query_builder();
        $qb->add_constraint('name', 'LIKE', $name);
        $qb->set_limit(1);
        $matches = $qb->execute_unchecked();
        if (count($matches) > 0)
        {
            return $matches[0];
        }
        
        // Strict name didn't match, seek by alternate names
        $qb = org_routamc_positioning_country_dba::new_query_builder();
        $qb->add_constraint('alternatenames', 'LIKE', "%{$name}%");
        // Most likely we're interested in the biggest city that matches
        $qb->add_order('population', 'DESC');
        $qb->set_limit(1);
        $matches = $qb->execute_unchecked();
        if (count($matches) > 0)
        {
            return $matches[0];
        }

        return false;
    }
    
    /**
     * Get the country object by code
     * 
     * @static
     * @access public
     * @param string $code                            Either two or three character representation
     * @return org_routamc_positioning_country_dba    Country object or null on failure
     */
    static function get_by_code($code)
    {
        $qb = self::new_query_builder();
        $qb->begin_group('OR');
            $qb->add_constraint('code', '=', $code);
            $qb->add_constraint('code3', '=', $code);
        $qb->end_group();
        
        $matches = $qb->execute();
        
        if (!isset($matches[0]))
        {
            return null;
        }
        
        return $matches[0];
    }
}
?>