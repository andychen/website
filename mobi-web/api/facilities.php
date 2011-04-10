<?

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'location_suggestion') {
  $data = json_decode(file_get_contents(LIBDIR . "LocationSuggestion.json"));
}

echo json_encode($data);

?>
