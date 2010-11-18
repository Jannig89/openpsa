<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: form.php 26503 2010-07-06 12:00:38Z rambo $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/** Auth Frontend Base Class */
require_once (MIDCOM_ROOT . '/midcom/services/auth/frontend.php');

/**
 * Form-based authentication frontend. This one is rather simple, it just renders a
 * two-field (username/password) form which is targeted at the current URL.
 *
 * @package midcom.services
 */
class midcom_services_auth_frontend_form extends midcom_services_auth_frontend
{
    /**
     * This call checks whether the two form fields we have created are present, if yes
     * it reads and returns their values.
     *
     * @return Array A simple associative array with the two indexes 'username' and
     *     'password' holding the information read by the driver or NULL if no
     *     information could be read.
     */
    function read_authentication_data()
    {
        if (   ! array_key_exists('midcom_services_auth_frontend_form_submit', $_REQUEST)
            || ! array_key_exists('username', $_REQUEST)
            || ! array_key_exists('password', $_REQUEST))
        {
            return null;
        }
        
        return array
        (
            'username' => trim($_REQUEST['username']),
            'password' => trim($_REQUEST['password'])
        );
    }

    /**
     * This call renders a simple form without any formatting (that is to be
     * done by the callee) that asks the user for his username and password.
     *
     * The default should be quite useable through its CSS.
     *
     * If you want to replace the form by some custom style, you can define
     * the style- or page-element <i>midcom_services_auth_frontend_form</i>. If this
     * element is present, it will be shown instead of the default style
     * included in this function. In that case you should look into the source
     * of it to see exactly what is required.
     *
     * @link http://www.midgard-project.org/midcom-permalink-c5e99db3cfbb779f1108eff19d262a7c further information about how to style these elements.
     */
    function show_authentication_form()
    {
        // Store the submitted form if the session expired, but user wants to save the data
        if (count($_POST) > 0)
        {
            $data =& midcom::get_custom_context_data('request_data');
            
            $data['restored_form_data'] = array();
            
            foreach ($_POST as $key => $value)
            {
                if (preg_match('/(username|password|frontend_form_submit)/', $key))
                {
                    continue;
                }
                
                $data['restored_form_data'][$key] = base64_encode(serialize($value));
            }
        }
        
        if (   function_exists('mgd_is_element_loaded')
            && mgd_is_element_loaded('midcom_services_auth_frontend_form'))
        {
            midcom_show_element('midcom_services_auth_frontend_form');
        }
        else
        {
            ?>
            <form name="midcom_services_auth_frontend_form" method='post' id="midcom_services_auth_frontend_form">
                <p>
                    <label for="username">
                        <?php echo midcom::i18n()->get_string('username', 'midcom'); ?><br />
                        <input name="username" id="username" type="text" class="input" />
                    </label>
                </p>
                <p>
                    <label for="password">
                        <?php echo midcom::i18n()->get_string('password', 'midcom'); ?><br />
                        <input name="password" id="password" type="password" class="input" />
                    </label>
                </p>
            <?php
            if (   isset($data['restored_form_data'])
                && count($data['restored_form_data']) > 0)
            {
                foreach ($data['restored_form_data'] as $key => $value)
                {
                    echo "                <input type=\"hidden\" name=\"restored_form_data[{$key}]\" value=\"{$value}\" />\n";
                }
                
                echo "                <p>\n";
                echo "                    <label for=\"restore_form_data\" class=\"checkbox\">\n";
                echo "                        <input name=\"restore_form_data\" id=\"restore_form_data\" type=\"checkbox\" value=\"1\" checked=\"checked\" class=\"checkbox\" />\n";
                echo "                        {midcom::i18n()->get_string('restore submitted form data', 'midcom')}?\n";
                echo "                    </label>\n";
                echo "                </p>\n";
            }
            ?>
                <div class="clear">
                  <input type="submit" name="midcom_services_auth_frontend_form_submit" id="midcom_services_auth_frontend_form_submit" value="<?php
                    echo midcom::i18n()->get_string('login', 'midcom'); ?>" />
                </div>
            </form>
            <?php
        }

        if ($GLOBALS['midcom_config']['auth_openid_enable'])
        {
            midcom::load_library('net.nemein.openid');
            $url = midcom::get_host_prefix() . 'midcom-exec-net.nemein.openid/initiate.php';
            ?>
            <!--<h3><?php echo midcom::i18n()->get_string('login using openid', 'net.nemein.openid'); ?></h3>-->

            <div id="open_id_form">
                <form action="<?php echo $url; ?>" method="post">
                    <label for="openid_url">
                        <p><?php echo midcom::i18n()->get_string('openid url', 'net.nemein.openid'); ?></p>
                        <input name="openid_url" id="openid_url" type="text" class="input" value="http://" />
                    </label>
                    <!--
                    <p class="helptext">
                      OpenID lets you safely sign in to different websites with a single password. <a href="https://www.myopenid.com/affiliate_signup?affiliate_id=17">Get an OpenID</a>.
                    </p>
                    -->
                    <input type="submit" name="midcom_services_auth_frontend_form_submit" id="openid_submit" value="<?php
                        echo midcom::i18n()->get_string('login', 'midcom'); ?>" />
                </form>
            </div>
            <?php
        }
    }

    /**
     * ??? IS THIS NEEDED ???
     * @ignore
     */
    function access_denied($reason) { _midcom_stop_request(__CLASS__ . '::' . __FUNCTION__ . ' must be overridden.'); }
}
?>