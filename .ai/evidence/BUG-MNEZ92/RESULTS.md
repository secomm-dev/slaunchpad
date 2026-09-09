# RESULTS — BUG-MNEZ92 (SLP-186)

> [Header][Account][UI] Lỗi UI tab Account ở header bị cắt khi xổ ra ở màn hình 1440x900 và 1024x768
> Verified: 2026-09-09 · Env: local `slaunchpad.localhost` (HEAD `6586660e`) + demo `slaunchpad-demo.secomm.vn` · Playwright headless chromium

## TL;DR

**Không phải bug code.** Fix SLP-129 (commit `082ba2d6`, 09-08) đã xử lý đúng — local PASS ở cả 2 viewport bị report (1440x900, 1024x768). Trên **demo**, static deploy (`version1788752540` = **2026-09-07 10:42 +07**) **cũ hơn** styles.css rebuild trong commit `082ba2d6` (09-08): CSS demo **thiếu rule `.sm\:left-auto` + `.sm\:-me-4`** trong khi markup **đã có** class `sm:left-auto sm:-me-4`.

**Action: redeploy demo** (sync code ≥ `082ba2d6` + `setup:static-content:deploy vi_VN en_US` + `cache:flush`) — human action, AI không deploy.

## Cơ chế lỗi trên demo (CSS over-constrained, LTR)

1. Nav class hiệu lực trên demo: `left-0` (+ `right-0`, width cố định `sm:w-48` = 192px) — `sm:left-auto` **không có rule CSS** → không hiệu lực.
2. Absolute element đặt cả `left` + `right` + `width` → over-constrained; LTR **bỏ qua `right`** → dropdown mở **sang phải** từ mép trái icon account.
3. Icon account cách mép phải viewport ~145px @1440 (ít hơn @1024) < width 160/192px → cắt mép phải. Khớp screenshot QC (cắt phải, logged-in).

## A/B: demo vs local (fetch 2026-09-09)

| Check | DEMO slaunchpad-demo.secomm.vn | LOCAL slaunchpad.localhost |
|---|---|---|
| Markup nav có `sm:left-auto sm:-me-4` | ✅ CÓ (`demo-nav-markup.txt`) | ✅ CÓ |
| CSS `styles.css` có rule `.sm\:left-auto` | ❌ **KHÔNG** (`demo-env-check.txt`) | ✅ CÓ (grep = 1) |
| CSS `styles.css` có rule `.sm\:-me-4` | ❌ **KHÔNG** | ✅ CÓ |
| CSS có `.lg\:mt-3` (rule build cũ) | ✅ (chứng minh file không rỗng/lỗi fetch) | ✅ |
| Static version timestamp | `1788752540` = **09-07 10:42 +07** (< commit 082ba2d6 09-08) | `1788841782` = 09-08 11:29 |

## Playwright local — 4 viewports (guest; anchoring không phụ thuộc login state)

Script: `customer-menu-viewport-check.js` · Screenshots: `dropdown-*.png` cùng dir.

| Viewport | nav.x → nav.right | width | fits viewport | stock anchor gap (btn.right+16) | console |
|---|---|---|---|---|---|
| **1440x900** (bị report) | 1116.5 → 1308.5 | 192.0 | ✅ true | **0.0** | sạch |
| **1024x768** (bị report) | 773.0 → 965.0 | 192.0 | ✅ true | **0.0** | sạch |
| 1280x800 (regression SLP-129) | 1029.0 → 1221.0 | 192.0 | ✅ true | 0.0 | sạch |
| 375x812 (regression SLP-129) | 68.0 → 228.0 | 160.0 | ✅ true | n/a (mobile mở trái theo design) | sạch |

**RESULT: PASS** — code HEAD đúng ở cả 2 viewport QC report, không regression mobile/desktop.

## Disposition

1. **Không đổi code** — template + CSS trên HEAD đúng và đã verify.
2. **Redeploy demo từ `origin/development`** (chứa `082ba2d6`): pull → `npm run build` (tailwind, phòng CSS source mới hơn) → `bin/magento setup:static-content:deploy vi_VN en_US` → `bin/magento cache:flush`. Sau deploy: QC chụp lại 1440x900 + 1024x768 (guest + logged-in) — dropdown phải neo phải icon, gap 16px, không cắt.
3. **Đề xuất process cho TL** (chưa làm — ngoài scope): bổ sung bước verify CSS đã-build vào deploy checklist (ví dụ grep rule class mới trong `styles.css` deployed sau static-content:deploy) — lỗi "code đúng, deploy stale CSS" sẽ tái diễn với mọi ticket UI thay đổi Tailwind class.

## Findings phụ (report TL)

- `.ai/bin/project-ai-idgen` bị **CRLF line-endings** → chạy trực tiếp fail `/usr/bin/env: 'bash\r'`. Workaround: `tr -d '\r'` sang file tạm. Nên fix line-endings + cân nhắc `.gitattributes` (`*.sh text eol=lf`).
- Memory `CURRENT_STATE.md` (09-08) ghi BUG-H929MC "Chưa commit" — **stale**: thực tế đã commit `082ba2d6`, có trên `origin/development`. Đã sửa CURRENT_STATE + NEXT_TASK kèm record này.

## Tái lập

```bash
# demo markup + CSS
curl -skL -A "Mozilla/5.0" https://slaunchpad-demo.secomm.vn/ -o /tmp/demo-home.html
tr '\n' ' ' < /tmp/demo-home.html | grep -o '<nav[^>]*customer-menu[^>]*>'
curl -sk "https://slaunchpad-demo.secomm.vn/static/version1788752540/frontend/Secomm/launchpad/vi_VN/css/styles.css" \
  | grep -c 'sm\\:left-auto'   # 0 = thiếu rule (root cause)

# local Playwright (shell as root; browser cache secomm)
NODE_PATH=/tmp/pw-cal/node_modules node .ai/evidence/BUG-MNEZ92/customer-menu-viewport-check.js
```
