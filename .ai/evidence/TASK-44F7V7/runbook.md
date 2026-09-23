# Runbook — GHTK staging contract probe (TASK-44F7V7)

## Trạng thái hiện tại (2026-09-14)

**BLOCKED_BY_CREDENTIAL** — `carriers/ghtk/api_token` + `x_client_source` trong project config
đều rỗng; không có staging token từ nguồn nào khác. Probe kit đã sẵn sàng, chạy 1 lệnh khi có token.

## Bước 1 — Lấy staging credential (ai có quyền portal GHTK thực hiện)

1. Đăng nhập **staging portal**: `https://khachhang-staging.ghtklab.com`
2. Tạo API token tại mục quản lý token (đặt tên `secomm-launchpad-probe`, hạn dùng ngắn,
   scope: calculate fee + create order + get status).
3. Ghi nhận `PARTNER_CODE` (shop code / private partner code) của staging shop.
4. **Không** paste token vào chat/log/commit — chỉ export vào shell session hiện tại.

## Bước 2 — Chạy probe

```bash
export GHTK_STAGING_TOKEN='<token staging>'
export GHTK_PARTNER_CODE='<partner code>'
# optional: export GHTK_PICK_ADDRESS_ID='<pick_address_id trên staging>'   # cho probe §15

php .ai/evidence/TASK-44F7V7/probe.php | tee .ai/evidence/TASK-44F7V7/results-$(date +%Y%m%d).json
```

Probe sẽ lần lượt chạy (masked output, token không bao giờ in):

| # | Probe | Mục đích |
|---|-------|----------|
| 0 | `GET /services/authenticated` | connectivity + token validity gate |
| 1 | RATE R1 ×4 (Hà Nội/TP.HCM/Đà Nẵng/An Bình-Cần Thơ trùng 5 tỉnh) — **không district** | Outcome A/B/C (district semantics) |
| 2 | RATE R2 (Hà Nội + legacy district **Ứng Hòa**) | so sánh R1 vs R2 cùng address |
| 3 | CREATE C1 (no district, ver absent, id `secomm-probe-c1-nodistrict-001`, products 0.2 kg) | CREATE address + weight runtime |
| 4 | CREATE C1 **repeat same id + same payload** | ORDER_ID_EXIST runtime shape |
| 5 | CREATE C2 (with district, id `secomm-probe-c2-withdistrict-001`) | district runtime |
| 6 | CREATE `?ver=1.5` (id `secomm-probe-ver15-001`) | version semantics |
| 7 | RATE/CREATE với `pick_address_id` (pick_* omitted) + `list_pick_add` | ID priority (nếu có id) |

## Bước 3 — Đọc kết quả

- RATE: R1 accepted đồng đều → Outcome A (`VN_ADMIN_2025 + TEXT_NAME, district optional`);
  R1 fail + R2 pass → Outcome B (cần district — phân biệt "district text" vs "legacy identity");
  lộn xộn → Outcome C (giữ NEEDS_RUNTIME_VERIFICATION, block production enablement).
- CREATE C1 repeat: kỳ vọng `ORDER_ID_EXIST` + `partner_id`/`ghtk_label`/`created`/`status` —
  ghi nguyên văn shape cho P1 recovery task.
- ver=1.5: không khác biệt → `VERIFIED_ENDPOINT_VERSION_ONLY`.
- Weight: response `fee`/order fee khớp scale 0.2 kg → khẳng định kg (TASK-KCXKVR conversion đúng).

## Bước 4 — Cleanup (staging only)

Đơn probe tạo ở trạng thái chưa-lấy-hàng → cancel được theo docs:

```bash
curl -s -X POST "https://services-staging.ghtklab.com/services/shipment/cancel/secomm-probe-c1-nodistrict-001" \
  -H "Token: $GHTK_STAGING_TOKEN" -H "X-Client-Source: $GHTK_PARTNER_CODE"
```

(Docs ghi POST nhưng sample GET — đây cũng chính là điểm method-discrepancy cần ghi nhận khi
thực chạy.) Không bao giờ cleanup/cancel trên production.

## Test data provenance (canonical, tái lập được)

| Case | scheme | province_code | ward_code | name (province/ward) |
|---|---|---|---|---|
| hn_phuong_ung_thien (cả role "cấp xã" — 2025 names không prefix) | VN_ADMIN_2025 (CURRENT) | VN-12 | VNA25-001EB63245 | Hà Nội / Ứng Thiên |
| hcm_binh_chan | VN_ADMIN_2025 | VN-15 | VNA25-00F4A0BB48 | Hồ Chí Minh / Bình Chánh |
| dn_phu_ninh | VN_ADMIN_2025 | VN-06 | VNA25-089B81B0E8 | Đà Nẵng / Phú Ninh |
| an_binh_cantho_dup5 (tên trùng ở 5 tỉnh) | VN_ADMIN_2025 | VN-04 | VNA25-22DABDD368 | Cần Thơ / An Bình |
| legacy district (R2) | VN_ADMIN_PRE_2025 | — | VNAP25-6E0A6B3F14 | Ứng Hòa (unique PRE district của Ứng Thiên qua incoming PRE edges) |

Nguồn: `secomm_vietnam_address_unit` / `secomm_vietnam_address_mapping` (local reference layer,
scheme VN_ADMIN_2025 = CURRENT) — query tại thời điểm 2026-09-14.
