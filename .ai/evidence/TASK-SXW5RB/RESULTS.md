# TASK-SXW5RB — Verify Results (2026-09-15)

**Task**: Config bật/tắt Snowdog Menu storefront (SLP-129) — Mode B
**Kiến trúc final**: module `Launchpad_SnowdogMenu` (config + ViewModel + plugin — theo chỉ thị user; vòng 1 dùng Secomm_Base, đã revert sạch về HEAD) + theme layer trong `Secomm/launchpad`.
**Env**: Magento 2.4.8-p5 + Hyvä 3.x, local `http://slaunchpad.localhost/` (Apache name-vhost — curl dùng `-H "Host: slaunchpad.localhost"` qua 127.0.0.1), db `slaunchpad`, MAGE_MODE developer.
**Commit**: verified_against_commit `c13aefaf` (working tree — chưa commit, chờ TL).

## Điều kiện verify

- `Snowdog_Menu` re-enabled + `setup:upgrade` (tạo 4 bảng `snowmenu_*` — bảng thiếu do DB re-import trước đó).
- Module mới `Launchpad_SnowdogMenu` enabled + `setup:di:compile` + `setup:upgrade` + `cache:flush`.
- Mọi thay đổi flag/fixture đều theo `cache:flush` toàn bộ (trap #4 TASK-Z3DAH5: `cache:clean block_html` không đủ).
- curl guest, homepage, marker counts bằng grep trên HTML thật.

## Kết quả vòng 2 (final — Launchpad_SnowdogMenu; 5/5 AC PASS)

| AC | Trạng thái | HTTP | initMobileMenu (native) | initMenuDesktop (native) | Company Menu (footer) | FIXTURE-SXW5RB (snowdog) | initTopmenuDesktop (snowdog) |
|----|-----------|------|------------------------|--------------------------|------------------------|--------------------------|------------------------------|
| AC-001 | flag mặc định (No, no DB row) | 200 (655KB) | 4 ✓ | 19 ✓ | 1 ✓ | — | 0 ✓ |
| AC-002 | flag=1, 0 menu | 200 (517KB) | 0 ✓ | 0 ✓ | 1 ✓ | — | 0 ✓ (render rỗng, không crash) |
| AC-003a | flag=1 + fixture | 200 (537KB) | 0 ✓ | 0 ✓ | 1 ✓ | **2 ✓** (desktop + mobile) | **2 ✓** |
| AC-003b | flag=0 + fixture còn | 200 (655KB) | 4 ✓ | 19 ✓ | 1 ✓ | **0 ✓ (plugin gate)** | 0 ✓ |
| AC-005 | Snowdog_Menu disabled | 200 | 4 ✓ | 19 ✓ | — | — | 0 ✓ |

- **AC-004 (toggle cycle)**: No→Yes→Yes(fixture)→No đều đúng qua `cache:flush`, không stale, không crash — chạy trọn vẹn 2 vòng (wiring cũ Secomm_Base + wiring final Launchpad_SnowdogMenu), cả hai PASS.
- **AC-003b là bằng chứng plugin**: menu data vẫn nằm trong DB nhưng output Snowdog = 0 → gate ở tầng plugin, không phải do menu rỗng.
- AC-005 re-check sau relocate: `module:disable Snowdog_Menu` → 200 + native đầy đủ → re-enabled (trạng thái cuối: **ENABLED** — config.php `Snowdog_Menu => 1`, `Launchpad_SnowdogMenu => 1`).
- Vòng 1 (Secomm_Base) cũng PASS 5/5 cùng hình dạng số liệu — chỉ khác path config/class; relocate KHÔNG làm mất behavior.

## Fixture (đã DELETE — restore as-found)

- Insert: 2 menu (`hyva-topmenu-desktop`, `hyva-topmenu-mobile`) + 4 `snowmenu_store` (store 1,2) + 2 node `custom_url` title `FIXTURE-SXW5RB`.
- Sau cleanup: `menus deleted: 2`, `nodes left: 0` (CASCADE), config row `snowdog_navigation/general/enabled` đã DELETE (as-found = no-row → default No từ config.xml).

## Traps ghi nhận trong lúc verify

1. **Node type `custom` KHÔNG tồn tại** trong `Snowdog\Menu\Model\NodeTypeProvider` (types hợp lệ: `category`, `product`, `cms_page`, `cms_block`, `custom_url`, `category_child`, `wrapper`) — node type lạ → exception khi render menu có node. Fixture round 1 dùng `custom` → render rỗng im lặng; đổi `custom_url` → PASS. Lưu ý: script fixture phải sửa cùng lúc với DB UPDATE (re-run script cũ = tái diễn bug).
2. **PSR-0 fallback `app/code/`**: đường dẫn file class phải mirror đúng FQN — plugin đặt sai tầng thư mục → "Class not found" 500. Plugin final: `Plugin/Snowdog/Menu/Block/Menu.php` = class `Launchpad\SnowdogMenu\Plugin\Snowdog\Menu\Block\Menu` (convention mirror-target).
3. Đổi plugin class/di.xml giữa chừng cần `setup:di:compile` lại (compiled metadata giữ tham chiếu class cũ), không chỉ cache:flush.
4. curl local name-vhost: `curl -H "Host: slaunchpad.localhost" http://127.0.0.1/` (domain không resolve từ shell).

## Env sau task (as-found)

- `Snowdog_Menu` = **1** (enabled) — khác working-tree ban đầu (diff uncommitted `0`, của session khác; re-enable = đúng scope ticket SLP-129, user đã trỏ line config.php khi ra lệnh thực thi). **Flag cho TL**: commit config.php sẽ đè workaround disable của người khác.
- `Launchpad_SnowdogMenu` = **1** (module mới, +1 dòng config.php — tách hunk khi commit, cùng lưu ý với BUG-KQ5A1D).
- `core_config_data`: 0 row mới (fixture + config row test đã DELETE); `Secomm_Base` đã revert sạch về HEAD (0 diff).
- Cache flushed sạch sau verify.
