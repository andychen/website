<?php

$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_lib_constants.php";

class FeaturesReader {
   
    static function getFeaturedItems() {
        $modified = filemtime(LIBDIR . "/static/FEATURES");

        $featuresJson = file_get_contents(LIBDIR . "/static/FEATURES/features.json");

        $features = json_decode($featuresJson, true);
        foreach($features['items'] as $index => $item) {
	    $features['items'][$index]['dimensions'] = self::getDimensions($item['id']);
        }

        return array('last-modified' => $modified, 'features' => $features["items"], 'more-features' => $features["more-items"]);
    }

    static function getFeaturedBanner() {
        $modified = filemtime(LIBDIR . "/static/FEATURES");

        $featuresJson = file_get_contents(LIBDIR . "/static/FEATURES/features.json");

        $features = json_decode($featuresJson, true);
        if(isset($features["banner"])) {
          $banner = $features["banner"];
          $banner['last-modified'] = $modified;
          $banner['showBanner'] = TRUE;
          $dimensions = self::getDimensions(self::photoBannerId($banner['id']));

          // rescale by scale factor
          $dimensions['width'] = $dimensions['width'] / $banner['scale'];
          $dimensions['height'] = $dimensions['height'] / $banner['scale'];
          unset($banner['scale']);
          $banner['dimensions'] = $dimensions;

        } else {
          $banner = array('showBanner' => FALSE);
        }

	return $banner;
    }

    static function outputImage($featureId) {
        $pathType = self::getPathType($featureId);

        $imageHandle = fopen($pathType['path'], "r");

        if(!$imageHandle) {
	    header("Status: 404 Not found");
            return;
        }

        header("Content-Type: image/${pathType['type']}");
        header("Cache-Control: max-age=86400");

        while(!feof($imageHandle)) {
	  echo fread($imageHandle, 1024);
        }
    }

    static function thumbnailId($id) {
        return "thumbnail152_" . $id;
    }

    static function photoBannerId($id) {
        return "banner_" . $id;
    }

    private static function getDimensions($id) {
      $pathType = self::getPathType($id);
      list($width, $height) = getimagesize($pathType['path']);
      return array('width' => $width, 'height' => $height);
    }

    private static function getPathType($id) {
        $imageFilename = LIBDIR . "/static/FEATURES/{$id}";

        if(file_exists($imageFilename . ".png")) {
	    $type = "png";
            $imagePath = "{$imageFilename}.png";
        } 
        if(file_exists($imageFilename . ".jpg")) {
	    $type = "jpeg";
            $imagePath = "{$imageFilename}.jpg";
        }
       
        return array("path" => $imagePath, "type" => $type);
    }
}  
