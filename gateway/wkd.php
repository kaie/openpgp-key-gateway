<?php

header('Content-Type: text/plain');

$q = $_SERVER['QUERY_STRING'];
if (!strlen($q)) {
  return;
}

$b64 = strtr($q, '-_', '+/');
$binary = base64_decode($b64, true);

$hpgp = gnupg_init(["file_name" => "/usr/bin/gpg", "home_dir" => "/home/gnupg-keyring/"]);
gnupg_seterrormode($hpgp, GNUPG_ERROR_EXCEPTION);

gnupg_adddecryptkey($hpgp, "18F82455FAF551851D1884301F61598816DAD843", "");
print_r(gnupg_geterror($hpgp));

$plain = gnupg_decrypt($hpgp, $binary);
$params = explode(" ", $plain);

if (count($params) != "3") {
  http_response_code(400);
  print_r("exit params size: " . count($params));
  return;
}

$sym_pass = trim($params[0]);
if (!ctype_alnum($sym_pass) || strlen($sym_pass) > 50) {
  http_response_code(400);
  echo "symmetric passphrase parameter must be strictly alphanumeric and max 50 characters";
  return;
}

$email = trim($params[1]);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo "unexpected email format";
  return;
}

$hash = trim($params[2]);
if (!ctype_alnum($hash) || strlen($hash) != 32) {
  http_response_code(400);
  echo "hash parameter must be alphanumeric and exactly 32 characters";
  return;
}

$email_parts = explode("@", $email);
if (count($email_parts) != "2") {
  http_response_code(400);
  print_r("bad email parameter");
  return;
}

$local = urlencode(trim($email_parts[0]));
$domain = trim($email_parts[1]);

$url_advanced = "https://openpgpkey." . $domain . "/.well-known/openpgpkey/"
  . $domain . "/hu/" . $hash . "?l=" . $local;

$url_direct = "https://" . $domain . "/.well-known/openpgpkey/hu/"
  . $hash . "?l=" . $local;

//print_r($url_advanced);
//echo "\n";
//print_r($url_direct);
//echo "\n";

$output = "";
try {
  $output = file_get_contents($url_advanced);
} catch (Exception $ex) {
}

if (!strlen($output)) {
  try {
    $output = file_get_contents($url_direct);
  } catch (Exception $ex) {
  }
}

$b64out = base64_encode($output);

$pad_prefix = "-----BEGIN PADDING-----\n";
$pad_suffix = "\n-----END PADDING-----\n";
$pad_size = 20480 - ((strlen($b64out) + strlen($pad_prefix) + strlen($pad_suffix)) % 20480);

$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/';
$max_index = strlen($chars) - 1;
$pad = $pad_prefix;

for ($i = 0; $i < $pad_size; $i++) {
  if ($i % 80 == 0) {
    $pad .= "\n";
    continue;
  }
  $index = rand(0, $max_index);
  $pad .= $chars[$index];
}
$pad .= $pad_suffix;


$tmpfname = tempnam("/tmp", "encrypt-in");

$fhandle = fopen($tmpfname, "w");
fwrite($fhandle, $b64out);
fwrite($fhandle, "\n");
fwrite($fhandle, $pad);
fclose($fhandle);

$cmd = "cat ${tmpfname} | gpg --homedir /home/gnupg-keyring/ --armor --symmetric --no-symkey-cache --passphrase ${sym_pass} --pinentry-mode loopback --batch --compress-algo none";

$ciphertext = shell_exec($cmd);

print_r($ciphertext);

unlink($tmpfname);
?>
