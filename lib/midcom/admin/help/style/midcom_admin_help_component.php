<?php
if (count($data['help_files']) > 0)
{
    echo "<h2>" . midcom::i18n()->get_string('toc', 'midcom.admin.help') . "</h2>\n";
    
    echo "<ul>\n";
    foreach ($data['help_files'] as $file_info)
    {
        $uri_string = basename($file_info['path']);
        $uri_parts = explode('.', $uri_string);
        $uri = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX) . "__ais/help/{$data['component']}/{$uri_parts[0]}/";
        echo "<li><a href=\"{$uri}\">{$file_info['subject']}</a></li>\n";
    }
    echo "</ul>\n";
}

?>