<?php
/**
 * @package midcom.helper.datamanager2
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: date.php 24927 2010-01-27 13:58:33Z jval $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/** We need the PEAR Date class. See http://pear.php.net/package/Date/docs/latest/ */
require_once('Date.php');

/**
 * Datamanager 2 date datatype. The type is based on the PEAR date types
 * types.
 *
 * <b>Available configuration options:</b>
 *
 * - <i>string storage_type:</i> Defines the storage format of the date. The default
 *   is 'ISO', see below for details.
 *
 * <b>Available storage formats:</b>
 *
 * - ISO: YYYY-MM-DD HH:MM:SS
 * - ISO_DATE: YYYY-MM-DD
 * - ISO_EXTENDED: YYYY-MM-DDTHH:MM:SS(Z|[+-]HH:MM)
 * - ISO_EXTENDED_MICROTIME: YYYY-MM-DDTHH:MM:SS.S(Z|[+-]HH:MM)
 * - UNIXTIME: Unix Timestamps (seconds since epoch)
 *
 * @package midcom.helper.datamanager2
 */
class midcom_helper_datamanager2_type_date extends midcom_helper_datamanager2_type
{
    /**
     * The current date encapsulated by this type.
     *
     * @var Date
     * @link http://pear.php.net/package/Date/docs/latest/
     */
    var $value = null;

    /**
     * The storage type to use, see the class introduction for details.
     *
     * @var string
     */
    var $storage_type = 'ISO';
    
    /**
     * Possible date field that must be earlier than this date field
     *
     * @var string
     */
    var $earlier_field = '';

    /**
     * Initialize the value with an empty Date class.
     */
    function _on_configuring($config)
    {
        $this->value = new Date();
    }

    function _on_validate()
    {
        if (empty($this->earlier_field))
        {
            return true;
        }
        
        if (   !isset($this->_datamanager->types[$this->earlier_field])
            || !is_a($this->_datamanager->types[$this->earlier_field], 'midcom_helper_datamanager2_type_date')
            || !$this->_datamanager->types[$this->earlier_field]->value)
        {
            debug_push_class(__CLASS__, __FUNCTION__);
            debug_add("Failed to validate date field {$this->name} with {$this->earlier_field}, as such date field wasn't found.",
                MIDCOM_LOG_INFO);
            debug_pop();
            $this->validation_error = sprintf($this->_l10n->get('type date: failed to compare date with field %s'), $this->earlier_field);
            return false;
        }
        
        // There is a bug in Date::compare() which converts the given values to UTC and changes timezone also to UTC
        // We need to change our values back because of that bug
        // (Even if your version of Date does not do this, do not remove this because other versions *do have* the bug)
        // (The only situation when this could be removed is if Date is fixed and we mark dependency for >= that version)
        $tz = date_default_timezone_get();
        $value = clone $this->value;
        $earlier_value = clone $this->_datamanager->types[$this->earlier_field]->value;
        if (Date::compare($this->value, $this->_datamanager->types[$this->earlier_field]->value) < 0)
        {
            date_default_timezone_set($tz);
            $this->value = $value;
            $this->_datamanager->types[$this->earlier_field]->value = $earlier_value;
            $this->validation_error = sprintf($this->_l10n->get('type date: this date cannot be earlier than %s'), $this->earlier_field);
            return false;
        }
        date_default_timezone_set($tz);
        $this->value = $value;
        $this->_datamanager->types[$this->earlier_field]->value = $earlier_value;
        
        return true;
    }

    /**
     * This function uses the PEAR Date constructor to handle the conversion.
     * It should be able to deal with all three storage variants transparently.
     *
     * @param mixed $source The storage data structure.
     */
    function convert_from_storage ($source)
    {
        if (! $source)
        {
            // Get some way for really undefined dates until we can work with null
            // dates everywhere midgardside.
            $this->value = new Date('00-00-0000 00:00:00');
            $this->value->day = 0;
            $this->value->month = 0;
        }
        else
        {
            $this->value = new Date($source);
        }
    }

    /**
     * Converts Date object to storage representation.
     *
     * @todo Move to getDate where possible.
     * @return string The string representation of the Date according to the
     *     storage_type.
     */
    function convert_to_storage()
    {
        switch ($this->storage_type)
        {
            case 'ISO':
                if ($this->is_empty())
                {
                    return '0000-00-00 00:00:00';
                }
                else
                {
                    return $this->value->format('%Y-%m-%d %T');
                }

            case 'ISO_DATE':
                if ($this->is_empty())
                {
                    return '0000-00-00';
                }
                else
                {
                    return $this->value->format('%Y-%m-%d');
                }

            case 'ISO_EXTENDED':
            case 'ISO_EXTENDED_MICROTIME':
                if ($this->is_empty())
                {
                    return '0000-00-00T00:00:00.0';
                }
                else
                {
                    return str_replace(',', '.', $this->value->format('%Y-%m-%dT%H:%M:%s%O'));
                }

            case 'UNIXTIME':
                if ($this->is_empty())
                {
                    return 0;
                }
                else
                {
                    return $this->value->getTime();
                }

            default:
                midcom::generate_error(MIDCOM_ERRCRIT, "Invalid storage type for the Datamanager Date Type: {$this->storage_type}");
                // This will exit.
        }
    }

    /**
     * CVS conversion is mapped to regular type conversion.
     */
    function convert_from_csv ($source)
    {
        $this->convert_from_storage($source);
    }

    /**
     * CVS conversion is mapped to regular type conversion.
     */
    function convert_to_csv()
    {
        if ($this->is_empty())
        {
            return '';
        }
        $format = $this->_get_format('short date csv');
        return $this->value->format($format);
    }

    function convert_to_html()
    {
        if ($this->is_empty())
        {
            return '';
        }
        else
        {
            $format = $this->_get_format();
            return htmlspecialchars($this->value->format($format));
        }
    }

    /**
     * Helper function that returns the localized date format with or without time
     * 
     * @param string $base The format we want to use
     */
    private function _get_format($base = 'short date')
    {
        $format = $this->_l10n_midcom->get($base);
        // FIXME: This is not exactly an elegant way to do this
        if ($this->storage_type != 'ISO_DATE'
            && ( !array_key_exists('show_time', $this->storage->_schema->fields[$this->name]['widget_config'])
                || $this->storage->_schema->fields[$this->name]['widget_config']['show_time']))
        {
            $format = $this->_l10n_midcom->get($base) . ' %T';
        }
        return $format;
    }

    /**
     * Tries to detect whether the date value entered is empty in terms of the Midgard
     * core. For this, all values are compared to zero, if all tests succeed, the date
     * is considered empty.
     *
     * @return boolean Indicating Emptyness state.
     */
    function is_empty()
    {
        return
        (
               $this->value->year == 0
            && $this->value->month == 0
            && $this->value->day == 0
            && $this->value->hour == 0
            && $this->value->minute == 0
            && $this->value->second == 0
        );
    }
}

?>
