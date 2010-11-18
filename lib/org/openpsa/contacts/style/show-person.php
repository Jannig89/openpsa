<?php
$nap = new midcom_helper_nav();
$node = $nap->get_node($nap->get_current_node());
?>
<div class="sidebar">
    <?php
    midcom::dynamic_load($node[MIDCOM_NAV_RELATIVEURL] . "person/memberships/{$data['person']->guid}/");
    midcom_show_style("show-person-account");

    // Try to find campaigns component
    $campaigns_node = midcom_helper_find_node_by_component('org.openpsa.directmarketing');
    if ($campaigns_node)
    {
        midcom::dynamic_load($campaigns_node[MIDCOM_NAV_RELATIVEURL] . "campaign/list/{$data['person']->guid}/");
    }
    ?>
</div>

<div class="main">
<?php
    $data['datamanager']->display_view();
    //add tabs
    org_openpsa_core_ui::render_tabs($data['person']->guid);
?>
</div>