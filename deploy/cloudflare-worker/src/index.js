export default {
  async fetch(request, env) {
    if (request.method !== "GET" && request.method !== "HEAD") {
      return new Response("Method Not Allowed", { status: 405 });
    }

    const url = new URL(request.url);
    const key = decodeURIComponent(url.pathname.replace(/^\/+/, ""));
    if (!isAllowedKey(key)) {
      return new Response("Not Found", { status: 404 });
    }

    const exp = parseInt(url.searchParams.get("exp") || "0", 10);
    const sig = url.searchParams.get("sig") || "";
    const cd = url.searchParams.get("cd") || ""; // base64url content-disposition (optional)
    if (!exp || !sig) {
      return new Response("Forbidden", { status: 403 });
    }
    if (Math.floor(Date.now() / 1000) > exp) {
      return new Response("Gone", { status: 410 });
    }

    const payload = `${key}\n${exp}\n${cd}`;
    const expected = await hmacHex(env.MEDIA_SECRET, payload);
    if (!timingSafeEqualHex(sig, expected)) {
      return new Response("Forbidden", { status: 403 });
    }

    const rangeHeader = request.headers.get("Range");
    const object = await env.MEDIA_BUCKET.get(key, rangeHeader ? { range: request.headers } : undefined);
    if (!object) {
      return new Response("Not Found", { status: 404 });
    }

    const headers = new Headers();
    object.writeHttpMetadata(headers);
    headers.set("Accept-Ranges", "bytes");
    const ttl = Math.max(0, exp - Math.floor(Date.now() / 1000));
    headers.set("Cache-Control", `private, max-age=${Math.min(ttl, 3600)}`);

    if (cd) {
      try {
        headers.set("Content-Disposition", fromBase64Url(cd));
      } catch (_) {
        // ignore malformed disposition
      }
    }

    if (object.range && typeof object.range.offset === "number") {
      const start = object.range.offset;
      const end = typeof object.range.end === "number" ? object.range.end : (object.size - 1);
      const length = typeof object.range.length === "number" ? object.range.length : (end - start + 1);
      headers.set("Content-Range", `bytes ${start}-${end}/${object.size}`);
      headers.set("Content-Length", String(length));
      return new Response(request.method === "HEAD" ? null : object.body, { status: 206, headers });
    }

    headers.set("Content-Length", String(object.size));
    return new Response(request.method === "HEAD" ? null : object.body, { status: 200, headers });
  },
};

// Allow only app media keys (audio, song artwork, artist images). Never placeholders.
function isAllowedKey(key) {
  if (!key || key.includes("..") || /[^A-Za-z0-9._\/-]/.test(key)) return false;
  const base = key.split("/").pop() || "";
  if (/^placeholder/i.test(base)) return false;
  if (/^audio\/[A-Za-z0-9._\/-]+$/.test(key)) return true;
  if (/^images\/artwork\/[A-Za-z0-9._\/-]+$/.test(key)) return true;
  if (/^images\/artists\/[A-Za-z0-9._\/-]+\.(?:jpe?g|png|gif|webp)$/i.test(key)) return true;
  // Legacy artist images stored in images/ root.
  if (/^images\/[A-Za-z0-9_-]+-[a-f0-9]{8}\.(?:jpe?g|png|gif|webp)$/i.test(key)) return true;
  return false;
}

async function hmacHex(secret, message) {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const sig = await crypto.subtle.sign("HMAC", key, enc.encode(message));
  return [...new Uint8Array(sig)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

function timingSafeEqualHex(a, b) {
  if (typeof a !== "string" || typeof b !== "string" || a.length !== b.length) return false;
  let out = 0;
  for (let i = 0; i < a.length; i++) out |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return out === 0;
}

function fromBase64Url(s) {
  s = s.replace(/-/g, "+").replace(/_/g, "/");
  while (s.length % 4) s += "=";
  return atob(s);
}
