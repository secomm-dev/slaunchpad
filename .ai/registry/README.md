# Capability Registry

> **Language:** English (machine-readable contract). This is the **single source of truth** for
> what the toolkit advertises and what is actually invokable. All generated human-facing docs
> (`CHEATSHEET.md`, `COMMAND_REFERENCE.md`, `supported-commands.md`) are **renders** of
> [`toolkit-capability-registry.yaml`](toolkit-capability-registry.yaml) — never independent lists.

## The rule this registry enforces

> Anything documented as usable must have a discoverable, valid implementation.
> Anything implemented must be accurately documented.

Concretely: for every capability, each `tools.{tool}.enabled: true` entry **must** point at an
adapter file that exists. `bin/project-ai-validate` checks this and fails on any mismatch
(material contract violation = error, not warning).

## What lives here

| File | Purpose |
|---|---|
| `toolkit-capability-registry.yaml` | Every capability: purpose, canonical source, aliases, per-tool adapter + invocation_type, portable fallback, approval gate, mutating flag, status |

## Schema (condensed)

```yaml
schema_version: 1
capabilities:
  <group.id>:
    purpose, canonical_source, aliases, mutating, approval_gate
    inputs: { required, optional }
    outputs: { paths, types }
    tools:
      claude:  { enabled, adapter, invocation, invocation_type, validation_status }
      codex:   { ... }
      copilot: { ... }
    portable_fallback: { invocation }
    documentation: { sources }
    status
deprecated:
  <id>: { purpose, reason, action }
aliases:
  "/name": "<canonical-id>"
```

`invocation_type`: `skill` | `prompt-snippet` | `nl-only` | `function` | `gate` | `none`.

## Groups

- **nav.\*** — navigator commands (always available). Backed by real Claude skills
  (`skills-source/nav-*/`) + the canonical Navigator engine (`shared-core/navigator/`).
  Codex/Copilot: natural language (parity not faked).
- **delivery.\*** — delivery lifecycle. Canonical = Intent layer; legacy shim names are aliases.
- **deprecated** — capabilities that must NOT be advertised (e.g. `tl-review` is a gate, not a command).

## How docs render from this

- `CHEATSHEET.template.md` — nav table + top intents come from registry entries flagged in
  `documentation.sources` (no hardcoded command names).
- `COMMAND_REFERENCE.template.md` — one row per enabled capability, with per-tool invocation_type.
- `supported-commands-template.md` — canonical command + aliases + NL example per capability.

## Relation to existing indexes

This registry **supersedes the command-name portions** of `shared-core/commands/commands-index.md`
and `shared-core/intents/supported-commands-template.md` as the source of truth; those files become
human-readable renders / alias maps. It does **not** touch `CAPABILITY_INVENTORY_STANDARD.md`
(that documents *business* migration capabilities — different domain; the name collision is resolved
by namespace, not rename).
