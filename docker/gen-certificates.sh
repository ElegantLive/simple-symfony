#!/bin/sh
#
# Generate the RSA key pairs the application loads from config/certificate:
#
#   jwt/  -> App\Service\Token      (firebase/php-jwt, RS256)
#   sign/ -> App\Service\Signature  (openssl_private_encrypt / _public_decrypt)
#
# Each directory gets three files, because the app and its clients want different
# encodings of the same key:
#
#   rsa_private.pem        PKCS#1  "BEGIN RSA PRIVATE KEY"  - what PHP's openssl reads
#   rsa_public.pem         SPKI    "BEGIN PUBLIC KEY"       - the part clients pin
#   pkcs8_rsa_private.pem  PKCS#8  "BEGIN PRIVATE KEY"      - for clients that require it
#
# Usage
#   docker/gen-certificates.sh [--bits N] [--force]
#
#   --bits N   key size, default 4096 (1024 as shipped is weak; do not go below 2048)
#   --force    overwrite existing keys, after copying them to *.bak.<timestamp>
#
# Without --force it refuses to run when any target already exists, because
# replacing the keys invalidates every JWT already issued and breaks any client
# that pinned rsa_public.pem.
#
# Run it inside the container (it has the openssl CLI):
#   docker compose run --rm --no-deps --entrypoint sh app docker/gen-certificates.sh
#
set -eu

cd "$(dirname "$0")/.."

BITS=4096
FORCE=0
CERT_BASE=${CERT_BASE:-config/certificate}

while [ $# -gt 0 ]; do
    case "$1" in
        --bits)  BITS=$2; shift 2 ;;
        --force) FORCE=1; shift ;;
        -h|--help)
            sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'
            exit 0 ;;
        *)
            echo "unknown argument: $1 (try --help)" >&2
            exit 2 ;;
    esac
done

case "$BITS" in
    ''|*[!0-9]*) echo "--bits must be a number" >&2; exit 2 ;;
esac
if [ "$BITS" -lt 2048 ]; then
    echo "--bits $BITS is below 2048; refusing" >&2
    exit 2
fi

command -v openssl >/dev/null 2>&1 || { echo "openssl not found on PATH" >&2; exit 1; }

# OpenSSL 3.x has genrsa emit PKCS#8 by default; -traditional restores the PKCS#1
# form this project's existing files use. The flag does not exist in 1.1.1, so
# probe for it instead of guessing from the version string.
TRADITIONAL=""
if openssl genrsa -traditional -out /dev/null 1024 2>/dev/null; then
    TRADITIONAL="-traditional"
fi

DIRS="$CERT_BASE/jwt $CERT_BASE/sign"

# Check writability up front. config/certificate is bind-mounted read-only on
# purpose in docker-compose.yml, so generating into the live path from inside the
# container fails with a bare "Read-only file system" unless that mount is
# overridden. Explain it here rather than leaving cp's error to be decoded.
for d in $DIRS; do
    if [ -d "$d" ] && [ ! -w "$d" ]; then
        cat >&2 <<EOF
$d is not writable.

Inside the container that is expected: docker-compose.yml mounts
config/certificate read-only. Override just that mount for this one command:

  docker compose run --rm --no-deps \\
    -v "\$PWD/config/certificate:/var/www/html/config/certificate" \\
    --entrypoint sh app docker/gen-certificates.sh

Or run this script on the host, where the directory is a normal writable path.
EOF
        exit 1
    fi
done

if [ "$FORCE" -eq 0 ]; then
    for d in $DIRS; do
        for f in rsa_private.pem pkcs8_rsa_private.pem rsa_public.pem; do
            if [ -e "$d/$f" ]; then
                echo "refusing to overwrite $d/$f" >&2
                echo >&2
                echo "These keys are live. Replacing them invalidates every JWT already" >&2
                echo "issued and breaks any client that pinned rsa_public.pem." >&2
                echo "Re-run with --force if that is what you want; the old files will be" >&2
                echo "kept as *.bak.<timestamp>." >&2
                exit 1
            fi
        done
    done
fi

generate() {
    dir=$1
    mkdir -p "$dir"

    if [ "$FORCE" -eq 1 ] && [ -e "$dir/rsa_private.pem" ]; then
        stamp=$(date +%Y%m%d%H%M%S)
        for f in rsa_private.pem pkcs8_rsa_private.pem rsa_public.pem; do
            [ -e "$dir/$f" ] && cp -p "$dir/$f" "$dir/$f.bak.$stamp"
        done
        echo "  backed up existing keys as *.bak.$stamp"
    fi

    openssl genrsa $TRADITIONAL -out "$dir/rsa_private.pem" "$BITS" 2>/dev/null
    openssl rsa -in "$dir/rsa_private.pem" -pubout -out "$dir/rsa_public.pem" 2>/dev/null
    openssl pkcs8 -topk8 -nocrypt -in "$dir/rsa_private.pem" -out "$dir/pkcs8_rsa_private.pem" 2>/dev/null

    chmod 600 "$dir/rsa_private.pem" "$dir/pkcs8_rsa_private.pem"
    chmod 644 "$dir/rsa_public.pem"

    # Verify rather than assume: the key must parse, and the public file must be
    # the one derived from this private key.
    openssl rsa -in "$dir/rsa_private.pem" -check -noout >/dev/null 2>&1 \
        || { echo "  $dir: private key failed openssl validation" >&2; exit 1; }

    derived=$(openssl rsa -in "$dir/rsa_private.pem" -pubout 2>/dev/null | openssl dgst -sha256)
    stored=$(openssl rsa -pubin -in "$dir/rsa_public.pem" -pubout 2>/dev/null | openssl dgst -sha256)
    [ "$derived" = "$stored" ] \
        || { echo "  $dir: rsa_public.pem does not match rsa_private.pem" >&2; exit 1; }

    priv_fmt=$(head -1 "$dir/rsa_private.pem")
    case "$priv_fmt" in
        *"BEGIN RSA PRIVATE KEY"*) fmt="PKCS#1" ;;
        *"BEGIN PRIVATE KEY"*)     fmt="PKCS#8 (genrsa emitted PKCS#8 - clients expecting PKCS#1 may care)" ;;
        *)                         fmt="unknown" ;;
    esac

    printf "  %-28s %s bit, %s, public matches private\n" "$dir/" "$BITS" "$fmt"
}

for d in $DIRS; do
    generate "$d"
done

echo "done"
