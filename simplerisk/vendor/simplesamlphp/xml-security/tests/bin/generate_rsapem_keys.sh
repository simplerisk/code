#!/usr/bin/env bash

DESTINATION="/tmp"
PASSWORD="1234"
KEYSIZE="1024"
VALID_UNTIL=36500

if [[ $1 == '-h' || $1 == '--help' ]]; then
    echo "Usage: $0 <destination directory>"
    exit
fi
if [[ $1 == '-d' || $1 == '--destination' ]]; then
    DESTINATION="${2:-/tmp}"
fi

CERTS_DIR="${DESTINATION}/certificates"
KEYS_DIR="${DESTINATION}/keys"

declare -a CERTS=(
  "selfsigned"
  "signed"
  "other"
  "expired"
  "corrupted"
  "broken"
)

## Generate directory structure ##

mkdir "${DESTINATION}/certificates"
mkdir "${DESTINATION}/keys"
touch "${DESTINATION}/index.txt"
echo 01 > "${DESTINATION}/serial.txt"

## Create CA ##

SUBJ="/emailAddress=noreply@simplesamlphp.org/CN=SimpleSAMLphp\ Testing\ CA/O=SimpleSAMLphp\ HQ/L=Honolulu/ST=Hawaii/C=US"

openssl genrsa -out "${KEYS_DIR}/simplesamlphp.org-ca_nopasswd.key" "${KEYSIZE}"

openssl rsa -aes256 -in "${KEYS_DIR}/simplesamlphp.org-ca_nopasswd.key" \
  -out "${KEYS_DIR}/simplesamlphp.org-ca.key" -passout "pass:${PASSWORD}"

openssl req -x509 -new -nodes -key "${KEYS_DIR}/simplesamlphp.org-ca_nopasswd.key" \
  -sha256 -days "${VALID_UNTIL}" -out "${CERTS_DIR}/simplesamlphp.org-ca.crt" \
  -subj "${SUBJ}"

openssl x509 -pubkey -noout -in "${CERTS_DIR}/simplesamlphp.org-ca.crt" > "${KEYS_DIR}/simplesamlphp.org-ca.pub"

## Generate certificates

for CERT in "${CERTS[@]}"
do
  SUBJ="/CN=${CERT}.simplesamlphp.org/O=SimpleSAMLphp\ HQ/L=Honolulu/ST=Hawaii/C=US"

  openssl genrsa -out "${KEYS_DIR}/${CERT}.simplesamlphp.org_nopasswd.key" "${KEYSIZE}"

  openssl rsa -aes256 -in "${KEYS_DIR}/${CERT}.simplesamlphp.org_nopasswd.key" \
    -out "${KEYS_DIR}/${CERT}.simplesamlphp.org.key" -passout "pass:${PASSWORD}"

  openssl req -new -key "${KEYS_DIR}/${CERT}.simplesamlphp.org_nopasswd.key" \
    -out "${KEYS_DIR}/${CERT}.simplesamlphp.org.csr" -subj "${SUBJ}"

  if [[ "$CERT" == "expired" ]]; then
    openssl x509 -req -in "${KEYS_DIR}/${CERT}.simplesamlphp.org.csr" \
      -CA "${CERTS_DIR}/simplesamlphp.org-ca.crt" \
      -CAkey "${KEYS_DIR}/simplesamlphp.org-ca_nopasswd.key" \
      -CAserial "${DESTINATION}/serial.txt" \
      -out "${CERTS_DIR}/${CERT}.simplesamlphp.org.crt" \
      -days 0 \
      -passin "pass:${PASSWORD}"
  elif [[ "$CERT" == "selfsigned" ]]; then
    openssl req -x509 -new -nodes -key "${KEYS_DIR}/selfsigned.simplesamlphp.org_nopasswd.key" \
      -sha256 -days "${VALID_UNTIL}" -out "${CERTS_DIR}/selfsigned.simplesamlphp.org.crt" \
      -subj "${SUBJ}"
  else
    openssl x509 -req -in "${KEYS_DIR}/${CERT}.simplesamlphp.org.csr" \
      -CA "${CERTS_DIR}/simplesamlphp.org-ca.crt" \
      -CAkey "${KEYS_DIR}/simplesamlphp.org-ca_nopasswd.key" \
      -CAserial "${DESTINATION}/serial.txt" \
      -out "${CERTS_DIR}/${CERT}.simplesamlphp.org.crt" \
      -days "${VALID_UNTIL}" \
      -passin "pass:${PASSWORD}"
  fi

  openssl x509 -pubkey -noout -in "${CERTS_DIR}/${CERT}.simplesamlphp.org.crt" \
    > "${KEYS_DIR}/${CERT}.simplesamlphp.org.pub"
done

## Corrupt certificate ##

# This sed-command will swap the first & last character
sed 's/^\(.\)\(.\+\)\(.\)$/\3\2\1/' "${KEYS_DIR}/corrupted.simplesamlphp.org_nopasswd.key" \
  > "${KEYS_DIR}/corrupted.simplesamlphp.org.key"
sed -i 's/^\(.\)\(.\+\)\(.\)$/\3\2\1/' "${KEYS_DIR}/corrupted.simplesamlphp.org.pub"
sed -i 's/^\(.\)\(.\+\)\(.\)$/\3\2\1/' "${CERTS_DIR}/corrupted.simplesamlphp.org.crt"
rm "${KEYS_DIR}/corrupted.simplesamlphp.org_nopasswd.key"

## Break PEM structure ##

# This sed-command will remove the spaces from the PEM header & footer, invalidating the PEM-structure
sed 's/ //g' "${KEYS_DIR}/broken.simplesamlphp.org_nopasswd.key" > "${KEYS_DIR}/broken.simplesamlphp.org.key"
sed -i 's/ //g' "${KEYS_DIR}/broken.simplesamlphp.org.pub"
sed -i 's/ //g' "${CERTS_DIR}/broken.simplesamlphp.org.crt"
rm "${KEYS_DIR}/broken.simplesamlphp.org_nopasswd.key"

## Clean-up csr- and ca-files
rm -f "${CERTS_DIR}/*.csr"
rm -f "${CERTS_DIR}/*_nopasswd.key"
rm -f "${KEYS_DIR}/*.csr"
rm -f "${KEYS_DIR}/*_nopasswd.key"
rm -f "${DESTINATION}/index.txt"
rm -f "${DESTINATION}/serial.txt"
