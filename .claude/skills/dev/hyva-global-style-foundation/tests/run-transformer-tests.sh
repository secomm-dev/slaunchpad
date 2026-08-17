#!/usr/bin/env bash
# hyva-global-style-foundation — transformer contract tests.
# Run from anywhere: paths resolve relative to this script.
# Requires: node >= 18. Exits 0 only when every test passes.
set -u
SKILL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FIX="$SKILL_DIR/tests/fixtures"
T="node $SKILL_DIR/scripts/token-transformer.mjs"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
PASS=0; FAIL=0
ok() { echo "  PASS  $1"; PASS=$((PASS+1)); }
no() { echo "  FAIL  $1"; FAIL=$((FAIL+1)); }
has() { grep -q "$1" "$2"; }
jget() { python3 -c "import json,sys;r=json.load(open(sys.argv[1]));v=r$2;print(v if not isinstance(v,(list,dict)) else json.dumps(v))" "$1" 2>/dev/null; }

echo "== T1: Test A regression — originating layout reproduces the pre-refactor baseline (byte-identical) =="
$T --contract "$FIX/originating-structure.contract.yaml" --input "$FIX/originating-structure" --output "$TMP/a1.css" --report "$TMP/a1.json" >"$TMP/a1.out" 2>&1
[ $? -eq 0 ] && ok "exit 0" || no "expected exit 0"
cmp -s "$FIX/originating-structure.baseline.css" "$TMP/a1.css" && ok "byte-identical to pre-refactor baseline" || no "CSS differs from baseline"
[ "$(jget "$TMP/a1.json" "['generatedBytes']")" = "2640" ] && ok "generatedBytes matches baseline" || no "generatedBytes mismatch"

echo "== T2: determinism — same input + same contract = byte-identical output =="
$T --contract "$FIX/originating-structure.contract.yaml" --input "$FIX/originating-structure" --output "$TMP/a2.css" --report "$TMP/a2.json" >/dev/null 2>&1
cmp -s "$TMP/a1.css" "$TMP/a2.css" && ok "repeat run byte-identical" || no "repeat run differs"

echo "== T3: exports are immutable inputs =="
BEFORE="$(find "$FIX/originating-structure" -name '*.tokens.json' -exec md5sum {} + | sort | md5sum)"
$T --contract "$FIX/originating-structure.contract.yaml" --input "$FIX/originating-structure" --dry-run --report "$TMP/dry.json" >/dev/null 2>&1
AFTER="$(find "$FIX/originating-structure" -name '*.tokens.json' -exec md5sum {} + | sort | md5sum)"
[ "$BEFORE" = "$AFTER" ] && ok "exports unchanged after run" || no "exports were mutated"
[ ! -f "$TMP/dry.css" ] && ok "--dry-run wrote no CSS output" || no "--dry-run wrote an output file"

echo "== T4: normalization collision -> generation fails, collision in audit evidence =="
$T --contract "$FIX/collision/contract.yaml" --input "$FIX/collision" --output "$TMP/c.css" --report "$TMP/c.json" >/dev/null 2>&1
[ $? -eq 1 ] && ok "exit 1 on collision" || no "expected exit 1"
[ "$(jget "$TMP/c.json" "['normalizedCasingConflicts'][0]['key']")" = "color:light:core-primary" ] && ok "collision recorded in report" || no "collision missing from report"

echo "== T5: unapproved mode stays audit-only (not emitted to production CSS) =="
[ "$(jget "$TMP/a1.json" "['auditOnlyModes']['colorModes']")" = '["dark"]' ] && ok "dark listed as audit-only" || no "dark not listed audit-only"
! grep -q "rgb(140 179 242" "$TMP/a1.css" && ok "dark-mode value absent from CSS" || no "dark-mode value leaked into CSS"

echo "== T6: broken alias -> unresolved + reported, never silently flattened =="
$T --contract "$FIX/broken-alias/contract.yaml" --input "$FIX/broken-alias" --output "$TMP/b.css" --report "$TMP/b.json" >/dev/null 2>&1
[ $? -eq 0 ] && ok "exit 0 (warn severity)" || no "unexpected exit code"
[ "$(jget "$TMP/b.json" "['brokenAliasMetadata'][0]")" = "colors/light.tokens.json:semantic/action" ] && ok "broken alias in report" || no "broken alias not reported"
grep -q "rgb(26 51 77 / 1)" "$TMP/b.css" && ok "literal value emitted (not dangling var())" || no "alias unexpectedly dropped/flattened"
! grep -q "var(--ds-missing-primary)" "$TMP/b.css" && ok "no reference to missing alias target" || no "dangling var() emitted"

echo "== T7: different collection layout + valid contract -> works with zero transformer edits =="
$T --contract "$FIX/alternate-structure/contract.yaml" --input "$FIX/alternate-structure" --output "$TMP/x1.css" --report "$TMP/x1.json" >/dev/null 2>&1
[ $? -eq 0 ] && ok "exit 0 on alternate layout" || no "alternate layout failed"
grep -q -- "--dsrc-base-ink" "$TMP/x1.css" && ok "custom source namespace (--dsrc-*)" || no "namespace not applied"
grep -q -- "--dsrc-action-primary-bg: var(--dsrc-ink)" "$TMP/x1.css" && ok "alias preserved via location-name collection match" || no "alias not preserved"
grep -q "@media (min-width: 1024px)" "$TMP/x1.css" && ok "breakpoint from custom token (viewports/desktop)" || no "breakpoint wrong"
grep -q -- "--spacing-2: 8px" "$TMP/x1.css" && ok "custom spacing prefix (gap/)" || no "spacing prefix not applied"
grep -q -- "--radius-pill: 9999px" "$TMP/x1.css" && ok "custom radius prefix (corner/) — no radius_defaults = emit all" || no "radius handling wrong"
grep -q -- "--shadow-md: 0px 6px 16px 0px var(--dsrc-tint-md-base)" "$TMP/x1.css" && ok "composed shadow with custom roots + var() color" || no "shadow composition wrong"
grep -q -- "--dt-heading-size: 36px" "$TMP/x1.css" && ok "responsive typography via custom modes (small/wide)" || no "typography modes wrong"
! grep -q "#e8e8f0" "$TMP/x1.css" && ok "unapproved night mode absent" || no "night mode leaked"
$T --contract "$FIX/alternate-structure/contract.yaml" --input "$FIX/alternate-structure" --output "$TMP/x2.css" >/dev/null 2>&1
cmp -s "$TMP/x1.css" "$TMP/x2.css" && ok "alternate layout deterministic" || no "alternate layout not deterministic"

echo "== T8: invalid contract -> explicit failure, exit 2 =="
$T --contract "$FIX/invalid.contract.yaml" --input "$FIX/collision" --output "$TMP/never.css" >/dev/null 2>&1
[ $? -eq 2 ] && ok "invalid contract exit 2" || no "invalid contract not rejected"
[ ! -f "$TMP/never.css" ] && ok "no output written on invalid contract" || no "output written despite invalid contract"
$T --input "$FIX/collision" --output "$TMP/never2.css" >/dev/null 2>&1
[ $? -eq 2 ] && ok "missing --contract exit 2" || no "missing contract not rejected"

echo "== T9: unapproved mode (dedicated fixture) -> audit-only, never in production output =="
$T --contract "$FIX/unapproved-mode/contract.yaml" --input "$FIX/unapproved-mode" --output "$TMP/u.css" --report "$TMP/u.json" >/dev/null 2>&1
[ $? -eq 0 ] && ok "exit 0" || no "unexpected exit"
grep -q "#f2e8cf" "$TMP/u.css" && ok "approved summer value emitted" || no "approved mode missing"
! grep -q "#dfe7ef" "$TMP/u.css" && ok "unapproved winter value absent from CSS" || no "unapproved mode leaked"
[ "$(jget "$TMP/u.json" "['auditOnlyModes']['colorModes']")" = '["winter"]' ] && ok "winter recorded audit-only" || no "audit-only list wrong"

echo "== T10: generated-output manifest — deterministic, versioned, ownership-recorded =="
$T --contract "$FIX/unapproved-mode/contract.yaml" --input "$FIX/unapproved-mode" --output "$TMP/n1.css" --manifest "$TMP/n1.manifest.json" >/dev/null 2>&1
$T --contract "$FIX/unapproved-mode/contract.yaml" --input "$FIX/unapproved-mode" --output "$TMP/n1.css" --manifest "$TMP/n2.manifest.json" >/dev/null 2>&1
cmp -s "$TMP/n1.manifest.json" "$TMP/n2.manifest.json" && ok "manifest byte-identical on repeat run" || no "manifest not deterministic"
[ "$(jget "$TMP/n1.manifest.json" "['generator']['transformer_version']")" != "None" ] && ok "manifest carries transformer_version" || no "transformer_version missing"
grep -q '"source_owned"' "$TMP/n1.manifest.json" && ok "manifest records ownership classes" || no "ownership classes missing"
grep -q "$(sha256sum "$FIX/unapproved-mode/colors/summer.tokens.json" | cut -d' ' -f1)" "$TMP/n1.manifest.json" && ok "manifest carries input checksums (sha256)" || no "input checksums wrong"

echo "== T11: change-only — one token value change touches only the generated layer =="
rm -rf "$TMP/co"; cp -r "$FIX/originating-structure" "$TMP/co"
echo "/* source-owned mapping */ .btn-primary{background:var(--ds-semantic-action-bg)}" > "$TMP/source-owned-mapping.css"
$T --contract "$FIX/originating-structure.contract.yaml" --input "$TMP/co" --output "$TMP/co1.css" --manifest "$TMP/co1.manifest.json" >/dev/null 2>&1
python3 -c "import json;p='$TMP/co/typography/mobile.tokens.json';d=json.load(open(p));d['body']['size']['\$value']=17;json.dump(d,open(p,'w'),indent=2)"
$T --contract "$FIX/originating-structure.contract.yaml" --input "$TMP/co" --output "$TMP/co2.css" --manifest "$TMP/co2.manifest.json" >/dev/null 2>&1
diff "$TMP/co1.css" "$TMP/co2.css" > "$TMP/co.diff"
[ "$(grep -c '^[<>]' "$TMP/co.diff")" = "2" ] && ok "generated CSS diff = exactly one changed line" || no "unexpected diff size ($(grep -c '^[<>]' "$TMP/co.diff") lines)"
grep -q -- "--ds-type-body-size: 17px" "$TMP/co2.css" && ok "new value emitted" || no "new value missing"
[ "$(cat "$TMP/source-owned-mapping.css")" = "/* source-owned mapping */ .btn-primary{background:var(--ds-semantic-action-bg)}" ] && ok "source-owned file untouched by regeneration" || no "source-owned file modified"
CHANGED="$(python3 -c "
import json
a=json.load(open('$TMP/co1.manifest.json'))['input']['files']
b=json.load(open('$TMP/co2.manifest.json'))['input']['files']
d=[k for k in a if a[k]!=b.get(k)]
print(len(d), d[0] if len(d)==1 else '')")"
[ "$CHANGED" = "1 typography/mobile.tokens.json" ] && ok "manifest pinpoints the changed input file" || no "manifest changed-file detection wrong: $CHANGED"

echo
echo "transformer tests: PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
