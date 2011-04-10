#!/usr/bin/php
<?
/**** 
 * this daemon regularly polls for stop predictions on each active route
 * doing this keeps caches fresh as a side effect
 * though this isn't the most logical way to keep caches fresh
 *
 * we will check for route-stop subscriptions and notify users
 * whose predictions are below SHUTTLE_NOTIFY_THRESHOLD
 */

define("SHUTTLE_ARRIVAL_DELAY", 340); // 5 minutes 40 seconds

require_once("DaemonWrapper.php");

$daemon = new DaemonWrapper("shuttle");
$daemon->start($argv);

$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_lib_constants.php";
require_once LIBDIR."TransitDataParser.php";
require_once LIBDIR . "db.php";
require_once "apns_lib.php";

$view = new TransitDataView(true);

while ($daemon->sleep(5)) {
  db::ping(); // keep the database connection running

  // Update the caches used by the api and mobile web
  // We only cache when the time is null so we have to call this twice
  $routesInfo = $view->getRoutes();
  
  $time = time();
  $notificationShuttleArrivalTime = $time + SHUTTLE_ARRIVAL_DELAY;

  //$routesInfo = $view->getRoutes($notificationShuttleArrivalTime);
  //error_log("shuttle daemon polling (memory_get_usage is ".number_format(memory_get_usage())." bytes)");
  
  $maxFrequencySeconds = 5*60;
  $routeSQLArgs = array();
  
  $view->refreshLiveParsers();
  $routesInfo = $view->getRoutes($notificationShuttleArrivalTime);
  foreach ($routesInfo as $routeID => $routeInfo) {
    $routeSQLArgs[] = "route_id='$routeID'";
      
    if ($routeInfo['running']) {
      $frequencySeconds = $routeInfo['frequency']*60;
      if ($frequencySeconds > $maxFrequencySeconds) {
        $maxFrequencySeconds = $frequencySeconds;
      }
    }
  }
  
  if (count($routeSQLArgs)) {
    // tweak these to control what range of times will get notifications
    $windowStart = $time;
    $windowEnd   = $notificationShuttleArrivalTime + ceil($maxFrequencySeconds/2);

    $sql = "SELECT device_id, device_type, route_id, stop_id, start_time FROM ShuttleSubscription WHERE ("
      .implode(' OR ', $routeSQLArgs).") AND start_time <= $windowEnd";

    if (!$result = db::$connection->query($sql)) {
      d_error("sql failed: {$db->errno} {$db->error} in $sql");
      continue;
    }
    
    while ($row = $result->fetch_assoc()) {
      $desiredShuttleArrivalTime = $row['start_time'];
      
      /*error_log("Looking at request for shuttle {$row['route_id']}, stop {$row['stop_id']} at ".
        strftime('%I:%M:%S %p', $desiredShuttleArrivalTime)." / currently notifying for shuttles arriving at ".
        strftime('%I:%M:%S %p', $notificationShuttleArrivalTime)." / window is ".
        strftime('%I:%M:%S %p', $windowStart).' to '.
        strftime('%I:%M:%S %p', $windowEnd));*/
      
      // remove rows whose desired times are more than 1.5 vehicles ago
      if ($desiredShuttleArrivalTime < $windowStart) {
        error_log("Warning: unsubscribing request for shuttle {$row['route_id']}, stop {$row['stop_id']} at ".
          strftime('%I:%M:%S %p', $desiredShuttleArrivalTime)." because it is too old");
        unsubscribe_shuttle($row);
        continue;
      }

      $stopInfo = $view->getStopInfoForRoute($row['route_id'], $row['stop_id']);
      $secondsToNextVehicle = $stopInfo['arrives'] - $time;
      
      // Check to see if one of the future predictions is a better match than this one
      if (isset($stopInfo['predictions']) && count($stopInfo['predictions'])) {     
        $timeToDesiredShuttle = $desiredShuttleArrivalTime - $time;
        
        $futurePredictions = array_slice($stopInfo['predictions'], 1);
        foreach ($futurePredictions as $futurePrediction) {
          $inaccuracy     = abs($timeToDesiredShuttle - $secondsToNextVehicle);
          $testInaccuracy = abs($timeToDesiredShuttle - $futurePrediction);
          
            //error_log("  Comparing shuttle at ".
            //  strftime('%I:%M:%S %p', $secondsToNextVehicle+$time)." with shuttle at ".
            //  strftime('%I:%M:%S %p', $futurePrediction+$time));
          if ($testInaccuracy < $inaccuracy && 
              ($timeToDesiredShuttle > $futurePrediction || $testInaccuracy < 60)) {
            $secondsToNextVehicle = $futurePrediction; // later vehicle is more accurate
            //error_log("    ".
            //  strftime('%I:%M:%S %p', $secondsToNextVehicle+$time)." is closer to desired time ".
            //  strftime('%I:%M:%S %p', $desiredShuttleArrivalTime));
          } else {
            //error_log("    Sticking with ".strftime('%I:%M:%S %p', $secondsToNextVehicle+$time));
            break;
          }
        }
      }
      $nextVehicleArrivalTime = $secondsToNextVehicle + $time;
      
      // Skip shuttles that are coming in more than the delay
      if ($nextVehicleArrivalTime > $notificationShuttleArrivalTime) {
        //$minutes = floor($secondsToNextVehicle / 60);
        //$seconds = $secondsToNextVehicle - ($minutes*60);
        //error_log("Skipping request for shuttle route {$row['route_id']}, stop {$row['stop_id']} ".
        //  "because the vehicle that best fits the requested arrival time ".
        //  strftime('%I:%M:%S %p', $desiredShuttleArrivalTime)." arrives in $minutes minutes, $seconds seconds");
        continue;
      }

      if ($secondsToNextVehicle > 0) {
        $minutes = floor($secondsToNextVehicle / 60);
        $timestr = date('g:ia', $nextVehicleArrivalTime);
        $routeName = $routesInfo[$row['route_id']]['name'];
      
        if ($stopInfo['live']) {
          $message = "$routeName arriving at {$stopInfo['name']} in $minutes minutes ($timestr)";
        } else {
          $message = "$routeName (NOT GPS TRACKED) scheduled to arrive at {$stopInfo['name']} in $minutes minutes ($timestr)";
        }
        error_log("Sending message '$message'");
        switch ($row['device_type']) {
          case 'apple':
            $aps = array('aps' => 
             array('alert' => $message,
                   'sound' => 'default'));
            APNS_DB::create_notification($row['device_id'], "shuttletrack:$route_id:$stop_id", $aps, false);
            break;
        }
        
        // make sure to unsubscribe this person so they don't 
        // get a message every 10 seconds until their subscription times out
        unsubscribe_shuttle($row);
      }
    }
  }
}
$daemon->stop();


function unsubscribe_shuttle($row) {
  error_log("    Unsubscribing notification for {$row['route_id']}/{$row['stop_id']}");
  $sql = "DELETE FROM ShuttleSubscription "
    .     "WHERE device_id='" . $row['device_id'] . "' "
    .       "AND device_type='" . $row['device_type'] . "' "
    .       "AND route_id='" . $row['route_id'] . "' "
    .       "AND stop_id='" . $row['stop_id'] . "'";
  if (!db::$connection->query($sql)) {
    d_error("unsubscribe failed: {$db->errno} {$db->error} in $sql");
  }
}
