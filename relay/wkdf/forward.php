<?php
header('Content-Type: text/plain');

$q = $_SERVER['QUERY_STRING'];
if (!strlen($q)) {
  return;
}

$url = 'https://gateway.pgp.icu/wkd.php?' . $q;

try {
  $result = file_get_contents($url);
} catch (Exception $ex) {
echo "exception on relay\n";
return;
}

echo $result;
?>
