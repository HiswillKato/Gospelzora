# Zora R2 media Worker

Serves private R2 media (bucket `zora`) on your own domain with short-lived HMAC-signed URLs, so R2/S3 credentials never appear in client-visible links. Reads come through this Worker; uploads/deletes/migration still use the app's S3 (R2) API credentials.

## 1. Prerequisites

- Node.js + npm
- Cloudflare account with the `zora.hiws.dev` zone (or your chosen zone) active
- Wrangler auth: `npx wrangler login`

## 2. Configure

- **Route/host:** edit `wrangler.toml` → `routes[0].pattern` and `zone_name`. Default is `cdn.zora.hiws.dev/*` on zone `zora.hiws.dev`. The hostname you choose must be set as `R2_MEDIA_URL` in `/var/www/zora/.env`.
- **DNS:** add/proxy the media hostname in Cloudflare DNS when you attach the route/custom domain (Workers dashboard or `wrangler deploy` will guide this).
- **Bucket binding:** `bucket_name = "zora"` is already set.

## 3. Set the shared secret

Generate once and use the **same** value in both places:

```bash
# 1) Worker secret
npx wrangler secret put MEDIA_SECRET
# paste the secret

# 2) App .env (server)
# R2_MEDIA_SECRET=<same secret>
# R2_MEDIA_URL=https://cdn.zora.hiws.dev
```

A secret has been generated on the server at deploy time; see the setup message or create your own with `openssl rand -hex 32`.

## 4. Deploy

```bash
cd deploy/cloudflare-worker
npm install
npx wrangler deploy
```

Test: `curl -I "https://cdn.zora.hiws.dev/<key>?exp=<ts>&sig=<hmac>"` should return 200 (or 206 with `Range`) for a valid token, 403/410 for invalid/expired.

## 5. App behavior

`app/services/ObjectStorage.php` emits Worker URLs when `R2_MEDIA_URL` and `R2_MEDIA_SECRET` are both set; otherwise it falls back to presigned R2 URLs. Upload/delete/exists/migration remain on the S3 API. Roll back by clearing `R2_MEDIA_URL` or `R2_MEDIA_SECRET`.
