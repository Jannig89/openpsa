<?php
$prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
?>
<h1><?php echo $data['view_title']; ?></h1>

<table>
    <thead>
        <?php
        echo "<th>" . midcom::i18n()->get_string('folders', 'midcom.admin.user') . "</th>\n";        
        foreach ($data['privileges'] as $privilege)
        {
            echo "<th>" . midcom::i18n()->get_string($privilege, 'midgard.admin.asgard') . "</th>\n";
        }
        ?>
    </thead>
    <tbody>
        <?php
        foreach ($data['objects'] as $guid => $privs)
        {
            $object = midcom::dbfactory()->get_object_by_guid($guid);
            if (!is_a($object, 'midcom_db_topic'))
            {
                continue;
            }
            echo "<tr>\n";
            echo "<th><a href=\"{$prefix}__mfa/asgard/object/permissions/{$object->guid}/\">{$object->extra}</a></th>\n";
            
            foreach ($privs as $privilege)
            {
                echo "<td>";
                if (!isset($privs[$privilege]))
                {
                    echo "&nbsp;</td>\n";
                    continue;
                }
                
                if ($privs[$privilege] == 1)
                {
                    echo midcom::i18n()->get_string('yes', 'midcom');
                }
                elseif ($privs[$privilege] == 2)
                {
                    echo midcom::i18n()->get_string('no', 'midcom');
                }
                
                echo "</td>\n";
            }
            echo "</tr>\n";
        }
        ?>
    </tbody>
</table>