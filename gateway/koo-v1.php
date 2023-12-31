<?php
header('Content-Type: text/plain');

$q = $_SERVER['QUERY_STRING'];
if (!strlen($q)) {
  return;
}

$b64 = strtr($q, '-_', '+/');
$binary = base64_decode($b64, true);

$hpgp = gnupg_init(["file_name" => "/usr/bin/gpg2", "home_dir" => "/home/gnupg-keyring/"]);
//gnupg_seterrormode($hpgp, GNUPG_ERROR_EXCEPTION);

gnupg_adddecryptkey($hpgp, "BA6DA5B473541CC246F0E5F7B2FD60B35239C2EF", "");
//print_r(gnupg_geterror($hpgp));

$plain = gnupg_decrypt($hpgp, $binary);
$params = explode(" ", $plain);

if (count($params) != "3") {
  print_r("exit params size: " . count($params));
  return;
}

$sym_pass = $params[0];
$action = $params[1];
$dest_param = $params[2];

$url = trim("https://keys.openpgp.org/vks/v1/${action}/${dest_param}");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FAILONERROR, true); // Required for HTTP error codes to be reported via our call to curl_error($ch)
curl_setopt($ch, CURLOPT_PROXY, "http://127.0.0.1:9050/");
curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
$output = curl_exec($ch);
//$curl_error = curl_error($ch);
//$curl_info = curl_getinfo($ch);
curl_close($ch);

$tmpfname = tempnam("/tmp", "encrypt-in");

$fhandle = fopen($tmpfname, "w");
fwrite($fhandle, $output);
fclose($fhandle);

$cmd = "cat ${tmpfname} | gpg --homedir /home/gnupg-keyring/ --armor --symmetric --no-symkey-cache --passphrase ${sym_pass} --pinentry-mode loopback --batch";

$ciphertext = shell_exec($cmd);

print_r($ciphertext);

unlink($tmpfname);
?>