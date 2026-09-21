# Evidence — TASK-6TNKDH (Phase GHN-B.2 — dataset authoring) · Giai đoạn 1 (unblocked)

Date: 2026-09-10 · Implementer: Claude (AI) · Spec: SPEC-TASK-6TNKDH

## 0. Trạng thái nhiệm vụ

Task này có 2 external inputs: **(1) GHN sandbox credentials** — KHÔNG tồn tại trong DB local
(0 rows `giaohangnhanh_setting%` / `secomm_ghn%` trong `core_config_data` — đã kiểm tra bằng
truy vấn masked); **(2) MySQL** — container tắt lúc bắt đầu, ĐÃ khởi động lại trong session
(docker WSL `service docker start` → `mysql84` healthy).

Giai đoạn 1 (mọi việc không cần credentials) hoàn tất 100%. Giai đoạn 2 (export → review →
commit dataset thật) BỊ BLOCK — theo SPEC §15 không bịa provider rows; cần owner cấu hình
credentials + duyệt approval rule (plan §Open questions).

## 1. Môi trường được phục hồi trong session (blocker ngoài phạm vi module)

| Vấn đề | Nguyên nhân | Xử lý |
|---|---|---|
| `bin/magento` chết: `Class Secomm\Ghtk\Model\Import\DirectoryReferenceGuard not found` | `generated/metadata/*.php` stale từ working tree đang dở của stream song song TASK-7AJ3K8 (class chỉ còn trong docs của họ) | Xóa `generated/metadata/*.php` → developer-mode tự regenerate → CLI sống (`Magento CLI 2.4.8-p5`) |
| `setup:upgrade` fail: `onDelete="RESTRICT"` không hợp lệ | **Defect thật trong db_schema.xml Ghn (GHN-B)** — chưa từng được Magento validate vì DB down từ lúc viết; enum chỉ có `CASCADE/SET NULL/NO ACTION` | Sửa thành `NO ACTION` (tương đương RESTRICT trong InnoDB) → `setup:upgrade` SUCCESS — schema Tier-2 giờ mới đượcMagento xác nhận thật |

## 2. Schema + DB verified thật (lần đầu)

```text
bin/magento setup:upgrade → "Upgrade completed successfully"
SHOW TABLES LIKE 'secomm_ghn%' → secomm_ghn_address_unit, secomm_ghn_address_mapping (+ legacy mapping_location)
unit rows: 0 | mapping rows: 0 (chưa có data — đúng, chưa export)
canonical: VN_ADMIN_2025 = 3.355 units, VN_ADMIN_PRE_2025 = 10.794 units (Sẵn sàng cho mapping)
```

## 3. Scoped unit tests

```text
OK — Tests: 125, Assertions: 360, Skipped: 1 (marker trung thực), Deprecations: 6
```

- Mới (TASK-6TNKDH): `BundledDatasetIntegrityTest` (4 — manifest checksum/count, master hierarchy
  portable + parent-complete, APPROVED portable/resolvable/1:1 đối chiếu canonical source CSV của
  VietNamAddress, placeholder-gate skip visible) · `MappingSuggesterTest` constructor mới.
- Tooling mới: `MappingSuggester::suggestFromExport()` (candidates từ export dir, không cần API)
  + review artifact `GHN_ADDRESS_MAPPING_REVIEW_<scheme>.csv` (§12: hierarchy path + names +
  reason CẢ HAI phía) + CLI `suggest --export-dir --review-output`.

## 4. e2e trên DB sống (plumbing, placeholder)

```text
bin/magento secomm:ghn:address:import
[master] GHN_ADMIN_2025: 0 records · affected 0 · disabled 0
[master] GHN_ADMIN_PRE_2025: 0 records · affected 0 · disabled 0
[mapping] VN_ADMIN_2025: 0 rows · APPROVED activated 0 · skipped (review 0 / unresolved 0 / ambiguous 0)
[mapping] VN_ADMIN_PRE_2025: 0 rows · APPROVED activated 0 ...
Dataset dir: app/code/Secomm/Ghn/data   ← bootstrap KHÔNG gọi GHN API ✓
bin/magento secomm:ghn:address:audit --scheme=VN_ADMIN_2025
→ fail-loud đúng: "GHN master data for GHN_ADMIN_2025 is empty — sync or import a master dataset first." ✓ (chưa có provider data)
```

## 5. Giai đoạn 2 — BLOCKED, checklist đã chuẩn sẵn (mechanical khi unblocked)

**One-command runner**: `.ai/scripts/ghn-dataset-author.sh` (syntax-checked) — export cả 2 scheme →
workfiles + review artifacts → stage tại `var/secomm_ghn/authoring/`. Script KHÔNG tự APPROVE và
KHÔNG đụng bundled `data/` — 2 bước human sau đó (review + commit) theo hướng dẫn in ra.

1. Owner cấu hình sandbox: `Stores → Configuration → Secomm → GHN Shipping` (environment=sandbox,
   Token/ShopId) — credentials không đi qua AI/chat.
2. Chạy `.ai/scripts/ghn-dataset-author.sh`.
3. Offline review theo rule TL duyệt (đề xuất trong plan §Open questions — exact-name 1:1 trong
   parent scope → APPROVED có note provenance; còn lại REVIEW_REQUIRED/UNRESOLVED).
4. Review artifacts `GHN_ADDRESS_MAPPING_REVIEW_*.csv` → audit unresolved/ambiguous.
5. Copy reviewed master+mapping+manifest (version `1.0.0`, counts, sha256) vào `data/`.
6. `import` → `audit` (production_ready HOẶC ghi nhận unresolved — không fake) → resolver smoke
   (2025 names verbatim / PRE_2025 triple) → commit `data/` + flip integrity-test skip marker.

## 5b. Giai đoạn 2 — EXPORT + CANDIDATE GENERATION ĐÃ CHẠY THẬT (2026-09-10)

**Credentials được owner cấu hình trong admin** (owner action — `carriers/secomm_ghn/*`, sandbox,
token encrypted 92 ký tự; không đi qua AI). Runner `.ai/scripts/ghn-dataset-author.sh` chạy trọn:

```text
[1/3] Export (GHN API thật — dev-online-gateway.ghn.vn):
  GHN_ADMIN_2025:      3.355 units  (34 tỉnh + 3.321 ward — khớp cấu trúc portal)
  GHN_ADMIN_PRE_2025: 12.772 units  (63 tỉnh + districts + wards; GHN giữ thêm ward cũ)
  + manifest.json (dataset_version=1.0.0-sandbox-20260910, counts, sha256)
[2/3] Suggest (offline, hierarchy-aware, từ export dir):
  VN_ADMIN_2025:     3.355 rows → REVIEW_REQUIRED 3.328 (99,2%) · AMBIGUOUS 0    · UNRESOLVED 27
  VN_ADMIN_PRE_2025: 11.357 rows → REVIEW_REQUIRED 10.888 (95,8%) · AMBIGUOUS 26 · UNRESOLVED 443
[3/3] Staged: var/secomm_ghn/authoring/{export,suggest}/
```

**Evidence tiers đã áp dụng (đề xuất — 0 row APPROVED)**: (1) exact normalized name trong parent
scope; (2) administrative-prefix normalization (`Xã/Phường/Thị trấn…` — GHN 2025 ward có prefix,
canonical tên trần; bug đầu tiên: prefix list thiếu dấu tiếng Việt — đã sửa); (3) ASCII-fold
(`Hoà Bình`/`Hòa Bình`, đ→d) — an toàn §8 vì candidate gấp đôi → AMBIGUOUS, không tự chọn.

**Review artifact** `GHN_ADDRESS_MAPPING_REVIEW_<scheme>.csv`: đủ context 2 phía (hierarchy path,
candidate names, reason, status) cho toàn bộ 14.712 canonical rows.

**Phân loại phần còn lại (chờ review pass)**: PRE ambiguous 26 = cặp `Thị trấn X` + `Xã X` cùng
tồn tại trong GHN scope (cần quyết định thật); unresolved = ward đổi tên GHN-vs-canonical, dataset
noise (canonical ward tên '3'), và GHN sandbox giữ ward ngoài canonical. KHÔNG fake — theo §15.

**Bugs gặp & sửa khi chạy thật**: `--version` option trùng Symfony Console reserved (→
`--dataset-version`); bash `set -u` prefix-assignment (tách dòng); suggester thiếu mkdir; matcher
expect DB-shape rows (file path tổng hợp entity/parent từ portable keys); **canonical DB defect
(VietNamAddress): child rows `parent_code` NULL + `getChildren('')` không khớp NULL → authoring
chuyển sang canonical SSOT = shipped CSVs qua `CanonicalCsvProvider` (34/63 regions synthesized
từ distinct region_code; defect DB ghi nhận cho stream VietNamAddress — không sửa module đó)**;
DI virtualType cần `setup:di:compile`; GET retry (3 lần, backoff) chỉ cho idempotent reads
(WSL→GHN network flake; POST vẫn zero-retry).

## 6. Config placement — chuẩn Delivery Methods (TL directive sau giai đoạn 1)

- `secomm_ghn/general/*` → **`carriers/secomm_ghn/*`** (section chuẩn Sales → Delivery Methods,
  group `secomm_ghn`; field ids giữ nguyên). Đồng bộ: `system.xml` (XSD VALID), `config.xml`
  defaults, `Model\Config` 9 constants, docblock; `acl.xml` XÓA (section owned by Magento_Sales).
- Verify: 126 tests / 363 assertions OK (thêm guard test path); `ScopeConfig::getValue` qua full
  bootstrap trả đúng defaults `0 / sandbox / 30`; path cũ không tồn tại. (`config:show` in trống
  cho giá trị chỉ có ở default — quirk của lệnh này, ScopeConfig là authoritative.)
- Credentials entry point cho giai đoạn 2 đổi thành: `Sales → Delivery Methods → GHN Shipping
  (Secomm_Ghn)`.

## 7. Separation of evidence (directive §21)

- **Provider facts**: CHƯA CÓ (blocked credentials) — sẽ nằm ở `export/` + manifest khi có.
- **Canonical data**: `Secomm_VietNamAddress` Files/CSV + DB units (3.355/10.794) — read-only.
- **AI-assisted candidates**: tooling `suggest` (normalizer/matcher) — chỉ REVIEW_REQUIRED.
- **Reviewed decisions**: chưa có — chờ rule TL + review pass giai đoạn 2.
