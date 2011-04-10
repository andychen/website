<?php
$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_lib_constants.php";

require "DrupalRssCckReader.php";

class MIT150Calendar {

  public static function getEventsByDate($date) {
    $filteredEvents = array();
    $firstSecond = strtotime($date);
    $lastSecond = $firstSecond + 24 * 60 * 60;    

    foreach(self::loadEvents(NULL, $arrayOrObject) as $event) {
      if(isset($event->end)) {
        $rangeOfDay = array($firstSecond, $lastSecond);
        $rangeOfEvent = array($event->start, $event->end);
      
        if(self::rangeIntersect($rangeOfDay, $rangeOfEvent)) {
          $filteredEvents[] = $event;
        }
      } else {
        if($firstSecond <= $event->start && $event->start <= $lastSecond) {
          $filteredEvents[] = $event;
        }
      }
    }
    return $filteredEvents;
  }

  public static function getEventById($id) {
    foreach(self::loadEvents(NULL, $arrayOrObject) as $event) {
      if($event->id == $id) {
        return $event;
      }
    }
  }


  public static function getEventsByDateRange($start, $end) {
    $filteredEvents = array();
    foreach(self::loadEvents(NULL) as $event) {
      if($event->start >= $start && $event->start <= $end) {
        $filteredEvents[] = $event;
      }
    }

    return $filteredEvents;
  }

  // simplest search possible 
  public static function searchEvents($searchTerm, $start, $end) {
    $filteredEvents = array();
    foreach(self::getEventsByDateRange($start, $end) as $event) {
      if(stripos($event->title, $searchTerm) !== FALSE) {
        $filteredEvents[] = $event;
      } else if(stripos($event->description, $searchTerm) !== FALSE) {
        $filteredEvents[] = $event;
      }
    }

    return $filteredEvents;
  }

  private static function loadEvents($options) {
    
    // $options ignored for now

    // cache for 60 minutes
    $diskCache = new DiskCache(CACHE_DIR . "MIT150", 3600, TRUE);
    $feedText = $diskCache->read("events");
    if(!$feedText) {
      $feedText = @file_get_contents(MIT150_EVENTS_FEED);
      $diskCache->write($feedText, "events");
    }
    if(!$feedText) {
      // failed to load feed
      error_log(MIT150_EVENTS_FEED . " failed to give a response", 1, DEVELOPER_EMAIL);
      return array();
    }
  
    $feed = new DrupalRssCckReader($feedText);
    
    $events = array();

    foreach($feed->getItems() as $item) {
      $events[] = self::formatEvent($item);
    }

    return $events;
  }




  private static function formatEvent($feedItem) {
    $event['id'] = $feedItem['guid'];
    $event['title'] = $feedItem['title'];
    $normalizedContent = DrupalRssCckReader::normalize($feedItem['content']);
    $event['start'] = $normalizedContent['date-event']['start'];

    if(isset($normalizedContent['date-event']['end'])) {
      $event['end'] = $normalizedContent['date-event']['end'];
    }

    $event['description'] = $normalizedContent['body'];
    $event['location'] = $normalizedContent['location-name'];
    $event['infourl'] = $feedItem['link'];
    $event['type'] = 'mit150';
    
    return (object) $event;
  }

  private static function rangeIntersect($range1, $range2) {
    // easy test to see if ranges intersect, is to test if they dont intersect
    if($range1[1] < $range2[0]) {
      return FALSE;
    }

    if($range2[1] < $range1[0]) {
      return FALSE;
    }

    return TRUE;
  }
}

?>