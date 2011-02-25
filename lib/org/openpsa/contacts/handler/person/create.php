<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.contacts person handler and viewer class.
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_person_create extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_create
{
    /**
     * The person we're working on, if any
     *
     * @param org_openpsa_contacts_person_dba
     */
    private $_person = null;

    /**
     * The parent group, if any
     *
     * @var org_openpsa_contacts_group_dba
     */
    private $_group = null;

    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_person'));
    }

    /**
     * This is what Datamanager calls to actually create a person
     */
    public function & dm2_create_callback(&$datamanager)
    {
        $person = new org_openpsa_contacts_person_dba();
        $person->firstname = "";
        $person->lastname = "";

        if (! $person->create())
        {
            debug_print_r('We operated on this object:', $person);
            throw new midcom_error("Failed to create a new person, cannot continue. Error: " . midcom_connection::get_error_string());
        }

        $this->_person =& $person;

        return $person;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_create($handler_id, array $args, array &$data)
    {
        $_MIDCOM->auth->require_valid_user();
        $_MIDCOM->auth->require_user_do('midgard:create', null, 'org_openpsa_contacts_person_dba');

        if (count($args) > 0)
        {
            // Get the organization
            $this->_group = new org_openpsa_contacts_group_dba($args[0]);
            $_MIDCOM->auth->require_do('midgard:create', $this->_group);
            $_MIDCOM->set_pagetitle($this->_group->official);
        }

        $data['controller'] = $this->get_controller('create');
        $this->_prepare_request_data();

        switch ($data['controller']->process_form())
        {
            case 'save':

                // Index the person
                $indexer = $_MIDCOM->get_service('indexer');
                org_openpsa_contacts_viewer::index_person($data['controller']->datamanager, $indexer, $this->_topic);

                // Add person to group if requested
                if ($this->_group)
                {
                    $member = new midcom_db_member();
                    $member->uid = $this->_person->id;
                    $member->gid = $this->_group->id;
                    $member->create();

                    if (!$member->id)
                    {
                        // TODO: Cleanup
                        throw new midcom_error("Failed adding the person to group #{$this->_group->id}, reason {$member->errstr}");
                    }
                }

                $formmanager = $this->_request_data["controller"]->formmanager;
                //create account too?
                if(!empty($formmanager->_types["username"]->value)){

                    // Create account
                    $account_helper = new org_openpsa_contacts_accounthelper();

                    $password = "";
                    //take user password?
                    if(intval($_POST['org_openpsa_contacts_person_account_password_switch']) > 0){
                        $password = $_POST['org_openpsa_contacts_person_account_password'];
                    }

                    $success = $account_helper->create_account(
                        $this->_person->guid, //guid
                        $formmanager->_types["username"]->value, //username
                        $formmanager->_types["email"]->value, //usermail
                        $password, //password
                        $formmanager->_types["send_welcome_mail"]->value //send_welcome mail
                    );

                    if(!$success){
                        throw new midcom_error("Failed creating account, reason: ".$account_helper->errstr);
                    }
                }

                // Relocate to group view
                $prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
                $_MIDCOM->relocate("{$prefix}person/{$this->_person->guid}/");
                // This will exit

            case 'cancel':
                $_MIDCOM->relocate('');
                // This will exit
        }


        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);

        org_openpsa_contacts_viewer::add_breadcrumb_path_for_group($this->_group, $this);
        $this->add_breadcrumb("", sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('person')));
    }

    private function _prepare_request_data()
    {
        //get rules for js in style
        $rules = $this->_config->get('password_match_score');
        $data_rules = midcom_helper_misc::get_snippet_content($rules);
        $result = eval ("\$contents = array ( {$data_rules}\n );");
        if ($result === false)
        {
            throw new midcom_error("Failed to parse the schema definition in '{$rules}', see above for PHP errors.");
        }
        $this->_request_data['password_rules'] = $contents['rules'];

        //get password_length & minimum score for js
        $this->_request_data['min_score'] = $this->_config->get('min_password_score');
        $this->_request_data['min_length'] = $this->_config->get('min_password_length');
        $this->_request_data['max_length'] = $this->_config->get('max_password_length');
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_create($handler_id, array &$data)
    {
        midcom_show_style("show-person-create");
    }
}
?>