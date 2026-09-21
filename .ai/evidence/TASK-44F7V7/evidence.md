# Evidence — TASK-44F7V7: GHTK staging contract probe

Date: 2026-09-14 · Status: **BLOCKED_BY_CREDENTIAL** — probe kit SẴN SÀNG, chưa chạy runtime

## A. Environment verification

| Item | Result |
|---|---|
| Staging endpoint | `https://services-staging.ghtklab.com` — official (logistic-overview docs); **live**: HTTP 401 trong ~0.3s (auth-gated, reachable) — verified 2026-09-14 |
| Production | KHÔNG được dùng (directive §2) — không request nào gửi |
| `dev.giaohangtietkiem.vn` | KHÔNG phải official endpoint (SPIKE-A1DGPY) — không dùng |
| `carriers/ghtk/api_token` | **RỖNG** (core_config_data, 2026-09-14) |
| `carriers/ghtk/x_client_source` | **RỖNG** |
| Kết luận | **BLOCKED_BY_CREDENTIAL** — không fake results, capability KHÔNG freeze, architecture KHÔNG đổi |

## B. Probe matrix — prepared, awaiting credential

Probe kit: [`probe.php`](probe.php) (masked output, token chỉ từ env) + [`runbook.md`](runbook.md)
(lấy token staging portal `khachhang-staging.ghtklab.com`, 1 lệnh chạy toàn bộ matrix, cleanup
staging-only bằng cancel API).

| Probe | Address shape | Result | Error/response | Conclusion |
|---|---|---|---|---|
| RATE no district (HN/TP.HCM/Đà Nẵng/An Bình) | province+ward | PENDING (blocked) | — | §7 Outcome A/B/C sau probe |
| RATE with district (Ứng Hòa) | province+district+ward | PENDING (blocked) | — | so sánh cùng address với R1 |
| CREATE no district (C1) | province+ward | PENDING (blocked) | — | |
| CREATE with district (C2) | province+district+ward | PENDING (blocked) | — | |
| CREATE kg weight (0.2) | products 0.2 + total_weight 0.2 | PENDING (blocked) | — | xác nhận TASK-KCXKVR conversion |
| ORDER_ID repeat | same id + same payload | PENDING (blocked) | — | recovery fields cho P1 |
| pick_address_id | nếu staging có | **NOT_VERIFIED** | — | không invent |

## C. Test data (canonical provenance — reproducible sau dataset updates)

Từ `secomm_vietnam_address_unit` (scheme **VN_ADMIN_2025 = CURRENT** per `secomm_vietnam_address_scheme`):

| Case | province_code | ward_code | province | ward | Vai trò |
|---|---|---|---|---|---|
| 1 | VN-12 | VNA25-001EB63245 | Hà Nội | Ứng Thiên | R1/R2 chính + đại diện cấp-xã (2025 names không prefix Phường/Xã — phát hiện dữ liệu ghi nhận) |
| 2 | VN-15 | VNA25-00F4A0BB48 | Hồ Chí Minh | Bình Chánh | representative TP.HCM |
| 3 | VN-06 | VNA25-089B81B0E8 | Đà Nẵng | Phú Ninh | representative Đà Nẵng |
| 4 | VN-04 | VNA25-22DABDD368 | Cần Thơ | An Bình | ward name trùng ở **5 tỉnh** (Đồng Tháp/Gia Lai/Phú Thọ/Vĩnh Long) |
| legacy | — | VNAP25-6E0A6B3F14 | — | Ứng Hòa | **unique** PRE district của Ứng Thiên (incoming PRE edges collapse = 1) — R2/C2 |

Điểm dữ liệu ghi nhận thêm: ward **Yên Sở** (VNA25-00CFE9F0C0) có 2 legacy districts (Hoàng Mai +
Thanh Trì) — phi-deterministic → cố ý KHÔNG dùng cho R2 (đúng §5: không dùng invented/ambiguous).

## D. Capability status (§21) — chưa freeze

```text
GHTK RATE
requiredScheme =        NEEDS_RUNTIME_VERIFICATION (candidate VN_ADMIN_2025)
supportedRepresentations = TEXT_NAME (docs-verified category; runtime acceptance pending)
districtRequirement =   NEEDS_RUNTIME_VERIFICATION (docs REQUIRED; 2-level reality pending — Outcome A/B/C)
supportsTextualFallback = false
confidence =            NEEDS_RUNTIME_VERIFICATION
evidence =              SPIKE-A1DGPY (docs) + TASK-44F7V7 probe kit (chờ token)

GHTK CREATE
requiredScheme =        NEEDS_RUNTIME_VERIFICATION (candidate VN_ADMIN_2025)
supportedRepresentations = TEXT_NAME (docs-verified category; runtime acceptance pending)
districtRequirement =   NEEDS_RUNTIME_VERIFICATION
supportsTextualFallback = false
confidence =            NEEDS_RUNTIME_VERIFICATION
evidence =              như trên
```

Lưu ý semantics (directive §22): GHTK là carrier **primary TEXT_NAME** — canonical text không phải
"fallback"; nếu probe xác nhận → `supportedRepresentations=[TEXT_NAME], supportsTextualFallback=false`
là shape đúng.

## E. Không làm (directive §24/§25)

0 change vào `Secomm_ShippingCore` / `Secomm_VietNamAddress` / production GHTK classes
(GhtkAddressCapability/Adapter/COD resolver/rate classifier/OrderSubmitService) trong task này.
Probe kit nằm isolated tại evidence dir này.

## F2. Module request parity (§18/H — static check, làm được không cần token)

```
FeeRequestMapper (rate path) emits:
  province, ward, weight, transport + conditional: district, value,
  pick_address_id | pick_province, pick_ward, pick_district
  → shape-identical với probe RATE payloads (R1/R2) ✓ PARITY

OrderRequestMapper (create path) emits:
  order: id, pick_name, pick_tel, pick_address, is_freeship, pick_money, value,
         transport, name, tel, address, province, ward, hamlet, total_weight
         + conditional: district, pick_address_id | pick_province, pick_ward, pick_district
  products: name, weight (kg — boundary converted), quantity, price
  → khớp 1:1 với probe CREATE payloads (C1/C2/ver/ORDER_ID_EXIST) ✓ PARITY
```

Kết luận: khi probe raw staging success với một shape, module path (`GhtkAddressAdapter →
FeeRequestMapper/OrderRequestMapper → GhtkApiClient`) sẽ emit CÙNG shape — không cần sửa code
theo probe (trừ trường hợp probe phát hiện docs-drift mới).
