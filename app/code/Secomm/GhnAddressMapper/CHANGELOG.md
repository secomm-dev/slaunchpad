# Changelog — Secomm_GhnAddressMapper

## 2026-09-03 — TASK-Q4B98P / DEC-FEATYA2C0W-004 (module slice)

### Added — VN scheme-swap reference guard
- `Model/Import/DirectoryReferenceGuard` — đăng ký bảng `secomm_ghn_address_mapping_location` (keys `region_id` + `city_id`) với guard extension point của `Secomm_VietNamAddress`; mỗi destructive VN scheme operation (swap/rebuild/stray purge) sẽ chặn khi bảng này vẫn tham chiếu runtime directory rows. Trước đây logic này hardcode trong `VietNamAddress/Model/Import/VnAddressSchemeImporter` — giờ bảng được khai báo bởi module sở hữu (DI argument `directoryReferenceGuards`). Behavior parity với guard cũ (cùng COUNT + cùng message, message có i18n vi_VN/en_US).
- `etc/di.xml` registration (item `secomm_ghn_address_mapping_location`).
- `i18n/{vi_VN,en_US}.csv` (mới) — guard message.
- Unit tests: `Test/Unit/Model/Import/DirectoryReferenceGuardTest.php` (6 cases).

### Changed
- `etc/module.xml` sequence += `Secomm_VietNamAddress`; `composer.json` require += `secomm/module-vietnam-address` (chuỗi dependency DEC-004 D1: carrier → VietNamAddress; không tạo cycle — VietNamAddress không depend module này).
