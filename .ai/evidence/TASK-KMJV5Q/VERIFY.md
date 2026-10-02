# Verify — TASK-KMJV5Q (SLP-291), 2026-10-01

Env: local dev, FPC ON (file-based), CLI as secomm. Probe: `curl -H "Host: slaunchpad.localhost" http://127.0.0.1/`.

| # | Check | Kết quả |
|---|---|---|
| 1 | AC-002 fallback (config trống) — cold render vs baseline | **PASS byte-identical** (`fallback-cold.html`, `final-fallback.html`) |
| 2 | AC-003 label+URL mới sau save (route `about-us` → `getUrl()`) | **PASS** (`configured-home.html`) |
| 3 | AC-004 external URL `https://example.com/privacy` passthrough | **PASS** (href exact, không prefix) |
| 4 | Observer invalidate: config fresh (DB-direct đổi) + FPC warm cũ → dispatch `admin_system_config_changed_section_launchpad_footer` → curl | **PASS** — warm giữ render cũ (1), sau event render mới (1) = observer đúng là thứ làm FPC invalidate |
| 5 | AC-005 XSS `<script>alert(1)</script>` trong label | **PASS** — raw 0 hit, escaped `&lt;script&gt;` trong anchor |
| 6 | AC-006 store scope: store 2 `launchpad_en` giá trị riêng; store không set → null = fallback | **PASS** (`verify-legal-store2.php`: default vs launchpad_en resolve khác nhau, privacy_en null) |
| 7 | AC-007 regression: 5 footer section + accordion + homepage + 0 exception | **PASS** (baseline/final full-page HTML) |
| 8 | Admin structure: `Magento\Config\Model\Config\Structure` load section | **PASS** — Footer / legal_links / 4 field đúng label (`verify-legal-structure.php`) |
| 9 | Machine gate `project-ai-validate --check-records --check-identity --check-specs` | **PASS cho record** (106 FAIL pre-existing legacy) |

## Chưa verify (chuyển TL/QC)
- **Admin UI click-through** (form render + save qua trình duyệt): cred file admin probe đã bị dọn khỏi /tmp → không login được. Cấu trúc đã chứng minh qua Structure API + value round-trip qua config:set; UI save ~2 phút manual.
- Store 2 render full-page qua HTTP (store-switch local chết — LL-0011); đã verify VM resolution per-store qua CLI emulation.

## Bài học (Lessons Learned)
1. **FPC warm-cache illusion**: lần curl đầu sau khi đổi template PASS "byte-identical" vì FPC serve entry cũ — template mới chưa hề chạy. Assert trên cold render (flush trước).
2. **Layout argument ≠ template variable**: `xsi:type="object"` là block data; template var chỉ từ `assign()`/`_viewVars` + engine `blockVariables` (Hyva inject `$viewModels` qua PhpPlugin). VM qua `$viewModels->require()` + class phải `implements ArgumentInterface`.
3. **`config:unset` không tồn tại** trong core — unset config phải SQL xóa row hoặc admin UI.
4. **`config:set` CLI tự clean config cache** (khác admin save?) — để cô lập observer phải đổi giá trị DB-direct + clean config cache tay.
