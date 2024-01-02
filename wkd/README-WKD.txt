This is the code currently used to run the experimental service at
pgpkey4jrbxx7zca6bzgrh2mhpj3wnis2ghmf7iwmao3kza7dbwddryd.onion

It can be used to download OpenPGP keys from WKD servers.

Below are instructions that explain how you can prepare a request on a Linux
computer, using a bash shell, send the request, and decrypt the result.


First specify your request. You need to calculate the WKD hash.

EMAIL="kaie@kuix.de"

HASH="7whr9jbcyx4ycgjfjnwogqac6qkgx4t9"
or
HASH=$(echo -n $EMAIL|sed 's/@.*$//g'|openssl sha1 -binary|zbase32-encode)
(see also file calc-wkd-hash.txt )

create a random secret, e.g. using
SECRET=$(xxd -u -l 16 -p /dev/urandom)

Ensure you have imported the public key of your gateway server
(e.g. pgpkey4jrbxx7zca6bzgrh2mhpj3wnis2ghmf7iwmao3kza7dbwddryd.onion )
into your gnupg keyring and have its fingerprint
GATEWAY_KEY="BA6DA5B473541CC246F0E5F7B2FD60B35239C2EF"

You need to know an url that offers to a relay service.
Below is the URL of an experimental relay.
FORWARDER="https://kuix.de/wkdf/forward.php"

Prepare the request parameter for the gateway
REQUEST_PARAM=$(echo "${SECRET} ${EMAIL} ${HASH}" | gpg --trust-model always --encrypt -r ${GATEWAY_KEY} |base64 -w0 | sed 's/+/-/g' | sed 's#/#_#g')

set a temporary filename
TMPFILE=/tmp/pubkey-for-WKD-${EMAIL}

get the result
wget -O ${TMPFILE}-encrypted "${FORWARDER}?${REQUEST_PARAM}"

decrypt the result
cat ${TMPFILE}-encrypted | gpg --decrypt --no-symkey-cache --passphrase ${SECRET} --pinentry-mode loopback --batch > ${TMPFILE}-ascii

Because the result of WKD is binary, and the result from the forwarder contains padding, we need to extract the binary data.
The result of the forwarder is text, and the first line (until the first carriage return) contains the base64-encoded binary request.

head -1 ${TMPFILE}-ascii | base64 -d > ${TMPFILE}

show the result file
less ${TMPFILE}

import the file
gpg --import ${TMPFILE}
