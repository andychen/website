<?
require LIBDIR . 'FeaturesReader.php';

$command = $_REQUEST['command'];

if($command == "list") {
  $features = FeaturesReader::getFeaturedItems();
  //$features = $featuresData['features'];

  foreach($features['features'] as $index => $feature) {
    $features['features'][$index]['photo-url'] = photoUrl($feature['id']);
  }


  foreach($features['more-features'] as $sectionIndex => $section) {
    foreach($section['items'] as $rowIndex => $item) {
      $item['thumbnail152-url'] = photoUrl(FeaturesReader::thumbnailId($item['id']));
      unset($item['id']);
      $features['more-features'][$sectionIndex]['items'][$rowIndex] = $item;
    }
  }
  //$featuresData['features'] = $features;
  
  //print_r($features);
  echo json_encode($features);

 } else if($command == "banner") {
  $featuredBanner = FeaturesReader::getFeaturedBanner();
  $featuredBanner['photo-url'] = photoUrl(FeaturesReader::photoBannerId($featuredBanner['id']));
  
  //print_r($featuredBanner);
  echo json_encode($featuredBanner);

 } else if($command == "image") {
  FeaturesReader::outputImage($_REQUEST['id']);
}


function photoUrl($id) {
  $port = ($_SERVER['SERVER_PORT'] == "80") ? "" : ":" . $_SERVER['SERVER_PORT'];
 
  return "http://{$_SERVER['SERVER_NAME']}{$port}{$_SERVER['PHP_SELF']}?module=features&command=image&id={$id}";
}

?>
