<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"> 
    <head>
        <title><(title)> - <?php echo midcom::get_context_data(MIDCOM_CONTEXT_PAGETITLE); ?></title>
         <?php
         midcom::print_head_elements(); 
         ?>
    </head>
    <body>
        <?php 
        midcom::content(); 
        midcom::uimessages()->show(); 
        midcom::toolbars()->show(); 
        ?>
    </body>
</html>
