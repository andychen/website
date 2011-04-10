<?php

require_once('TransitDataParser.php');

define('GTFS_DIR', WEBROOT.'shuttleschedule/gtfs');

class GTFSTransitService extends TransitService {
  public static function isAddition($exceptionType) {
    return $exceptionType == 1;
  }

  // never construct classes for non-running services
  public function isRunning($time) {
    return true;
  }
}

class GTFSTransitSegment extends TransitSegment {

  private $route;
  
  // for frequency-based segments
  private $firstTripTime = NULL;
  private $firstTripFrequency = 0;
  
  // for stop-time based segments
  private $firstStopTime = NULL;
  private $secondStopTime = NULL;
  
  // maintain a reference to the route so we can make queries through it
  public function __construct($id, $name, $service, $direction, $route) {
    parent::__construct($id, $name, $service, $direction);
    $this->route = $route;
    $this->loadFrequencies();
  }
  
  public function getFirstStopTime() {
    return $this->firstStopTime;
  }
  
  public function getFirstTripFrequency() {
    return $this->firstTripFrequency;
  }
  
  public function getFirstTripTime() {
    return $this->firstTripTime;
  }
  
  private function loadFrequencies() {
    $sql = 'SELECT *'
          .'  FROM frequencies'
          ." WHERE trip_id = '".$this->getID()."'";
    //error_log($sql);
    $result = $this->route->getDB()->query($sql);

    $firstTrip = 999999;
    $firstFrequency = 0;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
      $startTT = TransitTime::createFromString($row['start_time']);
      $endTT = TransitTime::createFromString($row['end_time']);
      $frequency = $row['headway_secs'];
      
      if ($startTT < $firstTrip) {
        $firstTrip = $startTT;
        $firstFrequency = intval($frequency);
      }

      // MIT shuttles don't run a full loop after end time
      // if other agencies do this we need to change the MIT data or add logic for this
      //TransitTime::addSeconds($endTT, intval($frequency)); // runs full loop after end_time
      $this->addFrequency($startTT, $endTT, $frequency);
    }

    if ($firstTrip != 999999) {
      $this->firstTripTime = $firstTrip;
    }

    if ($firstFrequency != 0) {
      $this->firstTripFrequency = $firstFrequency;
    }
    
    if (!$this->hasFrequencies()) { // this function works after the above sql query
      $sql = 'SELECT departure_time'
            .'  FROM stop_times'
            .' WHERE stop_sequence = 1'
            ."   AND trip_id = '".$this->getID()."'";
      $result = $this->route->getDB()->query($sql);
      if (!$row = $result->fetch(PDO::FETCH_ASSOC)) {
        return 0;
      }
      $this->firstStopTime = $row['departure_time'];
    }
  }
  
  public function getFrequency($time) {
    // we can call hasFrequencies as soon as the above is finished
    if (!$this->hasFrequencies()) {
      if ($this->secondStopTime === NULL) {

        $sql = 'SELECT s.departure_time AS departure_time'
              .'  FROM stop_times s, trips t'
              .' WHERE s.stop_sequence = 1'
              ."   AND t.route_id = '".$this->route->getID()."'"
              .'   AND s.trip_id = t.trip_id'
              ."   AND s.departure_time > '{$this->firstStopTime}'"
              .' ORDER BY s.departure_time';
        $result = $this->route->getDB()->query($sql);
        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
          $this->secondStopTime = $row['departure_time'];
        } else {
          $sql = str_replace('>', '<', $sql) . ' DESC';
          $result = $this->route->getDB()->query($sql);
          if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $this->secondStopTime = $this->firstStopTime;
            $this->firstStopTime = $row['departure_time'];
          }
        }
      }
      
      if (isset($this->firstStopTime) && isset($this->secondStopTime)) {
        $startTT = TransitTime::createFromString($this->firstStopTime);
        $endTT = TransitTime::createFromString($this->secondStopTime);
        return $endTT - $startTT;
      }

      return 0;

    } else {
      return parent::getFrequency($time);
    }
  }
  
  public function isRunning($time) {
    if ($this->hasPredictions())
      return true;
  
    if ($this->hasFrequencies()) {
      // parent's loop works since we always populate frequencies
      foreach ($this->frequencies as $index => $frequencyInfo) {
        if (TransitTime::isTimeInRange($time, $frequencyInfo['start'], $frequencyInfo['end'])) {
          return true;
        }
      }
      
    } else {
      // for now just use departure time (as opposed to arrival time)
      $sql = 'SELECT departure_time'
            .'  FROM stop_times'
            ." WHERE trip_id = '".$this->getID()."'"
            .' ORDER BY stop_sequence DESC'; // not sure if it's better to sort on departure_time
      $result = $this->route->getDB()->query($sql);
      $firstTT = TransitTime::createFromString($this->firstStopTime);
      $lastRow = $result->fetch(PDO::FETCH_ASSOC); // discard rest of results
      $lastTT = TransitTime::createFromString($lastRow['departure_time']);
      return TransitTime::isTimeInRange($time, $firstTT, $lastTT);
    }
    return false;
  }
  
  public function getStops() {
    if (!count($this->stops)) {
      $now = TransitTime::getCurrentTime();
/*
      if ($this->hasFrequencies()) {
        $frequency = $this->getFrequency($now);
        $timeClause = '';

      } else {
        // fourth place with 5am check
        $hours = intval(date('G', $now));
        if ($hours < 5) {
          $hours = strval(intval($hours) + 24);
        } else {
          $hours = date('H', $now);
        }
        $timeString = $hours.date(':i:s', $now);
        $timeClause = " AND departure_time > '$timeString'";
      }
*/
      $sql = 'SELECT arrival_time, departure_time, stop_id, stop_sequence'
            .'  FROM stop_times'
            ." WHERE trip_id = '".$this->getID()."'"
            //.$timeClause
            .' ORDER BY stop_sequence';
      //error_log($sql);
      $result = $this->route->getDB()->query($sql);
      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $stopIndex = intval($row['stop_sequence']);
        $arrivesTT = TransitTime::createFromString($row['arrival_time']);
        $departsTT = TransitTime::createFromString($row['departure_time']);
        $stopInfo = array(
          'stopID' => $row['stop_id'],
          'i' => $stopIndex,
          'arrives' => $arrivesTT,
          'departs' => $departsTT,
          'hasTiming' => true,
          );
        $this->stops[] = $stopInfo;
      }
    }
    
    return $this->stops;
  }

}

class GTFSTransitRoute extends TransitRoute {

  public function getDB() {
    return GTFSTransitDataParser::getDB($this->getAgencyID());
  }
  
  public function isRunning($time, &$inService=null, &$runningSegmentNames=null) {
    $isRunning = false;
    $inService = false;
    $runningSegmentNames = array();

    $this->getDirections();
    foreach ($this->directions as $direction) {
      foreach ($direction['segments'] as $segment) {
        $inService = true; // GTFSTransitService objects are only created if they are in service
        if ($segment->isRunning($time)) {
          $name = $segment->getName();
          if (isset($name) && !isset($runningSegmentNames[$name])) {
            //error_log("   Route {$this->name} has named running segment '$name' (direction '$direction')");
            $runningSegmentNames[$name] = $name;
          }
          $isRunning = true;
        }
      }
    }
    $runningSegmentNames = array_values($runningSegmentNames);
    return $isRunning;
  }
  
  public function getServiceFrequency($time) {
    // Time between shuttles at the same stop
    $frequency = 0;
    $firstTripTime = 999999;
    $firstSegment = NULL;
    
    if ($this->segmentsUseFrequencies()) {
      foreach ($this->directions as $direction) {
        foreach ($direction['segments'] as $segment) {
          if ($segment->isRunning($time)) {
            $frequency = $segment->getFrequency($time);
            if ($frequency > 0) { break; }
          }
          if ($frequency > 0) { break; }

          if (($aTripTime = $segment->getFirstTripTime()) < $firstTripTime) {
            $firstTripTime = $aTripTime;
            $firstSegment = $segment;
          }
          
        }
        if ($frequency > 0) { break; }
      }
      
      if ($frequency == 0) {
        $frequency = $segment->getFirstTripFrequency();
      }

    } else {
      // if nothing is running, these will be populated.
      // relying on the fact that only in-service segments are ever created
      $firstStopTime = '99:99:99';
      $secondStopTime = '99:99:99';
    
      foreach ($this->directions as $direction) {
        foreach ($direction['segments'] as $segment) {
          if ($segment->isRunning($time)) {
            $frequency = $segment->getFrequency($time);
            if ($frequency > 0) { break; }
          }
          if ($frequency > 0) { break; }
          if (($aStopTime = $segment->getFirstStopTime()) < $firstStopTime) {
            $firstStopTime = $aStopTime;
          }
          else if ($aStopTime < $secondStopTime) {
            $secondStopTime = $aStopTime;
          }
        }
        if ($frequency > 0) { break; }
      }

      if ($frequency == 0 && $firstStopTime != '99:99:99' && $secondStopTime != '99:99:99') {
        $startTT = TransitTime::createFromString($firstStopTime);
        $endTT = TransitTime::createFromString($secondStopTime);
        $frequency = $endTT - $startTT;
      }
    }
    
    return $frequency;
  }
  
  public function getDirections() {
    if (!count($this->directions)) {
      $now = TransitTime::getCurrentTime();
      $datetime = TransitTime::getLocalDatetimeFromTimestamp($now);
      
      $date = $datetime->format('Ymd');
      
      $segments = array();
      
      // exceptions in calendar_dates take precedence, so query this first
      $additions = array();
      $exceptions = array();
      $sql = 'SELECT t.service_id AS service_id, c.exception_type AS exception_type'
            .'  FROM trips t, calendar_dates c'
            ." WHERE route_id = '".$this->getID()."'"
            .'   AND t.service_id = c.service_id'
            ."   AND c.date = '$date'";
      //error_log($sql);
      $result = $this->getDB()->query($sql);
      $additionClause = '';
      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if (GTFSTransitService::isAddition($row['exception_type'])) {
          $additionClause .= 't.service_id = '.$row['service_id'].' OR ';
        } else {
          $exceptions[] = 't.service_id != '.$row['service_id'];
        }
      }
      $exceptionClause = count($exceptions) ? ' AND ('.implode(' OR ', $exceptions).')' : '';

      // get all segments that run today regardless of what time it is
      // presence of a segment indicates the route is in service
      $services = array();
      $dayOfWeek = strtolower(date('l', $date));
      $sql = 'SELECT t.trip_id AS trip_id, t.service_id AS service_id, t.trip_headsign AS trip_headsign, t.direction_id AS direction_id'
            .'  FROM trips t, calendar c'
            ." WHERE route_id = '".$this->getID()."'"
            .'   AND t.service_id = c.service_id'
            .$exceptionClause
            ."   AND ("
            .$additionClause
            ."(c.$dayOfWeek = 1 AND c.start_date <= $date AND c.end_date >= $date))";
      //error_log($sql);
      $result = $this->getDB()->query($sql);

      // prep variables in case nothing is running now
      $maxDefaultSegments = 4;
      $timesWithFreqs = array();
      $timesWithoutFreqs = array();
      $selectedSegments = array();
      for ($i = 0; $i < $maxDefaultSegments; $i++) {
        $timesWithFreqs[] = 999999;
        $timesWithoutFreqs[] = '99:99:99';
        $selectedSegments[] = null;
      }

      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $serviceID = $row['service_id'];
        $direction = ($row['direction_id'] === NULL) ? 'loop' : $row['direction_id'];
        if (!isset($services[$serviceID])) {
          $services[$serviceID] = new GTFSTransitService($serviceID);
        }
        $segment = new GTFSTransitSegment(
          $row['trip_id'],
          $row['trip_headsign'],
          $services[$serviceID],
          $direction,
          $this
          );

        if ($segment->isRunning($now)) {
          for ($i = 0; $i < $maxDefaultSegments; $i++) {
            if (!$selectedSegments[$i]) {
              $selectedSegments[$i] = $segment;
              break;
            }
          }
        } else if ($segment->hasFrequencies()) {
          $segmentTime = $segment->getFirstTripTime();
          for ($i = 0; $i < $maxDefaultSegments; $i++) {
            if (!$selectedSegments[$i] || $segmentTime < $timesWithFreqs[$i]) {
              $selectedSegments[$i] = $segment;
              $timesWithFreqs[$i] = $segmentTime;
              break;
            }
          }
        } else {
          $segmentTime = $segment->getFirstStopTime();
          for ($i = 0; $i < $maxDefaultSegments; $i++) {
            if (!$selectedSegments[$i] || $segmentTime < $timesWithoutFreqs[$i]) {
              $selectedSegments[$i] = $segment;
              $timesWithoutFreqs[$i] = $segmentTime;
              break;
            }
          }
        }
      }

      for ($i = 0; $i < $maxDefaultSegments; $i++) {
        if ($selectedSegments[$i]) {
          $this->addSegment($selectedSegments[$i]);
        }
      }
    }
    
    return parent::getDirections();
  }
  
  public function getDirection($id) {
    $this->getDirections();
    return parent::getDirection($id);
  }
  
  public function getSegmentsForDirection($direction) {
    $this->getDirections(); // make sure directions are populated
    return parent::getSegmentsForDirection($direction);
  }

}

// TODO for this class
// - abstract away db engine
// - determine value of keeping fetched routes and stops in memory
class GTFSTransitDataParser extends TransitDataParser {

  // if we use multiple gtfs files and one databases per file
  // maintain a central reference
  private static $gtfsPaths = array();
  private static $dbRefs = array();
  
  // for gtfs files that have multiple agencies, map all
  // agencies to a single canonical agency to simplify db referencing
  private static $agencyMap = array();
  private $agency;
  
  public static function getDB($agencyID) {
    $agency = self::$agencyMap[$agencyID];
  
    if (!isset(self::$dbRefs[$agency])) {
      $file = self::$gtfsPaths[$agency];
      //error_log($file);
      if (!file_exists($file)) {
        error_log("no GTFS db at '$file'");
        return;
      }

      $db = new PDO('sqlite:'.$file);
      if (!$db) {
        error_log("could not open db at '$file'");
        return;
      }
      self::$dbRefs[$agency] = $db;
    }
    return self::$dbRefs[$agency];
  }

  // superclass overrides

  protected function isLive() {
    return false;
  }

  protected function getStop($id) {
    if (!isset($this->stops[$id])) {
      $sql = "SELECT * FROM stops where stop_id = '$id'";
      //error_log($sql);

      $db = self::getDB($this->agency);
      $result = $db->query($sql);
      if (!$result) {
        error_log("error fetching stop: ".print_r($db->errorInfo(),true));
      }
      $row = $result->fetch(PDO::FETCH_ASSOC);
      $this->addStop(new TransitStop(
        $row['stop_id'],
        $row['stop_name'], // may be null
        $row['stop_desc'], // may be null
        $row['stop_lat'],
        $row['stop_lon']
        ));
    }
    
    return parent::getStop($id);
  }
  
  public function getStopInfoForRoute($routeID, $stopID) {
    // ensure the data required by TransitDataParser is loaded
    $this->getStop($stopID);
    
    // route->getPredictionsForStop($stopID, TransitTime::getCurrentTime())
    
    return parent::getStopInfoForRoute($routeID, $stopID);
  }
  
  public function getStopInfo($stopID) {
    // get all route IDs associated with this stop.
    $now = TransitTime::getCurrentTime();
    $sql = "SELECT DISTINCT t.route_id AS route_id"
          ."  FROM stop_times s, trips t"
          ." WHERE s.stop_id = '$stopID'"
          ."   AND s.trip_id = t.trip_id";
    $db = self::getDB($this->agency);
    $result = $db->query($sql);
    if (!$result) {
      error_log("error fetching stop info: ".print_r($db->errorInfo(),true));
    }

    // rest of this function is mostly like the parent
    // but we call this->getRoute and this->getStop
    $routePredictions = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
      $routeID = $row['route_id'];
      $route = $this->getRoute($routeID);
      if (!$route->isRunning($now, $inService) && !$inService)
        continue;
      $this->updatePredictionData($routeID);
      
      $routePredictions[$routeID] = $route->getPredictionsForStop($stopID, $now);
      $routePredictions[$routeID]['name'] = $route->getName();
      $routePredictions[$routeID]['live'] = $this->isLive();
    }

    $stop = $this->getStop($stopID);    
    $stopInfo = array(
      'name'        => $stop->getName(),
      'description' => $stop->getDescription(),
      'coordinates' => $stop->getCoordinates(),
      'routes'      => $routePredictions,
    );
    
    $this->applyStopInfoOverrides($stopID, $stopInfo);

    return $stopInfo;
  }
  
  protected function loadData($agencyIDs, $routeIDs, $args) {
    if (!count($agencyIDs)) {
      error_log("no agency IDs found");
      return;
    }
  
    $this->agency = $agencyIDs[0];
    foreach ($agencyIDs as $agencyID) {
      self::$agencyMap[$agencyID] = $this->agency;
    }
    $dbfile = $args['db'];
    self::$gtfsPaths[$this->agency] = GTFS_DIR.'/'.$dbfile;
    
    $this->loadRoutes();
  }
  
  private function loadRoutes() {
    if (!count($this->routes)) {
      $sql = "SELECT * from routes";
      $result = self::getDB($this->agency)->query($sql);
      if (!$result) {
        error_log('could not load routes: '.print_r(self::getDB($this->agency)->errorInfo(), true));
      }
      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $routeID = $row['route_id'];
        if (isset($row['route_long_name'])) {
          $routeName = $row['route_long_name'];
        } else if (isset($row['route_short_name'])) {
          $routeName = $row['route_short_name'];
        } else {
          $routeName = null;
        }
        
        $route = new GTFSTransitRoute(
          $routeID,
          $row['agency_id'],
          $routeName, // may be null
          $row['route_desc'] // may be null
          );

        $this->addRoute($route);
      }
    }
  }
  
}

