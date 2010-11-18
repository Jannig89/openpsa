<?php
$prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
$reporters = $data['reporters'];
$tasks = $data['tasks'];
$reports = $data['reports'];

$entries = array();

$grid_id = $data['status'] . '_hours_grid';

foreach ($reports['reports'] as $report)
{
    $entry = array();

    $description = "<em>" . $data['l10n']->get('no description given') . "</em>";
    
    if (! preg_match("/^[\W]*?$/", $report->description))
    {
        $description = $report->description;
    }

    $entry['id'] = $report->id;
    $entry['index_date'] = $report->date;
    $entry['date'] = strftime('%x', $report->date);

    if ($data['mode'] != 'simple')
    {
        $entry['task'] = $tasks[$report->task];
    }

    $entry['index_description'] = $description;
    $entry['description'] = '<a href="' . $prefix . 'hours/edit/' . $report->guid . '">' . $description . '</a>';

    $entry['reporter'] = $reporters[$report->person];

    $entry['index_hours'] = $report->hours;
    $entry['hours'] = $report->hours . ' ' . $data['l10n']->get('hours unit');

    $entries[] = $entry;
}
echo '<script type="text/javascript">//<![CDATA[';
echo "\nvar " . $grid_id . '_entries = ' . json_encode($entries);
echo "\n//]]></script>";

$footer_data = array
(
    'date' => $data['l10n']->get('total'),
    'hours' => $reports['hours']
);
?>
<div class="org_openpsa_expenses <?php echo $data['status']; ?> full-width" style="margin-bottom: 1em">

<form id="form_&(grid_id);" method="post" action="<?php echo $data['action_target_url']; ?>">
<input type="hidden" name="relocate_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>" />

<table id="&(grid_id);"></table>
<div id="p_&(grid_id);"></div>

<script type="text/javascript">
jQuery("#&(grid_id);").jqGrid({
      datatype: "local",
      data: &(grid_id);_entries,
      colNames: ['id', 'index_date', <?php
                 echo '"' . $data['l10n']->get('date') . '",'; 
                 if ($data['mode'] != 'simple')
                 {
                     echo '"' . $data['l10n']->get('task') . '",';
                 }
                 echo '"index_description", "' . $data['l10n']->get('description') . '",';
                 echo '"' . $data['l10n']->get('person') . '",';
                 echo '"index_hours", "' . $data['l10n']->get('hours') . '"';
      ?>],
      colModel:[
          {name:'id', index:'id', hidden: true, key: true},
          {name:'index_date',index:'index_date', sorttype: "integer", hidden: true},
          {name:'date', index: 'index_date', width: 80, align: 'center', fixed: true},
          <?php if ($data['mode'] != 'simple') 
          { ?>
              {name:'task', index: 'task'},
          <?php } ?>
          {name:'index_description', index: 'index_description', hidden: true},
          {name:'description', index: 'index_description', width: 300},
          {name:'reporter', index: 'reporter', width: 80},
          {name:'index_hours', index: 'index_hours', sorttype: "integer", hidden: true },
          {name:'hours', index: 'index_hours', width: 50, align: 'right'}
       ],
       pager: "#p_&(grid_id);",
       loadonce: true,
       caption: "&(data['subheading']:h);",
       footerrow: true,
       multiselect: true,
       onSelectRow: function(id)
       {
           if (jQuery("#&(grid_id);").jqGrid('getGridParam', 'selarrrow').length == 0)
           {
               jQuery('#action_select_&(grid_id);').hide();
           }
           else
           {
               jQuery('#action_select_&(grid_id);').show();
           }
       },
       onSelectAll: function(rowids, status)
       {
           if (!status)
           {
               jQuery('#action_select_&(grid_id);').hide();
           }
           else
           {
               jQuery('#action_select_&(grid_id);').show();
           }
       }
    });

jQuery("#&(grid_id);").jqGrid('footerData', 'set', <?php echo json_encode($footer_data); ?>);
        
jQuery("#form_&(grid_id);").submit(function()
{
    var s, i;
    s = jQuery("#&(grid_id);").jqGrid('getGridParam', 'selarrrow');
    for (i = 0; i < s.length; i++)
    {
        jQuery('<input type="checkbox" name="report[' + s[i] + ']" checked="checked" />').hide().appendTo('#form_&(grid_id);');
    }
});

</script>

<div class="action_select_div" id="action_select_&(grid_id);" style="display: none;">
<select id='<?php echo $data['status'];?>_hours_list_action_select' class='action_select' name='action' size='1'>
<?php
    echo "<option>" . midcom::i18n()->get_string("choose action", "midcom.admin.user") . "</option>";
    foreach ($data['action_options'] as $action_id => $option)
    {
        echo "<option value ='" . $action_id . "' >" . $data['l10n']->get($action_id) . "</option>";
    }
?>
</select>
<?php
//create the html for choosers
if ($data['show_widget'])
{
    ?>
    <div id='choosers' style='display:inline;'>
    <?php
    //iterate through choosers
    foreach ($data['widgets'] as $widget)
    {
        echo "<div style='display:none;' class='chooser_widget' id='chooser_" . $widget->_name . "'>";
        foreach($widget->_elements as $element)
        {
            echo $element->toHtml();
        }
        echo "</div>";
    }
    ?>
    </div>
    <script type="text/javascript">
    var action_options_object = new Object();
    var option = null ;

    <?php
        //create array for showing the choosers for specific actions
        foreach($data['action_options'] as $action_id => $option)
        {
            echo "option = null;";
            if (!empty($option))
            {
                echo "option = '" . $option . "';";
                echo '$("#' . $option .'").hide();';
            }
            echo "action_options_object['" . $action_id . "'] = option ; \n";
        }
    ?>
    //set the onchange function
    jQuery(document).ready(function()
    {
        //bind onchange function so select
        jQuery('.action_select').change(function()
        {
            chosen = $(this).val();
            $(".chooser_widget").hide();
            $("#choosers").children().hide();

            //check if chooser must be shown
            if (action_options_object[chosen] != null)
            {
                $(".chooser_widget").hide();
                $("#chooser_" + action_options_object[chosen]).children().filter("script").remove();
                $(this).after($("#chooser_" + action_options_object[chosen]));//.children().filter(":not(script)"));
                $("#chooser_" + action_options_object[chosen]).show().css('display' , 'inline');
            }
        });
    });
    //function to make checkboxes & select visible for current table
    function start_edit(status, object)
    {
        if ($('#action_select_' + status).is(':visible'))
        {
            $('.action_select_div').css('display', 'none');
        }
        else
        {
            $('.action_select_div').css('display', 'none');
            $('#action_select_' + status).css('display', 'inline');
        }
    }
    </script>
    <?php
    $data['show_widget'] = false;
}
?>
<input type="submit" name="send" />
</div>
</form>
</div>

