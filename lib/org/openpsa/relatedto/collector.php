<?php
/**
 * @package org.openpsa.relatedto
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: collector.php 25199 2010-02-24 22:36:17Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Wrapper for midcom_core_collector. It adds some additional logic to return related objects directly
 *
 * @package org.openpsa.relatedto
 */
class org_openpsa_relatedto_collector extends midcom_core_collector
{
    /**
     * Which type of links are we looking for, incoming or outgoing
     * 
     * @var string
     */
    private $_direction = '';
    
    /**
     * The prefix for query constraints concerning the object(s) at hand
     * 
     * @var string
     */
    private $_object_prefix = '';

    /**
     * The prefix for query constraints concerning the objects we're looking for
     * 
     * @var string
     */
    private $_other_prefix = '';

    /**
     * The class(es) of the objects we're looking for
     * 
     * @var string
     */
    private $_target_classes = array();

    /**
     * Additional constraints for the QBs used to find the related objects
     * 
     * @var array
     */
    private $_object_constraints = array();

    /**
     * Limit for the QBs used to find the related objects
     * 
     * @var integer
     */
    private $_object_limit = 0;

    /**
     * Orders for the QBs used to find the related objects
     * 
     * @var array
     */
    private $_object_orders = array();
    
    /**
     * Constructor, takes one or more object guids and classnames and constructs a collector accordingly.
     * 
     * Attention: At least one of these arguments has to be a string
     * 
     * @param mixed $guids One or more object guids
     * @param mixed $classes One or more target classes
     * @param string $direction incoming or outgoing
     */
    function __construct($guids, $classes, $direction = 'incoming')
    {
        $this->_set_direction($direction);
        
        //workaround for #1648
        midcom::load_library('org.openpsa.relatedto');

        if (is_string($guids))
        {
            parent::__construct('org_openpsa_relatedto_dba', $this->_object_prefix . 'Guid', $guids);
            $this->initialize();
            if (is_string($classes))
            {
            	$this->add_constraint($this->_other_prefix . 'Class', '=', $classes);
            }
            else
            {
            	$this->add_constraint($this->_other_prefix . 'Class', 'IN', $classes);
            }
        }
        else if (is_string($classes))
        {
            parent::__construct('org_openpsa_relatedto_dba', $this->_other_prefix . 'Class', $classes);
            $this->initialize();
            if (is_string($guids))
            {
                $this->add_constraint($this->_object_prefix . 'Guid', '=', $guids);
            }
            else
            {
                $this->add_constraint($this->_object_prefix . 'Guid', 'IN', $guids);
            }
        }
        else
        {
            midcom::generate_error(MIDCOM_ERRCRIT,
                'None of the arguments was passed as a string, cannot continue');
            // This will exit.
        }
        
        //save target classes for later use
        if (is_string($classes))
        {
            $this->_target_classes = array($classes);
        }
        else
        {
            $this->_target_classes = $classes;
        }
        
        $this->add_value_property($this->_other_prefix . 'Guid');
    }

    private function _set_direction($dir)
    {
    	$this->_direction = $dir;
        if ($dir == 'incoming')
        {
            $this->_object_prefix = 'to';
            $this->_other_prefix = 'from';
        }
        else
        {
            $this->_object_prefix = 'from';
            $this->_other_prefix = 'to';
        }
    }

    /**
     * Helper function that saves object QB constraints for later use
     *
     * @param string $field The DB field
     * @param string $operator The constraint operator
     * @param mixed $value The constraint value 
     */
    public function add_object_constraint($field, $operator, $value)
    {
        $this->_object_constraints[] = array
        (
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        );
    }

    /**
     * Helper function that saves object QB constraints for later use
     *
     * @param string $field The DB field
     * @param string $direction The direction (ASC, DESC)
     */
    public function add_object_order($field, $direction)
    {
        $this->_object_orders[] = array
        (
            'field' => $field,
            'direction' => $direction
        );
    }

    /**
     * Helper function that saves object QB constraints for later use
     *
     * @param string $field The DB field
     * @param string $direction The direction (ASC, DESC)
     */
    public function set_object_limit($limit)
    {
        $this->_object_limit = $limit;
    }

    /**
     * Helper function that applies constraints (if any) to the final object QBs
     *
     * @param midcom_core_querybuilder &$qb the QB instance in question
     */
    private function _apply_object_constraints(&$qb)
    {
        if (empty($this->_object_constraints))
        {
            return;
        }
        foreach ($this->_object_constraints as $constraint)
        {
            $qb->add_constraint($constraint['field'], $constraint['operator'], $constraint['value']);
        }
    }

    /**
     * Helper function that applies orders (if any) to the final object QBs
     *
     * @param midcom_core_querybuilder &$qb the QB instance in question
     */
    private function _apply_object_orders(&$qb)
    {
        if (empty($this->_object_orders))
        {
            return;
        }
        foreach ($this->_object_orders as $order)
        {
            $qb->add_order($order['field'], $order['direction']);
        }
    }

    /**
     * Helper function that applies the limit (if any) to the final object QBs
     *
     * @param midcom_core_querybuilder &$qb the QB instance in question
     */
    private function _apply_object_limit(&$qb)
    {
        if ($this->_object_limit == 0)
        {
            return;
        }
        $qb->set_limit($this->_object_limit);
    }

    /**
     * Helper function that returns an array of DBA objects grouped by the specified key
     * 
     * @param string $key The column the results should be grouped by
     */
    public function get_related_objects_grouped_by($key)
    {
        $entries = array();
        $guids = array();
        
        $this->add_value_property($key);

        $this->add_constraint('status', '<>', ORG_OPENPSA_RELATEDTO_STATUS_NOTRELATED);
        $this->execute();
        $relations = $this->list_keys();

        if (sizeof($relations) == 0)
        {
            return $entries;
        }

        foreach ($relations as $guid => $empty)
        {
            $group_value = $this->get_subkey($guid, $key);
            if (!array_key_exists($group_value, $guids))
            {
                $guids[$group_value] = array();
            }
            $guids[$group_value][] = $this->get_subkey($guid, $this->_other_prefix . 'Guid');
        }

        foreach ($guids as $group_value => $grouped_guids)
        {
            foreach ($this->_target_classes as $classname)
            {
                $qb = call_user_func(array($classname, 'new_query_builder'));
                $qb->add_constraint('guid', 'IN', $grouped_guids);
                $this->_apply_object_constraints($qb);
                $this->_apply_object_orders($qb);
                $this->_apply_object_limit($qb);
                $entries[$group_value] = array();
                $entries[$group_value] = array_merge($entries[$group_value], $qb->execute());
            }
        }

        return $entries;
    }

    
    /**
     * Helper function that returns an array of DBA objects
     * 
     * @param string $component A component name to further narrow down the results 
     */
    public function get_related_objects($component = false)
    {
        $entries = array();

        $guids = $this->get_related_guids($component);

        if (sizeof($guids) == 0)
        {
            return $entries;
        }

        foreach ($this->_target_classes as $classname)
        {
            $qb = call_user_func(array($classname, 'new_query_builder'));
            $qb->add_constraint('guid', 'IN', $guids);
            $this->_apply_object_constraints($qb);
            $this->_apply_object_orders($qb);
            $this->_apply_object_limit($qb);
            $entries = array_merge($entries, $qb->execute());
        }

        return $entries;
    }
    
    /**
     * Helper function that returns an array of related object GUIDs 
     *
     * @param string $component A component name to further narrow down the results 
     * @return array Array of GUIDs
     */
    public function get_related_guids($component = false)
    {
        $guids = array();

        if ($component)
        {
            $this->add_constraint($this->_other_prefix . 'Component', '=', $component);
        }

        $this->add_constraint('status', '<>', ORG_OPENPSA_RELATEDTO_STATUS_NOTRELATED);
        $this->execute();
        $relations = $this->list_keys();

        if (sizeof($relations) == 0)
        {
            return $guids;
        }

        foreach ($relations as $guid => $empty)
        {
            $guids[] = $this->get_subkey($guid, $this->_other_prefix . 'Guid');
        }
        
        return $guids;
    }
}
?>