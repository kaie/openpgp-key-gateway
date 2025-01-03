<?php
header('Content-Type: text/plain');

$q = $_SERVER['QUERY_STRING'];
if (!strlen($q)) {
  return;
}

$b64 = strtr($q, '-_', '+/');
$binary = base64_decode($b64, true);

$hpgp = gnupg_init(["file_name" => "/usr/bin/gpg", "home_dir" => "/home/gnupg-keyring/"]);
//gnupg_seterrormode($hpgp, GNUPG_ERROR_EXCEPTION);

gnupg_adddecryptkey($hpgp, "18F82455FAF551851D1884301F61598816DAD843", "");
//print_r(gnupg_geterror($hpgp));

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

$action = trim($params[1]);
$dest_param = trim($params[2]);

if ($action == "by-fingerprint") {
  if (!ctype_alnum($dest_param) || strlen($dest_param) != 40) {
    http_response_code(400);
    echo "fingerprint parameter isn't 40 characters or isn't alphanumeric";
    return;
  }
} else if ($action == "by-keyid") {
  if (!ctype_alnum($dest_param) || strlen($dest_param) != 16) {
    http_response_code(400);
    echo "fingerprint parameter isn't 16 characters or isn't alphanumeric";
    return;
  }
} else if ($action == "by-email") {
  if (!filter_var($dest_param, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "unexpected email format";
    return;
  }
} else {
  http_response_code(400);
  echo "invalid action parameter";
  return;
}

$url = trim("https://keys.openpgp.org/vks/v1/${action}/${dest_param}");

$output = file_get_contents($url);

$pad_prefix = "-----BEGIN PADDING-----\n";
$pad_suffix = "\n-----END PADDING-----\n";
$pad_size = 20480 - ((strlen($output) + strlen($pad_prefix) + strlen($pad_suffix)) % 20480);

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
fwrite($fhandle, $output);
fwrite($fhandle, $pad);
fclose($fhandle);

$cmd = "cat ${tmpfname} | gpg --homedir /home/gnupg-keyring/ --armor --symmetric --no-symkey-cache --passphrase ${sym_pass} --pinentry-mode loopback --batch --compress-algo none";

$ciphertext = shell_exec($cmd);

print_r($ciphertext);

unlink($tmpfname);
?>
