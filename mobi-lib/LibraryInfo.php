<?

/* this file uses the constants
 * CACHE_DIR
 * ICS_CACHE_LIFESPAN
 */
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_lib_constants.php";
require_once LIBDIR . 'mit_ical_lib.php';
require_once LIBDIR . "rss_services.php";
require_once LIBDIR . "DiskCache.php";

class LibraryRSS extends RSS {
  protected $rss_url = LIBRARY_OFFICE_RSS;
  protected $custom_tags = array('url', 'room', 'phone', 'calendar_url');
}

class LibraryInfo {

  public static $libraries = NULL;
  private static $cache = NULL;

  // returns google calendar url
  public static function ical_url($library) {
    $attribs = self::get_library_info($library);
    return $attribs['gcal'];
  }

  public static function get_calendar($library) {
    self::cache_ical($library);
    $cal = new ICalendar(self::$cache->getFullPath($library));
    return $cal;
  }

  public static function get_libraries() {
    if (self::$libraries === NULL) {
      $libraries = Array();
      $rss = new LibraryRSS();
      $feed = $rss->get_feed();
      foreach ($feed as $item) {
        $libraries[ $item['title'] ] = array(
          'url' => $item['url'],
          'tel' => $item['phone'],
          'location' => $item['room'],
          'gcal' => $item['calendar_url'],
          );
      }
      ksort($libraries);
      self::$libraries = $libraries;
    }
    return array_keys(self::$libraries);
  }

  public static function get_library_info($library) {
    if (self::$libraries === NULL) {
      self::get_libraries();
    }
    return self::$libraries[$library];
  }

  public static function cache_ical($library) {
    if (self::$cache === NULL) {
      self::$cache = new DiskCache(CACHE_DIR . "/LIBRARIES", ICS_CACHE_LIFESPAN, TRUE);
      self::$cache->setSuffix('.ics');
      self::$cache->preserveFormat();
    }

    if (!self::$cache->isFresh($library) || self::$cache->filesize($library) < 10) {
      $google_cal_url = self::ical_url($library);

      if ($contents = file_get_contents($google_cal_url)) {
        self::$cache->write($contents, $library);
      }
    }
  }

  public static function cache_icals() {
    foreach (self::get_libraries() as $library) {
      // TODO: remove these entries from drupal
      if ($library == 'Aeronautics and Astronautics Library'
	  || $library == 'Lindgren Library') continue;
      else self::cache_ical($library);
    }
  }
}

?>