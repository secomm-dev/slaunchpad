# Token transform contract

The transformer (`scripts/token-transformer.mjs`) is structure-agnostic: every
directory layout, mode name, audit heuristic and output namespace is supplied by
a project-owned **contract** file (`--contract <file.yaml>` or `.json`), never
hard-coded. This document is the contract's schema reference. The transformer
validates the contract strictly and exits `2` on any unknown key or invalid
value — an invalid contract never produces partial output.

A copyable starter lives at `assets/templates/token-contract.yaml`.

## Resolution rules

- `input.source_dir` (optional) resolves **relative to the contract file's
  directory**; `--input <dir>` (CLI) resolves relative to the cwd and overrides it.
- Contract syntax: a YAML subset (nested maps by indentation, block/flow
  sequences, flow maps, quoted scalars, `#` comments — no anchors, no tabs,
  no multi-line scalars) or plain JSON. Values containing `#`, leading
  `{`/`[`, or trailing spaces must be quoted.
- `schema_version` must be `1`.

## Field reference

### `input`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `source_dir` | string | — | Token export directory (optional if `--input` is passed) |
| `file_suffix` | string | `.tokens.json` | Only files ending with this suffix are read |
| `mode_resolution` | `figma-then-filename` \| `figma-only` \| `filename-only` | `figma-then-filename` | How a file's mode name is derived: `$extensions['com.figma.modeName']` first, else filename without suffix |

### `collections` (required)

Maps a **semantic role** to a location. Roles `color`, `brand`, `font`,
`typography`, `sizes`, `effects` are emitted; any other role name is an
audit-only collection (mode-completeness checked, tokens never emitted).
Every input file must map to exactly one collection; unmatched files are
reported as `unmappedFiles` (never silently dropped).

| Role | Shape | Emitted |
|---|---|---|
| `color` | `{ path: <dir>, mode_source: filename }` — one file per color mode at `<dir>/<mode><suffix>` | `:root` `--<ns.source>-*` (aliases preserved) |
| `brand` | `{ path: <dir>, mode_source: filename }` — one file per brand theme | `:root` `--<ns.source>-*` |
| `font` | `{ path: <dir>, mode_source: filename }` — one file per font mode | audit + allowlist only (font values reach CSS via typography/brand tokens) |
| `typography` | `{ path: <dir>, modes: { base: <file>, responsive: <file> } }` (`responsive` optional) | `:root` `--<ns.typography>-*` base; `@media` overrides for responsive |
| `sizes` | `{ file: <relpath> }` | `@theme` spacing/radius/breakpoints |
| `effects` | `{ file: <relpath> }` | `:root` `--<ns.effect>-*` + composed `@theme` shadows |
| any other | `{ path: <dir>, mode_source: filename }` | audit-only |

### `generation`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `approved_modes` | `{ color, brand, font }` → string | — | The production allowlist. Required per present mode collection before generation. Unapproved modes stay `auditOnlyModes`. |
| `typography.property_keys` | string[] | `[family, size, line-height, letter-spacing, weight-base, weight-strong]` | Token path segments selected as typography properties (`path.endsWith('/'+key)`) |
| `typography.unitless_key_pattern` | regex string | `^(weight-base\|weight-strong)$` | Tested against the **last** path segment; matching numbers emit without `px` |
| `breakpoint.token` | string | — | Sizes-collection token path holding the responsive breakpoint value |
| `breakpoint.fallback` | string | — | Explicit fallback when the token is missing. A missing token with no fallback is a `fail`, never a silent default. |
| `preserve_alias_collections` | string[] | `[]` | Collections (role or location name) whose alias targets emit `var(--…)` instead of flattened values — preserves runtime theming semantics |

### `exclude`

`paths`: string list. `prefix/**` excludes a subtree; `prefix` excludes the
exact path and everything below it. Excluded tokens are dropped from emitted
CSS blocks (audit still counts them).

### `audit.rules`

Array of rule mappings. Supported kind: `suspicious_zero`
(`collection`: declared role, `path_prefix`) — flags zero-valued component
tokens awaiting a design decision. Project heuristics live here, never in the
transformer. Unknown kinds fail contract validation.

### `output`

| Key | Default | Meaning |
|---|---|---|
| `namespaces.source` | `ds` | Prefix for source/project tokens (`--ds-*`) |
| `namespaces.typography` | `ds-type` | Typography token prefix |
| `namespaces.effect` | `ds-effect` | Effect token prefix |
| `theme_map.spacing_prefix` | `spacing/` | Sizes paths mapped to `@theme --spacing-*` |
| `theme_map.radius_prefix` | `radius/` | Sizes paths mapped to `@theme --radius-*` |
| `theme_map.breakpoint_prefix` | `breakpoint/` | Sizes paths mapped to `@theme --breakpoint-*` |
| `theme_map.radius_defaults` | — (map) | When present, radii equal to a default are **not** emitted (diff against framework defaults) |
| `shadow.root_prefix` | `shadow/` | Effect paths composed into `@theme --shadow-*` (`<root><size>/<layer>/{x,y,blur,spread}`) |
| `shadow.color_path_prefix` | `effects/shadows/` | Color-collection path holding shadow colors (`<prefix><size>/<layer>`) |
| `shadow.default_color` | — | Explicit fallback color for shadows without a color token. Required if any shadow lacks a color and must not silently default. |

### `severity`

Maps finding kinds to `warn` (report only) or `fail` (exit `1`). Kinds:
`json_error`, `normalization_collision` (both `fail` by default),
`duplicate_variable_id`, `broken_alias`, `invalid_value`,
`non_canonical_casing`, `incomplete_mode`, `suspicious_zero`,
`approved_mode_missing`, `collection_empty`, `shadow_color_unresolved`,
`breakpoint_missing` (remaining defaults per the transformer header).

## Determinism + exit codes

`same exports + same transformer version + same contract = byte-identical CSS`
(sorting is stable at every stage; no timestamps, no randomness). Exports are
immutable — the transformer never writes inside the input directory. Exit `0`
success (warnings allowed), `1` fail-severity findings (output + report are
still written so the failure is auditable), `2` usage/contract errors. Use
`--dry-run` for audit-only runs; `--report <json>` always records the full
audit (approved inputs, selected modes, collisions, unresolved/excluded
tokens, collection resolution, breakpoint provenance, generated byte count).

## Ownership

The contract that production builds consume must be **theme-owned** (e.g.
`<theme>/web/tailwind/tokens/contract.yaml`), copied from
`assets/templates/token-contract.yaml` and adapted to the project's export
layout. `.ai/`-side copies are governance records only and must never be
referenced by a production build.
