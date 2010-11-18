<?php
$prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
echo "<h1>" . midcom::i18n()->get_string('urlmethods', 'midcom.admin.help') . "</h1>\n";
if (count($data['urlmethods']) > 0)
{
    echo "<h2>" . midcom::i18n()->get_string('available url methods', 'midcom.admin.help') . "</h2>\n";
    
    $i = 0;
    foreach ($data['urlmethods'] as $file => $method_info)
    {
        $id = basename($method_info['url'],".php");
        $title = $method_info['url'];
?>
<fieldset id="handler_&(id);">
    <legend onclick="javascript:toggle_twisty('&(id);_contents')">
        &(title);
        <img class="twisty" src="<?php echo MIDCOM_STATIC_URL; ?>/midcom.admin.styleeditor/twisty-<?php echo ($i > 0) ? 'hidden' : 'down'; ?>.gif" alt="-" />
    </legend>
    <div id="&(id);_contents" style="display: <?php echo ($i > 0) ? 'none' : 'block'; ?>;" class="description">
<?php
        $i++;
        echo "<p>\n";
        echo $method_info['description'];
        echo "</p>\n";
?>
    </div>
</fieldset>
<?php
    }
}
else
{
    echo "<p>" . midcom::i18n()->get_string('no url methods found', 'midcom.admin.help') . "</p>";
}
?>