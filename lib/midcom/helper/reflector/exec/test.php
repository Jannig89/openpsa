<?php
/**
 * @package midcom.helper.reflector
 * @author The Midgard Project, http://www.midgard-project.org
 * @version $Id: test.php 22990 2009-07-23 15:46:03Z flack $
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

if (   !isset($_GET['guid'])
    || empty($_GET['guid']))
{
    midcom::generate_error(MIDCOM_ERRNOTFOUND, 'Specify $guid via GET for info');
    // this will exit
}

$object = midcom::dbfactory()->get_object_by_guid($_GET['guid']);
if (   !is_object($object)
    || !isset($object->guid)
    || empty($object->guid))
{
    midcom::generate_error(MIDCOM_ERRNOTFOUND, "Could not find object with GUID {$_GET['guid']}");
    // this will exit
}
$reflector =& midcom_helper_reflector::get($object);

echo "Got " . $reflector->get_class_label() . ' "' . $reflector->get_object_label($object) . "\", dump<pre>\n";
var_dump($object);
echo "</pre>\n";

if (midcom_helper_reflector_tree::has_children($object))
{
    echo "Object has children<br/>\n";
    echo "Child counts <pre>\n";
    $counts = midcom_helper_reflector_tree::count_child_objects($object);
    print_r($counts);
    echo "</pre>\n";
    echo "Child objects dump<pre>\n";
    $children = midcom_helper_reflector_tree::get_child_objects($object);
    var_dump($children);
    echo "</pre>\n";
    
}

?>