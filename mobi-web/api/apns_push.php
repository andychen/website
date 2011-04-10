<?

$data = Array();

switch ($_REQUEST['device_type']) {
 case 'apple':
   require "push/apns_lib.php";

   $command = ($_REQUEST['command']);
   if($command == 'register') {
     $identifiers = APNS_DB::create_device_pass_key();
     if(isset($_REQUEST['device_token'])) {
       APNS_DB::register_device_token($_REQUEST['device_token'], $identifiers['device_id'], $_REQUEST['app_id']);
      }
     $data = $identifiers;
       
   } else {
     $device_id = $_REQUEST['device_id'];

     if(!APNS_DB::verify_device_id($device_id, $_REQUEST['pass_key'])) {
       throw new Exception('invalid pass_key');
     }
         
     if($command == 'newDeviceToken') {
       APNS_DB::register_device_token($_REQUEST['device_token'], $device_id, $_REQUEST['app_id']);
	$data = array('success' => True);

     } elseif($command == 'moduleSetting') {
       $enabled = (bool)intval($_REQUEST['enabled']);
       $module = $_REQUEST['module_name'];
       APNS_DB::set_module_setting($device_id, $module, $enabled);
       $data = array('success' => True, 'module' => $module, 'enabled' => $enabled);

     } elseif($command == 'getUnreadNotifications') {
       $data = APNS_DB::get_unread_notifications($device_id);

     } elseif($command == 'markNotificationsAsRead') {
       $readNotifications = json_decode($_REQUEST['tags'], true);
       APNS_DB::mark_notifications_as_read($device_id, $readNotifications);
       $data = APNS_DB::get_unread_notifications($device_id);

     }
   }
   break;

 default:
   $data = array('error' => 'device type not supported');
   break;
 }

echo json_encode($data);

?>
