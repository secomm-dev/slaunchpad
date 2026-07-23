# Generation Log — Secomm Launchpad

> Machine-checkable record of how this `.ai/` was generated. Appended on each regeneration/sync.

## preflight (Step 0 — Project Selection Safety, Hard Gate 6)

```yaml
preflight:
  resolved_target: /var/www/html/slaunchpad
  resolved_via: "P2 (explicit name 'slaunchpad') + P4 (cwd)"
  candidates_considered:
    - /var/www/html/slaunchpad   # Magento composer.json + app/code + pub → candidate (selected)
  generator_root_excluded: /var/www/html/secomm-production-ai-toolkit   # forbidden write target
  example_mode: false
  route_selected: PROJECT_INITIALIZATION   # no prior .ai/ → init route
  init_state_before: 0 (NOT_INITIALIZED)
  init_state_after: DELIVERY_READY   # toolkit generated
  blueprint_status: approved (2026-07-14)
```

Target was **unique** (single candidate at P2+P4) → resolved silently, no human prompt required. Resolved target ≠ generator root (write-target guard passed).

## generation

```yaml
generation:
  date: 2026-07-14
  toolkit_version: 4.0
  stack_detected: magento-hyva (platform=magento + stack_variant=magento-hyva) — confidence high
  workflow_mode: A
  output_language: vi
  scope: full v4 toolkit
  capabilities_detected:
    - Magento Backend Development
    - Hyva Frontend / Theme Development
    - Payment Integration Security (VNPAY/Mollie)
    - Checkout Customization (Mageplaza OSC)
    - Address / Localization (VN)
    - Security Review
    - Performance Optimization
    - Architecture Design
    - Documentation
```

## v4 artifacts emitted

- `AGENTS.md` (base + magento-hyva overlay + project data + Tailwind v4 correction + §8.5/§8.6)
- `project-context/` 01–08 (base) + 09–12 (magento-hyva overlay) + `CODING_RULES.md`
- `project-context/memory/` (CURRENT_STATE, NEXT_TASK, DECISIONS, LESSONS_LEARNED, CONTINUOUS_LEARNING, RESEARCH_NOTES, SECURITY_BASELINE, KNOWN_RISKS pointer)
- `project-context/engineering-standards/` (base + MAGENTO + HYVA + PHP + relevant tech)
- `instincts/`, `rules/` (11), `hooks/` (12 split from HOOKS.md), `mcp/` (4), `commands/`, `evidence/`
- `agents/` (11: base 7 + magento-reviewer, hyva-migration, security-reviewer, performance-reviewer)
- `functions/` (base + magento + hyva + security + performance + function-index)
- `audits/` (index + workflows ref; base + magento + hyva)
- `checklists/`, `templates/`
- `research/`, `workflow/`, `intents/`, `initialization/` (v4 runtime subsystems)
- `runtime/` (project-state.yaml + PROJECT_NAVIGATOR.md + DECISION_QUEUE.md + README — Human Navigator)
- `enablement/` (WELCOME + guides + reference + learning)
- root: `AGENTS.md` (pointer), `CLAUDE.md` (thin wrapper), `pull_request_template.md`, `.github/copilot-instructions.md`, `codex/AGENTS.md`
- `.claude/skills/` (base 9 + magento + security) + `.claude/skills/dev/` (magento incl. hyva)
- `toolkit/` (PROJECT_AI_BLUEPRINT.md, generation-log.md, selection-manifest.md, validation-report.md, toolkit-version.md)
- `CHANGELOG_AI_TOOL.md` (initial v1.0.0 entry)
