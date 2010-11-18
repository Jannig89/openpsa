<?php
/**
 * @package midcom.baseclasses
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: dataexport.php 26505 2010-07-06 12:26:42Z rambo $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Generic CSV export handler baseclass
 *
 * @package midcom.baseclasses
 */
class midcom_baseclasses_components_handler_dataexport extends midcom_baseclasses_components_handler
{
    /**
     * The Datamanager of the objects to export.
     *
     * @var midcom_helper_datamanager2_datamanager
     * @access private
     */
    var $_datamanager = null;

    /**
     * Flag indicating whether or not the GUID should be included in exports.
     *
     * @var boolean
     * @access public
     */
    public $include_guid = true;

    /**
     * Flag indicating whether or not totals for number fields should be generated
     *
     * @var boolean
     * @access public
     */
    public $include_totals = false;

    var $_schema = null;

    var $_objects = array();

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    function _prepare_request_data()
    {
        $this->_request_data['datamanager'] =& $this->_datamanager;
        $this->_request_data['objects'] =& $this->_objects;
    }

    /**
     * Internal helper, loads the datamanager for the current type. Any error triggers a 500.
     *
     * @access private
     */
    function _load_datamanager($schemadb)
    {
        if (empty($this->_schema))
        {
            midcom::generate_error(MIDCOM_ERRCRIT, 'Export schema ($this->_schema) must be defined, hint: do it in "_load_schemadb"');
            // This will exit
        }
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($schemadb);

        if (   ! $this->_datamanager
            || ! $this->_datamanager->set_schema($this->_schema))
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "Failed to create a DM2 instance for schemadb schema '{$this->_schema}'.");
            // This will exit.
        }
    }

    function _load_schemadb()
    {
        midcom::generate_error(MIDCOM_ERRCRIT, 'Method "_load_schemadb" must be overridden in implementation');
    }

    function _load_data()
    {
        midcom::generate_error(MIDCOM_ERRCRIT, 'Method "_load_data" must be overridden in implementation');
    }

    function _handler_csv($handler_id, $args, &$data)
    {
        midcom::auth()->require_valid_user();

        //Disable limits
        @ini_set('memory_limit', -1);
        @ini_set('max_execution_time', 0);

        $this->_load_datamanager($this->_load_schemadb($handler_id, $args, $data));
        $this->_objects = $this->_load_data($handler_id, $args, $data);

        midcom::skip_page_style = true;

        if (   !isset($args[0])
            || empty($args[0]))
        {
            //We do not have filename in URL, generate one and redirect
            $fname = preg_replace('/[^a-z0-9-]/i', '_', strtolower($this->_topic->extra)) . '_' . date('Y-m-d') . '.csv';
            if (strpos(midcom_connection::get_url('uri'), '/', strlen(midcom_connection::get_url('uri')) - 2))
            {
                midcom::relocate(midcom_connection::get_url('uri') . $fname);
            }
            else
            {
                midcom::relocate(midcom_connection::get_url('uri') . "/{$fname}");
            }
            // This will exit
        }

        if(   !isset($data['filename'])
           || $data['filename'] == '')
        {
            $data['filename'] = str_replace('.csv', '', $args[0]);
        }

        $this->_init_csv_variables();
        midcom::skip_page_style = true;

        // FIXME: Use global configuration
        //midcom::cache()->content->content_type($this->_config->get('csv_export_content_type'));
        midcom::cache()->content->content_type('application/csv');
        _midcom_header('Content-Disposition: filename=' . $data['filename']);

        return true;
    }

    function _init_csv_variables()
    {
        // FIXME: Use global configuration
        if (   !isset($this->csv['s'])
            || empty($this->csv['s']))
        {
            $this->csv['s'] = ';';
            //$this->csv['s'] = $this->_config->get('csv_export_separator');
        }
        if (   !isset($this->csv['q'])
            || empty($this->csv['q']))
        {
            $this->csv['q'] = '"';
            //$this->csv['q'] = $this->_config->get('csv_export_quote');
        }
        if (   !isset($this->csv['d'])
            || empty($this->csv['d']))
        {
            //$this->csv['d'] = '.';
            $this->csv['d'] = $this->_l10n_midcom->get('decimal point');
        }
        if (   !isset($this->csv['nl'])
            || empty($this->csv['nl']))
        {
            $this->csv['nl'] = "\n";
            //$this->csv['nl'] = $this->_config->get('csv_export_newline');
        }
        if (   !isset($this->csv['charset'])
            || empty($this->csv['charset']))
        {
            // Default to ISO-LATIN-15 (Latin-1 with EURO sign etc)
            $this->csv['charset'] = 'ISO-8859-15';
            if (   isset($_SERVER['HTTP_USER_AGENT'])
                && !preg_match('/Windows/i', $_SERVER['HTTP_USER_AGENT']))
            {
                // Excep when not on windows, then default to UTF-8
                $this->csv['charset'] = 'UTF-8';
            }
            //$this->csv['charset'] = $this->_config->get('csv_export_charset');
        }
        if ($this->csv['s'] == $this->csv['d'])
        {
            midcom::generate_error(MIDCOM_ERRCRIT, "CSV decimal separator (configured as '{$this->csv['d']}') may not be the same as field separator (configured as '{$this->csv['s']}')");
        }
    }

    function _encode_csv($data, $add_separator = true, $add_newline = false)
    {
        /* START: Quick'n'Dirty on-the-fly charset conversion */
        if (   $this->csv['charset'] !== 'UTF-8'
            && function_exists('iconv'))
        {
            $to_charset = "{$this->csv['charset']}//TRANSLIT";
            // Ragnaroek-todo: use try-catch here to avoid trouble with the error_handler if iconv gets whiny
            $stat = @iconv('UTF-8', $to_charset, $data);
            if (!empty($stat))
            {
                $data = $stat;
            }
        }
        /* END: Quick'n'Dirty on-the-fly charset conversion */

        if (is_numeric($data))
        {
            // Decimal point format
            $data = str_replace('.', $this->csv['d'], $data);
        }

        // Strings and numbers beginning with zero are quoted
        if (   !empty($data)
               && (!is_numeric($data)
                   || preg_match('/^[0+]/', $data)))
        {
            // Make sure we have only newlines in data
            $data = preg_replace("/\n\r|\r\n|\r/", "\n", $data);
            // Escape quotes (PONDER: make configurable between doubling the character and escaping)
            $data = str_replace($this->csv['q'], '\\' . $this->csv['q'], $data);
            // Escape newlines
            $data = str_replace("\n", '\\n', $data);
            // Quote
            $data = "{$this->csv['q']}{$data}{$this->csv['q']}";
        }
        if ($add_separator)
        {
            $data .= $this->csv['s'];
        }
        if ($add_newline)
        {
            $data .= $this->csv['nl'];
        }
        return $data;
    }

    /**
     * Sets given object as storage object for DM2
     */
    function set_dm_storage(&$object)
    {
        return $this->_datamanager->set_storage($object);
    }

    function _show_csv($handler_id, &$data)
    {
        // Make real sure we're dumping data live
        midcom::cache()->content->enable_live_mode();
        while(@ob_end_flush());

        // Dump headers
        if ($this->include_guid)
        {
            echo $this->_encode_csv('GUID', true, false);
        }

        $i = 0;
        $datamanager =& $this->_datamanager;
        $totals = array();

        foreach ($datamanager->schema->field_order as $name)
        {
            $title =& $datamanager->schema->fields[$name]['title'];
            $type =& $datamanager->schema->fields[$name]['type'];
            if ($this->include_totals
                && $type == 'number')
            {
                $totals[$name] = 0;
            }
            $title = midcom::i18n()->get_string($title, $this->_component);
            $i++;
            if ($i < count($datamanager->schema->field_order))
            {
                echo $this->_encode_csv($title, true, false);
            }
            else
            {
                echo $this->_encode_csv($title, false, true);
            }
        }

        // Dump objects
        foreach ($this->_objects as $object)
        {
            if (!$this->set_dm_storage($object))
            {
                // Object failed to load, skip
                continue;
            }

            if ($this->include_guid)
            {
                echo $this->_encode_csv($object->guid, true, false);
            }

            $i = 0;
            foreach ($datamanager->schema->field_order as $fieldname)
            {
                $type =& $datamanager->types[$fieldname];
                $typename =& $datamanager->schema->fields[$fieldname]['type'];
                $data = '';
                $data = $type->convert_to_csv();

                if ($this->include_totals
                    && $typename == 'number')
                {
                    $totals[$fieldname] += $data;
                }
                $i++;
                if ($i < count($datamanager->schema->field_order))
                {
                    echo $this->_encode_csv($data, true, false);
                }
                else
                {
                    echo $this->_encode_csv($data, false, true);
                }
                $data = '';
                // Prevent buggy types from leaking their old value over
                $datamanager->types[$fieldname]->value = false;
            }
            flush();
        }
        if ($this->include_totals)
        {
            foreach ($datamanager->schema->field_order as $name)
            {
                $type =& $datamanager->schema->fields[$name]['type'];
                $value = "";
                $last = false;
                $i++;
                if ($i < count($datamanager->schema->field_order))
                {
                    $last = true;
                }
                if ($type == 'number')
                {
                    $value = $totals[$name];
                }

                echo $this->_encode_csv($value, true, $last);
            }
            flush();
        }
        // restart ob to keep MidCOM happy
        ob_start();
    }
}
?>