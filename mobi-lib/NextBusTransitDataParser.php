<?php

$docRoot = getenv("DOCUMENT_ROOT");

require_once('TransitDataParser.php');
require_once('DiskCache.php');
require_once($docRoot. '/mobi-config/mobi_lib_constants.php');

define('NEXTBUS_SERVICE_URL', 'http://webservices.nextbus.com/service/publicXMLFeed?');

class NextBusTransitDataParser extends TransitDataParser {
  private static $caches = array();
  private $predictionDataLoaded = array();
  
  protected function isLive() {
    return true;
  }
  
  public function getRouteVehicles($routeID) {
    $route = $this->getRoute($routeID);
    if (!$route) { return array(); }
    
    $vehicles = array();

    $xml = $this->queryNextBus(array(
      'a'       => $route->getAgencyID(),
      'command' => 'vehicleLocations',
      'r'       => $routeID,
      't'       => '0',
    ));
    
    if ($xml) {
      foreach ($xml->getElementsByTagName('vehicle') as $vehicle) {
        $attributes = $vehicle->attributes;
        
        $vehicleID = $attributes->getNamedItem('id')->nodeValue;
        $vehicles[$vehicleID] = array(
          'secsSinceReport' => 
                intval($attributes->getNamedItem('secsSinceReport')->nodeValue),
          'lat'      => $attributes->getNamedItem('lat')->nodeValue,
          'lon'      => $attributes->getNamedItem('lon')->nodeValue,
          'heading'  => $attributes->getNamedItem('heading')->nodeValue,
          'agencyID' => $route->getAgencyID(),
          'routeID'  => $routeID,
        );
        $vehicles[$vehicleID]['iconURL'] = 
          $this->getMapIconUrlForRouteVehicle($routeID, $vehicles[$vehicleID]);
      }
    }
    
    return $vehicles;
  }

  protected function updatePredictionData($routeID) {
    if (isset($this->predictionDataLoaded[$routeID])) {
      return; // already loaded
    }
    
    $route = $this->getRoute($routeID);
    if (!$route) { return; }

    $stopList = array();
    foreach ($route->getStops() as $stop) {
      $stopList[] = $routeID.'|null|'.$stop['stopID'];
    }

    if (count($stopList)) {
      $xml = $this->queryNextBus(array(
        'command' => 'predictionsForMultiStops',
        'a'       => $route->getAgencyID(),
        'stops'   => $stopList,
      ), $age);
      
      if ($xml) {
        $routePredictions = array();

        foreach ($xml->getElementsByTagName('predictions') as $predictions) {
          $stopID = $predictions->attributes->getNamedItem('stopTag')->nodeValue;
  
          foreach ($predictions->getElementsByTagName('prediction') as $prediction) {
            $attributes = $prediction->attributes;
            $directionID = self::getDirectionID($routeID, $attributes->getNamedItem('dirTag')->nodeValue);
            $offset = intval($attributes->getNamedItem('seconds')->nodeValue) + $age;
            
            if (!isset($routePredictions[$directionID])) {
              $routePredictions[$directionID] = array();
            }
            if (!isset($routePredictions[$directionID][$stopID])) {
              $routePredictions[$directionID][$stopID] = array();
            }
            $routePredictions[$directionID][$stopID][] = $offset;
          }
          unset($prediction);
        } 
        unset($predictions);
        
        foreach ($routePredictions as $directionID => $directionPredictions) {
          foreach ($directionPredictions as $stopID => $stopPredictions) {
            $route->setStopPredictions($directionID, $stopID, $stopPredictions);
          }
        }
      }
    }
    $this->predictionDataLoaded[$routeID] = true;
  }

  protected function loadData($agencyIDs, $routeIDs, $args) {
    if (isset($args['agencyRemap'])) {
      foreach ($agencyIDs as $index => $agencyID) {
        if (isset($args['agencyRemap'][$agencyID])) {
          $agencyIDs[$index] = $args['agencyRemap'][$agencyID];
        }
      }
    }
    $agencyIDs = array_unique($agencyIDs);

    foreach ($agencyIDs as $agencyID) {
      //error_log("NextBus loading ".str_pad($agencyID, 20)." memory_get_usage(): ".memory_get_usage());
      
      $xml = $this->queryNextBus(array(
        'command' => 'routeList',
        'a'       => $agencyID,
      ));
      
      if (!$xml) { continue; }
      
      $foundStops = array();
      
      foreach ($xml->getElementsByTagName('route') as $route) {
        $routeID = $route->attributes->getNamedItem('tag')->nodeValue;
        if (!in_array($routeID, $routeIDs)) {
          continue;
        }
        
        $this->addRoute(new TransitRoute(
          $routeID, 
          $agencyID, 
          $route->attributes->getNamedItem('title')->nodeValue, 
          '', // TODO
          $args['isLoop']
        ));
        
        $xml = $this->queryNextBus(array(
          'command' => 'routeConfig',
          'r'       => $routeID,
          'a'       => $agencyID,
        ));
        if (!$xml) {
          continue;
        }
        
        // Add the stops
        $stopOrder = array();
        foreach ($xml->getElementsByTagName('stop') as $stop) {
          $attributes = $stop->attributes;
          if (!$attributes->getNamedItem('title')) {
            continue;
          }
          
          $stopID = $attributes->getNamedItem('tag')->nodeValue;
          if (!isset($foundStops[$stopID])) {
            $this->addStop(new TransitStop(
              $stopID, 
              $attributes->getNamedItem('title')->nodeValue, 
              '', 
              $attributes->getNamedItem('lat')->nodeValue, 
              $attributes->getNamedItem('lon')->nodeValue
            ));
            $foundStops[$stopID] = true;
          }
          $stopOrder[$stopID] = count($stopOrder);
        }
        unset($stop);
        
        $directions = array();
        $serviceID = $routeID.'_service';
        $routeService = new TransitService($serviceID, true);

        // Add the segments
        foreach ($xml->getElementsByTagName('direction') as $direction) {
          $attributes = $direction->attributes;
          if ($attributes->getNamedItem('useForUI')->nodeValue != 'true') {
            continue;
          }
          
          $directionID = self::getDirectionID($routeID, $attributes->getNamedItem('tag')->nodeValue);
          
          if (!isset($directions[$directionID])) {
            $directions[$directionID] = array(
              'route'     => $routeID,
              'name'      => $attributes->getNamedItem('title')->nodeValue,
              'service'   => $serviceID,
              'stops'     => array(),
            );
          } else {
            if (strlen($directions[$directionID]['name'])) {
              $directions[$directionID]['name'] .= ' / ';
            }
            $directions[$directionID]['name'] .= $attributes->getNamedItem('title')->nodeValue;
          }
          
          foreach ($direction->getElementsByTagName('stop') as $index => $stop) {
            $stopID = $stop->attributes->getNamedItem('tag')->nodeValue;
            $directions[$directionID]['stops'][$stopID] = array(
              'stop'     => $stopID,
              'sequence' => $stopOrder[$stopID], //$index,
            );
          }
          unset($stop);
        }
        unset($direction);
        
        $paths = $this->getPaths($xml, $agencyID, $routeID, $directions);
        
        foreach ($paths as $pathIndex => $path) {
          $this->getRoute($routeID)->addPath(new TransitPath($pathIndex, $path));
        }
        unset($path);

        foreach ($directions as $directionID => $direction) {
          $segmentID = $directionID;
          if ($segmentID == 'loop') {
            $segmentID = $routeID;
          }
          
          $segment = new TransitSegment(
            $segmentID,
            $direction['name'],
            $routeService,
            $directionID
          );
          foreach ($direction['stops'] as $stopID => $stop) {
            $segment->addStop($stopID, $stop['sequence']);
            $segment->setStopPredictions($stopID, array());
          }
          $this->getRoute($direction['route'])->addSegment($segment);

          unset($stop);
        }
      }
      //error_log("NextBus loaded ".str_pad($agencyID, 20)."  memory_get_usage(): ".memory_get_usage());
    }
  }
  
  private static function getDirectionID($routeID, $tag) {
    $directionID = $tag;
    
    // MBTA has this silly version number in the middle of their direction ids
    // which is inconsistent between route config and predictions.  If the 
    // direction id matches the MBTA pattern, strip out the version number
    $parts = explode('_', $tag);
    if (count($parts) > 2) {
      $first = reset($parts);
      $last = end($parts);
      if ($first == $routeID && ($last == '0' || $last == '1')) {
        $directionID = $first.'_'.$last;
      }
    }
    
    return $directionID;
  }
  
  private static function mergePathsIfPointsMatch($path1, $path2) {
    $path1First = reset($path1);
    $path1Last = end($path1);
    $path2First = reset($path2);
    $path2Last = end($path2);
    
    if (self::pointsEqual($path1Last, $path2First)) {
      return self::getMergedPath($path1, $path2);
      
    } else if (self::pointsEqual($path2Last, $path1First)) {
      return self::getMergedPath($path2, $path1);
    }
    
    return false;
  }

  private static function getMergedPath($path1, $path2) {
    //error_log("Merging paths:");
    //self::printPaths(array($path1, $path2));
    array_pop($path1);
    return array_merge($path1, $path2);
  }

  private static function pointsEqual($p1, $p2) {
    foreach ($p1 as $i => $c) {
      if (!isset($p2[$i]) || $p1[$i] != $p2[$i]) {
        return false;
      }
    }
    return true;
  }
  
  private static function pointsAreSubset($haystack, $needle) {
    $isSubset = false;
    
    $haystack = array_values($haystack);
    $needle = array_values($needle);
    
    for ($h = 0; $h < count($haystack); $h++) {
      if (self::pointsEqual($haystack[$h], $needle[0])) {
        $isSubset = true;
        for ($n = 0; $n < count($needle); $n++) {
          if (($h + $n) >= count($haystack) || 
              !self::pointsEqual($haystack[$h+$n], $needle[$n])) {
            $isSubset = false;
            break;
          }
        }
        if ($isSubset) { break; }
      }
    }
    
    return $isSubset;
  }
  
  private static function printPaths($paths) {
    error_log("Paths:");
    foreach ($paths as $pathIndex => $path) {
      $index = str_pad($pathIndex, 35);
      $first = reset($path);
      $last = end($path);
      $firstPoint = str_pad($first['lat'].', ', 12).str_pad($first['lon'], 12);
      $lastPoint  = str_pad($last['lat'].', ', 12).str_pad($last['lon'], 12);
      error_log("    path $index ($firstPoint) -> ($lastPoint)");
    }
  }
  
  private function getPaths(&$xml, $agencyID, $routeID, &$directions) {
    
    $paths = array();
    
    $cache = self::getCacheForCommand(array('command' => 'routePath'), $this->daemonMode);
    $cacheName = "$agencyID.$routeID";
    
    if ($cache->isFresh($cacheName)) {
      $paths = json_decode($cache->read($cacheName), true);
      
    } else {
      // Note: this code assumes that direction id strings all have the same length
      // We'd check for actual direction id matches but sometimes the MBTA 
      // uses direction ids which are no longer in the list of directions in 
      // the route config

      foreach ($xml->getElementsByTagName('path') as $path) {
        $points = array();
        foreach ($path->getElementsByTagName('point') as $point) {
          $attributes = $point->attributes;
          $points[] = array(
            'lat' => $attributes->getNamedItem('lat')->nodeValue,
            'lon' => $attributes->getNamedItem('lon')->nodeValue,
          );
        }
        $paths[] = $points;
      }
      unset($point);
      unset($path);
      
      //self::printPaths($paths);
      
      // Match up path segments by endpoint
      $foundPair = true;
      while ($foundPair) {
        $foundPair = false;
        foreach ($paths as $pathIndex => $path) {
          foreach ($paths as $comparePathIndex => $comparePath) {
            if ($pathIndex == $comparePathIndex) { continue; }
            
            $merged = self::mergePathsIfPointsMatch($path, $comparePath);
            if ($merged) {
              $paths[] = $merged;
              $foundPair = true;
  
              unset($paths[$pathIndex]);
              unset($paths[$comparePathIndex]);
              break;
            }
          }
          if ($foundPair) { break; }
        }
        unset($path);
        unset($comparePath);
      }
  
      // Eliminate path subsets.  
      // Not sure why these are here but NextBus sometimes has extra segments
      if (count($paths) > 1) {
        $foundSubset = true;
        while ($foundSubset) {
          $foundSubset = false;
          foreach ($paths as $pathIndex => $path) {
            foreach ($paths as $comparePathIndex => $comparePath) {
              if ($pathIndex == $comparePathIndex) { continue; }
              
              if (count($path) >= count($comparePath)) {
                if (self::pointsAreSubset($path, $comparePath)) {
                  $foundSubset = true;
                  unset($paths[$comparePathIndex]);
                }
              } else {
                if (self::pointsAreSubset($comparePath, $path)) {
                  $foundSubset = true;
                  unset($paths[$pathIndex]);
                }
              }
            }
            if ($foundSubset) { break; }
          }
          unset($path);
          unset($comparePath);
        }
      }
      $paths = array_values($paths);
      $cache->write(json_encode($paths), $cacheName);
    }
    
    return $paths;
  }

  private static function loadXML($text) {
    $xml = new DOMDocument();
    $xml->loadXML($text);
    
    $errorCount = 0;
    foreach ($xml->getElementsByTagName('Error') as $error) {
      error_log($error->nodeValue);
      $errorsCount++;
    }
    if ($errorCount == 0) {
      return $xml;
    }
    return false;
  }
  
  private function queryNextBus($params, &$age=null) {
    $xml = null;

    $cache = self::getCacheForCommand($params, $this->daemonMode);
    $cacheName = self::getCacheNameForCommand($params);
    $age = $cache->getAge($cacheName);

    if ($cache->isFresh($cacheName)) {
      $xml = self::loadXML($cache->read($cacheName));
      
    } else {
      // suppress urlencoded brackets.  nextbus doesn't do brackets.
      $specialQueryArgs = '';
      if (isset($params['stops'])) {
        foreach ($params['stops'] as $stopArg) {
          $specialQueryArgs .= '&'.http_build_query(array(
            'stops' => $stopArg,
          ));
        }
        unset($params['stops']);
      }
      
      $url = NEXTBUS_SERVICE_URL.http_build_query($params).$specialQueryArgs;
      $contents = file_get_contents($url);
      //error_log("NextBusTransitDataParser requested $url", 0);
      
      if (!$contents) {
        error_log("Failed to read contents from $url, reading expired cache");
        $xml = self::loadXML($cache->read($cacheName));
        
      } else {
        $xml = self::loadXML($contents);
        if ($xml) {
          $age = 0; // fresh load!
          $cache->write($contents, $cacheName);
          
        } else {
          error_log("XML from $url had errors, reading expired cache");
          $xml = self::loadXML($cache->read($cacheName));
        }
      }
    }

    return $xml;
  }
  
  private static function getCacheForCommand($params, $daemonMode) {
    $cacheKey = $params['command'];
    
    if (!isset(self::$caches[$cacheKey])) {
      $cacheTimeout = 20;
      $suffix = 'xml';
      
      switch ($params['command']) {
        case 'routeList': 
          $cacheKey = 'routes';
          $cacheTimeout = NEXTBUS_ROUTE_CACHE_TIMEOUT;
          break;

        case 'routeConfig':
          $cacheKey = 'route';
          $cacheTimeout = NEXTBUS_ROUTE_CACHE_TIMEOUT;
          break;
          
        case 'predictions': 
        case 'predictionsForMultiStops':
          $cacheKey = 'predictions';
          $cacheTimeout = NEXTBUS_PREDICTION_CACHE_TIMEOUT;
          break;
          
        case 'vehicleLocations':
          $cacheKey = 'vehicles';
          $cacheTimeout = NEXTBUS_VEHICLE_CACHE_TIMEOUT;
          break;
        
        case 'routePath':  // fake command for path cache
          $cacheKey = 'path';
          $cacheTimeout = NEXTBUS_ROUTE_CACHE_TIMEOUT;
          $suffix = 'json';
          break;
      }
      
      // daemons should load cached files aggressively to beat user page loads
      if ($daemonMode) {
        $cacheTimeout -= 300;
        if ($cacheTimeout < 0) { $cacheTimeout = 0; }
      }
      
      self::$caches[$cacheKey] = new DiskCache(CACHE_DIR . 'NextBusParser', $cacheTimeout, TRUE);
      self::$caches[$cacheKey]->preserveFormat();
      self::$caches[$cacheKey]->setSuffix(".$cacheKey.$suffix");
    }
    
    return self::$caches[$cacheKey];
  }
  
  private static function getCacheNameForCommand($params) {
    $name = $params['a'];
    
    if (isset($params['r'])) {
      $name .= '.'.$params['r'];
    }
    
    if (isset($params['stops'])) {
      $parts = explode('|', reset($params['stops']));
      $name .= '.'.reset($parts);
    }
    return $name;
  }
}
