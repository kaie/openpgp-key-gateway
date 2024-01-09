<?php
//echo "hello\n";
//echo $_SERVER['REQUEST_URI'];
//echo "b\n";
//echo $_SERVER['QUERY_STRING'];

header('Content-Type: text/plain');


$q = $_SERVER['QUERY_STRING'];
if (!strlen($q)) {
  return;
}

//print_r($q);
//print_r("\n");

#$url = "https://keys.openpgp.org/vks/v1/by-fingerprint/" . $q;
#$result = file_get_contents($url);
#echo $result;

$url = 'http://pgpkey4jrbxx7zca6bzgrh2mhpj3wnis2ghmf7iwmao3kza7dbwddryd.onion/koo-v1.php?' . $q;

//print_r("opening: " . $url);
//print_r("\n");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_PROXY, "http://127.0.0.1:9050/");
curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
$output = curl_exec($ch);
$curl_error = curl_error($ch);
http_response_code(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
curl_close($ch);

//print_r("arrived after curl_close");

print_r($output);
print_r($curl_error);
?>
