<?php
$prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
?>
</div>

<script type="text/javascript">
jQuery('input.delete').bind('click', function(){
    var guid = this.id.substr(29);

    var loading = "<img src='" + MIDCOM_STATIC_URL + "/midcom.helper.datamanager2/ajax-loading.gif' alt='loading' />";
    jQuery('#org_openpsa_relatedto_line_' + guid)
        .css('text-align', 'center')
        .css('height', jQuery('#org_openpsa_relatedto_line_' + guid).height() + 'px')
        .html(loading);
    jQuery.ajax({
        url: "<?php echo $prefix; ?>__mfa/org.openpsa.relatedto/delete/" + guid + "/",
        success: function(){
            jQuery('#org_openpsa_relatedto_line_' + guid).slideUp('fast', function() {
                jQuery('#org_openpsa_relatedto_line_' + guid).remove();
            });
        }
    });
});
</script>

