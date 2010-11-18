<?php
midcom::header('Content-type: application/json');

if (   isset($_POST['latitude'])
    && isset($_POST['longitude']))
{
    // Updating user's location
    $location_array = array
    (
        'latitude' => (float) $_POST['latitude'],
        'longitude' => (float) $_POST['longitude'],
    );
    
    if (isset($_POST['accuracy']))
    {
        // W3C accuracy is in meters, convert to our approximates
        if ($_POST['accuracy'] < 30)
        {
            // Exact enough
            $location_array['accuracy'] = ORG_ROUTAMC_POSITIONING_ACCURACY_GPS;
        }
        elseif ($_POST['accuracy'] < 400)
        {
            $location_array['accuracy'] = ORG_ROUTAMC_POSITIONING_ACCURACY_STREET;
        }
        elseif ($_POST['accuracy'] < 5000)
        {
            $location_array['accuracy'] = ORG_ROUTAMC_POSITIONING_ACCURACY_CITY;
        }
        else
        {
            // Fall back to "state level"
            $location_array['accuracy'] = 50;
        }  
    }

    $location_array['source'] = 'browser';
    
    org_routamc_positioning_user::set_location($location_array);
}

echo json_encode(org_routamc_positioning_user::get_location());
?>