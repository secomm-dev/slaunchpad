#!/usr/bin/env bash
# =============================================================================
# TASK-6TNKDH — Phase GHN-B.2 stage 2 runner (dataset authoring, mechanical).
#
# Prerequisites (human/owner):
#   1. GHN sandbox credentials configured in admin:
#      Stores → Configuration → Secomm → GHN Shipping (Secomm_Ghn) → General
#      (environment = sandbox, API Token, ShopId) — never via chat/AI.
#   2. TL approval of the offline-review approval rule (exact-name 1:1 → APPROVED).
#
# What this script does (deterministic, in order):
#   1. Export both GHN schemes with the real API via the module exporter.
#   2. Generate candidate workfiles + review artifacts (offline, from the export).
#   3. Stage everything under var/secomm_ghn/authoring/ for the offline review pass.
#
# It does NOT flip anything to APPROVED and does NOT touch the bundled data/ —
# those are explicit human/reviewed steps after this script's output is reviewed.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/../.."

STAGE="var/secomm_ghn/authoring"
EXPORT="$STAGE/export"
SUGGEST="$STAGE/suggest"
mkdir -p "$SUGGEST"

echo "== [1/3] Export GHN master datasets (real API, sandbox credentials from admin config) =="
DATASET_VERSION="1.0.0-sandbox-$(date +%Y%m%d)"
bin/magento secomm:ghn:address:export --dir="$EXPORT" --dataset-version="$DATASET_VERSION"

echo "== [2/3] Candidate workfiles + review artifacts (offline, hierarchy-aware) =="
for SCHEME in VN_ADMIN_2025 VN_ADMIN_PRE_2025; do
  bin/magento secomm:ghn:address:suggest \
    --scheme="$SCHEME" \
    --export-dir="$EXPORT" \
    --output="$SUGGEST/${SCHEME}_workfile.csv" \
    --review-output="$SUGGEST/GHN_ADDRESS_MAPPING_REVIEW_${SCHEME}.csv"
done

echo "== [3/3] Staged for offline review =="
ls -la "$EXPORT/master" "$SUGGEST"

cat <<'NEXT'

NEXT STEPS (human + AI-assisted review, offline):
  1. Review var/secomm_ghn/authoring/suggest/GHN_ADDRESS_MAPPING_REVIEW_*.csv.
  2. Apply the TL-approved approval rule (exact-name 1:1 in-scope → APPROVED with
     provenance note); keep everything else REVIEW_REQUIRED/UNRESOLVED.
  3. Copy approved workfiles to data/mapping/*.csv, master CSVs to data/master/,
     bump data/manifest.json (dataset_version, counts, sha256).
  4. bin/magento secomm:ghn:address:import && bin/magento secomm:ghn:address:audit
     (gate: production_ready=true, or record unresolved honestly).
  5. Resolver smoke (2025 verbatim names / PRE_2025 triple) + flip the integrity
     test skip marker (TASK-6TNKDH plan step 7).
NEXT
