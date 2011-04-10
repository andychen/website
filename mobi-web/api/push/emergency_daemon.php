#!/usr/bin/php
<?php

define('EMERGENCY_MESSAGE_TIMEOUT', 60*60*24);

require_once "DaemonWrapper.php";

$daemon = new DaemonWrapper("emergency");
$daemon->start($argv);

$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_lib_constants.php";
require_once LIBDIR . "rss_services.php";
require_once LIBDIR . "db.php";
require_once "apns_lib.php";

$date = NULL;
$emergency = new Emergency();
$emergency->use_cache = False;

$version = get_version();
  
while($daemon->sleep(15)) {
  $data = $emergency->get_feed();
  if($data !== False) {
    $new_version = intval($data[0]['version']);
  }

  if($version && ($new_version > $version)) {
    // there is emergency unfortunately we now have to notify ALL devices

    db::ping();
    $emergency_apns = array('aps' => 
      array('alert' => substr($data[0]['text'], 0, 100), 'sound' => 'default')
    );

    $result = APNS_DB::get_active_devices();
    while($row = $result->fetch_assoc()) {
      APNS_DB::create_notification($row['device_id'], "emergencyinfo:", $emergency_apns);
    }
    $result->close();
    save_lastupdate();

  } elseif ($version && ($new_version == $version)) {
    // allow messages to sit until some timeout
    $last_notification = get_lastupdate();
    if (time() - $last_notification > EMERGNECY_MESSAGE_TIMEOUT) {
      $devices = APNS_DB::get_active_devices();
      while ($device = $devices->fetch_assoc()) {
	APNS_DB::mark_notifications_as_read($device['device_id'], array('emergencyinfo:'));
      }
      $devices->close();
      unsave_lastupdate(); // let presence of the file signal unsent notifications
    }
  }

  if($new_version > 0) {
    $version = $new_version;
    save_version($version);
  }
}

$daemon->stop();


/* these functions are for saving and grabbing the emergency version from disk
 that way the daemon is robust to being restarted */

function version_file_name() {
  return getenv('WSETCDIR') . "/pushd/emergency/last_emergency_version";
}

function get_version() {
  if(file_exists(version_file_name())) {
    return intval(file_get_contents(version_file_name()));
 } else {
    return NULL;
 }
}

function save_version($version) {
  file_put_contents(version_file_name(), $version);
}

// save date of last notification (add/remove) batch

function lastupdate_file_name() {
  return getenv('WSETCDIR') . "/pushd/emergency/last_notification_date";
}

function get_lastupdate() {
  if(file_exists(lastupdate_file_name())) {
    return intval(file_get_contents(lastupdate_file_name()));
 } else {
    return time();
 }
}

function save_lastupdate() {
  file_put_contents(lastupdate_file_name(), time());
}

function unsave_lastupdate() {
  unlink(lastupdate_file_name());
}

?>
