<?php
header('Content-Type: text/plain');

$q = $_SERVER['QUERY_STRING'];
if (!strlen($q)) {
  return;
}

$url = 'https://gateway.pgp.icu/koo-v1.php?' . $q;

$result = file_get_contents($url);
echo $result;
?>
