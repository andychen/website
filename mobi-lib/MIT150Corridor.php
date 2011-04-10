<?php

$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_lib_constants.php";

require_once "DiskCache.php";
require_once "DrupalRssCckReader.php";

class MIT150Corridor {

  public static function getItems($offset, $limit) {

    // cache for 30 minutes
    $diskCache = new DiskCache(CACHE_DIR . "MIT150", 1800, TRUE);
    $feedText = $diskCache->read("corridor");
    if(!$feedText) {
      $feedText = @file_get_contents(MIT150_CORRIDOR_FEED);
      $diskCache->write($feedText, "corridor");
    }
    if(!$feedText) {
      // failed to load feed
                     
      error_log(MIT150_CORRIDOR_FEED . " failed to give a response", 1, DEVELOPER_EMAIL);
      return array();
    }


    $feed = new DrupalRssCckReader($feedText);
    $feedItems = $feed->getItems();
    
    $positionLimit = min($offset+$limit, sizeof($feedItems));
    $items = array();
  
    for($i = $offset; $i < $positionLimit; $i++) {
      $feedItem = $feedItems[$i];
      $item = DrupalRssCckReader::normalize($feedItem['content']);
      $item['unique-id'] = $feedItem['link'];
      $item['date-posted-unix'] = strtotime($item['date-posted']);
      $item['plain-text'] = trim(self::utf8_entity_decode(strip_tags($item['body'])));
      $item['title'] = $feedItem['title'];

      $items[] = $item;
    }

    return $items;
  }

  function decimal_entities($text) {
    return preg_replace('/&#x([a-fA-F0-9]{2,8});/ue', "'&#' . hexdec('$1') . ';'", $text);
  }

  function utf8_entity_decode($entity){
    $entity = self::decimal_entities($entity);
    $convmap = array(0x0, 0x10000, 0, 0xfffff);
    return mb_decode_numericentity($entity, $convmap, 'UTF-8');
  }
}


?>