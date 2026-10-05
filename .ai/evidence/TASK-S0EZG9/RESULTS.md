# TASK-S0EZG9 (SLP-293) — [Home] Back to Top — RESULTS

Verify: 2026-10-01, local env `http://slaunchpad.localhost/` (curl `--resolve slaunchpad.localhost:80:127.0.0.1` — DNS hiccup), Playwright probe `verify.js` (copy của `/tmp/pw-cal/btt-verify.js`), chạy as `secomm`, cwd `/tmp/pw-cal` (node_modules playwright). Store vi_VN (store 1).

**Change set (4 file + record/evidence):**
- `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/back-to-top.phtml` (MỚI — template + Alpine `initBackToTop`)
- `app/design/frontend/Secomm/launchpad/Magento_Cms/layout/cms_index_index.xml` (+10 — block `back-to-top` additive, container `content` `after="-"`)
- `i18n/{vi_VN,en_US}.csv` (+1 row/file: `"Back to top"` → "Lên đầu trang" / identity)
- `web/css/styles.css` (rebuild — ⚠ artifact MIXED: chứa ambient rules từ sources uncommitted của session khác; commit tách hunk hoặc commit-artifact theo quyết định TL)

## Kết quả

### A. Render/curl (AC-001, AC-004)
| Check | Kết quả |
|---|---|
| Home HTTP 200, `initBackToTop` ×3 (function def + Alpine.data + x-data attr) | PASS |
| aria-label VI render (entity-encoded `L&#xEA;n&#x20;...` — escapeHtmlAttr chuẩn, browser decode) | PASS |
| PLP `/gear/bags.html` — 0 match `initBackToTop` (handle-scoped) | PASS (AC-001) |
| Served CSS hash (`/static/.../vi_VN/css/styles.css`) == source build hash | PASS (`5b1442…`) |

### B. Playwright behavioral — 13/13 PASS, 0 pageerror (AC-002..006, TC-6)
| Check | Kết quả |
|---|---|
| AC-002a hidden tại scrollY=0 | PASS |
| AC-002d hidden đúng tại 1 viewport (threshold strict `>`) | PASS |
| AC-002b visible khi scroll 1.5 viewport | PASS |
| AC-005 desktop 1440 bottom-right (1353,828) | PASS |
| AC-003 smooth scroll (36 scroll events tăng dần, end=0, mid=88) | PASS |
| AC-002c ẩn lại khi về top | PASS |
| AC-004 icon-only: 1 svg Lucide arrow-up, text rỗng, bg = rgb(69,116,76) = #45744c primary olive | PASS |
| TC-6 keyboard: focus + Enter → về top | PASS |
| AC-006 4 native `<dialog>` trên page (cart drawer + quickview = top layer, luôn đè floating button) | PASS (structural) |
| AC-002 mobile 375 hidden tại top | PASS |
| AC-005 mobile 375 visible bottom-right (303,740) | PASS |
| AC-005 mobile không horizontal overflow | PASS |
| AC-003 mobile click → top | PASS |

Screenshots: `desktop-scrolled.png` (1440, nút hiện tại flash sale section), `mobile-scrolled.png` (375).

### C. i18n framework-level (verify-dict.php — CLI store emulation)
| Store | `__('Back to top')` | Kết quả |
|---|---|---|
| `default` (vi) | "Lên đầu trang" | PASS (TRANSLATED) |
| **EN live** | store `launchpad_en` resolve "Lên đầu trang" | **BLOCKED — pre-existing**: store 2 locale = vi_VN (drift TASK-K14RVZ đã flag) + `?___store` switch chết local (LL-0011) → **EN = QC demo** (row CSV line 951 identity `"Back to top","Back to top"` đã verify file-level) |

## Quy trình build/deploy-local đã chạy
1. `npm run build` as secomm (`web/tailwind/`) — utilities mới (`bg-hp-brand-dark`, `outline-hp-brand-dark`, `focus-visible:outline-2`, `translate-y-4`, `.z-40`, `x-cloak`) đã compile ✓
2. **F9 procedure**: cp `styles.css` → `pub/static/frontend/Secomm/launchpad/{vi_VN,en_US}/css/` as secomm (en_US dir bị wipe trước đó — tạo lại bằng mkdir; md5 3 file = 1 hash duy nhất)
3. `cache:flush` as secomm (sau layout edit)

## Traps ghi nhận
- **`grep -c` exit 1 khi count=0 đứt `&&` chain** — lệnh compound của chính session này: grep -c trả 0 match → exit 1 → cp bị skip âm thầm, chỉ cache:flush chạy (phía sau `;`). Phát hiện bằng timestamp/ownership file pub/static. → Kiểm tra artifact sau bước dựa-continue.
- DNS `slaunchpad.localhost` hiccup → curl `--resolve slaunchpad.localhost:80:127.0.0.1` (trap đã biết, TASK-Y23EMS).
- `pub/static` wiped giữa phiên (en_US statics mất) — tái tạo thủ công; sở hữu root-owned gây EACCES Playwright khi ghi PNG → evidence dir chown secomm.
- aria-label VI entity-encoded trong HTML source — grep plain-text 0 match KHÔNG phải lỗi; verify bằng dump markup.
