This is the code currently used to run the experimental service at
pgpkey4jrbxx7zca6bzgrh2mhpj3wnis2ghmf7iwmao3kza7dbwddryd.onion

It can be used to download OpenPGP keys from the keys.openpgp.org server
(which is operated by someone else).

Below are instructions that explain how you can prepare a request on a Linux
computer, using a bash shell, send the request, and decrypt the result.


First specify your request for keys.openpgp.org
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


The remainder of these instructions is the same for all the above requests.

create a random secret, e.g. using
SECRET=$(xxd -u -l 16 -p /dev/urandom)

Ensure you have imported the public key of your gateway server
(e.g. pgpkey4jrbxx7zca6bzgrh2mhpj3wnis2ghmf7iwmao3kza7dbwddryd.onion )
into your gnupg keyring and have its fingerprint
GATEWAY_KEY="BA6DA5B473541CC246F0E5F7B2FD60B35239C2EF"

You need to know an url that offers to a relay service.
Below is the URL of an experimental relay.
FORWARDER="https://kuix.de/koof/v1/forward.php"

Prepare the request parameter for the gateway
REQUEST_PARAM=$(echo "${SECRET} ${ACTION} ${PARAM}" | gpg --trust-model always --encrypt -r ${GATEWAY_KEY} |base64 -w0 | sed 's/+/-/g' | sed 's#/#_#g')

set a temporary filename
TMPFILE=/tmp/pubkey-for-${PARAM}

get the result
wget -O ${TMPFILE}-encrypted "${FORWARDER}?${REQUEST_PARAM}"

decrypt the result
cat ${TMPFILE}-encrypted | gpg --decrypt --no-symkey-cache --passphrase ${SECRET} --pinentry-mode loopback --batch > ${TMPFILE}

show the result file
less ${TMPFILE}

import the file
gpg --import ${TMPFILE}
