<?
require LIBDIR . 'TourReader.php';

$command = $_REQUEST['command'];

if($command == "toursList") {
  echo json_encode(TourReader::getTours());
} else if($command == "tourDetails") {
  $id = $_REQUEST['tourId'];

  $tourDetails = TourReader::getTourDetails($id);
  populatePhotoAudioUrls($id, $tourDetails);

  //print_r($tourDetails);
  echo json_encode($tourDetails);
}

function populatePhotoAudioUrls($tourId, &$tourDetails) {
  populateArrayWithPhotoAudioUrls($tourId, $tourDetails);

  $sites = $tourDetails['sites'];
  foreach($sites as $siteIndex => $site) {
    populateArrayWithPhotoAudioUrls($tourId, $sites[$siteIndex]);
    populateArrayWithPhotoAudioUrls($tourId, $sites[$siteIndex]['exit-directions']);

    $sites[$siteIndex]['content'] = populateSidetripPhotoAudioUrls($tourId, $site['content']);
    $sites[$siteIndex]['exit-directions']['content'] = populateSidetripPhotoAudioUrls($tourId, $site['exit-directions']['content']);
  }
  $tourDetails['sites'] = $sites;

  foreach($tourDetails['start-locations']['items'] as $index => $startLocation) {
    if(isset($startLocation['photo-id'])) {
      $startLocation['photo-url'] = mediaUrl('image', $tourId, 'photoId', $startLocation['photo-id']);
      unset($startLocation['photo-id']);
      $tourDetails['start-locations']['items'][$index] = $startLocation;
    }
  }
}

function populateArrayWithPhotoAudioUrls($tourId, &$data) {
  if(isset($data['photo-id'])) {
    $data['photo-url'] = mediaUrl('image', $tourId, 'photoId', $data['photo-id']);
    unset($data['photo-id']);
  }

 if(isset($data['thumbnail156-id'])) {
    $data['thumbnail156-url'] = mediaUrl('image', $tourId, 'photoId', $data['thumbnail156-id']);
    unset($data['thumbnail156-id']);
  }

  if(isset($data['audio-id'])) {
    $data['audio-url'] = mediaUrl('audio', $tourId, 'audioId', $data['audio-id']);
    unset($data['audio-id']);
  }
}

function populateSidetripPhotoAudioUrls($tourId, $content) {
  foreach($content as $contentNodeIndex => $contentNode) {
    populateArrayWithPhotoAudioUrls($tourId, $contentNode);
    $content[$contentNodeIndex] = $contentNode;
  }
  return $content;
}

function mediaUrl($scriptName, $tourId, $mediaIdKey, $mediaId) {
  $query = http_build_query(array("tourId" => $tourId, $mediaIdKey => $mediaId));

  $requestParts = explode('?', $_SERVER['REQUEST_URI']);
  $requestPath = $requestParts[0];  

  $portName = ($_SERVER['SERVER_PORT'] == 80) ? "" : ":" . $_SERVER['SERVER_PORT'];

  return "http://{$_SERVER['SERVER_NAME']}$portName{$requestPath}tours/{$scriptName}.php?$query";
}

?>
