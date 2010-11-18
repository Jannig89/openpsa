<?php
$deliverable = $data['deliverable'];
$prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
?>
<li class="deliverable collapsed" id="deliverable_<?php echo $data['deliverable_object']->guid; ?>">
    <span class="icon">
    </span>
    <a name="<?php echo $data['deliverable_object']->guid; ?>"></a>
    <div class="tags">&(deliverable['tags']:h);</div>
    <?php
    echo "<h3><a href=\"{$prefix}deliverable/{$data['deliverable_object']->guid}/\">{$data['deliverable_object']->title}</a></h3>\n";
    ?>
    <div class="information" id="information_<?php echo $data['deliverable_object']->guid; ?>">
    <table class="details">
        <tbody>
            <tr>
                <th><?php echo $data['l10n']->get('supplier'); ?></th>
                <td>&(deliverable['supplier']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('estimated delivery'); ?></th>
                <td>&(deliverable['end']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('price per unit'); ?></th>
                <td>&(deliverable['pricePerUnit']:h); / &(deliverable['unit']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('cost per unit'); ?></th>
                <td>&(deliverable['costPerUnit']:h); &(deliverable['costType']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('units'); ?></th>
                <td>&(deliverable['units']:h); / &(deliverable['plannedUnits']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('total'); ?></th>
                <td>&(deliverable['price']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('invoice by actual units'); ?></th>
                <td>&(deliverable['invoiceByActualUnits']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('invoice approved only'); ?></th>
                <td>&(deliverable['invoiceApprovedOnly']:h);</td>
            </tr>
            <tr>
                <th><?php echo $data['l10n']->get('total cost'); ?></th>
                <td>&(deliverable['cost']:h);</td>
            </tr>
        </tbody>
    </table>

    <div class="description">
        &(deliverable['description']:h);
    </div>

    <div class="components">
        &(deliverable['components']:h);
    </div>
    </div>

    <div class="tasks">
        <?php
        if (   $data['projects_url']
            && $data['deliverable_object']->orgOpenpsaObtype == ORG_OPENPSA_PRODUCTS_PRODUCT_TYPE_SERVICE
            && $data['deliverable_object']->state >= ORG_OPENPSA_SALESPROJECT_DELIVERABLE_STATUS_ORDERED)
        {
            midcom::dynamic_load($data['projects_url'] . "task/list/all/agreement/{$data['deliverable_object']->id}");
            // FIXME: This is a rather ugly hack
            midcom::style->enter_context(0);
        }
        ?>
    </div>
    <div class="invoices">
        <?php
        if (   $data['invoices_url']
            && $data['deliverable_object']->invoiced > 0)
        {
            midcom::dynamic_load($data['invoices_url'] . "list/deliverable/{$data['deliverable_object']->guid}");
            // FIXME: This is a rather ugly hack
            midcom::style->enter_context(0);
        }
        ?>
    </div>
    <div class="toolbar">
        <form method="post" action="&(prefix);deliverable/process/<?php echo $data['deliverable_object']->guid; ?>">
        <?php
        echo $data['deliverable_toolbar'];
        ?>
        </form>
    </div>
</li>

<script type="text/javascript">
jQuery('#deliverable_<?php echo $data['deliverable_object']->guid; ?> .icon').click(function(e){
    jQuery('#information_<?php echo $data['deliverable_object']->guid; ?>').toggle('fast');
    if (jQuery('#deliverable_<?php echo $data['deliverable_object']->guid; ?>').hasClass('expanded'))
    {
        jQuery('#deliverable_<?php echo $data['deliverable_object']->guid; ?>').removeClass('expanded');
        jQuery('#deliverable_<?php echo $data['deliverable_object']->guid; ?>').addClass('collapsed');
    }
    else
    {
        jQuery('#deliverable_<?php echo $data['deliverable_object']->guid; ?>').addClass('expanded');
        jQuery('#deliverable_<?php echo $data['deliverable_object']->guid; ?>').removeClass('collapsed');
    }

});
</script>