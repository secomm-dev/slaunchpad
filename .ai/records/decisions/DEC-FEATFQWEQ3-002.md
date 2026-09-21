---
id: DEC-FEATFQWEQ3-002
title: 'Secomm_Ghn mapping lifecycle: versioned export → offline reviewed mapping → import/bootstrap (auto-mapping demoted to candidate-generation tooling)'
status: accepted             # approved 2026-09-10 (user directive — "decisions already approved" trong request GHN-B2)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit:
supersedes:
  - "SPEC-FEAT-FQWEQ3 §7 (một phần — pipeline 'exact match → curated alias → approved' không còn là cơ chế sản xuất mapping)"
superseded_by:
work_items: [FEAT-FQWEQ3, TASK-TBM30R]
---

# Decision Record: Secomm_Ghn mapping lifecycle — offline reviewed import/bootstrap

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-10 (user directive, chat).
     Nguồn: directive "Review and update Secomm_Ghn address-data/mapping lifecycle" 2026-09-10. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Status

Accepted (2026-09-10 — user directive)

## Decision Type

Architecture

## Context

GHN-B (TASK-MZ2TCB) đã ship mapping pipeline tự động: exact normalized-name match trong parent scope
+ curated alias → ghi thẳng row APPROVED. Về vận hành, auto-mapping theo tên rủi ro dữ liệu
(1:1 về tên không đảm bảo đúng tuyến sau merge/sáp nhập hành chính) và khó kiểm soát khi GHN đổi
master data. Launchpad cần mapping "reviewed by human/AI offline, versioned, reproducible" thay vì
"generated at runtime".

## Decision (Decision)

1. **Không cơ chế nào tự tạo APPROVED mapping**: exact name, normalized name, `extension_names`,
   fuzzy, curated alias đều KHÔNG được tự ghi production mapping. Các cơ chế này chỉ còn là
   tooling đề xuất ứng viên / hỗ trợ audit / sinh workfile offline.
2. **Lifecycle chuẩn**:
   `GHN API → sync/export deterministic CSV → offline AI/manual mapping (đối chiếu canonical
   Secomm_VietNamAddress source CSVs) → reviewed mapping CSV → import → audit → runtime resolver`.
3. **Bootstrap first-install**: `Secomm_Ghn` ship bộ dataset reviewed/versioned trong source
   (`data/master/*.csv`, `data/mapping/*.csv`, `data/manifest.json`) — cài mới KHÔNG cần gọi GHN
   API để có mapping chuẩn. Bootstrap deterministic, repeatable mọi môi trường.
4. **Portable identity**: Secomm `scheme_code + unit_code`; GHN `scheme_code + provider_key/
   provider_id/provider_code`. CẤM entity_id/region_id/city_id trong file mapping/master.
   DB entity ids chỉ được resolve bên trong importer/runtime persistence.
5. **Tách bạch**: sync master data (API) và activation mapping dataset là 2 operation độc lập;
   provider sync không được ghi đè quyết định mapping đã review; provider unit mất khỏi snapshot
   mới → DISABLED (không DELETE).
6. **Import fail-loud + UPSERT**: import chỉ activate row `APPROVED`; các trạng thái
   REVIEW_REQUIRED/UNRESOLVED/AMBIGUOUS chỉ sống trong file offline. Validate structure/scheme
   pair/existence/duplicate/dangling/conflict; malformed → fail loud; không truncate.
7. **Manifest** nhẹ (dataset_version, generated_at, scheme, counts, checksum, source environment)
   cho reproducibility/auditability — không xây versioning platform.
8. **Audit** mở rộng: mapped/unmapped/ambiguous/invalid/stale/disabled/duplicate/dangling/coverage%
   + cờ production-ready (target: unresolved=ambiguous=invalid=duplicate=dangling=0) — không đạt
   thì dataset không được coi là production-ready.
9. **Runtime resolver giữ nguyên nguyên tắc**: stable code → APPROVED mapping → stable GHN identity;
   PRE_2025 trả đủ legacy triple; 2025 trả verbatim names; fail closed; không fuzzy/AI/guess.

## Consequences

- (+) Mapping production có human/AI review + versioned + checksum — audit được, rollback được,
  reproducible mọi môi trường; first-install không phụ thuộc GHN API.
- (+) Auto-mapping bugs không thể lan vào production runtime.
- (−) Chi phí vận hành ban đầu: phải chạy export + offline review + import trước khi có mapping
  hữu ích; bundled dataset phải được refresh theo release khi GHN đổi master data.
- (−) `MappingGenerator` (auto-write APPROVED) bị thu hồi → thay bằng `MappingSuggester`
  (workfile, classification A của §15 legacy-matcher review).

## Verification

* Không còn đường code nào ghi row APPROVED ngoài `MappingImporter` (grep `rebuildScheme|upsert`
  trên AddressMapping chỉ còn importer).
* Bundled `data/` + `manifest.json` tồn tại; bootstrap command chạy được không cần GHN API.
* Audit có dangling/duplicate/production_ready; resolver test chứng minh chỉ dùng APPROVED import.
