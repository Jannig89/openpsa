<?php
/**
 * @package org.openpsa.products
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: csv.php 24513 2009-12-21 14:10:44Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/** @ignore */
require_once(MIDCOM_ROOT.'/midcom/baseclasses/components/handler/dataexport.php');
/**
 * @package org.openpsa.products
 */
class org_openpsa_products_handler_product_csv extends midcom_baseclasses_components_handler_dataexport
{
    function __construct()
    {
        parent::__construct();
    }

    function _load_schemadb($handler_id, &$args, &$data)
    {
        midcom::skip_page_style = true;
        $data['session'] = new midcom_services_session('org_openpsa_products_csvexport');
        if (!empty($_POST))
        {
            $data['session']->set('POST_data', $_POST);
        }
        $root_group_guid = $this->_config->get('root_group');
        $group_name_to_filename = '';
        if ($root_group_guid)
        {
            $root_group = org_openpsa_products_product_group_dba::get_cached($root_group_guid);
            $group_name_to_filename = strtolower(str_replace(' ', '_', $root_group->code)).'_';
        }

        if(isset($args[0]))
        {
            $data['schemadb_to_use'] = str_replace('.csv', '', $args[0]);
            $data['filename'] = $group_name_to_filename . $data['schemadb_to_use'] . '_' . date('Y-m-d') . '.csv';
        }
        else if(    isset($_POST)
                && array_key_exists('org_openpsa_products_export_schema', $_POST))
        {
            //We do not have filename in URL, generate one and redirect
            $schemaname = $_POST['org_openpsa_products_export_schema'];
            if(strpos(midcom_connection::get_url('uri'), '/', strlen(midcom_connection::get_url('uri')) - 2))
            {
                midcom::relocate(midcom_connection::get_url('uri') . "{$schemaname}");
            }
            else
            {
                midcom::relocate(midcom_connection::get_url('uri') . "/{$schemaname}");
            }
            // This will exit
        }
        else
        {
            $this->_request_data['schemadb_to_use'] = $this->_config->get('csv_export_schema');
        }

        $this->_schema = $this->_config->get('csv_export_schema');

        if (isset($this->_request_data['schemadb_product'][$this->_request_data['schemadb_to_use']]))
        {
            $this->_schema = $this->_request_data['schemadb_to_use'];
        }

        $this->_schema_fields_to_skip = split(',',$this->_config->get('export_skip_fields'));

        return $this->_request_data['schemadb_product'];
    }

    function _load_data($handler_id, &$args, &$data)
    {
        if (   empty($_POST)
            && $data['session']->exists('POST_data'))
        {
            $_POST = $data['session']->get('POST_data');
            $data['session']->remove('POST_data');
        }

        $qb = org_openpsa_products_product_dba::new_query_builder();

        $qb->add_order('code');
        $qb->add_order('title');
        $products = array();
        
        $root_group_guid = $this->_config->get('root_group');
        if ($root_group_guid)
        {
            $root_group = new org_openpsa_products_product_group_dba($root_group_guid);
            if (   !isset($_POST['org_openpsa_products_export_all'])
                || empty($_POST['org_openpsa_products_export_all']))
            {
                $qb->add_constraint('productGroup', '=', $root_group->id);
                // We have data only from one product group, though this seems to be too late...
                $this->_schema_fields_to_skip[] = 'productGroup';
            }
            else
            {
                $qb_groups = org_openpsa_products_product_group_dba::new_query_builder();
                $qb_groups->add_constraint('up', 'INTREE', $root_group->id);
                $groups = $qb_groups->execute();
                $qb->begin_group('OR');
                $qb->add_constraint('productGroup', '=', $root_group->id);
                foreach($groups as $group)
                {
                    $qb->add_constraint('productGroup', '=', $group->id);
                }
                $qb->end_group('OR');
            }
            
        }

        $all_products = $qb->execute();
        foreach ($all_products as $product)
        {
            $schema = $product->get_parameter('midcom.helper.datamanager2', 'schema_name');
            if ($schema != $this->_schema)
            {
                continue;
            }
            
            $products[] = $product;
        }

        return $products;
    }
}
?>