<?

require_once LIBDIR . "MIT150Corridor.php";
switch ($_REQUEST['command']) {
 case 'list':
   $offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;
   $limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 10;
   $data = MIT150Corridor::getItems($offset, $limit);
   break;

}

echo json_encode($data);
?>
