<?
require_once("api_header.php");

$module = $_REQUEST['module'];
log_api($module);

switch ($module) {
 case 'emergency':
   require('emergency.php');
   break;

 case 'people':
   require('people.php');
   break;

 case 'stellar':
   require('stellar.php');
   break;

 case 'calendar':
   require('calendar.php');
   break;

 case 'tours':
   require('tours.php');
   break;

 case 'corridor':
   require('corridor.php');
   break;

 case 'anniversary':
   require('anniversary.php');
   break;

 case 'features':
   require('features.php');
   break;

 case 'push':
   require('apns_push.php');
   break;


 case 'facilities':
   require('facilities.php');
   break;

 default:
   $data = Array('error' => 'not a valid query');
   echo json_encode($data);
   break;
}



?>