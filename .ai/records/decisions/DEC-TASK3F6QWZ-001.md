---
id: DEC-TASK3F6QWZ-001
title: 'Decouple GHN address mapping into dedicated Secomm_GhnAddressMapper module with database persistence and admin UI'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-20
created: 2026-08-20
last_verified: 2026-08-20
verified_against_commit: af1a16d16fef464d2d46e3309a6f1947b1981d39
supersedes: []
superseded_by:
work_items: [TASK-3F6QWZ]
---

# Decision Record: Decouple GHN Address Mapping into dedicated Secomm_GhnAddressMapper module

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-20 — TASK-3F6QWZ (SLP-12) Architecture Decision -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context
Trong module GHN trước đây (`Boolfly_GiaoHangNhanh`), dữ liệu mapping giữa các cấp hành chính của Magento/Secomm (Tỉnh/Thành, Quận/Huyện, Phường/Xã) và ID của GHN (`district_id`, `ward_code`) được lưu trữ cứng trong các file JSON tĩnh khổng lồ (`district.json`, `province.json`, `secomm_giaohangnhanh_ward.json` với hơn 35.000 dòng code).

Nhược điểm:
1. Chiếm dụng dung lượng repo lớn, làm chậm quá trình nạp và phân giải địa chỉ khi tính phí vận chuyển.
2. Không thể điều chỉnh, bổ sung mapping mới hoặc sửa lỗi sai lệch ID khi GHN cập nhật danh mục nếu không sửa mã nguồn và deploy lại.
3. Không thể tích hợp linh hoạt với module `Secomm_AddressDropdown` và dữ liệu địa chỉ động của dự án Launchpad.

## Quyết định (Decision)
1. **Tách riêng thành module `Secomm_GhnAddressMapper`**:
   - Chịu trách nhiệm hoàn toàn về việc ánh xạ giữa dữ liệu địa chỉ của Magento (`region_id`, `city_id` / `city_name`, `ward_id` / `ward_name`) và các mã định danh của GHN (`ghn_province_id`, `ghn_district_id`, `ghn_ward_code`).
   - Xóa bỏ toàn bộ các tệp JSON tĩnh trong `Secomm_GiaoHangNhanh`.

2. **Lưu trữ CSDL + Caching**:
   - Sử dụng bảng CSDL `secomm_ghn_address_mapping` (với index tối ưu hóa cho truy vấn theo `region_id`, `city_id`, `ward_name`).
   - Xây dựng Repository và Cache type chuyên biệt `secomm_ghn_mapping` để đảm bảo tốc độ phân giải địa chỉ sub-millisecond trong luồng tính giá ship checkout.

3. **Quản trị & Vận hành (Admin UI & CLI)**:
   - Cung cấp Admin UI đầy đủ: Grid danh sách, Form thêm/sửa có cascading dropdown (Tỉnh → Quận → Phường), tính năng Import / Export file CSV và tải file mẫu (Sample pack download).
   - Bổ sung CLI commands (`secomm:ghn-mapping:import`, `secomm:ghn-mapping:export`, `secomm:ghn-mapping:validate`) phục vụ tự động hóa triển khai trên môi trường staging/production.

## Hệ quả (Consequences)
- (+) Giảm kích thước module GHN, loại bỏ hơn 35.000 dòng file JSON tĩnh.
- (+) Linh hoạt cho phép vận hành cập nhật mapping trực tiếp trên Admin mà không cần can thiệp code.
- (+) Module `Secomm_GiaoHangNhanh` trở nên tinh gọn, chỉ tập trung vào giao tiếp API với GHN.
- (−) Cần chạy setup/import mapping CSV ban đầu khi dựng môi trường mới.
