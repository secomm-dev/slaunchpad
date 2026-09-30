---
id: DEC-TASK3ZEB25-001
title: 'PICK_PRIMARY candidate selection semantics FROZEN: 0 candidate fail-closed; 1 candidate select sole (không inspect is_primary); >1 + đúng 1 primary → primary; >1 + 0/multiple primary → deterministic first (code ASC) + diagnostic MULTIPLE_PRIMARY_CANDIDATES (không fail flow)'
status: accepted             # TL directive 2026-09-25 (30 section) + plan approval
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-25
created: 2026-09-25
last_verified: 2026-09-25
verified_against_commit:
supersedes: [DEC-TASK5YGKME-001]
superseded_by:
work_items: [TASK-3ZEB25]
---

# Decision Record: PICK_PRIMARY Candidate Selection Semantics — FROZEN

## Status

Accepted (2026-09-25 — TL directive "Audit & Align PICK_PRIMARY Candidate Selection
Semantics", 30 section; plan approval cùng ngày).

## Decision Type

Architecture — selection semantics freeze trong Secomm_VietNamAddress selector + policy
application (ShippingCore handoff, code không đổi). v10 freeze amended có chủ đích, scoped.

## Decisions (frozen)

1. **0 candidate → FAIL CLOSED** (`NOT_APPLICABLE` → handoff `CANONICAL_UNRESOLVED`) —
   không fabricate PRE code.
2. **1 candidate → SELECT sole NGAY**, KHÔNG inspect `is_primary` (fast path trước DB
   query) — reason `SOLE_CANDIDATE`.
3. **>1 + đúng 1 primary → SELECT primary** (`CURATED_PRIMARY`) — preferred curated path.
4. **>1 + 0 primary → SELECT deterministic first** (`DETERMINISTIC_FIRST_NO_PRIMARY`) —
   business approximation cho merged administrative wards, no fail-close.
5. **>1 + multiple primary → SELECT deterministic first**
   (`DETERMINISTIC_FIRST_MULTIPLE_PRIMARY`) + diagnostic `MULTIPLE_PRIMARY_CANDIDATES`
   (logger.warning, safe payload: scheme/codes/counts/policy — KHÔNG PII, KHÔNG fail flow).
6. **Deterministic ordering = candidate code sort ASC ("source_code ASC")** — enforced
   trong selector (một chỗ duy nhất, §7); KHÔNG raw DB/insertion order, KHÔNG
   `mapping_id` làm business priority.
7. **Provider data không influence selection (§8)** — GHN mapping không tham gia pick;
   selected candidate thiếu GHN mapping → `PROVIDER_MAPPING_MISSING`, KHÔNG retry
   candidate khác (§19). Không fuzzy (§20).
8. **Chỉ PICK_PRIMARY có representative selection (§10)** — STRICT (unavailable) và
   FALLBACK (no selection, fallback-eligible ambiguity) giữ nguyên.
9. `STATUS_NO_DESIGNATED_PRIMARY` / `STATUS_MULTIPLE_PRIMARY` constants giữ BC; default
   implementation không còn trả về; handoff vẫn fail-closed defensively với non-SELECTED.
10. Provenance: `selectionReason` trên selection VO (4 reason constants); snapshot
    `CanonicalResolutionSnapshot` đã có fields nhưng KHÔNG wire mới (§17 — no new audit
    subsystem).

## Supersede

`DEC-TASK5YGKME-001` — supersede ở 2 điểm: (a) MULTIPLE_PRIMARY fail-closed →
deterministic first + diagnostic; (b) count=1 `NOT_APPLICABLE` → `SOLE_CANDIDATE`.
Điểm còn lại của DEC-TASK5YGKME-001 (auto-first cho 0-primary) được giữ và nâng thành
frozen rule; DEC-FEATYA2C0W-006 amendment tiếp tục bị supersede (theo TASK-5YGKME).

## Rationale

- Directive TL 2026-09-25: business rule rõ ràng — curation defect (multiple primary)
  không được phép chặn rate flow; selection phải deterministic, order-independent.
- Verified dataset: Phước Long `VNA25-6561267821` → 3 PRE candidates, 0 primary —
  đại diện cho ~hầu hết ward merger.

## Consequences

- Curation lỗi (2+ is_primary=1) chỉ visible qua log — cần monitor
  `MULTIPLE_PRIMARY_CANDIDATES`; dọn data bằng tay khi phát hiện (§26: không mass-set).
- GHTK (shared chain) hưởng cùng semantics.

## Verification

- Selector test matrix 9 tests (§13 đủ case + §14 order independence 2 nhóm + §15
  fast-path no-DB-query + diagnostic payload).
- Handoff integration: auto-first → resolved, carrier nhận đúng 1 code, không leak (§22).
- STRICT/FALLBACK regressions giữ nguyên green (§23/§24).
- Suites VietNamAddress/ShippingCore/Ghn green + compile + validator (§28).
