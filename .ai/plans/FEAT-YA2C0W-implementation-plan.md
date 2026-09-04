# Implementation Plan: Vietnam current/legacy address profiles + relations + resolver — FEAT-YA2C0W

> Mode A/B — Plan only, chưa viết code (No Code Without Plan; No Code Without Valid Spec).
> Gate đầu tiên **ĐÃ MỞ 2026-08-25**: DEC-FEATYA2C0W-001 accepted + Spec Approved (D-R1 region-level phase / D-R2 prefix L- / D-R3 type nullable / D-R4 +11.4k rows). Task kích hoạt được ngay: TASK-R83FXW (Step 2). Lưu ý: TASK-ADT94K (Step 3) vẫn blocked-by TASK-4F1K3N (FEAT-2PZQKJ).

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | FEAT-YA2C0W (sub-tickets TASK-R83FXW…TASK-S0M7YC) / SPEC-FEAT-YA2C0W |
| Specification | [SPEC-FEAT-YA2C0W-vietnam-current-legacy-address](../specs/SPEC-FEAT-YA2C0W-vietnam-current-legacy-address.md) — Status **Draft** (phải Approved trước code) |
| Author | Claude (AI-assisted draft) — 2026-08-25 |
| Reviewer (TL) | User acting as SA/TL (chat; formal name [TBD]) — approved 2026-08-25 |
| Workflow Mode | A (feature) — task-level mode trong từng record |
| Date | 2026-08-25 |

## 1. Approach

Country layer thuần additive trên nền FEAT-2PZQKJ — **không sửa `Secomm_AddressDropdown`**:

- **Bước 1 — Profiles + membership current** (deploy độc lập được): đăng ký XML + i18n + config mapping + seed 34 claims. Không phụ thuộc task pending nào (engine đã done).
- **Bước 2 — Legacy dataset** (blocked-by import v2 TASK-4F1K3N): rewrite CSV codes `L-xx` + import depth-2 + 63 claims. Snapshot-diff chứng minh data current bất biến.
- **Bước 3 — Relations region-level + CLI**: bảng mới + import/validate deterministic; ward-level chỉ khi D-R1 có data.
- **Bước 4 — Resolver**: contract + impl + unit tests (mock relations).
- **Bước 5 — Integration tests + QC**: isolation 2 chiều, profile levels, resolver trên data thật, regression checklist.
- **Bước 6 — Docs + boundary audit + scope-transfer note TASK-4F1K3N.**

Plan derives behavior từ Spec; conflict → quay lại spec, không âm thầm đổi.

## 2. Files affected (tổng quan — chi tiết trong mini-spec từng task)

| File / Area | Change type | Task |
|------|-------------|------|
| `Secomm/VietNamAddress/etc/address_profiles.xml` | new | R83FXW |
| `Secomm/VietNamAddress/i18n/{vi_VN,en_VN,en_US}.csv` | modify | R83FXW |
| `Secomm/VietNamAddress/Setup/Patch/Data/SeedCurrentProfileMembership.php` (+config default) | new | R83FXW |
| `Secomm/VietNamAddress/Files/VN_Address_Legacy.csv` | new (rewrite từ VN_Address.csv) | ADT94K |
| `Secomm/VietNamAddress/Setup/Patch/Data/ImportLegacyAddressPatch.php` + membership | new | ADT94K |
| `Secomm/VietNamAddress/etc/db_schema.xml` + `db_schema_whitelist.json` | new | X0XKH4 |
| `Secomm/VietNamAddress/Files/VN_Address_Relations.csv` | new (region-level seed; nguồn documented) | X0XKH4 |
| `Secomm/VietNamAddress/Model/ResourceModel/Relation*` + `Model/RelationRepository` (internal) | new | X0XKH4 |
| `Secomm/VietNamAddress/Console/{ImportRelationsCommand,ValidateRelationsCommand}.php` + `etc/di.xml` consoles | new | X0XKH4 |
| `Secomm/VietNamAddress/Api/VietnamAddressResolverInterface.php` + `Api/Data/VietnamAddressResolutionInterface.php` + DTO + `Model/VietnamAddressResolver.php` + di preferences | new | AP6YXP |
| `Secomm/VietNamAddress/Test/Unit/...` + `Test/Integration/...` | new | AP6YXP, 7RK8Q3 |
| README/CHANGELOG + `.ai/records/tasks/TASK-4F1K3N.md` (note) | modify | S0M7YC |

## 3. Steps (độc lập reviewable)

1. **Gate 0 — DEC + Spec approval** — risk: n/a — deps: none
   - SA/TL chốt D-R1 (nguồn ward relations: chưa có → phase region-level trước), D-R2 (prefix L-), D-R3 (relation_type nullable), D-R4 (+14k rows).
   - verify: DEC `status: accepted`; Spec Approved; TL reviewer vào metadata.
2. **TASK-R83FXW profiles + membership current** — risk: medium — deps: 1
   - XML 2 profiles; i18n keys 3 locale; data patch idempotent (34 membership + config default không đè); smoke qua AddressSchemaProvider.
   - verify: AC-001..004 (2 vs 3 levels; resolve('VN') = vn_current).
3. **TASK-ADT94K legacy dataset** — risk: medium — deps: 1, **2; blocked-by TASK-4F1K3N (FEAT-2PZQKJ)**
   - Rewrite CSV (L- codes + code refs); import depth-2; 63 membership; snapshot-diff current data.
   - verify: AC-001..004 (counts; current nguyên vẹn; không code trùng).
4. **TASK-X0XKH4 relation table + CLI + seed region-level** — risk: medium — deps: 3 (cần L- codes tồn tại)
   - Declarative schema + whitelist; CSV region-level (nguồn ghi trong header); 2 CLI commands; `--suggest` report-only.
   - verify: AC-001..004; generate-whitelist exit 0 cho module.
5. **TASK-AP6YXP resolver** — risk: medium — deps: 4
   - Contract + DTO + impl + 6 unit scenarios (mock).
   - verify: AC-001..003.
6. **TASK-7RK8Q3 integration + QC** — risk: medium — deps: 2,3,4,5
   - Isolation 2 chiều (data-level), profile levels qua OM, resolver 3 status trên data thật, QC checklist regression.
   - verify: AC-001..004 + evidence.
7. **TASK-S0M7YC docs + audit** — risk: low — deps: 6
   - README/CHANGELOG; boundary grep; TASK-4F1K3N scope note; coordinate TASK-ZHFVRH.
   - verify: AC-001..003.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Import legacy đè data current | high | Namespace L- + snapshot diff (AC-002 TASK-ADT94K); không dùng entity 9-cột |
| Storefront VN dropdown đổi hành vi | medium | FEAT additive; QC checklist sau mỗi task; renderer không đổi trong FEAT này |
| Import patch re-run nhân bản | medium | Idempotency test từng patch; UNIQUE keys schema |
| DB phình (+14k rows) | low | D-R4 sign-off; index sẵn |

## 5. Test approach

- Unit: resolver 6 scenarios; CLI validate; converter/idempotency helpers.
- Integration (DB dev): membership isolation; profile levels; resolver end-to-end; import re-run.
- QC L2/L3: theo checklist TASK-7RK8Q3 (không chạm checkout flow — không cần L3 payment trừ khi review yêu cầu).
- Evidence per task + feature-level `.ai/runtime/evidence/FEAT-YA2C0W/`.

## 6. Out of scope

Carrier mapping (GHN/GHTK/Ahamove), carrier master-data sync, geocoding/lat-lng/polygon, shipping quote/shipment integration, table-rate fallback, renderer schema-driven (TASK-3T3NSV), GraphQL callers migration (TASK-YQSS3M), sửa Secomm_AddressDropdown.

## 7. Open questions / Escalation

- D-R1..D-R4 — **Tier 2 (SA/TL)** — chặn Step 1.
- Ward-level relations data — **TL/PM** — chặn phần ward của Step 4 (region-level vẫn chạy).
- TASK-4F1K3N xong khi nào (unblock Step 3) — **TL planning**.
