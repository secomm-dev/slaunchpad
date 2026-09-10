---
id: DEC-FEATHEHJQ4-001
legacy_ids: [DEC-010]
title: VNPAY IPN signature verification scheme
status: proposed
created: 2026-07-21
last_verified: 2026-07-21
verified_against_commit:
supersedes: []
superseded_by:
work_items: [FEAT-HEHJQ4]
---

# Decision Record: VNPAY IPN signature verification scheme

## Context

FEAT-HEHJQ4 (Mode A) hardens the VNPAY IPN callback. Current `Ipn::execute()` compares `$secureHash == $vnp_SecureHash` (non-strict, timing-unsafe) and the exact hash-data construction may not match the current VNPAY HMAC-SHA512 spec. Security/signature category.

## Decision (open — SA/TL)

Adopt **constant-time** comparison (`hash_equals`) and align the hash-data construction (sorted params, `urlencode` vs `rawurlencode`, `vnp_SecureHashType`/`vnp_SecureHash` exclusion, ordering) to the **current VNPAY spec** (confirm exact contract with SA). Reject on mismatch → `RspCode '97'`; never disclose which field failed.

## Alternatives

- Keep `==` (timing attack risk — rejected).
- `strcmp`/`memcmp` (still timing-leaky — rejected).

## Consequences

Prevents timing-attack signature forgery; correctness depends on exact VNPAY spec match (must be confirmed). Adds a hard dependency on the documented VNPAY signature contract.

## Affected components

`Secomm_VNPAY/Controller/Order/Ipn.php` (signature block); possibly a shared verify helper.

## Related records

- Feature: FEAT-HEHJQ4 (AC-001)
- DECISIONS.md index: DEC-FEATHEHJQ4-001
