<?php
$project =& $data['object'];
$project->get_members();
$view = $data['object_view'];
$prefix = midcom::get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
$nap = new midcom_helper_nav();
$node = $nap->get_node($nap->get_current_node());
?>
<div class="sidebar">
    <?php
    $customer = org_openpsa_contacts_group_dba::get_cached($project->customer);
    if ($customer)
    {
        echo "<h2>" . $data['l10n']->get('customer') . "</h2>\n";

        $customer_html = $customer->official;

        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $contacts_url = $siteconfig->get_node_full_url('org.openpsa.contacts');

        if($contacts_url)
        {
            $customer_html = '<a href="' . $contacts_url . '/group/' . $customer->guid . '/">' . $customer_html . "</a>\n";
        }
        echo $customer_html;
    }

    if ($project->manager)
    {
        echo "<h2>" . $data['l10n']->get('manager') . "</h2>\n";
        $contact = org_openpsa_contactwidget::get($project->manager);
        echo $contact->show_inline();
    }
    else if (count($project->resources) > 0)
    {
        echo "<h2>" . $data['l10n']->get('resources') . "</h2>\n";
        foreach ($project->resources as $contact_id => $display)
        {
            $contact = org_openpsa_contactwidget::get($contact_id);
            echo $contact->show_inline() . " ";
        }
    }

    if (count($project->contacts) > 0)
    {
        echo "<h2>" . $data['l10n']->get('contacts') . "</h2>\n";
        foreach ($project->contacts as $contact_id => $display)
        {
            $contact = org_openpsa_contactwidget::get($contact_id);
            echo $contact->show();
        }
    }

    if ($project->newsTopic)
    {
        $news_node = $nap->get_node($project->newsTopic);
        if ($news_node)
        {
            echo "<div class=\"area\">\n";
            midcom::dynamic_load($news_node[MIDCOM_NAV_RELATIVEURL] . "latest/4");
            echo "<p><a href=\"{$news_node[MIDCOM_NAV_RELATIVEURL]}\">" . $data['l10n']->get('news area') . "</a></p>\n";
            echo "</div>\n";
        }
    }
    if ($project->forumTopic)
    {
        $forum_node = $nap->get_node($project->forumTopic);
        if ($forum_node)
        {
            echo "<div class=\"area\">\n";
            midcom::dynamic_load($forum_node[MIDCOM_NAV_RELATIVEURL] . "latest/4");
            echo "</div>\n";
        }
    }
    ?>
</div>

<div class="main org_openpsa_projects_project">
    <h1><?php echo $data['l10n']->get('project'); ?>: &(view['title']:h);</h1>

    <div class="status <?php echo $project->status_type; ?>"><?php echo $data['l10n']->get('project status') . ': ' . $data['l10n']->get($project->status_type); ?></div>

    <div class="time">&(view['start']:h); - &(view['end']:h);</div>

    &(view['description']:h);

    <?php

    midcom::dynamic_load($node[MIDCOM_NAV_RELATIVEURL] . "task/list/project/{$project->guid}/");

    // TODO: Show help message otherwise?
    ?>
</div>
