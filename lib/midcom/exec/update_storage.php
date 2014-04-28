<?php
if (extension_loaded('midgard'))
{
    throw new midcom_error("This script requires Midgard2 API support");
}

midcom::get('auth')->require_valid_user('basic');
midcom::get('auth')->require_admin_user();

midcom::get()->disable_limits();

while(@ob_end_flush());
echo "<pre>\n";

echo "<h1>Update Class Storage</h1>\n";
flush();

midgard_storage::create_base_storage();
echo "  Created base storage\n";

$types = midcom_connection::get_schema_types();
$start = microtime(true);
foreach ($types as $type)
{
    if (midgard_storage::class_storage_exists($type))
    {
        midgard_storage::update_class_storage($type);
        echo "  Updated storage for {$type}\n";
    }
    else
    {
        midgard_storage::create_class_storage($type);
        echo "  Created storage for {$type}\n";
    }
    flush();
}
echo "Processed " . count($types) . " schema types in " . round(microtime(true) - $start, 2) . "s";
echo "\n\nDone.";
echo "</pre>";
ob_start();
?>