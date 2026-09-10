---
id: TASK-ZR2ZNS
type: task
title: Split Pancake adapter into Bridge + Function modules and deprecate legacy Secomm_Pancake
project_code: SLP
parent:                       # null — epic SLP-30 chưa có canonical FEAT record (không tự tạo Feature chỉ để có parent, §3)
mode: A
specification_level: MINI
spec_status: DRAFT            # plan + epic do dev session 2026-09-10 tự cập nhật; chờ TL approve để VALID
specification_ref: "[SLP-30] Epic.md (rewrite 2026-09-10) + plans/2026-09-10-bridge-function-convention.md"
risk: high                    # restructure module + module enable/disable + admin UI relocate; không đổi DB schema Core
status: in_progress           # code xong + smoke pass (2026-09-10), working tree CHƯA commit, chờ TL review
created: 2026-09-10
updated: 2026-09-10
external_refs:
  cursor: SLP-30              # mirror .cursor/tasks/SLP-30/ — consolidate 4 ticket con CONV-001/FNC-001/BRG-001/MIG-001
legacy_ids: []                # không phải consolidate từ SL-NNN; là 4 ticket Cursor con của epic
ticket_ref:
  - SLP-30/CONV-001
  - SLP-30/FNC-001
  - SLP-30/BRG-001
  - SLP-30/MIG-001
decisions: [DEC-TASKZR2ZNS-001]
decision_assessment: material
components:
  - Secomm_PancakeBridge      # mới (0.1.0)
  - Secomm_PancakeFunction    # mới (0.1.0)
  - Secomm_Pancake            # legacy stub 0.9.0-deprecated
source_areas:
  - app/code/Secomm/PancakeBridge/
  - app/code/Secomm/PancakeFunction/
  - app/code/Secomm/Pancake/                  # stub hoá — 61 file xóa, giữ 4 file
  - app/etc/config.php                        # module enable state
  - .cursor/tasks/SLP-30/plans/2026-09-10-bridge-function-convention.md
  - .ai/project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md
changes_project_state: true
changes_architecture: true    # convention module POS: Core + Bridge + Function
changes_integration: false    # contract POS không đổi (config path pancake/*, service_code=pancake BC)
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-10
supersedes: []
---

# [SLP][TASK-ZR2ZNS] Split Pancake adapter into Bridge + Function modules and deprecate legacy Secomm_Pancake

<!-- Consolidate 4 ticket Cursor CONV-001/FNC-001/BRG-001/MIG-001 (2026-09-10). Record do AI draft sau khi dev session thực hiện — chờ TL review. -->

## Summary

Tách adapter đơn `Secomm_Pancake` theo convention **Core + Bridge + Function**: `Secomm_PancakeFunction` (vendor logic: PosClient, PayloadBuilder, parser, status mapper/catalog, warehouse catalog) + `Secomm_PancakeBridge` (Magento wiring: config, exporter, cron/CLI/webhook, admin warehouse/status map, DI pools, data patches). Legacy `Secomm_Pancake` thành registration stub disabled. Behavior relocate-only — không đổi contract POS, DB schema Core, config path (`pancake/*`), `service_code=pancake`.

## Mini Spec

### Goal

Thay monolith adapter bằng cặp module Bridge/Function theo convention POS của project để tách rời vendor logic khỏi Magento wiring (chuẩn bị cho adapter POS thứ hai + test vendor logic độc lập).

### Expected Behavior

- Dependency: `Bridge → Function → FulfillmentCore(Api)` và `Bridge → FulfillmentCore`; **Function không reference Bridge**.
- CLI `secomm:pancake:poll`, webhook route, admin menu/ACL/config path giữ nguyên (BC).
- `Secomm_Pancake` disabled (stub rỗng, README deprecated trỏ sang module mới).
- Không sequence ShippingCore/Ghtk/Ahamove.

### Constraints / Rules

- Relocate-only: không đổi behavior export/poll/webhook/admin maps; full e2e DoD-01/02 re-run sau TL review nếu cần.
- Timeline/status mapping behavior giữ như TASK-BS91A3 (active map → đổi order status).
- Mode A + Tier 2 (order lifecycle, PII, external API); không chạm DB schema.

### Out of Scope

- Adapter POS thứ hai; microservice Function ngoài Magento; đổi contract POS API.

### Acceptance Criteria

- AC-001 (AC-C1): `module:status` — Bridge + Function enabled, `Secomm_Pancake` disabled
- AC-002 (AC-C3): Function không có import/reference `Secomm\PancakeBridge`
- AC-003 (AC-C5): smoke export/poll parity qua Bridge (`secomm:pancake:poll --force --limit=2` ok)
- AC-004: `setup:upgrade` + cache flush OK sau split; mapping rows/status maps giữ nguyên (BC)

## Approach

Theo plan `2026-09-10-bridge-function-convention.md`: scaffold 2 module → move classes + namespaces → disable legacy → smoke. Script hỗ trợ: `tools/create_pancake_bridge.py`, `tools/stub_pancake_and_migrate.sh`.

## Implementation Notes

- `Secomm_PancakeFunction` 0.1.0: `Api/PosApiConfigInterface`, `Model/ServiceCode`, `Model/Client/*`, `Model/Order/PayloadBuilder`, `Model/Inbound/OrderPayloadParser`, `Model/Mapping/{PancakeStatusMapper,PancakeStatusCatalog}`, `Model/Warehouse/PosWarehouseCatalog`, 3 unit tests (namespaces mới).
- `Secomm_PancakeBridge` 0.1.0: `Model/Config/PancakeConfig` (+ preference `PosApiConfigInterface`), `Model/Order/PancakeOrderExporter`, `Cron/PollUpdatedOrders`, `Console/Command/PollOrdersCommand`, `Controller/Webhook/Index`, `Controller/Adminhtml/{WarehouseMap,StatusMap}/*`, `Model/{StatusMap,WarehouseMap}/{Form,Listing}DataProvider`, admin views (menu/acl/system/routes/layouts/ui_components), `Setup/Patch/Data` (seed + backfill), `Model/Log/PancakeLogConfig`, i18n, `etc/di.xml` pools (exporter/mapper/logger gates).
- Legacy `Secomm_Pancake` 0.9.0-deprecated: stub 4 file (composer/README/registration/module.xml trống sequence); 61 file xóa; CHANGELOG stub mới tạo (history kế thừa chuyển sang Bridge).
- `app/etc/config.php`: `Secomm_Pancake=0`, `Bridge=1`, `Function=1`.
- CHANGELOG: Bridge 0.1.0 kế thừa toàn bộ history 0.1.0–0.3.1 của monolith; Function 0.1.0.
- **Bám kèm working tree (không thuộc item này — tách commit riêng)**: `app/code/Secomm/Ahamove/etc/db_schema.xml` (xóa UNIQUE `AHAMOVE_CITY_CITY_ID` — Tier 2 DB schema, scope khác), `app/design/.../tailwind-source.css` (+1 line), `dev/null` (file rác 0 byte do redirect nhầm — nên xoá), `.cursor.zip`, `tools/` (script migration — quyết định TL có commit không).

## Verification

- [x] AC-001 — evidence `.ai/evidence/SLP-30/README.md` §Bridge/Function migration (`module:status` Pass)
- [x] AC-002 — grep no Bridge refs (evidence Pass)
- [x] AC-003 — poll smoke `--force --limit=2` `ok=2 fail=0`, status map FOUND (2026-09-10)
- [ ] AC-004 — `setup:upgrade` Pass (evidence); mapping rows BC đã kiểm (export rows cũ vẫn poll được) — nhưng full DoD-01/02 e2e sau split **chưa re-run**, chờ TL quyết có cần không
- [ ] TL review + commit (Level 2 gate)

## Related records

- DEC-TASKZR2ZNS-001 (convention Bridge/Function)
- TASK-BS91A3 (status mapping — UI/patches relocate sang Bridge, logic map giữ ở Core)
- Evidence: `.ai/evidence/SLP-30/README.md`
- Mirror tickets: `.cursor/tasks/SLP-30/[SLP-30][{CONV-001,FNC-001,BRG-001,MIG-001}]-*.md`
