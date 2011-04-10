<?php
$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_lib_constants.php";

define('TOUR_DATA_DIR', LIBDIR.'/static/TOURS');

class TourReader {

    static function getTours() {
        $files = scandir(TOUR_DATA_DIR);
        $tourIds = array();
        foreach ($files as $file) {
            if (strpos($file, '.') === false) {
                $tourIds[] = $file;
            }
        }

        $tours = array();
        foreach($tourIds as $id) {
  	    $tour = self::getTourDetails($id);
            $tours[] = array(
               'id' => $id, 
               'title' => $tour['title'], 
               'last-modified' => self::getModificationDate($id),
            );
        }
        return $tours;
    }
    
    static function getTourDetails($tourId) {
        $tourXML = new DOMDocument();

        libxml_use_internal_errors(true);
        $xmlText = file_get_contents(TOUR_DATA_DIR . "/$tourId/tour.xml");
        
        // pray "__SERVER_DOMAIN__" is not used in any of the tour content
        $xmlText = str_replace("__SERVER_DOMAIN__", $_SERVER['SERVER_NAME'], $xmlText);
        $tourXML->loadXML($xmlText);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        if(sizeof($errors)) {
	    throw new TourReaderXmlException($errors);
        }  

        $rootNode = self::getChildNode($tourXML, "tour");
        $introductionNode = self::getChildNode($rootNode, 'introduction');
        $tourInfo = array(
	    "title" => self::getValue($introductionNode, 'title'),
	    "description-top" => self::getHtml(self::getChildNode($introductionNode, 'description-top')),
	    "description-bottom" => self::getHtml(self::getChildNode($introductionNode, 'description-bottom')),
            "feedback" => self::getFeedback($rootNode),
            "links" => self::getLinks($rootNode),
        );
        self::addPhotoAudio($tourInfo, $tourId, 'introduction');
      

        $sitesXML = self::getChildNode($rootNode, 'sites');
        $sites = array();
        foreach($sitesXML->getElementsByTagName('site') as $siteNode) {

          $siteId = $siteNode->getAttribute('id');
	  $site = array(
	      'latlon' => self::getLatLon($siteNode),
              'title' => self::getValue($siteNode, 'title'),
              'content' => self::getContent($siteNode, $tourId, $siteId),
              'id' => $siteId,
	  );
	  self::addPhotoAudio($site, $tourId, $siteId);

	  if(self::hasValue($siteNode, 'exit-directions')) {
	    $site['exit-directions'] = self::getDirections($siteNode, 'exit-directions', $tourId, $siteId);
          }

          $sites[] = $site;
        }
        $tourInfo['sites'] = $sites;

        // parse the start locations data
        $startLocationsXML = $tourXML->getElementsByTagName('start-locations')->item(0);
       
        $startLocationItemsXML = $startLocationsXML->getElementsByTagName('locations')->item(0);        
        $startLocationItems = array();
        foreach($startLocationItemsXML->getElementsByTagName('location') as $locationXML) {

	    $startLocationId = $locationXML->getAttribute('id');
	    $location = array(
		 'id' => $startLocationId,
		 'title' => self::getValue($locationXML, 'title'),
                 'content' => self::getValue($locationXML, 'content'),
                 'latlon' => self::getLatLon($locationXML),
                 'start-site' => self::getValue($locationXML, 'start-site'),
	    );
            self::addFilename($location, "photo-id", $tourId, 'startLocation_' . $startLocationId, "images", "jpg");
            $startLocationItems[] = $location;
        }
        $startLocations = array(
	    'header' => self::getValue($startLocationsXML, 'header'),
	    'items' => $startLocationItems,
        );
        
	$tourInfo['start-locations'] = $startLocations;                                 

        return $tourInfo;
    }


    private static function getModificationDate($tourId) {
      $modDate1 = filemtime(TOUR_DATA_DIR . "/$tourId/tour.xml");
      $modDate2 = filemtime(TOUR_DATA_DIR . "/$tourId/audio");
      $modDate3 = filemtime(TOUR_DATA_DIR . "/$tourId/images");

      return max($modDate1, $modDate2, $modDate3);
    }
      
    /*
     * trim extra white spaces that come from the XML file,
     * but still want there to be some way to indicate newlines
     * this function makes it easier for the user to enter in large
     * blocks of text into the XML
     */
    static function trimWhiteSpaces($text) {

      // there is likely a simpler/better way to do this
      
      $text = preg_replace('/(\ )+/', ' ', $text);
      $text = preg_replace('/\n(\n)+/',"\n\n", $text);
      $text = str_replace("\n ", "\n", $text);
      $text = str_replace(" \n", "\n", $text);
      $paragraphs = explode("\n\n", $text);

      foreach($paragraphs as $index => $line) {
	$paragraphs[$index] = str_replace("\n", " ", $line);
      }
       
      $text = implode("\n", $paragraphs);
      return trim($text);
    }    

    static function getChildNode($xml, $tag) {
      foreach($xml->childNodes as $childNode) {
        if($childNode->nodeName == $tag) {
          return $childNode;
        }
      }
      return NULL;
    }

    static function getValue($xml, $tag) {
      if(!self::hasValue($xml, $tag)) {
	throw new TourReaderMissingTagException($xml, $tag);
      }
      return self::trimWhiteSpaces(self::getChildNode($xml, $tag)->nodeValue);
    }

    static function hasValue($xml, $tag) {
      return (self::getChildNode($xml, $tag) !== NULL);
    }

    static function getHtml($node) {
          // add paragraph tags unless auto-paragraph is disabled
          if($node->hasAttribute('auto-paragraph') && $node->getAttribute('auto-paragraph') == 'false') {
            return $node->nodeValue;
          } else {
            return self::addParagraphTags($node->nodeValue);
          }
    }

    static function getContent($xml, $tourId, $contentId) {
      $content = self::getChildNode($xml, 'content');

      $contentItems = array();
      foreach($content->childNodes as $node) {
	if($node->nodeName == 'content-text') {
          $contentItems[] = array("type" => "inline", "html" => self::getHtml($node));
        } else if($node->nodeName == 'side-trip') {
          $contentItems[] = self::getSideTrip($node, $tourId, $contentId);
        }
      }
      return $contentItems;
    }

    static function addParagraphTags($text) {
      $paragraphs = explode("\n", self::trimWhiteSpaces($text));
       
      // we are assuming that <side-trip> tags exist on there own line
      // and should not be surrounded by p tags
      $markup = "";
      foreach($paragraphs as $paragraph) {
	$markup .= '<p>' . $paragraph . '</p>';
      }
      return $markup;
    }

    static function getDirections($xml, $tag, $tourId, $siteId) {
      $directionsNode = $xml->getElementsByTagName($tag)->item(0);
      $directions = array(
         'title' => self::getValue($directionsNode, 'title'), 
         'content' => self::getContent($directionsNode, $tourId, "{$siteId}_directions"),
         'path' => self::getPath($directionsNode),
         'zoom' => intval(self::getValue($directionsNode, 'zoom')),
      );
      self::addPhotoAudio($directions, $tourId, "{$siteId}_directions");
      return $directions;
    }

    static function getSideTrip($xml, $tourId, $contentId) {
      $sideTripId = $xml->getAttribute("id");
      $contentNode = self::getChildNode($xml, 'content-text');

      $sideTrip = array(
	    "type" => "sidetrip",
	    "title" => $xml->getAttribute("title"),
            "id" => $sideTripId,
            "latlon" => self::getLatLon($xml),
            "html" => self::getHtml($contentNode),
      );
      self::addPhotoAudio($sideTrip, $tourId, "{$contentId}_{$sideTripId}");

      return $sideTrip;
    }   

    static function getLatLon($xml) {
        return array(
	    'latitude' => floatval(self::getValue($xml, 'latitude')),
	    'longitude' => floatval(self::getValue($xml, 'longitude')),
        );
    }

    static function getPath($xml) {
      $pathText = self::getValue($xml, 'path');
      $pointStrings = preg_split('/\s/', $pathText);
      $points = array();

      foreach($pointStrings as $pointString) {
        $parts = explode(",", $pointString);
        if(count($parts) > 1) {
          $points[] = array(
	    "latitude" => (float)$parts[0],
	    "longitude" => (float)$parts[1]
	  );
        }
      }

      return $points;
    }

    static function addPhotoAudio(&$data, $tourId, $id) {
      // add image
      self::addFilename($data, "photo-id", $tourId, $id, "images", "jpg");

      // add thumbnail image
      self::addFilename($data, "thumbnail156-id", $tourId, "thumbnail156_$id", "images", "jpg");

      // add audio
      self::addFilename($data, "audio-id", $tourId, $id, "audio", "mp3");
    }

    /*
     * this checks if a file of a certain type exists (say image or audio)
     * add if it does adds tag to indicate it's filename
     */
    static function addFilename(&$data, $filenameKey, $tourId, $filename, $path, $prefix) {
      $fullpath = TOUR_DATA_DIR . "/$tourId/$path/{$filename}.$prefix";

      if(file_exists($fullpath)) {
        $data[$filenameKey] = $filename;
      }
    }

    static function getFeedback($xml) {
      $feedbackXML = self::getChildNode($xml, "feedback");
      return array(
	"subject" => self::getValue($feedbackXML, "subject")
      );
    }

    static function getLinks($xml) {
      $linksXML = self::getChildNode($xml, "links");
      $links = array();
      foreach($linksXML->getElementsByTagName('link') as $linkNode) {
        $links[] = array(
	  "title" => self::getValue($linkNode, "title"),
	  "url" => self::getValue($linkNode, "url"),
	);
      }
      return $links;
    }
}

class TourReaderException extends Exception {}

class TourReaderXmlException extends TourReaderException {

  private $xmlErrors;

  public function __construct($xmlErrors) {
    $this->xmlErrors = $xmlErrors;
    foreach($xmlErrors as $error) {
      $this->message .= "file: {$error->file} line: {$error->line} message: {$error->message}\n";
    }
  }

  public function getErrors() {
    return $this->xmlErrros;
  }
}

class TourReaderMissingTagException extends TourReaderException {

    private $xml;
    private $tag;

    public function __construct($xml, $tag) {
        $this->xml = $xml;
        $this->tag = $tag;
        $valueSummary = trim($xml->nodeValue);
        if(strlen($valueSummary) > 30) {
	  $valueSummary = substr($valueSummary, 0, 30) . "...";
        }
        $this->message = "a node of type {$xml->nodeName} with value: \"$valueSummary\", is missing an \"{$tag}\" element";
    }
}
?>
