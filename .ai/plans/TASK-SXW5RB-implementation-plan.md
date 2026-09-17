# TASK-SXW5RB (SLP-129) — Implementation Plan: Config bật/tắt Snowdog Menu storefront

> Mode B · Specification: Embedded Mini-Spec trong `.ai/records/tasks/TASK-SXW5RB.md` (spec_status: VALID) · Created 2026-09-15 · Scope update: PHP layer relocated sang module `Launchpad_SnowdogMenu` theo chỉ thị user (2026-09-15)

| Item | Reference |
|------|-----------|
| Specification | Embedded Mini-Spec — `.ai/records/tasks/TASK-SXW5RB.md` (5 sections đầy đủ) |
| Related records | BUG-H929MC (SLP-129, gốc), TASK-Z3DAH5 (env traps), TASK-ZQ9ZE1 (megamenu — phụ thuộc Snowdog) |

## Files changed

| # | File | Action | Purpose |
|---|------|--------|---------|
| 1 | `app/code/Launchpad/SnowdogMenu/etc/adminhtml/system.xml` | mới | Section `snowdog_navigation` (tab `hyva_themes` — Stores > Configuration > Hyva Theme), field `general/enabled` (Yes/No, default No, showInStore=1, `canRestore`) |
| 2 | `app/code/Launchpad/SnowdogMenu/etc/config.xml` | mới | Default `0` |
| 3 | `app/code/Launchpad/SnowdogMenu/ViewModel/Config.php` | mới | VM đọc flag (`isSetFlag` SCOPE_STORE) cho templates + plugin |
| 4 | `app/code/Launchpad/SnowdogMenu/Plugin/Snowdog/Menu/Block/Menu.php` | mới | `afterToHtml` trên `Snowdog\Menu\Block\Menu` → `''` khi flag No |
| 5 | `app/code/Launchpad/SnowdogMenu/etc/frontend/di.xml` | mới | Đăng ký plugin (frontend-scope only) |
| 6 | `app/code/Launchpad/SnowdogMenu/{registration.php,etc/module.xml,composer.json,README.md,CHANGELOG.md}` | mới | Khung module (idiom `Launchpad_QuickView`) |
| 7 | `app/design/frontend/Secomm/launchpad/Snowdog_Menu/layout/default_hyva.xml` | edit (+2 dòng) | `remove="false"` restore `topmenu_mobile`/`topmenu_desktop` |
| 8 | `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/header/topmenu.phtml` | mới (copy vendor + guard) | Flag No → native children; flag Yes → Snowdog mobile child; giữ cache-tags line |
| 9 | `app/design/frontend/Secomm/launchpad/Snowdog_Menu/templates/page/js/plugins/collapse.phtml` | mới (copy + guard) | Không load Alpine collapse plugin khi flag No |
| 10 | `app/etc/config.php` | CLI `module:enable` (as secomm) | `Snowdog_Menu` 0→1 (đè diff uncommitted của session khác — đúng scope SLP-129) + dòng mới `Launchpad_SnowdogMenu => 1` |

## Sequence

1. **Env**: `module:enable Snowdog_Menu` → `setup:di:compile` → `setup:upgrade` (tạo lại 4 bảng `snowmenu_*` — bảng thiếu do DB re-import) → `cache:flush`. Sequence proven TASK-Z3DAH5. chown file đụng (BUG-SDZPCD).
2. **Module `Launchpad_SnowdogMenu`**: khung module + system.xml + config.xml + `ViewModel\Config` + plugin + `etc/frontend/di.xml` (idiom `Launchpad_QuickView` — `declare(strict_types=1)`, constructor promotion, header comment TASK/SLP). Config path `snowdog_navigation/general/enabled` (section riêng, tab `hyva_themes` theo chỉ thị user — không đụng tab `secomm` lẫn section `secomm_storefront` của Secomm_Base).
3. **Theme layer**: layout restore (2 dòng) + 2 template guard trỏ `Launchpad\SnowdogMenu\ViewModel\Config`.
4. **Cache**: `cache:flush` toàn bộ sau mọi template/block edit (TASK-Z3DAH5 trap #4: `cache:clean block_html` KHÔNG đủ).
5. **Verify**: curl guest × 5 AC (native ON/OFF, fixture render, toggle cycle, module-disabled smoke). Menu fixture: insert tạm `snowmenu_menu` + `snowmenu_node` (type `custom_url` — `custom` không tồn tại trong NodeTypeProvider) qua PDO as-secomm → verify → DELETE + flush (as-found restore).
6. **Re-verify sau relocate**: full matrix chạy lại 2 lần — vòng 1 (wiring Secomm_Base, đã revert) và vòng 2 (Launchpad_SnowdogMenu, final) — đều PASS.

## Guards

- Không sửa: `app/code/Snowdog/**`, `vendor/**`, `app/code/Secomm/Base/**` (user chỉ thị: không viết trong Secomm — hosting riêng `Launchpad_SnowdogMenu`), checkout/payment/shipping/order (không thuộc §12).
- Không đụng các diff uncommitted khác của working tree (chỉ config.php Snowdog line + module line mới là in-scope).
- Không thêm storefront string (BR-001); không class Tailwind mới.
- Restore-as-found: menu fixture DELETE sau verify; config row test DELETE sau verify (as-found = no-row → default No); DB row dọn sạch.
- Nếu AC lệch behavior → dừng, điều tra, KHÔNG tự ý đổi kiến trúc (plugin là quyết định có rationale — record §Findings).
