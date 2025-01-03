This is the code currently used to run the experimental services at
https://relay.pgp.icu and https://gateway.pgp.icu

It can be used to download OpenPGP keys from the keys.openpgp.org (KOO) server
and from WKD servers. (Both are operated by others).

Below are instructions that explain how you can prepare a request on a Linux
computer, using a bash shell, send the request, and decrypt the result.


create a random secret, e.g. using
SECRET=$(xxd -u -l 16 -p /dev/urandom)

Ensure you have imported the public key of your gateway server
(e.g. gateway.pgp.icu )
into your gnupg keyring and have its fingerprint
GATEWAY_KEY="18F82455FAF551851D1884301F61598816DAD843"


For requests to keys.openpgp.org (KOO):

    Chose exactly one of the following three:

    (1)
    ACTION="by-fingerprint"
    PARAM="21D16E67E18398C8DA9DDF2E1C27423725007724"

    (2)
    ACTION="by-keyid"
    PARAM="40DC6189F9269749"

    (3)
    ACTION="by-email"
    PARAM="kaie@kuix.de"


For WKD requests:

    You need to calculate the WKD hash.

    EMAIL="kaie@kuix.de"

    HASH="7whr9jbcyx4ycgjfjnwogqac6qkgx4t9"
    or
    HASH=$(echo -n $EMAIL|sed 's/@.*$//g'|openssl sha1 -binary|zbase32-encode)
    (see also file calc-wkd-hash.txt )


You need to know an url that offers to a relay service, and
prepare the request parameter for the gateway

For KOO:
    FORWARDER="https://relay.pgp.icu/koof/v1/forward.php"
    REQUEST_PARAM=$(echo "${SECRET} ${ACTION} ${PARAM}" | gpg --trust-model always --encrypt -r ${GATEWAY_KEY} |base64 -w0 | sed 's/+/-/g' | sed 's#/#_#g')
    TMPFILE=/tmp/pubkey-for-${PARAM}

For WKD:
    FORWARDER="https://relay.pgp.icu/wkdf/forward.php"
    REQUEST_PARAM=$(echo "${SECRET} ${EMAIL} ${HASH}" | gpg --trust-model always --encrypt -r ${GATEWAY_KEY} |base64 -w0 | sed 's/+/-/g' | sed 's#/#_#g')
    TMPFILE=/tmp/pubkey-for-WKD-${EMAIL}
    

get the result
wget -O ${TMPFILE}-encrypted "${FORWARDER}?${REQUEST_PARAM}"

decrypt the result
cat ${TMPFILE}-encrypted | gpg --decrypt --no-symkey-cache --passphrase ${SECRET} --pinentry-mode loopback --batch > ${TMPFILE}

Because the result of a WKD request is binary, and the result from the forwarder contains padding, we need to extract the binary data.
The result of the forwarder is text, and the first line (until the first carriage return) contains the base64-encoded binary request.

head -1 ${TMPFILE} | base64 -d > ${TMPFILE}-wkd

show the result file
less ${TMPFILE}
or less ${TMPFILE}-wkd


