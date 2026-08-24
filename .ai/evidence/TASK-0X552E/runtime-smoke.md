# LC-30 — Runtime Smoke Evidence

Task: TASK-0X552E (SPEC-TASK-0X552E, LC-30 AI Discoverability baseline)
Environment: local docker (`slaunchpad-phpfpm-1` PHP 8.3.20, nginx app container), base URL
https://webhook.thanhaloha.io.vn/ , store "Default Store View" (id 1), feature enabled at
default scope, `cache/lifetime = 86400`. Re-captured 2026-08-24 after final-correction pass.

## 1. GET /llms.txt (enabled) — 200

```
HTTP/2 200
content-type: text/plain; charset=UTF-8
cache-control: max-age=86400, public        <- derives from seocomm_ai_discoverability/cache/lifetime
etag: "f79d236e565c905b5c41366389b5e333e60285dd"
```

Body (functionally unchanged):

```
# Default Store View
> Default Store View

Locale: vi_VN

## Priority Pages
- [Default Store View]: https://webhook.thanhaloha.io.vn
```

## 2. HEAD /llms.txt — same headers, empty body

```
HTTP/2 200
content-length: 0
cache-control: max-age=86400, public
```

## 3. Conditional GET (If-None-Match matches etag) — 304

```
curl -H 'If-None-Match: "f79d236e565c905b5c41366389b5e333e60285dd"' → 304
```

## 4. Unsupported methods — deterministic 404 (was 302, corrected)

The router only matches GET/HEAD; other verbs fall through to no-route:

```
POST /llms.txt → 404        PUT /llms.txt → 404
```

(No 405/`Allow` header — deliberate: a proper 405 would need a second action seam; a
deterministic 404 is the accepted contract per correction task §2.)

## 5. Disabled feature → 404 (GET and HEAD)

`seocomm_ai_discoverability/general/enabled=0` → GET 404 and HEAD 404; re-enabled → 200.

## 6. HTTP TTL follows the store-scoped cache configuration (corrected)

- `cache/lifetime = 86400` → `Cache-Control: max-age=86400, public` + ETag/304
- `cache/lifetime = 0` (merchant disabled caching) → `Cache-Control: must-revalidate,
  no-cache, no-store` (nginx reorders directives), **no ETag, no 304** — no intermediary may
  serve a stale body. Provider regenerates every request as designed.
- Restoring `86400` → 200 with `max-age=86400` again.

Provider and HTTP headers read the same `Config::getCacheLifetime(storeId)` — unit-proven in
`Test/Unit/Controller/Index/IndexTest.php`.

## 7. Cache hit at the provider seam

Bootstrap script inside the container read `CacheInterface::load("seocomm_llms_txt_store_1")`
after the first GET → `CACHE HIT len=133` (byte length of the body above).

## 8. rel="describedby" link on the storefront homepage

```
<link rel="describedby" href="https://webhook.thanhaloha.io.vn/llms.txt">
```

Homepage renders 200 with the link present (template guard via ViewModel `isEnabled()`; an
earlier `ifConfig`-on-`<block>` attempt was invalid per page_configuration.xsd and removed).

## Environment-level deviations (documented, NOT LC-30 defects)

- `PHPSESSID` Set-Cookie appears on `/llms.txt` — a third-party module starts the session at
  front-controller level; the same cookie is set on core `/robots.txt`. The endpoint code
  starts no session and sets no cookies. LC-30 does not fix this global stack behavior.
- Only one store view exists locally, so multi-store isolation is proven at unit level only.
