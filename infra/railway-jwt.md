# JWT Keys For Railway

Use Railway environment variables with base64-encoded keys.

## Generate the keys

```bash
openssl genrsa -out private.pem -aes256 4096
openssl rsa -pubout -in private.pem -out public.pem
base64 -w 0 private.pem
base64 -w 0 public.pem
```

## Configure Railway

Set these variables in Railway:

```dotenv
LEXIK_JWT_PASSPHRASE=
JWT_SECRET_KEY=/var/www/html/config/jwt/private.pem
JWT_PUBLIC_KEY=/var/www/html/config/jwt/public.pem
JWT_SECRET_KEY_BASE64=
JWT_PUBLIC_KEY_BASE64=
```

`docker-entrypoint.sh` decodes `JWT_SECRET_KEY_BASE64` and `JWT_PUBLIC_KEY_BASE64`
at container startup and writes the files expected by LexikJWTAuthenticationBundle.

Do not commit production keys into the repository or bake them into the image.
