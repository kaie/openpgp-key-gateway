
# Protocol for Private OpenPGP Key Retrieval

## Author
**Kai Engert**  

---

## Abstract

This document describes a protocol for privately downloading OpenPGP keys from keyservers, including `keys.openpgp.org` (KOO) and Web Key Directory (WKD) servers. The protocol leverages a relay and gateway architecture to ensure user privacy, inspired by Oblivious HTTP (OHTTP). It ensures that:
- The gateway cannot identify the original requester.
- The relay cannot determine the content of the request or response.

The protocol specifies distinct mechanisms for interacting with KOO and WKD servers.

---

## Components

### Client
- Prepares requests by encrypting them with the gateway’s public OpenPGP key.
- Generates a random symmetric session key ("SECRET") for response encryption.
- Receives and decrypts responses using the same symmetric key.

### Relay
- Forwards client requests verbatim to the gateway.
- Reuses the HTTP response code received from the gateway for the client response.
- Does not log or alter requests or responses.

### Gateway
- Processes requests received from the relay.
- Downloads requested keys from the appropriate server (KOO or WKD).
- Encrypts responses using the symmetric key provided by the client.
- Adds padding to responses to obscure their size.

---

## Protocol Details

### Request Preparation
The client constructs the request payload depending on the target server.

#### For `keys.openpgp.org` (KOO):
1. Combine the following parameters, separated by whitespace:
   - `SECRET`: A random symmetric session key (e.g., generated using `xxd -u -l 16 -p /dev/urandom`).
   - `ACTION`: Specifies the type of request to be performed. It can take one of the following values:
     - `by-fingerprint`: Requires a fingerprint identifier.
     - `by-keyid`: Requires a key ID.
     - `by-email`: Requires an email address.
   - `PARAM`: The identifier corresponding to the selected `ACTION` (e.g., a fingerprint, key ID, or email address).

   ```bash
   REQUEST_PARAM=$(echo "${SECRET} ${ACTION} ${PARAM}" | gpg --trust-model always --encrypt -r ${GATEWAY_KEY} | base64 -w0 | sed 's/+/-/g' | sed 's#/#_#g')
   ```

#### For Web Key Directory (WKD):
1. Combine the following parameters, separated by whitespace:
   - `SECRET`: A random symmetric session key.
   - `EMAIL`: The email address for the WKD lookup.
   - `HASH`: A zbase32-encoded SHA1 hash of the local part of the email address.

   The hash can be calculated as follows:
   ```bash
   HASH=$(echo -n $EMAIL | sed 's/@.*$//g' | openssl sha1 -binary | zbase32-encode)
   ```

   ```bash
   REQUEST_PARAM=$(echo "${SECRET} ${EMAIL} ${HASH}" | gpg --trust-model always --encrypt -r ${GATEWAY_KEY} | base64 -w0 | sed 's/+/-/g' | sed 's#/#_#g')
   ```

### Request Forwarding
The relay forwards the encrypted request verbatim to the gateway using HTTPS. Different URLs are used to distinguish between KOO and WKD operations:
- `/koof/v1/forward.php?` for KOO.
- `/wkdf/forward.php?` for WKD.

The relay does not modify or log the request.

### Gateway Processing
Upon receiving a request:

1. Decrypt the payload using the gateway’s private OpenPGP key.
2. Parse the parameters (`SECRET`, `ACTION`, `PARAM` for KOO or `SECRET`, `EMAIL`, `HASH` for WKD).
3. Construct the appropriate URL:
   - For KOO:
     ```
     https://keys.openpgp.org/vks/v1/${action}/${param}
     ```
   - For WKD:
     ```
     https://${domain}/.well-known/openpgpkey/hu/${hash}
     ```
4. Download the requested OpenPGP key or WKD binary data.

### Response Construction
The gateway constructs the response as follows:

1. For KOO:
   - Concatenate the downloaded public key block (or an empty string if unavailable) with padding:
     ```php
     $pad_prefix = "-----BEGIN PADDING-----
";
     $pad_suffix = "
-----END PADDING-----
";
     $pad_size = 20480 - ((strlen($output) + strlen($pad_prefix) + strlen($pad_suffix)) % 20480);

     $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/';
     $max_index = strlen($chars) - 1;
     $pad = $pad_prefix;

     for ($i = 0; $i < $pad_size; $i++) {
         if ($i % 80 == 0) {
             $pad .= "
";
             continue;
         }
         $index = rand(0, $max_index);
         $pad .= $chars[$index];
     }
     $pad .= $pad_suffix;
     ```

2. For WKD:
   - Include the base64-encoded binary WKD data as the first line of the response, followed by padding as described above.

3. Encrypt the concatenated result using the provided `SECRET`:
   ```bash
   gpg --armor --symmetric --no-symkey-cache --passphrase ${sym_pass} --pinentry-mode loopback --batch --compress-algo none
   ```

### Response Handling
The relay forwards the gateway’s response verbatim to the client.

For WKD, the client must extract the binary WKD data by decoding the base64-encoded first line of the response.

---

## Error Handling
- If the requested key is unavailable, the gateway returns only padding.
- There is no protocol-defined format for reporting malformed requests or communication errors. The relay reuses the HTTP response code from the gateway.

---

## Abuse Prevention
- No initial rate-limiting or CAPTCHA mechanisms.
- Abuse mitigation measures may be implemented later, such as:
  - IP rate-limiting
  - Allow-lists for trusted relays or gateways.

---

## Security Considerations
- The protocol assumes TLS for communication between the client, relay, and gateway.
- Neither the relay nor the gateway logs requests or responses.
- Padding ensures that the relay cannot infer request or response sizes.

---

## Interoperability
- Initially bound to `keys.openpgp.org` and WKD servers.
- Designed for general OpenPGP use, avoiding dependencies beyond RFC 4880 and RFC 6637.

---

## References
- [RFC 4880: OpenPGP Message Format](https://tools.ietf.org/html/rfc4880)
- [RFC 6637: Elliptic Curve Cryptography in OpenPGP](https://tools.ietf.org/html/rfc6637)
