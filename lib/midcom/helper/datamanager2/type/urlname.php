<?php
/**
 * @package midcom.helper.datamanager2
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: urlname.php 25328 2010-03-18 19:10:35Z indeyets $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/** @ignore */
require_once(MIDCOM_ROOT . '/midcom/helper/datamanager2/type/text.php');

/**
 * Datamanager 2 URL-name datatype, text encapsulated here is checked for
 * name cleanliness and url-safety semantics and for uniqueness. This extends 
 * the normal text datatype with the following config additions:
 *
 * <b>New configuration options:</b>
 *
 * - <i>string title_field:</i> Defaults to 'title', this is the name of the field 
 *   (in same schema) to use for title information (used when autogenerating values)
 * - <i>bool allow_catenate:</i> Defaults to false, if this is set to true then on 
 *   name value clash, we autogenerate a new unique name and use it transparently
 *   in stead of raisin validation error.
 * - <i>bool allow_unclean:</i> Defaults to false, if this is set to true then we
 *   do not check name for "cleanlines"
 *
 * @package midcom.helper.datamanager2
 */
class midcom_helper_datamanager2_type_urlname extends midcom_helper_datamanager2_type_text
{
    /**
     * Do we allow automatic catenation to make the name unique?
     *
     * @type boolean
     * @access public
     */
    var $allow_catenate = false;

    /**
     * Do we allow "unclean" names
     *
     * @type boolean
     * @access public
     */
    var $allow_unclean = false;
    
    /**
     * The field (in the same schema) that we use for title value
     *
     * @type string
     * @access public
     */
    var $title_field = 'title';

    /**
     * Keep the original value in store
     */
    var $_orig_value = null;

    /**
     * This event handler is called after construction, so passing references to $this to the
     * outside is safe at this point.
     *
     * @return boolean Indicating success, false will abort the type construction sequence.
     * @access protected
     */
    function _on_initialize()
    {
        // We need the reflector later
        midcom::componentloader()->load('midcom.helper.reflector');
        /**
         * If write_privilege is not set, default to midcom:urlname.
         *
         * NOTE: In theory we're a bit late here with manipulating this value
         * but since it ATM only affects widgets (which are initialized later) anyway
         * and in fact we want it to only freeze the widget this is fine
         */
        $schema =& $this->_datamanager->schema->fields[$this->name];
        if (!isset($schema['write_privilege']))
        {
            $schema['write_privilege'] = array
            (
                'privilege' => 'midcom:urlname',
            );
        }
        return true;
    }

    function convert_from_storage($source)
    {
        $this->_orig_value = $source;
        parent::convert_from_storage($source);
    }

    /**
     * Helper to get copy in stead of reference to given object
     *
     * This is to avoid messing with the original values when using the name uniqueness checks
     */
    function _copy_object($object)
    {
        return $object;
    }

    /**
     * Make sure our name is nice and clean
     *
     * @see http://trac.midgard-project.org/ticket/809
     */
    function _on_validate()
    {
        $schema = $this->storage->_schema->fields[$this->name];
        $copy = $this->_copy_object($this->storage->object);
        $property = $schema['storage']['location'];

        if (empty($this->value))
        {
            if (   isset($this->_datamanager->types[$this->title_field])
                && $this->_datamanager->types[$this->title_field]->value)
            {
                $copy->{$property} = midcom_generate_urlname_from_string($this->_datamanager->types[$this->title_field]->value);
                $this->value = midcom_helper_reflector_tree::generate_unique_name($copy);
            }
        }

        $copy->{$property} = $this->value;

        if (!midcom_helper_reflector::name_is_safe($copy, $property))
        {
            $this->validation_error = sprintf($this->_l10n->get('type urlname: name is not "URL-safe", try "%s"'), midcom_generate_urlname_from_string($this->value)); 
            return false;
        }

        if (   !$this->allow_unclean
            && !midcom_helper_reflector::name_is_clean($copy, $property))
        {
            $this->validation_error = sprintf($this->_l10n->get('type urlname: name is not "clean", try "%s"'), midcom_generate_urlname_from_string($this->value)); 
            return false;
        }

        if (!midcom_helper_reflector_tree::name_is_unique($copy))
        {
            $new_name = midcom_helper_reflector_tree::generate_unique_name($copy);
            if ($this->allow_catenate)
            {
                // If allowed to, silently use the generated name
                $this->value = $new_name;
                $this->_orig_value = $new_name;
                $copy->{$property} = $this->value;
            }
            else
            {
                $this->validation_error = sprintf($this->_l10n->get('type urlname: name is already taken, try "%s"'), $new_name); 
                return false;
            }
        }

        return true;
    }
}

?>