# Evidence — TASK-7AJ3K8 (GHTK alignment lên target architecture ShippingCore)

Date: 2026-09-10 · Branch: development (uncommitted — CLAUDE.md: no commit/push)

## Gate 1 — Unit tests (phpunit-secomm.xml)

```
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "Ghtk|ShippingCore|VietNamAddress"
OK (issues = 6 pre-existing PHPUnit deprecations, unrelated)
Tests: 508, Assertions: 1378
```
Baseline trước task: 448 tests / 1240 assertions (cùng filter) → +60 tests / +138 assertions.

Full suite: 1135 tests — 7 errors TẤT CẢ ở `Secomm_Tracking` (EventNormalizerTest — module GA4,
uncommitted work của FEAT-31X6N2, KHÔNG thuộc scope). Verified pre-existing: fail cả trên cây
HEAD (git stash -u round-trip, errors y nguyên).

## Gate 2 — Spec-first validator

```
$ php .ai/bin/project-ai-validate --check-specs --check-records
exit=0 (VALID) — 0 FAIL, 0 WARN mới
```

## Gate 3 — AC-1 grep (0 directory/locale knowledge trong Ghtk production code)

```
$ grep -rn "directory_region_city\|directory_country_region\|directory_region_city_name\|vi_VN" app/code/Secomm/Ghtk --include="*.php" | grep -v Test/
→ 3 hits, TẤT CẢ là docblock/comment (GhtkAddress VO note, DirectoryReferenceGuard note,
  GhtkAddressMapInterface schema note) — 0 code reference.
```

## Gate 4 — Reverse dependency (ShippingCore KHÔNG import carrier)

```
$ grep -rn "use Secomm\\Ghtk|GiaoHangNhanh|Ahamove|GhnAddressMapper" app/code/Secomm/ShippingCore --include="*.php" | grep -v Test/
→ CLEAN
```

## Gate 5 — Behavior preservation checks

- Legacy pickup config: `GhtkOriginProvider` KHÔNG đổi (git diff: chỉ class GhtkDestinationResolver
  type trong 2 consumer + PickupAddressResolver internals); `GhtkOriginProviderTest` pass nguyên bản.
- Create-order single attempt: `GhtkApiClientTest::testSubmitOrderIsASingleAttemptNeverRetried` (1 call).
- Fee GET retry: `testGetFeeRetriesServerErrorUpToRetryMax` (2 calls với retry_max=1) +
  `testGetFeeNeverRetries{ClientErrors,RateLimitOrInvalidJson}`.
- Webhook path: không đổi (WebhookPayloadParser + processor) — test cũ pass nguyên bản.
- AMBIGUOUS không pick: `GhtkDestinationResolverTest::testAmbiguousCanonicalOutcomeNeverSendsARequest`
  + manager `testIdentityMissingWithContextCandidatesSurfacesAmbiguous`.

## Files

Xem `git status --porcelain | grep -E "Ghtk|ShippingCore|VietNamAddress"` — Production:
3 class GHTK xoá, 4 class GHTK mới, ShippingCore +9 class/contract, VietNamAddress +3 class.
Governance: records/tasks/TASK-7AJ3K8.md, specs/SPEC-TASK-7AJ3K8-..., records/decisions/DEC-TASK7AJ3K8-001.md,
plans/TASK-7AJ3K8-implementation-plan.md, DECISIONS.md index line.


---

# r1 evidence (2026-09-10) — TEXT_NATIVE correction (DEC-TASK7AJ3K8-002)

## Gate 1 — Unit tests (phpunit-secomm.xml, filter Ghtk|ShippingCore|VietNamAddress)

```
OK (6 pre-existing PHPUnit deprecations, unrelated)
Tests: 511, Assertions: 1400
```
r0: 508/1378 → r1: 511/1400. Full suite unchanged: 7 errors TẤT CẢ ở Secomm_Tracking (pre-existing,
verified r0 trên cây HEAD).

## Gate 2 — §15 grep (obsolete full-mapping assumptions)

```
$ grep -rni "mapping miss|exact mapping|alias map|alias hit|carrier directory|full mapping" app/code/Secomm/Ghtk --include="*.php" | grep -v Test/ | grep -vi override
→ CLEAN
```

## Gate 3 — Validator

```
$ php .ai/bin/project-ai-validate --check-specs --check-records  → exit 0 (VALID)
```

## Gate 4 — Same-adapter consistency (§16 Consistency)

```
grep "GhtkAddressAdapter" app/code/Secomm/Ghtk/Model/{Carrier/Ghtk.php,OrderSubmit/OrderSubmitService.php,Address/PickupAddressResolver.php}
→ cả 3 consumer type-hint CÙNG GhtkAddressAdapter; fee + create-order + pickup name-path cùng resolve() semantics.
```

## E2E sandbox — PENDING (không chặn code-complete)

Tại thời điểm thực hiện (2026-09-10):
- `dev.giaohangtietkiem.vn` (GHTK dev sandbox) — TIMEOUT từ dev env (curl 8s, 000).
- `services.giaohangtietkiem.vn` (production) — reachable nhưng KHÔNG chạy test: không có
  token merchant khả dụng (DB local mysql84:3307 connection refused — container down; không
  đọc token từ bất kỳ nguồn nào khác) và không bắn request thử nghiệm vào production API.

Runbook để hoàn tất §17 (khi env available):
```
# 1. Bật DB + xác nhận token test trong config (không dùng token production)
php bin/magento setup:upgrade            # r1 schema re-key bắt buộc trước
mysql ... -e "SELECT COUNT(*) FROM secomm_ghtk_address_map;"   # bảng override rỗng

# 2. Fee-only probe (safe read, không create order) — cho mỗi representative address:
curl -s "https://dev.giaohangtietkiem.vn/services/shipment/fee?province=<name_vi>&ward=<name_vi>&weight=1000&transport=road" -H "Token: $GHTK_DEV_TOKEN"
# Địa chỉ đại diện: Hà Nội / TP.HCM / Đà Nẵng; 1 ward post-2025; 1 xã;
# 1 ward name trùng giữa 2 tỉnh; 1 address không district.

# 3. Kỳ vọng: canonical name_vi → success:true với fee hợp lệ.
#    Unit nào fail → thêm ĐÚNG unit đó vào secomm_ghtk_address_map (override row) rồi re-test.
# 4. Create order chỉ chạy trên sandbox với partner_order_id test, KHÔNG production.
```
Trạng thái AC #7: PENDING — architecture chọn Option B (override-only) chính vì chưa có
evidence để chọn Option A (remove table).

---

# AI Pre-review r1 (2026-09-10) — findings + self-fix

Review theo skill review-code (AGENTS §7.2/§8.3/§12). Findings self-fixed trong run:

1. **[WARN→fixed]** `RetryPolicy::singleAttempt()` 0 production consumer (D10 unused abstraction)
   → `GhtkApiClient::submitOrder` giờ chạy QUA `RetryExecutor` + `RetryPolicy::singleAttempt()`
   — rule create-order-single-attempt là explicit policy (behavior identical, test cũ pass).
2. **[WARN→fixed]** Admin UI còn wording "Mapping" (Upload/Index/upload.phtml/menu.xml title/acl)
   — sai semantics r1 (override table) → đổi "Address Overrides"; ACL id + form field name giữ
   nguyên (không vỡ admin roles/URL).
3. **[NOTE→fixed]** `ValidatorTest::testRejectsUnknownScheme` assertion convoluted →
   `assertStringContainsString`.
4. **[NOTE→fixed]** `Importer` log ternary thừa → `count($removedIds)`.

Post-fix: scoped suite 511/511 pass; validator exit 0.
