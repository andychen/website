<?php
//error_reporting(0);
$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "api/api_header.php";

log_api('shuttles');

require_once LIBDIR."TransitDataParser.php";

$data = Array();
$command = $_REQUEST['command'];

//$allstops = array("mass84_d","massbeac","comm487","commsher","comm478","beacmass","mass77","edge","kendsq_d","amhewads","medilab","kres","burtho","tangwest","w92ames","simmhl","vassmass","statct","massnewb","beac528","bays111","bays155","stpaul259","manc58","here32","nw10","nw30","nw86","nw61","mainwinds","porthamp","camb638","camb5th","6thcharl","elotmain","amesbld66","mitmed","kendsq","wadse40","mccrmk","newho","ww15","brookchest","putmag","rivfair","rivpleas","rivfrank","sydgreen","paci70","whou");

//error_log("API COMMAND $command...");


switch ($command) {
  case 'locInfo':
    $view = new TransitDataView();
		$lat_phone = $_REQUEST['lat'];
    $lon_phone = $_REQUEST['lon'];
    
		$data["closestStops"] = $view->getClosestStopInfo(5, $lat_phone, $lon_phone); 

    
/*		

		
		//Sort stops by distance away from phone... and note if a stop has any routes running.
		foreach ($allstops as $key => $stopId){
		  $stopInfo = $view->getStopInfo($stopId);
			$lat_stop = $stopInfo["coordinates"]["lat"];
			$lon_stop = $stopInfo["coordinates"]["lon"];
			$isAnyRunning = false;
			$routes = array();
			foreach ($stopInfo["routes"] as $key => $info){
				//Probably non-ideal place to adjust the route title.
			  $info["name"] = str_ireplace("Saferide ", "", $info["name"]);
				$stopInfo["routes"][$key] = $info;
			  if ($info["running"]) $isAnyRunning = true;
			}
			$stopInfo["isAnyRunning"] = $isAnyRunning;
			$stopInfo["stopID"] = $stopId;
			$locations[strval($view->getDistance($lat_phone, $lon_phone, $lat_stop, $lon_stop))] = $stopInfo;
		}
		ksort($locations);
    
		//Return stop info for 5 closest stops that have > 0 shuttles running.
		$closestStops = array();
		foreach ($locations as $key => $info)
		{
  		$stop = array();
  		$stops = array();
      if ($info["isAnyRunning"]) {
        foreach ($info['routes'] as $routeID => $stopTimes) {
          $stops[] = formatStopInfo($routeID, $info["stopID"], $info, $stopTimes);
        }
        $stop['stops'] = $stops;
				$closestStops[] = $stop;
      }
			if (count($closestStops) >= 5) break;
		}
		$data["closestStops"] = $closestStops;
    */
    break;

  case 'stopInfo':
    $view = new TransitDataView();

    $stopID = $_REQUEST['id'];
    $time = time();
    
    $stopInfo = $view->getStopInfo($stopID);
    $stops = array();
    if (isset($stopInfo['routes'])) {
      foreach ($stopInfo['routes'] as $routeID => $stopTimes) {
        $stops[] = formatStopInfo($routeID, $stopID, $stopInfo, $stopTimes);
      }
      $data['stops'] = $stops;
      $data['now'] = $time;
    } else {
      $data['error'] = "could not perform $command";
    }
    break;
    
  case 'routes': // static info about all routes
		$t = microtime(True);
    $view = new TransitDataView();
    $routesInfo = $view->getRoutes();
    echo microtime(True) - $t;
    foreach ($routesInfo as $routeID => $routeInfo) {
      $entry = formatRouteInfo($routeID, $routeInfo);
      
      if (!isset($_REQUEST['compact']) || !$_REQUEST['compact']) {
        $paths = $view->getRoutePaths($routeID);
        $fullRouteInfo = $view->getRouteInfo($routeID);
        
        $pathAdded = false;
        $stops = array();
        foreach ($fullRouteInfo['stops'] as $stopID => $stopInfo) {
          $stop = formatStopForRouteInfo($stopID, $stopInfo);
          if (!$pathAdded) {
            $stop['path'] = mergePaths($paths);
            $pathAdded = true;
          }
          $stops[] = $stop;
        }
        $entry['stops'] = $stops;
      }
      
      $data[] = $entry;
    }
    break;
    
  case 'routeInfo': // live info for individual routes
    $routeID = $_REQUEST['id'];
    $time = time();
    if ($routeID) {
      $view = new TransitDataView();
      $routeInfo = $view->getRouteInfo($routeID);
      $paths = $view->getRoutePaths($routeID);
      $vehicles = $view->getRouteVehicles($routeID);
      
      $pathAdded = false;
      $stops = array();
      foreach ($routeInfo['stops'] as $stopID => $stopInfo) {
        $stop = formatStopForRouteInfo($stopID, $stopInfo);
        if (isset($_REQUEST['full']) && $_REQUEST['full'] == 'true' && !$pathAdded) {
          $stop['path'] = mergePaths($paths);
          $pathAdded = true;
        }
        $stops[] = $stop;
      }
      $data = formatRouteInfo($routeID, $routeInfo);
      $data['stops'] = $stops;
      $data['now'] = $time;
      $data['vehicleLocations'] = array_values($vehicles);
  
    } else {
      $data = Array('error' => "no route parameter");
    }  
    break;
    
  case 'subscribe': 
  case 'unsubscribe':
    require_once $APIROOT . '/push/apns_lib.php';
    
    $data = array('error' => "could not perform $command");
    
    if ($sub = APNSSubscriber::create()) {
      $routeID = $_REQUEST['route'];
      $stopID = $_REQUEST['stop'];
      $params = Array(
        'route_id' => $routeID,
        'stop_id'  => $stopID,
      );
      
      // Always unsubscribe any existing subscriptions
      $unsubscribed = $sub->unsubscribe("ShuttleSubscription", $params);
      
      if ($command == 'unsubscribe') {
        if ($unsubscribed) {
          $data = array('success' => $command);
        }
      } else {
        $requestTime = isset($_REQUEST['time']) ? intval($_REQUEST['time']) : time();
        $params['start_time'] = $requestTime;  // give the backend the real request time
        
        if ($sub->subscribe("ShuttleSubscription", $params)) {        
          $view = new TransitDataView();
          $routeInfo = $view->getRouteInfo($routeID, $requestTime);
          
          $frequencySeconds = $routeInfo['frequency']*60;
          $padding = ceil($frequencySeconds / 2);
          
          $data = array(
            'success'     => $command,
            'start_time'  => $requestTime - $padding,
            'expire_time' => $requestTime + $padding,
          );
        
          //error_log("Requested shuttle notification for route $routeID, stop $stopID at ".strftime('%I:%M:%S %p', $requestTime)." from ".strftime('%I:%M:%S %p', $data['start_time'])." to ".strftime('%I:%M:%S %p', $data['expire_time']));
        }
      }
    }
    break;
}

//error_log(print_r($data, true));

echo json_encode($data);

//error_log("API COMMAND $command FINISHED");

function formatRouteInfo($routeID, $routeInfo) {
  return array(
    'route_id'   => "$routeID", // php really likes to make the #1 bus an integer
    'title'      => $routeInfo['name'],
    'interval'   => $routeInfo['frequency'],
    'isSafeRide' => $routeInfo['agency'] == 'saferide' ? true : false,
    'isRunning'  => $routeInfo['running'] ? true : false,
    'summary'    => isset($routeInfo['description']) ? $routeInfo['description'] : '',
    'gpsActive'  => isset($routeInfo['live']) && $routeInfo['live'] ? true : false,
  );
}

function formatStopForRouteInfo($stopID, $stopInfo) {
  $stop = array(
    'id'          => "$stopID",
    'title'       => $stopInfo['name'],
    'lat'         => $stopInfo['coordinates']['lat'],
    'lon'         => $stopInfo['coordinates']['lon'],
    'next'        => (int)$stopInfo['arrives'],
    'predictions' => array(),
  );
  if (isset($stopInfo['predictions']) && count($stopInfo['predictions']) > 1) {
    array_shift($stopInfo['predictions']); // remove prediction corresponding to $stop['next']
    $stop['predictions'] = $stopInfo['predictions'];
  }
  if ($stopInfo['upcoming']) {
    $stop['upcoming'] = true;
  }
  return $stop;
}

function formatStopInfo($routeID, $stopID, $stopInfo, $stopTimes) {
  $stop = array(
    'id'          => "$stopID",
    'stop_title'  => $stopInfo['name'],
    'route_id'    => "$routeID",
		'route_title' => $stopInfo['routes'][$routeID]['name'],
    'lat'         => $stopInfo['coordinates']['lat'],
    'lat'         => $stopInfo['coordinates']['lat'],
    'lon'         => $stopInfo['coordinates']['lon'],
    'next'        => (int)$stopTimes['arrives'],
    'gps'         => $stopTimes['live'],
  );
  if (isset($stopTimes['predictions']) && count($stopTimes['predictions']) > 1) {
    array_shift($stopTimes['predictions']); // remove prediction corresponding to $stop['next']
    $stop['predictions'] = $stopTimes['predictions'];
  }
  return $stop;
}

function mergePaths($paths) {
  // the iPhone app does not understand paths which aren't in a loop.  Wheeee!
  $paths = array_values($paths);

  if (count($paths) > 1) {
    $foundPair = true;
    while ($foundPair) {
      $foundPair = false;
      for ($i = 0; $i < count($paths); $i++) {
        for ($j = 0; $j < count($paths); $j++) {
          if ($i == $j) { continue; }
          
          $path1 = array_values($paths[$i]);
          $path2 = array_values($paths[$j]);
          //error_log("Path 1 ($i): ".count($path1)." points");
          //error_log("Path 2 ($j): ".count($path2)." points");
          for ($x = 0; $x < count($path1)-1; $x++) {
            for ($y = 0; $y < count($path2)-1; $y++) {
              
              if ($path1[$x] == $path2[$y] && $path1[$x+1] == $path2[$y+1]) {
                // Found a place to attach the paths!
                $path1Segment1 = array_slice($path1, 0, $x+1);
                $path1Segment2 = array_slice($path1, $x);
                $path2Segment1 = array_slice($path2, 0, $y+1);
                $path2Segment2 = array_slice($path2, $y);
                
                unset($paths[$i]);
                unset($paths[$j]);
                $paths[] = mergeArrays(array(
                  $path1Segment1,
                  array_reverse($path2Segment1),
                  array_reverse($path2Segment2),
                  $path1Segment2,
                ));
                $foundPair = true;
                break;
              } else if ($path1[$x] == $path2[$y+1] && $path1[$x+1] == $path2[$y]) {
                // Found a place to attach the paths!
                $path1Segment1 = array_slice($path1, 0, $x+1);
                $path1Segment2 = array_slice($path1, $x);
                
                unset($paths[$i]);
                unset($paths[$j]);
                $paths[] = mergeArrays(array(
                  $path1Segment1,
                  $path2,
                  $path1Segment2,
                ));
                $foundPair = true;
              }
            }
            if ($foundPair) { break; }
          }
          if ($foundPair) { break; }
        }
        if ($foundPair) { break; }
      }
    }
  }

  if (count($paths) > 1) {
    error_log("Warning!  Multiple path segments after merge.");
  }  

  // Last ditch effort... if there is still more than one we will just
  // merge and live with the criss-crosses
  $mergedPath = array();
  foreach ($paths as $path) {
    $mergedPath = array_merge($mergedPath, $path);
  }
  
  return $mergedPath;
}

function mergeArrays($arrays) {
  $result = array();
  foreach ($arrays as $array) {
    $result = array_merge($result, $array);
  }
  return array_values($result);
}
