<?php
/**
 * @package midcom.helper.activitystream
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id$
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Activity Log library interface
 *
 * Startup loads main class, which is used for all operations.
 *
 * @package midcom.helper.activitystream
 */
class midcom_helper_activitystream_interface extends midcom_baseclasses_components_interface
{

    function __construct()
    {
        parent::__construct();

        $this->_component = 'midcom.helper.activitystream';
    }

    function _on_initialize()
    {
        return true;
    }

    function _on_watched_operation($operation, &$object)
    {
        debug_push_class($object, __FUNCTION__);
        if (!$object->_use_activitystream)
        {
            // Activity Log not used for this object
            debug_pop();
            return;
        }

        // Create an activity log entry
        if (!midcom::auth()->request_sudo('midcom.helper.activitystream'))
        {
            // Not allowed to create activity logs
            debug_pop();
            return;
        }

        $activity = new midcom_helper_activitystream_activity_dba();
        $activity->target = $object->guid;

        if ($object->_activitystream_verb)
        {
            $activity->verb = $object->_activitystream_verb;
        }
        else
        {
            $activity->verb = midcom_helper_activitystream_activity_dba::operation_to_verb($operation);
        }
        if (!$activity->verb)
        {
            debug_add('Cannot generate a verb for the activity, skipping');
            midcom::auth()->drop_sudo();
            debug_pop();
            return;
        }

        static $handled_targets = array();
        if (isset($handled_targets["{$activity->target}_{$activity->actor}"]))
        {
            // We have already created an entry for this object in this request, skip
            debug_pop();
            return;
        }

        if ($object->_rcs_message)
        {
            $activity->summary = $object->_rcs_message;
        }
        
        if (midcom::auth()->user)
        {
            $actor = midcom::auth()->user->get_storage();
            $activity->actor = $actor->id;
        }
        
        $activity->application = midcom::get_context_data(MIDCOM_CONTEXT_COMPONENT);

        if ($activity->create())
        {
            $handled_targets["{$activity->target}_{$activity->actor}"] = true;
        }
        
        midcom::auth()->drop_sudo();
        debug_pop();
    }
}
?>