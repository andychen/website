<?php
$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_lib_constants.php";

define("OPENHOUSE_BASE_URL", "http://mit150.mit.edu/open-house");

require_once "DrupalRssCckReader.php";
require_once "DiskCache.php";

class OpenHouseCalendar {

  private static $categories = array(
     array('catid' => '39', 
           'identifier' => 'engineering-technology-and-invention', 
           'name' => 'Engineering, Technology, and Invention'),

     array('catid' => '40', 
           'identifier' => 'energy-and-sustainability', 
           'name' => 'Energy and Sustainability'),

     array('catid' => '41', 
           'identifier' => 'entrepreneurship-and-management', 
           'name' => 'Entrepreneurship and Management'),

     array('catid' => '42', 
           'identifier' => 'life-sciences-and-biotechnology', 
           'name' => 'Life Sciences and Biotechnology'),

     array('catid' => '43', 
           'identifier' => 'sciences', 
           'name'  => 'The Sciences'),

     array('catid' => '44', 
           'identifier' => 'air-and-space-flight', 
           'name' => 'Air and Space Flight'),

     array('catid' => '45', 
           'identifier' => 'architecture-planning-and-design', 
           'name' => 'Architecture, Planning and Design'),

     array('catid' => '46', 
           'identifier' => 'air-and-space-flight', 
           'name' => 'Arts, Humanities, and Social Sciences'),

     array('catid' => '47', 
           'identifier' => 'mit-learning-life-and-culture', 
           'name' => 'MIT Learning, Life, and Culture'),
  );

  public static function getCategories() {
    $categories = array();
    foreach(self::$categories as $category) {
      $category['type'] = 'openhouse';
      $categories[] = (object) $category;
    }
    return $categories;
  }

  public static function getEventsByDate($category, $start, $end) {
    $filteredEvents = array();
    $firstSecond = strtotime($start);
    $lastSecond = strtotime($end) + 24 * 60 * 60;    

    foreach(self::loadEvents($category) as $event) {
      if($firstSecond <= $event->start && $event->start <= $lastSecond) {
          $filteredEvents[] = $event;
      }
    }
    return $filteredEvents;
  }


  public static function searchCategoryByDate($searchTerm, $category, $start, $end) {
    $filteredEvents = array();
    $firstSecond = strtotime($start);
    $lastSecond = strtotime($end) + 24 * 60 * 60;    

    return self::searchCategory($searchTerm, $firstSecond, $lastSecond, $category);
  }

  
  public static function getEventById($id) {
    foreach(self::loadEvents('all') as $event) {
      if($event->id == $id) {
        return $event;
      }
    }
  }

  public static function searchEvents($searchTerm, $start, $end) {
    return self::searchCategory($searchTerm, $start, $end, 'all');
  }

  // simplest search possible 
  private static function searchCategory($searchTerm, $start, $end, $categoryID) {
    $filteredEvents = array();
    foreach(self::loadEvents($categoryID) as $event) {
      if($event->start >= $start && $event->start <= $end) {
        if($searchTerm) {
          if(stripos($event->title, $searchTerm) !== FALSE) {
            $filteredEvents[] = $event;
          } else if(stripos($event->description, $searchTerm) !== FALSE) {
            $filteredEvents[] = $event;
          }
        } else {
          $filteredEvents[] = $event;
        }
      }
    }

    return $filteredEvents;
  }

  private static function loadEvents($catID) {
    
    // $options ignored for now

    // cache for 60 minutes
    $diskCache = new DiskCache(CACHE_DIR . "OPENHOUSE", 3600, TRUE);
    $feedText = $diskCache->read("$catID.xml");
    if(!$feedText) {
      $feedText = file_get_contents(OPENHOUSE_BASE_URL . "/$catID/rss.xml");
      $diskCache->write($feedText, "$catID.xml");
    }
    if(!$feedText) {
      // failed to load feed
      error_log(OPENHOUSE_BASE_URL . "/$catID.xml" . " failed to give a response", 1, DEVELOPER_EMAIL);
      return array();
    }

    $feed = new DrupalRssCckReader($feedText);
    
    foreach($feed->getItems() as $item) {
      $events[] = self::formatEvent($item);
    }

    return $events;
  }

  private static function formatEvent($feedItem) {
    $event['id'] = $feedItem['guid'];
    $event['title'] = $feedItem['title'];
    $normalizedContent = DrupalRssCckReader::normalize($feedItem['content']);
    $event['start'] = $normalizedContent['activity-time']['start'];

    if(isset($normalizedContent['activity-time']['end'])) {
      $event['end'] = $normalizedContent['activity-time']['end'];
    }

    $description = $normalizedContent['body'];
    $description .= "<p>Sponsor: " . htmlentities($normalizedContent['activity-sponsor'], ENT_COMPAT, 'UTF-8') . "</p>";
    $description .= "<p>Format: " . htmlentities($normalizedContent['activity-format'], ENT_COMPAT, 'UTF-8') . "</p>";
    if($normalizedContent['activity-family-friendly']) {
      $description .= "<p>Family Friendly</p>";
    } 
    $event['description'] = $description;

    if($normalizedContent['activity-location']) {      
      $event['shortloc'] = $normalizedContent['activity-location'];
    }
    $event['location'] = self::getOptString($normalizedContent, 'activity-location-desc');
    $event['infourl'] = $feedItem['link'];
    $event['type'] = 'openhouse';

    return (object) $event;
  }

  private static function getOptString($array, $key) {
    if(isset($array[$key])) {
      return $array[$key];
    } else {
      return "";
    }
  }
}

?>