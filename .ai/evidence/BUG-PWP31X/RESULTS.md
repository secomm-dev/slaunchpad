# BUG-PWP31X (SLP-152) — Verification Results

**Date**: 2026-09-14 · **Mode**: C · **Change set**: 2 dòng value trong `vi_VN.csv` + 2 dòng trong `en_US.csv` (theme dict `Secomm/launchpad/i18n/`), 0 dòng PHP/template

## Fix

| Key | vi value cũ → mới | en value cũ → mới |
|---|---|---|
| `City` | `Thành phố` → **`Phường/Xã`** | `City` → **`Ward/Commune`** |
| `Please select a region, state or province.` | `Vui lòng chọn vùng, tiểu bang hoặc tỉnh.` → **`Vui lòng chọn Tỉnh/Thành phố.`** | identity → **`Please select a Province/City.`** |

Wording mirror `Secomm_VietNamAddress/i18n/` (module dict đã chuẩn hóa sau TASK-6MKF0V — data 2 cấp tỉnh→phường/xã) — **chờ TL duyệt** (chỉnh = 1 dòng CSV/key).

## Verify: live render + cascade e2e — 12/12 + 10/10 PASS

### Phase 1 — curl render (đăng nhập thật `qc-slp128b4@test.local`, form `customer/address/new`)

| # | Check | Kết quả |
|---|---|---|
| 1 | vi/label dropdown cấp 2 = "Phường/Xã" (`>Phường/Xã</span>`) | PASS |
| 2 | vi/region placeholder = "Vui lòng chọn Tỉnh/Thành phố." | PASS |
| 3 | vi/placeholder cũ "vùng, tiểu bang hoặc tỉnh" GONE | PASS |
| 4 | vi/placeholder phường/xã = "Vui lòng chọn Phường/Xã" (module template) | PASS |
| 5 | vi/label standalone "Thành phố" GONE | PASS |
| 6 | en/label = "Ward/Commune" | PASS |
| 7 | en/region placeholder = "Please select a Province/City." | PASS |
| 8 | en/placeholder cũ GONE | PASS |
| 9 | en store switch qua `?___store=launchpad_en` (`<html lang="en">`) | PASS |

Raw HTML: `.ai/runtime/evidence/BUG-PWP31X/addr-new-{vi,en}.html` (gitignored). Script: `verify-live.sh`.

### Phase 2 — Playwright cascade e2e (`e2e-edit-save.js`, `ADDR_ID=8`) — 12/12 PASS

```
PASS vi/city label = Phường/Xã
PASS vi/region placeholder
PASS vi/ward placeholder
PASS vi/edit prefill: region selected — region_id=1157
PASS vi/edit prefill: ward selected — ward=An Khanh
PASS vi/cascade: đổi tỉnh reload phường/xã
PASS vi/save: success message sau redirect — .../customer/address/index/ — Bạn đã lưu địa chỉ.
PASS vi/edit round-trip: ward mới prefill đúng — Ba Vi (expect Ba Vi)
PASS vi/ward label giữ dấu hiển thị — label=Ba Vì
PASS en/city label = Ward/Commune
PASS en/region placeholder
PASS console errors
```

Covers AC-004 (cascade end-to-end per §7.1): GraphQL `GetListCity` load phường/xã theo tỉnh, edit-mode prefill (BUG-25XDH4 behavior giữ nguyên), save + round-trip.

## Verify: env as-found (sau restore `address/general/enable`)

- `GetListCity` = 0 (module template OFF — như trước khi verify), label "Phường/Xã" + region placeholder mới **vẫn render** (template Hyvä default dùng cùng dict) — fix có hiệu lực ở CẢ hai trạng thái flag.
- Checkout OSC không lộ: luma-scope dùng `Launchpad_MageplazaTranslate` (LL-0011), theme dict dead ở đó.

## Cross-page sweep (cùng lớp lỗi — fix đi kèm value key)

`__('City')` + `__('Please select a region, state or province.')` trong Hyvä scope: `address/grid.phtml` (header cột sổ địa chỉ), `form/register.phtml`, `php-cart/shipping.phtml` (cart estimate), template AddressDropdown — **tất cả là ngữ cảnh địa chỉ VN** → wording mới đúng ở mọi vị trí. Checkout OSC + admin không bị ảnh hưởng.

## Findings flag TL (pre-existing, ngoài scope)

1. **Sổ địa chỉ render rỗng** dù `customer_address_entity` có row: `getAdditionalAddresses()` trả `[]` → "Sổ địa chỉ của bạn chưa có mục nào khác." — header cột "Phường/Xã" (fix của ticket này) chỉ render khi có address. Cần điều tra riêng (Grid block collection / plugin) nếu muốn header hiện thực tế.
2. **Register form không render region select** (local) — usage key ở `form/register.phtml` chỉ theoretical.
3. **Test data**: `customer_address_entity` id=8 cho `qc-slp128b4@test.local` (password `QcPass123!`, từ BUG-HE2NGV) — giữ lại cho QC; duplicate id=9 đã DELETE. Cleanup SQL: `DELETE FROM customer_address_entity WHERE parent_id=8;` sau QC.
4. **ENV restored as-found**: `address/general/enable` — row KHÔNG tồn tại trước phiên → đã DELETE sau verify (local render template Hyvä default; demo có flag=1 — state của screenshot ticket). Local reproduce: `bin/magento config:set address/general/enable 1` + flush.
5. **Concurrent session**: `vi_VN.csv` dòng 603 có working-tree change không thuộc change set này (`"Shop By","Mua theo"→"Bộ lọc"` — session song song) → commit tách hunk.

## Screenshots (`.ai/evidence/BUG-PWP31X/`)

- `form-vi-new.png` — form thêm địa chỉ vi: label "Phường/Xã", placeholders đúng
- `form-vi-edit.png` — form edit vi sau khi đổi tỉnh + chọn phường/xã
- `after-save-vi.png` — sổ địa chỉ sau save, message "Bạn đã lưu địa chỉ."
- `form-en.png` — form en: "Ward/Commune" + "Please select a Province/City."
