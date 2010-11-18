<?php

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_billing_data_dba extends midcom_core_dbaobject
{

    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'org_openpsa_billing_data';

    function __construct($id = null)
    {
        return parent::__construct($id);
    }

    static function new_query_builder()
    {
        return midcom::dbfactory()->new_query_builder(__CLASS__);
    }

    static function new_collector($domain, $value)
    {
        return midcom::dbfactory()->new_collector(__CLASS__, $domain, $value);
    }

    static function &get_cached($src)
    {
        return midcom::dbfactory()->get_cached(__CLASS__, $src);
    }

    function get_parent_guid_uncached()
    {
        return $this->linkGuid;
    }
    /**
     * funciton to render the address of billing_data
     */
    function render_address()
    {
        //add contact address if needed
        $this->set_address();

        //html-ouptut
        echo '<div class="vcard">';
        echo '<div style="text-align:center"><em>' . midcom::i18n()->get_string('invoice address', 'org.openpsa.contacts') . "</em></div>\n";
        echo "<strong>\n";
        echo $this->recipient . "\n";
        echo "</strong>\n";
        echo "<p>{$this->street}<br />\n";
        echo "{$this->postcode} {$this->city}</p>\n";
        echo "</div>\n";
    }

    /**
     * function to add the address of the contact(person/group) to the billing_data
     * if the flag useContactAddress is set
     */
    function set_address()
    {
        if($this->useContactAddress && !empty($this->linkGuid))
        {
            //get the contact object
            $contact = midcom::dbfactory()->get_object_by_guid($this->linkGuid);
            if(empty($contact->guid))
            {
                debug_push_class(__CLASS__, __FUNCTION__);
                debug_add("Failed to load contact with GUID: " .$this->linkGuid . " - last error:" . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
                debug_pop();
                return false;
            }
            switch (true)
            {
                case is_a($contact , 'org_openpsa_contacts_person_dba'):
                    $this->recipient = $contact->firstname . " " . $contact->lastname;
                    break;
                case is_a($contact , 'org_openpsa_contacts_group_dba'):
                    $this->recipient = $contact->official;
                    break;
                default:
                    break;
            }
            $this->street = $contact->street;
            $this->postcode = $contact->postcode;
            $this->city = $contact->city;
            $this->country = $contact->country;
            $this->email = $contact->email;
        }
    }
}
?>