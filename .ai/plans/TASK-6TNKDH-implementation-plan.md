# Implementation Plan: TASK-6TNKDH — Phase GHN-B.2 initial GHN address dataset authoring

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-6TNKDH (parent FEAT-FQWEQ3; slice GHN-B.2 — KHÔNG phải GHN-C) |
| Mode | A (production address dataset) |
| Specification | [specs/SPEC-TASK-6TNKDH-secomm-ghn-initial-dataset-authoring.md](../specs/SPEC-TASK-6TNKDH-secomm-ghn-initial-dataset-authoring.md) — FULL, VALID (TL/SA directive 2026-09-10) |
| Decision | DEC-FEATFQWEQ3-002 (lifecycle giữ nguyên) |
| Reuses | TASK-TBM30R lifecycle toàn bộ (exporter/importers/suggester/audit/CLI) + TASK-MZ2TCB fetchers + `Secomm_VietNamAddress` canonical CSVs (read-only) |
| Risk | High — dataset production đầu tiên; execution phụ thuộc 2 external inputs |

## Approach

2 giai đoạn trong 1 task:

**Giai đoạn 1 — unblocked (done trong plan này):** hoàn thiện tooling authoring + integrity gate +
chứng minh plumbing trên DB sống: (a) `MappingSuggester::suggestFromExport` — candidate workfile từ
export dir (không cần API lần 2); (b) review artifact `GHN_ADDRESS_MAPPING_REVIEW_<scheme>.csv`
(hierarchy context 2 phía — §12); (c) `BundledDatasetIntegrityTest` file-to-file (manifest checksum/
counts, portable identity, hierarchy parent-complete, APPROVED resolvable 2 phía, 1:1) — sống mãi
làm guard cho `data/`; (d) e2e bootstrap placeholder trên DB sống.

**Giai đoạn 2 — execution (blocked, mechanical khi unblocked):** owner cấu hình GHN sandbox
credentials (`secomm_ghn/general/*`, admin — không qua AI) → `export --scheme` × 2 → `suggest
--export-dir` × 2 → offline review (AI-assisted theo rules được TL duyệt + manual cho ambiguous) →
đặt APPROVED + note evidence → copy reviewed artifacts vào `data/` + bump manifest `1.0.0` →
`import` → `audit` (production_ready hoặc ghi nhận unresolved trung thực) → resolver smoke →
commit. Refresh-cycle: chạy lại export mới so với bundled (DISABLED-not-delete đã có test +
UnitPersister dùng chung).

## Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `Model/Address/Mapping/MappingSuggester.php` | modify | +`suggestFromExport` + review artifact (§12) |
| `Console/Command/SuggestMappingCommand.php` | modify | +`--export-dir` `--review-output` |
| `Test/Unit/Model/Address/Dataset/BundledDatasetIntegrityTest.php` | new | integrity gate (§20) |
| `Test/Unit/Model/Address/Mapping/MappingSuggesterTest.php` | modify | constructor mới |
| `app/code/Secomm/Ghn/etc/db_schema.xml` | fix | `onDelete="RESTRICT"` → `NO ACTION` (RESTRICT không nằm trong enum Magento; defect lộ khi DB sống lần đầu — NO ACTION tương đương InnoDB) |
| `data/*` (giai đoạn 2) | modify | dataset thật + manifest 1.0.0 |
| `.ai/evidence/TASK-6TNKDH/*` | new | evidence tách bạch provider facts / canonical / AI candidates / reviewed decisions |

## Steps

1. Suggester export-dir + review artifact + CLI + tests — risk: medium — verify: phpunit.
2. Integrity tests — risk: low — verify: phpunit (1 skip trung thực cho placeholder).
3. db_schema fix + `setup:upgrade` thật — risk: high (Tier-2 schema, TL review point) — verify:
   bảng tồn tại, `db:status` whitelist khớp.
4. e2e bootstrap placeholder — risk: low — verify: CLI output (đã chạy).
5. Giai đoạn 2 (blocked): credentials → export → review → commit data/ 1.0.0 → import → audit →
   resolver smoke → refresh-cycle check.

## Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Generated metadata stale (Ghtk stream) phá CLI | medium | đã xử lý phiên hiện tại: xóa `generated/metadata/*.php` để developer-mode regenerate |
| Dataset commit sai lan vào mọi project | high | BundledDatasetIntegrityTest + audit production_ready + review artifact bắt buộc |

## Test approach

Unit/integrity như trên; e2e thật (AC-D5) chạy khi credentials + clean DB.

## Out of scope

SPEC §1 scope list (rate/shipment/VietMap/NO_MATCH/checkout/carrier khác).

## Open questions / Escalation

- **[BLOCKED — cần owner]** GHN sandbox Token/ShopId: cấu hình trong admin `Stores → Configuration → Secomm → GHN Shipping` (environment sandbox) — không gửi credentials qua chat.
- **[Cần TL duyệt approval rule]** Đề xuất: exact normalized name 1:1 trong đúng parent scope (cả 2 phía cùng cấu trúc hành chính chính thức) → được phép APPROVED với note `exact-name:1:1:<dataset provenance>`; mọi trường hợp còn lại REVIEW_REQUIRED/UNRESOLVED chờ review tay. Đây là quyết định review có chủ đích (không phải auto-approve im lặng — rule được ghi DEC/evidence trước khi chạy).
