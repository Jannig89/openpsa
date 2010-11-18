<?php
/**
 * Created on 2006-08-09
 * @author Henri Bergius
 * @package org.openpsa.products
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 *
 */

/**
 * The midcom_baseclasses_components_handler class defines a bunch of helper vars
 *
 * @package org.openpsa.products
 * @see midcom_baseclasses_components_handler
 */
class org_openpsa_products_handler_group_list  extends midcom_baseclasses_components_handler
{

    /**
     * Simple default constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Can-Handle check against the current group GUID. We have to do this explicitly
     * in can_handle already, otherwise we would hide all subtopics as the request switch
     * accepts all argument count matches unconditionally.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean True if the request can be handled, false otherwise.
     */
    function _can_handle_list($handler_id, $args, &$data)
    {
        if ($handler_id == 'index')
        {
            // We're in root-level product index
            if ($data['root_group'])
            {
                $data['group'] = org_openpsa_products_product_group_dba::get_cached($data['root_group']);
                if (   !$data['group']
                    || !$data['group']->guid)
                {
                    return false;
                }
                $data['view_title'] = $data['group']->title;
            }
            else
            {
                $data['group'] = null;
                $data['view_title'] = $this->_l10n->get('product database');
            }
            $data['parent_group'] = $data['root_group'];
        }
        else
        {
            // We're in some level of groups
            $qb = org_openpsa_products_product_group_dba::new_query_builder();
            if ($handler_id == 'list_intree')
            {
                $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $parentgroup_qb->add_constraint('code', '=', $args[0]);
                $groups = $parentgroup_qb->execute();
                if (empty($groups))
                {
                    // No such parent group found
                    return false;
                }
                if (   isset($groups[0])
                    && isset($groups[0]->id)
                    && !empty($groups[0])
                   )
                {
                    $qb->add_constraint('up', '=', $groups[0]->id);
                    $qb->add_constraint('code', '=', $args[1]);
                }
            }
            else if ($handler_id == 'listall')
            {
                $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $parentgroup_qb->add_constraint('code', '=', $args[0]);
                $groups = $parentgroup_qb->execute();
                if (empty($groups))
                {
                    // No such parent group found
                    return false;
                }
                if (   isset($groups[0])
                    && isset($groups[0]->id)
                    && !empty($groups[0]))
                {
                    $qb->add_constraint('up', '=', $groups[0]->id);
                }
            }
            else
            {
                $qb->add_constraint('code', '=', $args[0]);
            }

            $results = $qb->execute();
            if (count($results) == 0)
            {
                if (!mgd_is_guid($args[0]))
                {
                    return false;
                }

                $data['group'] = new org_openpsa_products_product_group_dba($args[0]);
                if (   !$data['group']
                    || !$data['group']->guid)
                {
                    return false;
                }
            }
            else
            {
                $data['group'] = $results[0];
            }

            $data['parent_group'] = $data['group']->id;
            
            if ($handler_id == 'listall')
            {
                $group_up = new org_openpsa_products_product_group_dba($data['group']->up);
                if (    isset($group_up)
                    &&  isset($group_up->title)
                    && !empty($group_up)
                   )
                {
                    $data['group'] = $group_up;
                }
            }

            if ($this->_config->get('code_in_title'))
            {
                $data['view_title'] = "{$data['group']->code} {$data['group']->title}";
            }
            else
            {
                $data['view_title'] = $data['group']->title;
            }
            
            if ($handler_id == 'listall')
            {
                $data['view_title'] = sprintf($this->_l10n_midcom->get('All %s'), $data['view_title']);
            }

            $data['acl_object'] = $data['group'];
        }

        return true;
    }

    /**
     * The handler for the group_list article.
     *
     * @param mixed $handler_id the array key from the request array
     * @param array $args the arguments given to the handler
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    function _handler_list($handler_id, $args, &$data)
    {

        // Query for sub-objects
        $group_qb = org_openpsa_products_product_group_dba::new_query_builder();
        if ($handler_id == 'list_intree')
        {
            $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $parentgroup_qb->add_constraint('code', '=', $args[0]);
            $groups = $parentgroup_qb->execute();
            if (count($groups) == 0)
            {
                return false;
                // No matching group
            }
            else
            {
                $categories_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $categories_qb->add_constraint('up', '=', $groups[0]->id);
                $categories_qb->add_constraint('code', '=', $args[1]);
                $categories = $categories_qb->execute();

                $data['parent_category_id'] = $categories[0]->id;
                $data['parent_category'] = $groups[0]->code;
            }

        }
        else if ($handler_id == 'listall')
        {
            $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $parentgroup_qb->add_constraint('code', '=', $args[0]);
            $groups = $parentgroup_qb->execute();

            if (count($groups) == 0)
            {
                return false;
                // No matching group
            }
            else
            {
                $data['group'] = $groups[0];
                $categories_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $categories_qb->add_constraint('up', '=', $groups[0]->id);
                $categories = $categories_qb->execute();
                for ($i = 0; $i < count($categories); $i++)
                {
                    $categories_in[$i] = $categories[$i]->id;
                }
            }

        }
        else if ($handler_id == 'list')
        {
            // if config set to redirection mode and not in dynamic load
            if (   $this->_config->get('redirect_to_first_product')
                && midcom::get_current_context() == 0)
            {
                $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
                
                $group_gb = org_openpsa_products_product_group_dba::new_query_builder();
                $group_gb->add_constraint('code', '=', $args[0]);
                $groups = $group_gb->execute();
                if (count($groups) != 0)
                {
                    $group_id = $groups[0]->__object->id;
                    $product_qb = org_openpsa_products_product_dba::new_query_builder();
                    $product_qb->add_constraint('productGroup', '=', $group_id);
                    $product_qb->set_limit(1);
                    $product_qb->add_order($this->_config->get('redirect_order_by'));
                    $products = $product_qb->execute();
                    if (count($products) != 0)
                    {
                        midcom::relocate($prefix."product/{$products[0]->code}/");
                    }
                }
            }

            $guidgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $guidgroup_qb->add_constraint('guid', '=', $args[0]);
            $groups = $guidgroup_qb->execute();

            if (count($groups) > 0)
            {
                $categories_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $categories_qb->add_constraint('id', '=', $groups[0]->up);
                $categories = $categories_qb->execute();
                
                if (count($categories) > 0)
                {
                    $data['parent_category'] = $categories[0]->code;                    
                }
            }
            else
            {
                //do not set the parent category. The category is already a top category.
            }
        }
        else if (   $handler_id == 'index'
                 && $this->_config->get('redirect_to_first_product')
                 && midcom::get_current_context() == 0)
        {
            $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

            $group_gb = org_openpsa_products_product_group_dba::new_query_builder();
            $group_gb->add_constraint('guid', '=', $this->_config->get('root_group'));
            $groups = $group_gb->execute();
            if (count($groups) != 0)
            {
                $relocate_url = '';

                $group_id = $groups[0]->__object->id;
                $product_qb = org_openpsa_products_product_dba::new_query_builder();
                $product_qb->add_constraint('productGroup', '=', $group_id);
                $product_qb->set_limit(1);
                $product_qb->add_order($this->_config->get('redirect_order_by'));
                $products = $product_qb->execute();
                if (count($products) != 0)
                {
                    $relocate_url = $prefix . "product/{$products[0]->code}/";
                }
                else
                {
                    $linked_products = array();

                    if ($this->_config->get('enable_productlinks'))
                    {
                        $qb_productlinks = org_openpsa_products_product_link_dba::new_query_builder();
                        $qb_productlinks->add_constraint('productGroup', '=', $group_id);
                        $qb_productlinks->add_constraint('product', '<>', 0);
                        $qb_productlinks->set_limit(1);
                        $productlinks = $qb_productlinks->execute();

                        if (count($productlinks) != 0)
                        {
                            $product = new org_openpsa_products_product_dba($productlinks[0]->product);
                            if ($product)
                            {
                                $relocate_url = $prefix . "product/{$product->code}/";
                            }
                        }
                    }
                }
                if ($relocate_url != '')
                {
                    midcom::relocate($relocate_url);
                }
            }
        }

        $group_qb->add_constraint('up', '=', $data['parent_group']);

        foreach ($this->_config->get('groups_listing_order') as $ordering)
        {
            if (preg_match('/\s*reversed?\s*/', $ordering))
            {
                $reversed = true;
                $ordering = preg_replace('/\s*reversed?\s*/', '', $ordering);
            }
            else
            {
                $reversed = false;
            }

            if ($reversed)
            {
                $group_qb->add_order($ordering, 'DESC');
            }
            else
            {
                $group_qb->add_order($ordering);
            }
        }

        $linked_products = array();

        if ($this->_config->get('enable_productlinks'))
        {
            $mc_productlinks = org_openpsa_products_product_link_dba::new_collector('productGroup', $data['parent_group']);
            $mc_productlinks->add_value_property('product');
            $mc_productlinks->execute();
            $productlinks = $mc_productlinks->list_keys();

            foreach ($productlinks as $guid => $array)
            {
                $linked_products[] = $mc_productlinks->get_subkey($guid, 'product');
            }
            $this->_request_data['linked_products'] = $linked_products;
        }

        $data['groups'] = $group_qb->execute();
        $data['products'] = array();
        if ($this->_config->get('group_list_products'))
        {
            midcom::load_library('org.openpsa.qbpager');
            $product_qb = new org_openpsa_qbpager('org_openpsa_products_product_dba', 'org_openpsa_products_product_dba');
            $product_qb->results_per_page = $this->_config->get('products_per_page');

            if (count($linked_products) > 0)
            {
                $product_qb->begin_group('OR');
            }

            if (   $data['group']
                && $data['group']->orgOpenpsaObtype == ORG_OPENPSA_PRODUCTS_PRODUCT_GROUP_TYPE_SMART)
            {
                // Smart group, query products by stored constraints
                $constraints = $data['group']->list_parameters('org.openpsa.products:constraints');
                if (empty($constraints))
                {
                    $product_qb->add_constraint('productGroup', '=', $data['parent_group']);
                }
                
                $reflector = new midgard_reflection_property('org_openpsa_products_product');
                
                foreach ($constraints as $constraint_string)
                {
                    $constraint_members = explode(',', $constraint_string);
                    if (count($constraint_members) != 3)
                    {
                        midcom::generate_error(MIDCOM_ERRCRIT, "Invalid constraint '{$constraint_string}'");
                    }
                    
                    // Reflection is needed here for safety
                    $field_type = $reflector->get_midgard_type($constraint_members[0]);
                    switch ($field_type)
                    {
                        case 4:
                            midcom::generate_error(MIDCOM_ERRCRIT, "Invalid constraint: '{$constraint_members[0]}' is not a Midgard property");
                        case MGD_TYPE_INT:
                            $constraint_members[2] = (int) $constraint_members[2];
                            break;
                        case MGD_TYPE_FLOAT:
                            $constraint_members[2] = (float) $constraint_members[2];
                            break; 
                        case MGD_TYPE_BOOLEAN:
                            $constraint_members[2] = (boolean) $constraint_members[2];
                            break;
                    }
                    $product_qb->add_constraint($constraint_members[0], $constraint_members[1], $constraint_members[2]);
                }
            }
            else if ($handler_id == 'list_intree')
            {
                $product_qb->add_constraint('productGroup', '=', $data['parent_category_id']);
            }
            else if ($handler_id == 'listall')
            {
                $product_qb->add_constraint('productGroup', 'IN', $categories_in);
            }
            else
            {
                $product_qb->add_constraint('productGroup', '=', $data['parent_group']);
            }
            if (count($linked_products) > 0)
            {
                $product_qb->add_constraint('id', 'IN', $linked_products);
                $product_qb->end_group();
            }

            // This should be a helper function, same functionality, but with different config-parameter is used in /handler/product/search.php
            foreach ($this->_config->get('products_listing_order') as $ordering)
            {
                if (preg_match('/\s*reversed?\s*/', $ordering))
                {
                    $reversed = true;
                    $ordering = preg_replace('/\s*reversed?\s*/', '', $ordering);
                }
                else
                {
                    $reversed = false;
                }

                if ($reversed)
                {
                    $product_qb->add_order($ordering, 'DESC');
                }
                else
                {
                    $product_qb->add_order($ordering);
                }
            }

            if ($this->_config->get('enable_scheduling'))
            {
                $product_qb->add_constraint('start', '<=', time());
                $product_qb->begin_group('OR');
                    /*
                     * List products that either have no defined end-of-market dates
                     * or are still in market
                     */
                    $product_qb->add_constraint('end', '=', 0);
                    $product_qb->add_constraint('end', '>=', time());
                $product_qb->end_group();
            }

            $data['products'] = $product_qb->execute();

            $data['products_qb'] =& $product_qb;
        }

        // Prepare datamanager
        $data['datamanager_group'] = new midcom_helper_datamanager2_datamanager($data['schemadb_group']);
        $data['datamanager_product'] = new midcom_helper_datamanager2_datamanager($data['schemadb_product']);
        
        // Populate toolbar
        if ($this->_request_data['group'])
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "edit/{$this->_request_data['group']->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                    MIDCOM_TOOLBAR_HELPTEXT => null,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ENABLED => $this->_request_data['group']->can_do('midgard:update'),
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
        }

        $allow_create = false;
        if ($data['group'])
        {
            $allow_create_group = $data['group']->can_do('midgard:create');
            $allow_create_product = $data['group']->can_do('midgard:create');            
            
            if ($data['group']->orgOpenpsaObtype == ORG_OPENPSA_PRODUCTS_PRODUCT_GROUP_TYPE_SMART)
            {
                $allow_create_product = false;
            }
        }
        else
        {
            $allow_create_group = midcom::auth->can_user_do('midgard:create', null, 'org_openpsa_products_product_group_dba');
            $allow_create_product = midcom::auth->can_user_do('midgard:create', null, 'org_openpsa_products_product_dba');
        }

        foreach (array_keys($data['schemadb_group']) as $name)
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "create/{$data['parent_group']}/{$name}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf
                    (
                        $this->_l10n_midcom->get('create %s'),
                        $this->_l10n->get($data['schemadb_group'][$name]->description)
                    ),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-dir.png',
                    MIDCOM_TOOLBAR_ENABLED => $allow_create_group,
                )
            );
        }

        foreach (array_keys($data['schemadb_product']) as $name)
        {
            if (isset($data['schemadb_product'][$name]->customdata['icon']))
            {
                $icon = $data['schemadb_product'][$name]->customdata['icon'];
            }
            else
            {
                $icon = 'stock-icons/16x16/new-text.png';
            }
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "product/create/{$data['parent_group']}/{$name}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf
                    (
                        $this->_l10n_midcom->get('create %s'),
                        $this->_l10n->get($data['schemadb_product'][$name]->description)
                    ),
                    MIDCOM_TOOLBAR_ICON => $icon,
                    MIDCOM_TOOLBAR_ACCESSKEY => 'n',
                    MIDCOM_TOOLBAR_ENABLED => $allow_create_product,
                )
            );
        }
        
        if (   $this->_config->get('enable_productlinks')
            && isset($data['schemadb_productlink']))
        {
            $data['datamanager_productlink'] = new midcom_helper_datamanager2_datamanager($data['schemadb_productlink']);
            foreach (array_keys($data['schemadb_productlink']) as $name)
            {
                if (isset($data['schemadb_productlink'][$name]->customdata['icon']))
                {
                    $icon = $data['schemadb_productlink'][$name]->customdata['icon'];
                }
                else
                {
                    $icon = 'stock-icons/16x16/new-text.png';
                }
                $this->_view_toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => "productlink/create/{$data['parent_group']}/{$name}/",
                        MIDCOM_TOOLBAR_LABEL => sprintf
                        (
                            $this->_l10n_midcom->get('create %s'),
                            $this->_l10n->get($data['schemadb_productlink'][$name]->description)
                        ),
                        MIDCOM_TOOLBAR_ICON => $icon,
                        MIDCOM_TOOLBAR_ACCESSKEY => 'n',
                        MIDCOM_TOOLBAR_ENABLED => $allow_create_product,
                    )
                );
            }
        }

        if ($data['group'])
        {
            if ($GLOBALS['midcom_config']['enable_ajax_editing'])
            {
                $data['controller'] = midcom_helper_datamanager2_controller::create('ajax');
                $data['controller']->schemadb =& $data['schemadb_group'];
                $data['controller']->set_storage($data['group']);
                $data['controller']->process_ajax();
                $data['datamanager_group'] =& $data['controller']->datamanager;
            }
            else
            {
                $data['controller'] = null;
                if (!$data['datamanager_group']->autoset_storage($data['group']))
                {
                    midcom::generate_error(MIDCOM_ERRCRIT, "Failed to create a DM2 instance for product group {$data['group']->guid}.");
                    // This will exit.
                }
            }
            midcom::bind_view_to_object($data['group'], $data['datamanager_group']->schema->name);
        }

        /***
         * Set the breadcrumb text
         */
        $this->_update_breadcrumb_line();

        // Set the active leaf
        if (   $this->_config->get('display_navigation')
            && $this->_request_data['group'])
        {
            $group = $this->_request_data['group'];

            // Loop as long as it is possible to get the parent group
            while ($group->guid)
            {
                // Break to the requested level (probably the root group of the products content topic)
                if (   $group->id === $this->_config->get('root_group')
                    || $group->guid === $this->_config->get('root_group'))
                {
                    break;
                }
                $temp = $group->id;
                if ($group->up == 0)
                {
                    break;
                }
                $group = new org_openpsa_products_product_group_dba($group->up);
            }

            if (isset($temp))
            {
                // Active leaf of the topic
                $this->_component_data['active_leaf'] = $temp;
            }
        }

        /**
         * change the pagetitle. (must be supported in the style)
         */
        midcom::set_pagetitle($this->_request_data['view_title']);
        return true;
    }

    /**
     * This function does the output.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    function _show_list($handler_id, &$data)
    {
        if ($data['group'])
        {
            if ($data['controller'])
            {
                $data['view_group'] = $data['controller']->get_content_html();
            }
            else
            {
                $data['view_group'] = $data['datamanager_group']->get_content_html();
            }
        }

        $prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

        if (   count($data['groups']) >= 1
            && (   count($data['products']) == 0
                || $this->_config->get('listing_primary') == 'groups'
               )
           )
        {
            if ( $this->_config->get('disable_subgroups_on_frontpage') !== true )
            {
                midcom_show_style('group_header');

                $groups_counter = 0;
                $data['groups_count'] = count($data['groups']);

                midcom_show_style('group_subgroups_header');

                foreach ($data['groups'] as $group)
                {
                    $groups_counter++;
                    $data['groups_counter'] = $groups_counter;

                    $data['group'] = $group;
                    if (! $data['datamanager_group']->autoset_storage($group))
                    {
                        debug_push_class(__CLASS__, __FUNCTION__);
                        debug_add("The datamanager for group #{$group->id} could not be initialized, skipping it.");
                        debug_print_r('Object was:', $group);
                        debug_pop();
                        continue;
                    }
                    $data['view_group'] = $data['datamanager_group']->get_content_html();

                    if ($group->code)
                    {
                        if (isset($data["parent_category"]))
                        {
                            $data['view_group_url'] = "{$prefix}" . $data["parent_category"] . "/{$group->code}/";
                        }
                        else
                        {
                            $data['view_group_url'] = "{$prefix}{$group->code}/";
                        }
                    }
                    else
                    {
                        $data['view_group_url'] = "{$prefix}{$group->guid}/";
                    }

                    midcom_show_style('group_subgroups_item');
                }

                midcom_show_style('group_subgroups_footer');
                midcom_show_style('group_footer');
            }
        }
        else if (count($data['products']) > 0)
        {
            midcom_show_style('group_header');

            $products_counter = 0;
            $data['products_count'] = count($data['products']);

            midcom_show_style('group_products_header');

            foreach ($data['products'] as $product)
            {
                $products_counter++;
                $data['products_counter'] = $products_counter;

                $data['product'] = $product;
                if (! $data['datamanager_product']->autoset_storage($product))
                {
                    debug_push_class(__CLASS__, __FUNCTION__);
                    debug_add("The datamanager for product #{$product->id} could not be initialized, skipping it.");
                    debug_print_r('Object was:', $product);
                    debug_pop();
                    continue;
                }
                $data['view_product'] = $data['datamanager_product']->get_content_html();

                if ($product->code)
                {
                    if (isset($data["parent_category"]))
                    {
                        $data['view_product_url'] = "{$prefix}product/" . $data["parent_category"] . "/{$product->code}/";
                    }
                    else
                    {
                        $data['view_product_url'] = "{$prefix}product/{$product->code}/";
                    }
                }
                else
                {
                    $data['view_product_url'] = "{$prefix}product/{$product->guid}/";
                }

                $show_style = 'group_products_item';
                if (   $this->_config->get('enable_productlinks')
                    && is_array($data['linked_products'])
                    && count($data['linked_products']) > 0
                    && in_array($product->id, $data['linked_products']))
                {
                    $show_style = 'group_products_item_link';
                }
                midcom_show_style($show_style);
            }

            midcom_show_style('group_products_footer');
            midcom_show_style('group_footer');
        }
        else
        {
            midcom_show_style('group_empty');
        }

    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     */
    function _update_breadcrumb_line()
    {
        $tmp = Array();

        $group = $this->_request_data['group'];
        $root_group = $this->_config->get('root_group');

        if (!$group)
        {
            return false;
        }

        $parent = $group;

        while ($parent)
        {
            $group = $parent;

            if ($group->guid === $root_group)
            {
                break;
            }

            if ($group->code)
            {
                $url = "{$group->code}/";
            }
            else
            {
                $url = "{$group->guid}/";
            }


            $tmp[] = Array
            (
                MIDCOM_NAV_URL => $url,
                MIDCOM_NAV_NAME => $group->title,
            );
            $parent = $group->get_parent();
        }

        // If navigation is configured to display product groups, remove the lowest level
        // parent to prevent duplicate entries in breadcrumb display
        if (   $this->_config->get('display_navigation')
            && isset($tmp[count($tmp) - 1]))
        {
            unset($tmp[count($tmp) - 1]);
        }

        midcom::set_custom_context_data('midcom.helper.nav.breadcrumb', array_reverse($tmp));
    }
}
?>