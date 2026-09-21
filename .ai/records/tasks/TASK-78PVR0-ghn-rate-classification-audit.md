---
id: TASK-78PVR0
type: task
title: 'GHN RATE outcome classification audit — verify mapping-removal quote behavior (QC S2 follow-up), classification matrix + resolver fallback verdict'
project_code: SLP
parent: {type: feature, id: FEAT-GGTWXW}
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: embedded-mini-spec
risk: low
status: in_progress
priority: medium
decision_assessment: none-material
decisions: []
components: [CMP-SHIPPING]
source_areas: [app/code/Secomm/Ghn/]
changes_project_state: false
created: 2026-09-16
updated: 2026-09-16
owner: [dev]
related_tickets: [TASK-5XQXZK]
---

# [SLP][FEAT-GGTWXW][TASK-78PVR0] GHN RATE outcome classification audit

## Embedded Mini-Spec

### Goal
Traced chính xác GHN rate-resolution path khiến quote vẫn SUCCESS sau khi QC S2 xóa mapping
rows; chốt matrix terminal outcomes; verdict KEEP/FIX/DEPRECATE resolver fallback.

### Scope
In: GhnMappingResolver/AddressMapping/GhnRateCalculator/GhnApiClient classification. Out:
ShippingCore/Launchpad architecture changes, bridge code.

### Approach
Code trace + DB evidence + classification matrix + (nếu defect) surgical fix + tests.

### Constraints
Fail-closed resolver semantics (SPEC-FEAT-FQWEQ3 §7); không đổi architecture trừ khi có
contract defect thật; GHN-only.

### Rules
Report + matrix + recommendation; tests cho bất kỳ fix nào; evidence ghi `.ai/evidence/TASK-78PVR0/`.

---

# Audit Report (2026-09-16)

## 1. Traced path — KHÔNG có internal fallback masking

QC S2 đã xóa mapping row **sai chiều scheme**: `secomm_ghn_address_mapping` chứa mapping
**dual-scheme** (DEC-FEATFQWEQ3-001: sync cả 2 scheme). RATE đọc đúng row scheme
`VN_ADMIN_PRE_2025` (vd `VNAP25-F34178AB9E`, entity 13629, APPROVED) — row đó không bị xóa
(S2 chỉ xóa row key-2025 `VNA25-23A715C820`, là key của CREATE path).

Exact path: destCity text → Stage-1 handoff (canonical 2025 → PRE unique) →
`GhnRateCalculator::quote()` → `GhnMappingResolver::resolve(VN_ADMIN_PRE_2025, PRE_code)` →
`AddressMapping::findApproved(PRE-key row)` → unit ACTIVE → complete legacy triple → GHN fee
API. **Verdict: INTENTIONAL current behavior (dual-scheme mapping design) — không phải legacy
compat, không phải mapping-failure masking.**

## 2. Fail-closed audit (objective 5) — PASS
`GhnMappingResolver::load()` throw `GhnMappingNotFoundException` cho: no approved mapping /
unit missing / unit disabled / incomplete legacy triple. Không name-guess, không auto-pick
candidate, không fake success. Tests sẵn: testMissingMappingThrows,
testDisabledUnitThrowsAndIsNotCached, testPre2025WardResolvesCompleteLegacyTriple,
testNewModelWardResolvesVerbatimNames (GhnMappingResolverTest).

## 3. Terminal GHN RATE outcome matrix (GhnRateCalculator + Carrier\Ghn)

| Outcome | Reason | Condition |
|---|---|---|
| UNAVAILABLE | UNSUPPORTED_DESTINATION | dest country ≠ VN |
| UNAVAILABLE | INVALID_CONFIGURATION | base currency ≠ VND; unusable weight unit config |
| UNAVAILABLE | SERVICE_UNAVAILABLE | parcel not deliverable (no API call) |
| UNAVAILABLE | GHN_HEAVY_PARCEL_UNSUPPORTED (carrier-owned) | ≥20kg type-5 at RATE |
| UNAVAILABLE | (handoff reason) ?? UNSUPPORTED_DESTINATION | handoff not applicable |
| UNAVAILABLE | CANONICAL_AMBIGUOUS | Stage-1: >1 PRE candidates (eligible fallback) |
| UNAVAILABLE | CANONICAL_UNMAPPED | Stage-1: 0 PRE candidates (not eligible) |
| UNAVAILABLE | CANONICAL_UNRESOLVED | Stage-1 unresolved, không phân biệt được |
| UNAVAILABLE | PROVIDER_MAPPING_MISSING | Stage-2: no APPROVED mapping / unit disabled / incomplete triple |
| UNAVAILABLE | SERVICE_UNAVAILABLE | provider auth / invalid address / invalid request / rate unavailable |
| TECHNICAL_FAILURE | TECHNICAL_ERROR | curl timeout / no response; HTTP 5xx; 429; empty/unexpected response envelope (ProviderRemote); carrier catch-all Throwable |

TECHNICAL conditions verified trong `GhnApiClient` (timeout 115/151, remote 5xx/empty/envelope
120/162/171/178) — đủ 4 lớp timeout/connection/5xx/malformed.

## 4. Surgical fix required: NONE
Không có contract defect. Verdict: **KEEP** (dual-scheme mapping + fail-closed resolver).

## 5. Follow-up evidence gap (không block)
S2-E2E redo 2 lần vẫn chưa quan sát được lane "1 member ineligible + 1 member TECHNICAL →
fallback" qua GHTK-refused (GHTK record path cần trace log-level: nghi ngờ dest-resolve gate
hoặc rate-cache tương tác). Lane này đã COVER đầy đủ bởi unit matrix
(FallbackCoordinatorTest + SafeDegradationEligibilityPolicyTest). Việc còn lại: trace GHTK
record flow bằng log, xác nhận TECHNICAL thật khi API refused — task nhỏ kế tiếp trong epic,
không chạm bridge/ShippingCore.
