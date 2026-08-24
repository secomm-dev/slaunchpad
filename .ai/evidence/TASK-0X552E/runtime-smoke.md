# LC-30 — Runtime Smoke Evidence

Task: TASK-0X552E (SPEC-TASK-0X552E, LC-30 AI Discoverability baseline)
Environment: local docker (`slaunchpad-phpfpm-1` PHP 8.3.20, nginx app container), base URL
https://webhook.thanhaloha.io.vn/ , store "Default Store View" (id 1), feature enabled at
default scope. Captured 2026-08-24.

## 1. GET /llms.txt (enabled) — 200

```
HTTP/2 200
content-type: text/plain; charset=UTF-8
cache-control: max-age=3600, public
etag: "f79d236e565c905b5c41366389b5e333e60285dd"
```

Body:

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
content-type: text/plain; charset=UTF-8
content-length: 0
cache-control: max-age=3600, public
etag: "f79d236e565c905b5c41366389b5e333e60285dd"
```

## 3. Conditional GET (If-None-Match matches etag) — 304

```
curl -H 'If-None-Match: "f79d236e565c905b5c41366389b5e333e60285dd"' → 304
```

## 4. POST /llms.txt → 302 (never reaches generation; standard routing)

## 5. Disabled feature → 404 (GET and HEAD)

Verified earlier in session: `seocomm_ai_discoverability/general/enabled=0` → both GET and
HEAD return 404; re-enabled → 200 again (transcript, docker runtime).

## 6. Cache hit at the provider seam

Bootstrap script inside the container read `CacheInterface::load("seocomm_llms_txt_store_1")`
after the first GET → `CACHE HIT len=133` (byte length of the body above).

## 7. rel="describedby" link on the storefront homepage

```
<link rel="describedby" href="https://webhook.thanhaloha.io.vn/llms.txt">
```

Found in rendered homepage HTML (460 KB page) after layout cache clean. Note: an initial
attempt used an `ifConfig` attribute on `<block>` — invalid per page_configuration.xsd and it
took the whole page down with a ValidationException; fixed by guarding in the template via the
ViewModel `isEnabled()` check (see diff summary). Template guard verified: link renders only
when the feature is enabled for the store view.

## Environment-level deviations (documented, not module defects)

- `PHPSESSID` Set-Cookie appears on `/llms.txt` — a third-party module starts the session at
  front-controller level; the same cookie is set on core `/robots.txt`. The endpoint code
  starts no session and sets no cookies.
- Only one store view exists locally, so multi-store isolation is proven at unit level only.
