<?
$docRoot = getenv("DOCUMENT_ROOT");

require_once('mobi_lib_config.php');

/*
 * use this file for storing constants that are used in multiple files
 * DO NOT STORE sensitive info like passwords
 * those go in config.php
 * which should not be committed
 */

define("USE_PRODUCTION", True);

define("DEVELOPER_EMAIL", "mobile-project-errors@mit.edu");

// file/directory locations
define("CACHE_DIR", $docRoot.'/mobi-lib/cache/');
define("LIBDIR", $docRoot.'/mobi-lib/');

/* misc */
define("TIMEZONE", "America/New_York");

/* SHUTTLESCHEDULE */
define("NEXTBUS_FEED_URL", 'http://www.nextbus.com/s/xmlFeed?');
define("NEXTBUS_AGENCY", 'mit');
define("NEXTBUS_ROUTE_CACHE_TIMEOUT", 86400); // max age, routeConfig data
define("NEXTBUS_PREDICTION_CACHE_TIMEOUT", 20); // max age, predictions
define("NEXTBUS_VEHICLE_CACHE_TIMEOUT", 10); // max age, vehicle locations
define("NEXTBUS_CACHE_MAX_TOLERANCE", 90); // when to revert to pub schedule
define("NEXTBUS_DAEMON_PID_FILE", CACHE_DIR . 'NEXTBUS_DAEMON_PID');
define("TRANSIT_VIEW_CACHE_TIMEOUT", 45); // view cache timeout, daemon should keep this updated

/* STELLAR */
define("STELLAR_COURSE_DIR", CACHE_DIR . 'STELLAR_COURSE/'); // dir for subject listing files
define("STELLAR_COURSE_CACHE_TIMEOUT", 86400); // how long to keep cached subject files
define("STELLAR_FEED_DIR", CACHE_DIR . 'STELLAR_FEEDS/'); // dir for cached rss data
define("STELLAR_FEED_CACHE_TIMEOUT", 900); // how long to keep cached rss files
define("STELLAR_SUBSCRIPTIONS_FILE", CACHE_DIR . 'STELLAR_SUBSCRIPTIONS');
define("STELLAR_USE_PRODUCTION", True);
if(USE_PRODUCTION) {
  define("STELLAR_BASE_URL", "http://stellar.mit.edu/courseguide/course/");
  define("STELLAR_RSS_URL", "http://stellar.mit.edu/SRSS/rss");
} else {
  define("STELLAR_BASE_URL", "http://stellar-dev.mit.edu/courseguide/course/");
  define("STELLAR_RSS_URL", "http://stellar-dev.mit.edu/SRSS/rss");
}

/* LIBRARIES */
define("ICS_CACHE_LIFESPAN", 900);
define('LIBRARY_OFFICE_RSS', 'http://localhost/drupal/library_office/rss.xml');

// EMERGENCY
if(USE_PRODUCTION) {
  define("EMERGENCY_RSS_URL", 'http://emergency.mit.net/emergency/mobirss');
} else {
  define("EMERGENCY_RSS_URL", 'http://emergency.mit.net/emtest/mobirss');
}

// 3DOWN
define("THREEDOWN_RSS_URL", 'http://3down.mit.edu/3down/index.php?rss=1');

/* 
// these aren't being used, just keeping a record of what may

// PEOPLE DIRECTORY
define("LDAP_SERVER", 'ldap.mit.edu');

*/

/* Events Calendar */
define("EVENTS_CALENDAR_UNIQUE_EVENT_URL", "http://events.mit.edu/event.html?id=");

/* news office */
define("NEWSOFFICE_FEED_URL", 'http://web.mit.edu/newsoffice/feeds/iphone.php');
define("NEWSOFFICE_STORY_URL", 'http://web.mit.edu/newsoffice/index.php?option=com_content&view=article&id=');
define("NEWSOFFICE_SEARCH_URL", 'http://web.mit.edu/newsoffice/index.php?option=com_search&view=isearch');

// MIT 150
define("MIT150_EVENTS_FEED", "http://mit150.mit.edu/native_mobile_events");
define("MIT150_CORRIDOR_FEED", "http://mit150.mit.edu/native_mobile_corridor/rss.xml");

  



?>
