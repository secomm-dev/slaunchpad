#!/usr/bin/env node
// Secomm Production AI Toolkit — hyva-global-style-foundation
// Contract-driven design-token transformer: Figma/DTCG-style *.tokens.json exports
// → deterministic CSS (Tailwind v4 CSS-first: :root custom properties + @theme).
//
// Structure-agnostic: every directory layout, mode name, audit heuristic and
// output namespace is supplied by a project-owned contract file (YAML subset or
// JSON — see references/token-contract.md). The transformer itself is generic.
//
// Determinism contract:
//   same input exports + same transformer version + same contract
//   = byte-identical generated CSS.
// Exports are immutable inputs — this script never writes inside --input.
//
// Exit codes:
//   0  success (warnings permitted)
//   1  findings whose severity is "fail" (see contract.severity) — output and
//      report are still written so the failure is auditable
//   2  usage error or invalid contract
//
// Usage:
//   node token-transformer.mjs --contract <file.yaml|file.json> \
//     [--input <token-dir>] [--output <css>] [--report <json>] [--dry-run]
//
// --input overrides contract input.source_dir (when relative, source_dir is
// resolved against the contract file's directory; --input against the cwd).

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const TRANSFORMER_VERSION = '2.1.0';
const CONTRACT_SCHEMA_VERSION = 1;

const KNOWN_ROLES = ['color', 'brand', 'font', 'typography', 'sizes', 'effects'];
const MODE_ROLES = ['color', 'brand', 'font']; // multi-mode, mode-from-filename collections
const SEVERITY_KINDS = [
  'json_error', 'normalization_collision', 'duplicate_variable_id', 'broken_alias',
  'invalid_value', 'non_canonical_casing', 'incomplete_mode', 'suspicious_zero',
  'approved_mode_missing', 'collection_empty', 'shadow_color_unresolved', 'breakpoint_missing',
];
const DEFAULT_SEVERITY = {
  json_error: 'fail', normalization_collision: 'fail', duplicate_variable_id: 'warn',
  broken_alias: 'warn', invalid_value: 'warn', non_canonical_casing: 'warn',
  incomplete_mode: 'warn', suspicious_zero: 'warn', approved_mode_missing: 'fail',
  collection_empty: 'warn', shadow_color_unresolved: 'fail', breakpoint_missing: 'fail',
};

// ---------------------------------------------------------------------------
// Minimal YAML-subset parser (no dependency). Supports: nested maps by
// indentation, block sequences ("- x", "- key: value", "- {flow}"), flow maps
// and sequences, quoted scalars, numbers, booleans, null, # comments.
// Unsupported: anchors, multi-line scalars, tabs, block scalars.
// ---------------------------------------------------------------------------
function parseYamlSubset(text) {
  const lines = [];
  text.split(/\r?\n/).forEach((raw, ln) => {
    if (raw.includes('\t')) throw new Error(`contract YAML line ${ln + 1}: tabs are not supported`);
    let cut = -1, q = null;
    for (let i = 0; i < raw.length; i++) {
      const ch = raw[i];
      if (q) { if (ch === q && raw[i - 1] !== '\\') q = null; }
      else if (ch === '"' || ch === "'") q = ch;
      else if (ch === '#') { cut = i; break; }
    }
    const s = cut >= 0 ? raw.slice(0, cut) : raw;
    if (!s.trim()) return;
    lines.push({ indent: s.match(/^ */)[0].length, text: s.trim(), ln: ln + 1 });
  });
  const err = (m) => { throw new Error(`contract YAML: ${m}`); };
  const scalar = (tok) => {
    tok = tok.trim();
    if (!tok) err('empty value');
    if (/^".*"$/.test(tok) || /^'.*'$/.test(tok)) return tok.slice(1, -1);
    if (tok === 'true') return true;
    if (tok === 'false') return false;
    if (tok === 'null' || tok === '~') return null;
    if (/^-?\d+(\.\d+)?$/.test(tok)) return Number(tok);
    return tok;
  };
  const findColon = (s) => {
    let q = null, depth = 0;
    for (let i = 0; i < s.length; i++) {
      const ch = s[i];
      if (q) { if (ch === q) q = null; }
      else if (ch === '"' || ch === "'") q = ch;
      else if (ch === '{' || ch === '[') depth++;
      else if (ch === '}' || ch === ']') depth--;
      else if (ch === ':' && depth === 0) return i;
    }
    return -1;
  };
  const splitFlow = (src) => {
    const parts = []; let depth = 0, q = null, cur = '';
    for (const ch of src) {
      if (q) { cur += ch; if (ch === q) q = null; continue; }
      if (ch === '"' || ch === "'") { q = ch; cur += ch; continue; }
      if (ch === '{' || ch === '[') { depth++; cur += ch; continue; }
      if (ch === '}' || ch === ']') { depth--; cur += ch; continue; }
      if (ch === ',' && depth === 0) { parts.push(cur); cur = ''; continue; }
      cur += ch;
    }
    parts.push(cur);
    return parts.map((p) => p.trim()).filter((p) => p !== '');
  };
  const valueOf = (src) => {
    src = src.trim();
    if (src.startsWith('{')) {
      if (!src.endsWith('}')) err(`unterminated flow map: ${src}`);
      const obj = {};
      for (const part of splitFlow(src.slice(1, -1))) {
        const c = findColon(part);
        if (c < 0) err(`flow map entry missing colon: ${part}`);
        obj[part.slice(0, c).trim().replace(/^['"]|['"]$/g, '')] = valueOf(part.slice(c + 1));
      }
      return obj;
    }
    if (src.startsWith('[')) {
      if (!src.endsWith(']')) err(`unterminated flow sequence: ${src}`);
      return splitFlow(src.slice(1, -1)).map(valueOf);
    }
    return scalar(src);
  };
  const block = (indent) => {
    const first = lines[i];
    if (!first || first.indent < indent) err('expected an indented block');
    return first.text.startsWith('- ') || first.text === '-' ? seq(indent) : map(indent);
  };
  const map = (indent) => {
    const obj = {};
    while (i < lines.length && lines[i].indent === indent && !lines[i].text.startsWith('- ') && lines[i].text !== '-') {
      const { text, ln } = lines[i];
      const c = findColon(text);
      if (c < 0) err(`line ${ln}: missing ':' in "${text}"`);
      const key = text.slice(0, c).trim().replace(/^['"]|['"]$/g, '');
      const rest = text.slice(c + 1).trim();
      if (!key) err(`line ${ln}: empty key`);
      i++;
      if (rest) obj[key] = valueOf(rest);
      else if (i < lines.length && lines[i].indent > indent) obj[key] = block(lines[i].indent);
      else err(`line ${ln}: key "${key}" has no value`);
    }
    return obj;
  };
  const seq = (indent) => {
    const arr = [];
    while (i < lines.length && lines[i].indent === indent && (lines[i].text.startsWith('- ') || lines[i].text === '-')) {
      const rest = lines[i].text.replace(/^-( |$)/, '');
      if (!rest) { i++; if (i < lines.length && lines[i].indent > indent) arr.push(block(lines[i].indent)); else err('empty sequence item'); continue; }
      if (rest.startsWith('{') || rest.startsWith('[')) { arr.push(valueOf(rest)); i++; continue; }
      const c = findColon(rest);
      if (c >= 0) {
        const key = rest.slice(0, c).trim();
        const val = rest.slice(c + 1).trim();
        const obj = {};
        i++;
        if (val) obj[key] = valueOf(val);
        else if (i < lines.length && lines[i].indent > indent) obj[key] = block(lines[i].indent);
        else err('sequence map item missing value');
        arr.push(obj);
      } else { arr.push(scalar(rest)); i++; }
    }
    return arr;
  };
  let i = 0;
  const root = block(lines.length ? lines[0].indent : 0);
  if (i < lines.length) err(`line ${lines[i].ln}: unexpected content (check indentation)`);
  return root;
}

// ---------------------------------------------------------------------------
// Contract loading + strict validation
// ---------------------------------------------------------------------------
function validateContract(c) {
  const errs = [];
  if (!c || typeof c !== 'object' || Array.isArray(c)) return ['contract root must be a mapping'];
  for (const k of Object.keys(c)) if (!['schema_version', 'input', 'collections', 'generation', 'exclude', 'audit', 'output', 'severity'].includes(k)) errs.push(`unknown top-level key "${k}"`);
  if (c.schema_version !== 1) errs.push(`schema_version must be 1 (got ${JSON.stringify(c.schema_version)})`);
  if (c.input !== undefined) {
    if (typeof c.input !== 'object' || Array.isArray(c.input)) errs.push('input must be a mapping');
    else {
      for (const k of Object.keys(c.input)) if (!['source_dir', 'file_suffix', 'mode_resolution'].includes(k)) errs.push(`input: unknown key "${k}"`);
      for (const k of ['source_dir', 'file_suffix']) if (c.input[k] !== undefined && typeof c.input[k] !== 'string') errs.push(`input.${k} must be a string`);
      if (c.input.mode_resolution !== undefined && !['figma-then-filename', 'figma-only', 'filename-only'].includes(c.input.mode_resolution)) errs.push(`input.mode_resolution must be one of figma-then-filename|figma-only|filename-only (got ${c.input.mode_resolution})`);
    }
  }
  const cols = c.collections;
  if (!cols || typeof cols !== 'object' || Array.isArray(cols)) errs.push('collections mapping is required');
  else for (const [role, def] of Object.entries(cols)) {
    if (!def || typeof def !== 'object' || Array.isArray(def)) { errs.push(`collections.${role}: must be a mapping`); continue; }
    for (const k of Object.keys(def)) if (!['path', 'file', 'mode_source', 'modes'].includes(k)) errs.push(`collections.${role}: unknown key "${k}"`);
    const hasPath = def.path !== undefined, hasFile = def.file !== undefined;
    if (hasPath === hasFile) errs.push(`collections.${role}: exactly one of "path" or "file" is required`);
    if (hasPath && typeof def.path !== 'string') errs.push(`collections.${role}.path must be a string`);
    if (hasFile && typeof def.file !== 'string') errs.push(`collections.${role}.file must be a string`);
    if (def.mode_source !== undefined && def.mode_source !== 'filename') errs.push(`collections.${role}.mode_source: only "filename" is supported`);
    if (def.modes !== undefined) {
      if (typeof def.modes !== 'object' || Array.isArray(def.modes)) errs.push(`collections.${role}.modes must be a mapping`);
      else for (const k of Object.keys(def.modes)) {
        if (!['base', 'responsive'].includes(k)) errs.push(`collections.${role}.modes: only "base"/"responsive" supported (got "${k}")`);
        if (typeof def.modes[k] !== 'string') errs.push(`collections.${role}.modes.${k} must be a string`);
      }
    }
    if (MODE_ROLES.includes(role) && def.mode_source !== 'filename') errs.push(`collections.${role}: "mode_source: filename" is required`);
    if (['sizes', 'effects'].includes(role) && !hasFile) errs.push(`collections.${role}: "file" is required (single-file collection)`);
    if (role === 'typography' && (!def.modes || !def.modes.base)) errs.push('collections.typography.modes.base is required');
    if (role !== 'typography' && def.modes !== undefined) errs.push(`collections.${role}: "modes" is only valid for the typography role`);
  }
  if (c.generation !== undefined) {
    if (typeof c.generation !== 'object' || Array.isArray(c.generation)) errs.push('generation must be a mapping');
    else {
      for (const k of Object.keys(c.generation)) if (!['approved_modes', 'typography', 'breakpoint', 'preserve_alias_collections'].includes(k)) errs.push(`generation: unknown key "${k}"`);
      if (c.generation.approved_modes !== undefined) {
        if (typeof c.generation.approved_modes !== 'object' || Array.isArray(c.generation.approved_modes)) errs.push('generation.approved_modes must be a mapping');
        else for (const [k, v] of Object.entries(c.generation.approved_modes)) {
          if (!MODE_ROLES.includes(k)) errs.push(`generation.approved_modes: unknown mode role "${k}"`);
          if (typeof v !== 'string') errs.push(`generation.approved_modes.${k} must be a string`);
        }
      }
      if (c.generation.typography !== undefined) {
        if (typeof c.generation.typography !== 'object' || Array.isArray(c.generation.typography)) errs.push('generation.typography must be a mapping');
        else {
          for (const k of Object.keys(c.generation.typography)) if (!['property_keys', 'unitless_key_pattern'].includes(k)) errs.push(`generation.typography: unknown key "${k}"`);
          if (c.generation.typography.property_keys !== undefined && (!Array.isArray(c.generation.typography.property_keys) || !c.generation.typography.property_keys.length || c.generation.typography.property_keys.some((k) => typeof k !== 'string'))) errs.push('generation.typography.property_keys must be a non-empty string array');
          if (c.generation.typography.unitless_key_pattern !== undefined && typeof c.generation.typography.unitless_key_pattern !== 'string') errs.push('generation.typography.unitless_key_pattern must be a string');
        }
      }
      if (c.generation.breakpoint !== undefined) {
        if (typeof c.generation.breakpoint !== 'object' || Array.isArray(c.generation.breakpoint)) errs.push('generation.breakpoint must be a mapping');
        else {
          for (const k of Object.keys(c.generation.breakpoint)) if (!['token', 'fallback'].includes(k)) errs.push(`generation.breakpoint: unknown key "${k}"`);
          for (const k of ['token', 'fallback']) if (c.generation.breakpoint[k] !== undefined && typeof c.generation.breakpoint[k] !== 'string') errs.push(`generation.breakpoint.${k} must be a string`);
        }
      }
      if (c.generation.preserve_alias_collections !== undefined && (!Array.isArray(c.generation.preserve_alias_collections) || c.generation.preserve_alias_collections.some((v) => typeof v !== 'string'))) errs.push('generation.preserve_alias_collections must be a string array');
    }
  }
  if (c.exclude !== undefined) {
    if (typeof c.exclude !== 'object' || Array.isArray(c.exclude)) errs.push('exclude must be a mapping');
    else {
      for (const k of Object.keys(c.exclude)) if (k !== 'paths') errs.push(`exclude: unknown key "${k}"`);
      if (c.exclude.paths !== undefined && (!Array.isArray(c.exclude.paths) || c.exclude.paths.some((p) => typeof p !== 'string'))) errs.push('exclude.paths must be a string array');
    }
  }
  if (c.audit !== undefined) {
    if (typeof c.audit !== 'object' || Array.isArray(c.audit)) errs.push('audit must be a mapping');
    else {
      for (const k of Object.keys(c.audit)) if (k !== 'rules') errs.push(`audit: unknown key "${k}"`);
      if (c.audit.rules !== undefined) {
        if (!Array.isArray(c.audit.rules)) errs.push('audit.rules must be an array');
        else c.audit.rules.forEach((r, idx) => {
          if (!r || typeof r !== 'object' || Array.isArray(r)) { errs.push(`audit.rules[${idx}]: must be a mapping`); return; }
          for (const k of Object.keys(r)) if (!['kind', 'collection', 'path_prefix'].includes(k)) errs.push(`audit.rules[${idx}]: unknown key "${k}"`);
          if (r.kind !== 'suspicious_zero') errs.push(`audit.rules[${idx}]: kind must be "suspicious_zero" (got ${JSON.stringify(r.kind)})`);
          if (typeof r.collection !== 'string' || !cols || !(r.collection in cols)) errs.push(`audit.rules[${idx}].collection must name a declared collection`);
          if (typeof r.path_prefix !== 'string' || !r.path_prefix) errs.push(`audit.rules[${idx}].path_prefix must be a non-empty string`);
        });
      }
    }
  }
  if (c.output !== undefined) {
    if (typeof c.output !== 'object' || Array.isArray(c.output)) errs.push('output must be a mapping');
    else {
      for (const k of Object.keys(c.output)) if (!['namespaces', 'theme_map', 'shadow'].includes(k)) errs.push(`output: unknown key "${k}"`);
      if (c.output.namespaces !== undefined) {
        if (typeof c.output.namespaces !== 'object' || Array.isArray(c.output.namespaces)) errs.push('output.namespaces must be a mapping');
        else for (const [k, v] of Object.entries(c.output.namespaces)) {
          if (!['source', 'typography', 'effect'].includes(k)) errs.push(`output.namespaces: unknown key "${k}"`);
          if (typeof v !== 'string' || !v) errs.push(`output.namespaces.${k} must be a non-empty string`);
        }
      }
      if (c.output.theme_map !== undefined) {
        if (typeof c.output.theme_map !== 'object' || Array.isArray(c.output.theme_map)) errs.push('output.theme_map must be a mapping');
        else {
          for (const k of Object.keys(c.output.theme_map)) if (!['spacing_prefix', 'radius_prefix', 'breakpoint_prefix', 'radius_defaults'].includes(k)) errs.push(`output.theme_map: unknown key "${k}"`);
          for (const k of ['spacing_prefix', 'radius_prefix', 'breakpoint_prefix']) if (c.output.theme_map[k] !== undefined && typeof c.output.theme_map[k] !== 'string') errs.push(`output.theme_map.${k} must be a string`);
          if (c.output.theme_map.radius_defaults !== undefined) {
            if (typeof c.output.theme_map.radius_defaults !== 'object' || Array.isArray(c.output.theme_map.radius_defaults)) errs.push('output.theme_map.radius_defaults must be a mapping');
            else for (const [k, v] of Object.entries(c.output.theme_map.radius_defaults)) if (typeof v !== 'string') errs.push(`output.theme_map.radius_defaults.${k} must be a string`);
          }
        }
      }
      if (c.output.shadow !== undefined) {
        if (typeof c.output.shadow !== 'object' || Array.isArray(c.output.shadow)) errs.push('output.shadow must be a mapping');
        else {
          for (const k of Object.keys(c.output.shadow)) if (!['root_prefix', 'color_path_prefix', 'default_color'].includes(k)) errs.push(`output.shadow: unknown key "${k}"`);
          for (const k of ['root_prefix', 'color_path_prefix', 'default_color']) if (c.output.shadow[k] !== undefined && typeof c.output.shadow[k] !== 'string') errs.push(`output.shadow.${k} must be a string`);
        }
      }
    }
  }
  if (c.severity !== undefined) {
    if (typeof c.severity !== 'object' || Array.isArray(c.severity)) errs.push('severity must be a mapping');
    else for (const [k, v] of Object.entries(c.severity)) {
      if (!SEVERITY_KINDS.includes(k)) errs.push(`severity: unknown kind "${k}"`);
      if (v !== 'warn' && v !== 'fail') errs.push(`severity.${k} must be warn|fail`);
    }
  }
  return errs;
}

function loadContract(p) {
  const raw = fs.readFileSync(p, 'utf8');
  let data;
  try {
    data = p.endsWith('.json') ? JSON.parse(raw) : parseYamlSubset(raw);
  } catch (e) {
    console.error(`Invalid contract (${p}): ${e.message}`);
    process.exit(2);
  }
  const errs = validateContract(data);
  if (errs.length) {
    console.error(`Invalid contract (${p}):`);
    errs.forEach((e) => console.error(`  - ${e}`));
    process.exit(2);
  }
  return { data, raw, sha256: crypto.createHash('sha256').update(raw).digest('hex') };
}

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------
const argv = process.argv.slice(2);
const arg = (name) => { const i = argv.indexOf(name); return i >= 0 ? argv[i + 1] : null; };
const contractPath = arg('--contract');
const inputArg = arg('--input');
const output = arg('--output');
const reportPath = arg('--report');
const dryRun = argv.includes('--dry-run');
const manifestArg = arg('--manifest');
if (!contractPath || (!dryRun && !output)) {
  console.error('Usage: token-transformer.mjs --contract <file.yaml|file.json> [--input <token-dir>] [--output <css>] [--report <json>] [--manifest <json>] [--dry-run]');
  console.error('Note: approved color/brand/font modes live in the contract (generation.approved_modes), not on the CLI.');
  process.exit(2);
}
const contractFile = path.resolve(contractPath);
let contract;
try {
  contract = loadContract(contractFile);
} catch (e) {
  console.error(`Cannot read contract: ${e.message}`);
  process.exit(2);
}
const C = contract.data;
const inputDir = inputArg
  ? path.resolve(inputArg)
  : C.input?.source_dir ? path.resolve(path.dirname(contractFile), C.input.source_dir) : null;
if (!inputDir || !fs.statSync(inputDir, { throwIfNoEntry: false })?.isDirectory()) {
  console.error(`Input token directory not found: ${inputArg ? inputArg : '(no --input and no contract input.source_dir)'}`);
  process.exit(2);
}
const fileSuffix = C.input?.file_suffix ?? '.tokens.json';
const modeResolution = C.input?.mode_resolution ?? 'figma-then-filename';
const severity = { ...DEFAULT_SEVERITY, ...(C.severity || {}) };

const norm = (s) => String(s).toLowerCase().replaceAll('_', '-').replace(/[^a-z0-9-]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
const cols = C.collections;
const excludePaths = C.exclude?.paths || [];
const ns = { source: 'ds', typography: 'ds-type', effect: 'ds-effect', ...(C.output?.namespaces || {}) };
const themeMap = { spacing_prefix: 'spacing/', radius_prefix: 'radius/', breakpoint_prefix: 'breakpoint/', ...(C.output?.theme_map || {}) };
const radiusDefaults = C.output?.theme_map?.radius_defaults !== undefined
  ? new Map(Object.entries(C.output.theme_map.radius_defaults)) : null;
const shadowCfg = { root_prefix: 'shadow/', color_path_prefix: 'effects/shadows/', ...(C.output?.shadow || {}) };
const approved = C.generation?.approved_modes || {};
const propertyKeys = C.generation?.typography?.property_keys || ['family', 'size', 'line-height', 'letter-spacing', 'weight-base', 'weight-strong'];
const unitlessPattern = new RegExp(C.generation?.typography?.unitless_key_pattern ?? '^(weight-base|weight-strong)$');
const breakpointCfg = C.generation?.breakpoint || {};

// Match keys for a collection: normalized role name + normalized location name
// (path segment, or file basename without suffix). Figma alias set names and
// preserve_alias_collections entries may use either.
const colMatchKeys = new Map(); // role -> [norm keys]
for (const [role, def] of Object.entries(cols)) {
  const loc = def.path ?? def.file.slice(0, def.file.length - fileSuffix.length);
  colMatchKeys.set(role, [...new Set([role, loc].map(norm))]);
}
const roleForName = (name) => {
  const n = norm(name);
  for (const [role, keys] of colMatchKeys) if (keys.includes(n)) return role;
  return null;
};
const preserveAliasKeys = new Set();
for (const e of (C.generation?.preserve_alias_collections || [])) {
  const role = roleForName(e);
  if (role) colMatchKeys.get(role).forEach((k) => preserveAliasKeys.add(k));
  else preserveAliasKeys.add(norm(e));
}

// ---------------------------------------------------------------------------
// Discovery + parse (exports are never mutated)
// ---------------------------------------------------------------------------
const files = [];
const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name)).forEach((e) => {
  const p = path.join(dir, e.name);
  if (e.isDirectory()) walk(p);
  else if (e.name.endsWith(fileSuffix)) files.push(p);
});
walk(inputDir);
const rel = (f) => path.relative(inputDir, f).replaceAll(path.sep, '/');

const tokens = [];
const jsonErrors = [];
const visit = (value, parts, meta) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return;
  if (Object.hasOwn(value, '$value')) {
    tokens.push({ ...meta, path: parts.join('/'), type: value.$type || typeof value.$value, value: value.$value, extensions: value.$extensions || {}, description: value.$description || '' });
    return;
  }
  for (const key of Object.keys(value).sort()) if (!key.startsWith('$')) visit(value[key], [...parts, key], meta);
};
const modeOf = (json, file) => {
  const figma = json.$extensions?.['com.figma.modeName'];
  const fromFile = path.basename(file, fileSuffix);
  if (modeResolution === 'figma-only') return figma || null;
  if (modeResolution === 'filename-only') return fromFile;
  return figma || fromFile;
};
for (const file of files) {
  try {
    const json = JSON.parse(fs.readFileSync(file, 'utf8'));
    visit(json, [], { file: rel(file), mode: modeOf(json, file) });
  } catch (e) { jsonErrors.push({ file, error: e.message }); }
}

// ---------------------------------------------------------------------------
// Collection resolution: every file maps to exactly one declared collection
// (or is reported as unmapped — never silently dropped or guessed).
// ---------------------------------------------------------------------------
const fileRole = new Map();
for (const f of files) {
  const r = rel(f);
  let role = null;
  for (const [cRole, def] of Object.entries(cols)) {
    if (def.file !== undefined) { if (r === def.file) { role = cRole; break; } }
    else if (r === def.path || r.startsWith(`${def.path}/`)) { role = cRole; break; }
  }
  fileRole.set(r, role);
}
const roleFiles = new Map();
for (const r of Object.keys(cols)) roleFiles.set(r, []);
const unmappedFiles = [];
for (const f of files) {
  const r = rel(f);
  const role = fileRole.get(r);
  if (role) roleFiles.get(role).push(r); else unmappedFiles.push(r);
}
const collectionEmpty = [...roleFiles].filter(([, fs_]) => fs_.length === 0).map(([role]) => role);
const roleTokens = (role) => tokens.filter((t) => fileRole.get(t.file) === role);
const selectFile = (suffix) => tokens.filter((t) => t.file === suffix);

// ---------------------------------------------------------------------------
// Audit (universal DTCG hygiene + contract-configured rules)
// ---------------------------------------------------------------------------
const byId = new Map(), byNormalized = new Map();
for (const t of tokens) {
  const id = t.extensions['com.figma.variableId'];
  if (id) (byId.get(id) || byId.set(id, []).get(id)).push(t);
  const key = `${fileRole.get(t.file) ?? '_unmapped'}:${t.mode}:${norm(t.path)}`;
  (byNormalized.get(key) || byNormalized.set(key, []).get(key)).push(t);
}
const duplicateIds = [...byId].filter(([, v]) => new Set(v.map((t) => t.path)).size > 1).map(([id, v]) => ({ id, locations: v.map((t) => `${t.file}:${t.path}`) }));
const casingConflicts = [...byNormalized].filter(([, v]) => new Set(v.map((t) => t.path)).size > 1).map(([key, v]) => ({ key, paths: [...new Set(v.map((t) => t.path))] }));
const invalidValues = tokens.filter((t) => t.value === null || t.value === '' || typeof t.value === 'undefined').map((t) => `${t.file}:${t.path}`);
const nonCanonicalCasing = tokens.filter((t) => /[A-Z]/.test(t.path)).map((t) => `${t.file}:${t.path}`);

const rolePathSets = new Map();
for (const t of tokens) {
  const role = fileRole.get(t.file);
  if (!role) continue;
  (rolePathSets.get(role) || rolePathSets.set(role, new Set()).get(role)).add(t.path);
}
const aliases = tokens.filter((t) => t.extensions['com.figma.aliasData']);
const brokenAliases = aliases.filter((t) => {
  const a = t.extensions['com.figma.aliasData'];
  const role = a.targetVariableSetName ? roleForName(a.targetVariableSetName) : null;
  return !a.targetVariableName || !a.targetVariableSetName || role === null || !rolePathSets.get(role)?.has(a.targetVariableName);
}).map((t) => `${t.file}:${t.path}`);

const incompleteModes = [];
for (const [role, fs_] of roleFiles) {
  if (fs_.length < 2) continue; // mode-completeness only applies to multi-mode collections
  const union = new Set(tokens.filter((t) => fileRole.get(t.file) === role).map((t) => t.path));
  for (const file of fs_) {
    const own = new Set(tokens.filter((t) => t.file === file).map((t) => t.path));
    const missing = [...union].filter((p) => !own.has(p));
    if (missing.length) incompleteModes.push({ file, missing });
  }
}

const auditRuleFindings = [];
for (const rule of (C.audit?.rules || [])) {
  if (rule.kind !== 'suspicious_zero') continue;
  for (const t of tokens) {
    if (t.value === 0 && fileRole.get(t.file) === rule.collection && t.path.startsWith(rule.path_prefix)) {
      auditRuleFindings.push({ rule: `suspicious_zero[${rule.collection}/${rule.path_prefix}]`, location: `${t.file}:${t.path}` });
    }
  }
}

const normModes = (role) => roleFiles.get(role).map((f) => norm(f.split('/').pop().slice(0, -fileSuffix.length)));
const approvedModeMissing = [];
for (const role of MODE_ROLES) {
  if (!(role in cols) || approved[role] === undefined) continue;
  const want = norm(approved[role]);
  const have = normModes(role);
  if (!have.includes(want)) approvedModeMissing.push({ role, approved: want, available: have });
}
const productionAllowlist = {
  colorModes: approved.color ? [norm(approved.color)] : [],
  brandThemes: approved.brand ? [norm(approved.brand)] : [],
  fontModes: approved.font ? [norm(approved.font)] : [],
  typographyModes: cols.typography ? [cols.typography.modes.base, cols.typography.modes.responsive].filter(Boolean) : [],
};
const auditOnlyModes = {
  colorModes: ('color' in cols) ? normModes('color').filter((m) => !productionAllowlist.colorModes.includes(m)) : [],
  brandThemes: ('brand' in cols) ? normModes('brand').filter((m) => !productionAllowlist.brandThemes.includes(m)) : [],
  fontModes: ('font' in cols) ? normModes('font').filter((m) => !productionAllowlist.fontModes.includes(m)) : [],
};

// ---------------------------------------------------------------------------
// Value formatting (deterministic)
// ---------------------------------------------------------------------------
const value = (t) => {
  if (t.type === 'color') {
    if (!t.value?.components) return t.value?.hex || null;
    const [r, g, b] = t.value.components.map((x) => Math.round(x * 255));
    return `rgb(${r} ${g} ${b} / ${Number((t.value.alpha ?? 1).toFixed(4))})`;
  }
  if (typeof t.value === 'number') {
    const number = Number(t.value.toFixed?.(4) ?? t.value);
    return unitlessPattern.test(t.path.split('/').pop()) ? String(number) : `${number}px`;
  }
  return String(t.value);
};
const cssValue = (t, preserveAliases = false) => {
  const alias = t.extensions['com.figma.aliasData'];
  if (preserveAliases && alias?.targetVariableName && preserveAliasKeys.has(norm(alias.targetVariableSetName))) {
    return `var(--${ns.source}-${norm(alias.targetVariableName)})`;
  }
  return value(t);
};
const isExcluded = (tokenPath) => excludePaths.some((p) => {
  if (p.endsWith('/**')) return tokenPath.startsWith(p.slice(0, -2));
  return tokenPath === p || tokenPath.startsWith(`${p}/`) || tokenPath.startsWith(`${p}/**`);
});
const cssBlock = (selector, list, prefix, preserveAliases = false) => {
  const lines = list.map((t) => {
    if (isExcluded(t.path)) return null;
    const line = `  --${prefix}-${norm(t.path)}: ${cssValue(t, preserveAliases)};`;
    return line.includes('null') ? null : line;
  }).filter(Boolean);
  return `${selector} {\n${[...new Set(lines)].join('\n')}\n}`;
};

// ---------------------------------------------------------------------------
// Generation (approved modes only; everything else stays audit-only)
// ---------------------------------------------------------------------------
const approvedColors = ('color' in cols && approved.color) ? selectFile(`${cols.color.path}/${norm(approved.color)}${fileSuffix}`) : [];
const approvedBrandFile = ('brand' in cols && approved.brand) ? `${cols.brand.path}/${norm(approved.brand)}${fileSuffix}` : null;
const sizesTokens = 'sizes' in cols ? roleTokens('sizes') : [];
const effectsTokens = 'effects' in cols ? roleTokens('effects') : [];
const typographyFile = (slot) => cols.typography?.modes?.[slot]
  ? `${cols.typography.path}/${cols.typography.modes[slot]}${fileSuffix}` : null;
const typographyTokens = (slot) => {
  const f = typographyFile(slot);
  return f ? selectFile(f).filter((t) => propertyKeys.some((k) => t.path.endsWith(`/${k}`))) : [];
};

const themeLines = [
  ...sizesTokens.filter((t) => t.path.startsWith(themeMap.spacing_prefix)).map((t) => `  --spacing-${norm(t.path.slice(themeMap.spacing_prefix.length))}: ${value(t)};`),
  ...sizesTokens
    .filter((t) => t.path.startsWith(themeMap.radius_prefix))
    .filter((t) => !radiusDefaults || radiusDefaults.get(norm(t.path.slice(themeMap.radius_prefix.length))) !== value(t))
    .map((t) => `  --radius-${norm(t.path.slice(themeMap.radius_prefix.length))}: ${value(t)};`),
  ...sizesTokens.filter((t) => t.path.startsWith(themeMap.breakpoint_prefix)).map((t) => `  --breakpoint-${norm(t.path.slice(themeMap.breakpoint_prefix.length))}: ${value(t)};`),
];
const effectMap = new Map(effectsTokens.map((t) => [t.path, t.value]));
const shadowColorUnresolved = [];
const shadowSizes = [...new Set(effectsTokens.filter((t) => t.path.startsWith(shadowCfg.root_prefix)).map((t) => t.path.slice(shadowCfg.root_prefix.length).split('/')[0]))];
for (const size of shadowSizes) {
  const layers = [...new Set(effectsTokens.filter((t) => t.path.startsWith(`${shadowCfg.root_prefix}${size}/`)).map((t) => t.path.split('/')[2]))].map((layer) => {
    const base = `${shadowCfg.root_prefix}${size}/${layer}`;
    const color = approvedColors.find((t) => t.path === `${shadowCfg.color_path_prefix}${size}/${layer}`);
    let colorValue;
    if (color) colorValue = `var(--${ns.source}-${norm(color.path)})`;
    else if (shadowCfg.default_color !== undefined) colorValue = shadowCfg.default_color;
    else { shadowColorUnresolved.push(`${size}/${layer}`); return null; }
    return `${effectMap.get(`${base}/x`) || 0}px ${effectMap.get(`${base}/y`) || 0}px ${effectMap.get(`${base}/blur`) || 0}px ${effectMap.get(`${base}/spread`) || 0}px ${colorValue}`;
  }).filter(Boolean);
  if (layers.length) themeLines.push(`  --shadow-${norm(size)}: ${layers.join(', ')};`);
}

const responsiveSlot = cols.typography?.modes?.responsive;
let desktopBreakpoint = breakpointCfg.token ? value(sizesTokens.find((t) => t.path === breakpointCfg.token)) || null : null;
let breakpointSource = breakpointCfg.token ? 'token' : null;
if (desktopBreakpoint === null) {
  if (breakpointCfg.fallback !== undefined) { desktopBreakpoint = breakpointCfg.fallback; breakpointSource = 'fallback'; }
  else if (responsiveSlot) breakpointSource = 'missing';
}

const modeHeaderParts = [approved.color, approved.brand, approved.font].filter(Boolean).map(norm);
const css = [
  '/** GENERATED FILE — DO NOT EDIT.',
  ' * Source: approved exported design tokens.',
  ` * Production modes: ${modeHeaderParts.join(' + ')}.`,
  ' */',
  approvedBrandFile ? cssBlock(':root', selectFile(approvedBrandFile), ns.source) : '',
  approvedColors.length ? cssBlock(':root', approvedColors, ns.source, true) : '',
  typographyFile('base') ? cssBlock(':root', typographyTokens('base'), ns.typography) : '',
  effectsTokens.length ? cssBlock(':root', effectsTokens, ns.effect) : '',
  responsiveSlot && desktopBreakpoint ? `@media (min-width: ${desktopBreakpoint}) {\n${cssBlock(':root', typographyTokens('responsive'), ns.typography)}\n}` : '',
  themeLines.length ? `@theme {\n${[...new Set(themeLines)].join('\n')}\n}` : '',
  '',
].join('\n\n');

// ---------------------------------------------------------------------------
// Report + exit codes
// ---------------------------------------------------------------------------
const findings = {
  json_error: jsonErrors,
  normalization_collision: casingConflicts,
  duplicate_variable_id: duplicateIds,
  broken_alias: brokenAliases,
  invalid_value: invalidValues,
  non_canonical_casing: nonCanonicalCasing,
  incomplete_mode: incompleteModes,
  suspicious_zero: auditRuleFindings,
  approved_mode_missing: approvedModeMissing,
  collection_empty: collectionEmpty,
  shadow_color_unresolved: shadowColorUnresolved,
  breakpoint_missing: breakpointSource === 'missing' ? [{ token: breakpointCfg.token ?? null }] : [],
};
const report = {
  transformerVersion: TRANSFORMER_VERSION,
  contract: { path: contractFile, sha256: contract.sha256, schema_version: C.schema_version },
  input: { source_dir: inputDir, file_suffix: fileSuffix, mode_resolution: modeResolution },
  files: files.map(rel),
  fileCount: files.length,
  tokenCount: tokens.length,
  collections: Object.fromEntries(Object.keys(cols).map((role) => [role, { files: roleFiles.get(role), emitted: KNOWN_ROLES.includes(role), modes: KNOWN_ROLES.includes(role) && role !== 'typography' && role !== 'sizes' && role !== 'effects' ? normModes(role) : undefined }])),
  unmappedFiles,
  productionAllowlist,
  auditOnlyModes,
  jsonErrors,
  duplicateFigmaVariableIds: duplicateIds,
  normalizedCasingConflicts: casingConflicts,
  nonCanonicalCasing,
  brokenAliasMetadata: brokenAliases,
  incompleteModes,
  invalidValues,
  auditRuleFindings,
  approvedModeMissing,
  collectionEmpty,
  shadowColorUnresolved,
  breakpoint: { token: breakpointCfg.token ?? null, fallback: breakpointCfg.fallback ?? null, source: breakpointSource, resolved: desktopBreakpoint },
  generatedBytes: Buffer.byteLength(css),
};
if (reportPath) { fs.mkdirSync(path.dirname(path.resolve(reportPath)), { recursive: true }); fs.writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n'); }
if (!dryRun) {
  fs.mkdirSync(path.dirname(path.resolve(output)), { recursive: true });
  fs.writeFileSync(output, css);
  // Generated-output manifest: records what this generator owns, with which
  // versions/inputs — the idempotency + change-only + safe-cleanup record.
  // Deterministic by construction (no timestamps): identical inputs+contract+
  // versions produce an identical manifest.
  const fileChecksums = {};
  for (const f of files) fileChecksums[rel(f)] = crypto.createHash('sha256').update(fs.readFileSync(f)).digest('hex');
  const manifest = {
    generator: { skill: 'hyva-global-style-foundation', transformer_version: TRANSFORMER_VERSION, contract_schema_version: CONTRACT_SCHEMA_VERSION },
    contract: { path: contractFile, sha256: contract.sha256, schema_version: C.schema_version },
    input: { source_dir: inputDir, files: fileChecksums },
    outputs: {
      generated_css: { path: path.resolve(output), ownership: 'generated', bytes: Buffer.byteLength(css), sha256: crypto.createHash('sha256').update(css).digest('hex') },
      ...(reportPath ? { audit_report: { path: path.resolve(reportPath), ownership: 'generated' } } : {}),
    },
    ownership: {
      immutable: ['token export files under input.source_dir (never mutate, never normalize in place)'],
      generated: ['outputs.generated_css', 'outputs.audit_report', 'this manifest (safe to replace; never manually edit)'],
      source_owned: ['semantic Tailwind/Hyva mapping layer (--ds-* -> --color-*/--form-*/--btn-*/*--outline-*)', 'component API + theme-specific source styles (regeneration must not overwrite)'],
    },
    approved_modes: { color: approved.color ?? null, brand: approved.brand ?? null, font: approved.font ?? null, typography: productionAllowlist.typographyModes },
    audit_only_modes: auditOnlyModes,
    findings: Object.fromEntries(SEVERITY_KINDS.map((k) => [k, { severity: severity[k], count: Array.isArray(findings[k]) ? findings[k].length : 0 }])),
    breakpoint: report.breakpoint,
  };
  const manifestPath = manifestArg ? path.resolve(manifestArg) : path.join(path.dirname(path.resolve(output)), 'design-tokens.manifest.json');
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n');
}
console.log(JSON.stringify(report));
const failedKinds = SEVERITY_KINDS.filter((k) => severity[k] === 'fail' && findings[k]?.length);
if (failedKinds.length) {
  console.error(`Generation failed (severity: fail): ${failedKinds.join(', ')} — see report for details.`);
  process.exitCode = 1;
}
