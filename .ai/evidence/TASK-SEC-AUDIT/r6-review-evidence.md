# Evidence — r6 adversarial review + success commit point (2026-09-22)

## Task 1/2 — Success path & commit-point fix

OLD (defect): execute → recordDecision(SUCCESS) → adjuster → buildResult → return.
Exception sau record (adjuster/factory) → catch-all record UNEXPECTED/NONE → success-terminal
merge GIỮ stale SUCCESS → collector nói success, customer không có rate, fallback suppress sai.

NEW (frozen): execute → adjust (1 lần) → buildResult → recordDecision(SUCCESS, NONE) → return.
Non-success → record trước hide (exactly once). Unexpected throw → catch-all record
UNEXPECTED_RUNTIME_FAILURE/NONE (không stale SUCCESS). Invariant: transported SUCCESS IFF
usable Magento method đã build cho execution đó.

## Task 3 — post-success failure tests (GhnTest, 23/68)

1. happy: SUCCESS transported + Result instance ✓
2. adjuster throw → UNEXPECTED/NONE, không SUCCESS ✓
3. method-factory throw → UNEXPECTED/NONE ✓
4. result-factory throw → UNEXPECTED/NONE ✓
5. next collection zero leak (state-flag stub, run2 SUCCESS) ✓

## Task 4 — collector consistency

SUCCESS-terminal merge chỉ meaning khi SUCCESS qua commit point mới — ordering fix loại
same-invocation inconsistency. Multi-invocation duplicate (carrier gọi 2 lần/thuật collection)
vẫn theo frozen merge rules. Không đổi duplicate semantics.

## Task 5 — P1 FEAT-QA23PZ findings

- BLOCKER (FIXED r5/r6): success record trước commit point (mục trên).
- HIGH (đã có từ stream, verified): FALLBACK_ONLY skip origin/handoff/contributor/API ✓;
  eligibility trước mode ✓; single resolution (quoteWithHandoff không resolve lại) ✓.
- MEDIUM (backlog): `$this->getData("store")` store-scope null→default-store hợp lệ nhưng
  cần note multi-store runtime verify.
- LOW: `hide()` Error-object path theo showmethod — có tests; FALSE POSITIVE: standalone
  calculator path không chạy song song (chỉ qua contributor).

## Task 6 — P2 findings

- BLOCKER (FIXED r5): duplicate semantics union-cross-execution → first-wins-whole + full
  identity (r4).
- HIGH (FIXED): transport presence thuộc identity; explicit NONE không legacy re-judge.
- MEDIUM: transport-version field chưa có (additive note — chưa cần vì chỉ 1 version).
- LOW: telemetry debug-level, không PII ✓.

## Task 7 — P3a/P3b/P3c

- P3a: SQL bound params ✓; ACL test ✓; POST+form_key ✓; Guard per-value ✓; importer trusted ✓;
  master-switch store/cache-scoped ✓ (cache-key fix); factories = flagged decision (r2 evidence).
- P3b: keyset migration + old-value guard + idempotent convergence ✓ (manifest 41 keys,
  disjoint check); checksum sha256 ✓; no swap/purge ✓.
- P3c: first-save qua resource-seam persisted id ✓; concurrent-safe (request-scoped) ✓;
  Option B partial-save atomic settings transaction ✓; FK CASCADE single owner (plugin delete
  removed) ✓; inactive-carrier fallback ✓; C3 quote-based group ✓.

## Task 9 — regression (combined stack)

AD 96/223 · VN 192/776 · SC 424/1113 · Ghn 373/210,947 · Ghtk 237/585 · MT 98/190 —
0 fail/error. compile OK. validator exit 0. Commit plan: `commit-plan.md`.
