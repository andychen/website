<?php

require_once('TransitDataParser.php');

define('GTFS_DIR', WEBROOT.'shuttleschedule/gtfs');

class GTFSTransitDataParser extends TransitDataParser {
  protected function isLive() {
    return false;
  }
  
  protected function loadData($agencyIDs, $routeIDs, $args) {
    $directory = $args['directory'];
    
    $gtfsPath = GTFS_DIR.'/'.$directory;
    
    if (!file_exists($gtfsPath)) {
      error_log("no GTFS directory '$directory'");
      return;
    }
    
    //error_log("   GTFS loading ".str_pad($directory, 20)." memory_get_usage(): ".memory_get_usage());
  
    //
    // routes
    //
    $csv = new CSVParser("$gtfsPath/routes.txt");
    while ($fields = $csv->next()) {
      if (in_array($fields['route_id'], $routeIDs)) {
        $shortName = $this->getField($fields, 'route_short_name', $fields['route_id']);
        $this->addRoute(new TransitRoute(
          $fields['route_id'], 
          $fields['agency_id'],
          $this->getField($fields, 'route_long_name', $shortName),
          $this->getField($fields, 'route_desc')
        ));
      }
    }    
    $csv->close();
    
    //
    // stops
    //
    $foundStopIDs = array();
    
    $csv = new CSVParser("$gtfsPath/stops.txt");
    while ($fields = $csv->next()) {
      $foundStopIDs[$fields['stop_id']] = true;
      
      $this->addStop(new TransitStop(
        $fields['stop_id'],
        $this->getField($fields, 'stop_name'),
        $this->getField($fields, 'stop_desc'),
        $fields['stop_lat'],
        $fields['stop_lon']
      ));
    }    
    $csv->close();

    //
    // services
    //
    $services = array();
    $serviceIsRunningCache = array();
    
    
    $csv = new CSVParser("$gtfsPath/calendar.txt");
    while ($fields = $csv->next()) {
      if (!isset($services[$fields['service_id']])) {
        $services[$fields['service_id']] = new TransitService($fields['service_id']);
      }
      $services[$fields['service_id']]->addDateRange(
        $fields['start_date'], $fields['end_date'],
        array(
          'monday'    => $this->getField($fields, 'monday', 0),
          'tuesday'   => $this->getField($fields, 'tuesday', 0),
          'wednesday' => $this->getField($fields, 'wednesday', 0),
          'thursday'  => $this->getField($fields, 'thursday', 0),
          'friday'    => $this->getField($fields, 'friday', 0),
          'saturday'  => $this->getField($fields, 'saturday', 0),
          'sunday'    => $this->getField($fields, 'sunday', 0),
        )
      );
    }
    $csv->close();

    $csv = new CSVParser("$gtfsPath/calendar_dates.txt");
    while ($fields = $csv->next()) {
      if (!isset($services[$fields['service_id']])) {
        // sometimes the MBTA doesn't bother specifying the service in calendar.txt
        $services[$fields['service_id']] = new TransitService($fields['service_id']);
      }
      
      if ($fields['exception_type'] == 1) {
        $services[$fields['service_id']]->addAdditionalDate($fields['date']);
      } else {
        $services[$fields['service_id']]->addExceptionDate($fields['date']);
      }
    }    
    $csv->close();

    //
    // segments
    //
    $segments = array();
    $now = TransitTime::getCurrentTime();

    $csv = new CSVParser("$gtfsPath/trips.txt");
    while ($fields = $csv->next()) {
      if (!isset($segments[$fields['trip_id']]) && in_array($fields['route_id'], $routeIDs)) {
        if (!isset($serviceIsRunningCache[$fields['service_id']])) {
          $serviceIsRunningCache[$fields['service_id']] = $services[$fields['service_id']]->isRunning($now);
        }
        
        // skip segments associated with services that aren't running
        if ($serviceIsRunningCache[$fields['service_id']]) {
          $direction = isset($fields['direction_id']) ? $fields['direction_id']: 'loop';
          
          $segments[$fields['trip_id']] = new TransitSegment(
            $fields['trip_id'],
            isset($fields['trip_headsign']) ? $fields['trip_headsign'] : null,
            $services[$fields['service_id']],
            $direction
          );
          $this->getRoute($fields['route_id'])->addSegment($segments[$fields['trip_id']]);
        }
      }
    }
    $csv->close();
    
    unset($services);
    
    $csv = new CSVParser("$gtfsPath/stop_times.txt");
    while ($fields = $csv->next()) {
      if (isset($segments[$fields['trip_id']]) && 
          $fields['pickup_type'] == '0' && $fields['drop_off_type'] == '0') {
        if (isset($foundStopIDs[$fields['stop_id']])) {
          $arrivesTT = TransitTime::createFromString($fields['arrival_time']);
          $departsTT = TransitTime::createFromString($fields['departure_time']);

          $segments[$fields['trip_id']]->addStop($fields['stop_id'], $fields['stop_sequence']);
          $segments[$fields['trip_id']]->setStopTimes($fields['stop_id'], $arrivesTT, $departsTT);
        }
      }
    }  
    $csv->close();

    $csv = new CSVParser("$gtfsPath/frequencies.txt");
    while ($fields = $csv->next()) {
      if (isset($segments[$fields['trip_id']])) {
        // segment uses frequencies, will ignore start and stop times
        $startTT = TransitTime::createFromString($fields['start_time']);
        $endTT = TransitTime::createFromString($fields['end_time']);
        
        TransitTime::addSeconds($endTT, intval($fields['headway_secs'])); // runs full loop after end_time

        $segments[$fields['trip_id']]->addFrequency($startTT, $endTT, $fields['headway_secs']);
      }
    }    
    $csv->close();

    unset($segments);
    //error_log("   GTFS loaded ".str_pad($directory, 20)."  memory_get_usage(): ".memory_get_usage());
  }

  protected function getField($fields, $key, $default=null) {
    if (isset($fields[$key]) && strlen($fields[$key])) { 
      return $fields[$key];
    }
    return $default;
  }
}


class CSVParser {

  private $fp;
  private $headers = array();

  public function __construct($filename, $headers=TRUE) {
    if (!file_exists($filename) || !is_readable($filename)) {
      error_log("could not open $filename");
      return;
    }
    $this->fp = fopen($filename, 'r');
    if ($this->fp) {
      $this->headers = fgetcsv($this->fp);
      //error_log(print_r($this->headers, true));
    }
  }
  
  function close() {
    if ($this->fp) {
      fclose($this->fp);
    }
  }

  public function next() {
    if (!$this->fp || feof($this->fp) || !($row = fgetcsv($this->fp))) {
      return NULL;
    } else {
      $result = array();
      foreach ($this->headers as $index => $header) {
        $result[$header] = isset($row[$index]) ? $row[$index] : null;
      }
    }
    return $result;
  }
}
