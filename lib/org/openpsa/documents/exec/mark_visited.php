<?php
midcom::auth->require_valid_user();

if ($_SERVER['REQUEST_METHOD'] != 'POST')
{
    midcom::generate_error(MIDCOM_ERRFORBIDDEN, 'Only POST requests are allowed here.');
}

if (!array_key_exists('guid', $_POST))
{
    midcom::generate_error(MIDCOM_ERRCRIT,
                'No document specified, aborting.');
}

$document = new org_openpsa_documents_document_dba($_POST['guid']);
$person = midcom::auth->user->get_storage();
if ((int) $person->get_parameter('org.openpsa.documents_visited', $document->guid) < (int) $document->metadata->revised)
{
    $person->set_parameter('org.openpsa.documents_visited', $document->guid, time());
}
?>