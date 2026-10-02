#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const args = process.argv.slice(2);
const option = name => {
  const index = args.indexOf(name);
  return index === -1 ? null : args[index + 1];
};
const input = option('--input');
const contractPath = option('--contract');
const output = option('--output');
const reportPath = option('--report');
const dryRun = args.includes('--dry-run');

if (!input || !contractPath || (!dryRun && !output)) {
  console.error('Usage: token-transformer.mjs --input <dir> --contract <file> [--output <css>] [--report <json>] [--dry-run]');
  process.exit(2);
}

const inputRoot = path.resolve(input);
const contract = JSON.parse(fs.readFileSync(path.resolve(contractPath), 'utf8'));
const normalize = value => value.toLowerCase().replaceAll('_', '-').replace(/[^a-z0-9-]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
const relative = file => path.relative(inputRoot, file).replaceAll(path.sep, '/');
const collectionByFile = new Map();
for (const collection of contract.collections) {
  for (const file of collection.files) collectionByFile.set(file, collection);
}

const files = [];
const walk = directory => fs.readdirSync(directory, { withFileTypes: true })
  .sort((left, right) => left.name.localeCompare(right.name))
  .forEach(entry => {
    const file = path.join(directory, entry.name);
    if (entry.isDirectory()) walk(file);
    else if (entry.name.endsWith('.tokens.json')) files.push(file);
  });
walk(inputRoot);

const jsonErrors = [];
const tokens = [];
const visit = (value, parts, metadata) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return;
  if (Object.hasOwn(value, '$value')) {
    tokens.push({
      ...metadata,
      path: parts.join('/'),
      type: value.$type || typeof value.$value,
      value: value.$value,
      description: value.$description || '',
      extensions: value.$extensions || {}
    });
    return;
  }
  for (const key of Object.keys(value).sort()) {
    if (!key.startsWith('$')) visit(value[key], [...parts, key], metadata);
  }
};

for (const file of files) {
  try {
    const json = JSON.parse(fs.readFileSync(file, 'utf8'));
    const fileName = relative(file);
    visit(json, [], {
      file: fileName,
      mode: json.$extensions?.['com.figma.modeName'] || path.basename(file, '.tokens.json'),
      collection: collectionByFile.get(fileName) || null
    });
  } catch (error) {
    jsonErrors.push({ file: relative(file), error: error.message });
  }
}

const excludedFiles = new Set(contract.excluded.files);
const isExcluded = token => excludedFiles.has(token.file)
  || contract.excluded.pathPrefixes.some(prefix => `${token.file}:${token.path}`.startsWith(prefix));
const productionTokens = tokens.filter(token => token.collection && !isExcluded(token));
const missingContractFiles = [...collectionByFile.keys()].filter(file => !files.some(candidate => relative(candidate) === file));
const uncontractedFiles = files.map(relative).filter(file => !collectionByFile.has(file) && !excludedFiles.has(file));

const byId = new Map();
const byCollectionPath = new Map();
const normalizedLocations = new Map();
for (const token of tokens) {
  const id = token.extensions['com.figma.variableId'];
  if (id) (byId.get(id) || byId.set(id, []).get(id)).push(token);
  if (token.collection) {
    const key = `${token.collection.figmaName}:${token.path}`;
    (byCollectionPath.get(key) || byCollectionPath.set(key, []).get(key)).push(token);
  }
  const normalizedKey = `${token.file}:${normalize(token.path)}`;
  (normalizedLocations.get(normalizedKey) || normalizedLocations.set(normalizedKey, []).get(normalizedKey)).push(token);
}

const duplicateFigmaVariableIds = [...byId]
  .filter(([, matches]) => new Set(matches.map(token => token.path)).size > 1)
  .map(([id, matches]) => ({ id, locations: matches.map(token => `${token.file}:${token.path}`) }));
const normalizedCasingConflicts = [...normalizedLocations]
  .filter(([, matches]) => new Set(matches.map(token => token.path)).size > 1)
  .map(([key, matches]) => ({ key, paths: [...new Set(matches.map(token => token.path))] }));
const invalidValues = productionTokens
  .filter(token => token.value === null || token.value === '' || typeof token.value === 'undefined')
  .map(token => `${token.file}:${token.path}`);
const suspiciousButtonCtaZeros = tokens
  .filter(token => token.value === 0 && token.file.startsWith('buttons/') && token.path.startsWith('cta/'))
  .map(token => `${token.file}:${token.path}`);
const nonCanonicalCasing = productionTokens
  .filter(token => /[A-Z]/.test(token.path))
  .map(token => `${token.file}:${token.path}`);

const resolveAlias = token => {
  const alias = token.extensions['com.figma.aliasData'];
  if (!alias) return { target: null, method: null, error: null };
  const idMatches = byId.get(alias.targetVariableId) || [];
  if (idMatches.length === 1) return { target: idMatches[0], method: 'id', error: null };
  const exactMatches = byCollectionPath.get(`${alias.targetVariableSetName}:${alias.targetVariableName}`) || [];
  if (exactMatches.length === 1) return { target: exactMatches[0], method: 'collection-path', error: null };
  return {
    target: null,
    method: null,
    error: exactMatches.length > 1 ? 'ambiguous' : 'unresolved'
  };
};

const aliasResults = productionTokens
  .filter(token => token.extensions['com.figma.aliasData'])
  .map(token => ({ token, ...resolveAlias(token) }));
const unresolvedProductionAliases = aliasResults
  .filter(result => result.error)
  .map(result => ({
    location: `${result.token.file}:${result.token.path}`,
    targetCollection: result.token.extensions['com.figma.aliasData'].targetVariableSetName,
    targetPath: result.token.extensions['com.figma.aliasData'].targetVariableName,
    reason: result.error
  }));

const variableName = token => {
  const layer = token.collection.layer;
  if (layer === 'typography') return `--ds-type-${normalize(token.path)}`;
  if (layer === 'button') return `--ds-button-${normalize(token.mode)}-${normalize(token.path)}`;
  if (layer === 'size') return `--ds-size-${normalize(token.path)}`;
  if (layer === 'effect') return `--ds-effect-${normalize(token.path)}`;
  return `--ds-${normalize(token.path)}`;
};
const primitiveValue = token => {
  if (token.type === 'color') {
    if (token.value?.components) {
      const [red, green, blue] = token.value.components.map(component => Math.round(component * 255));
      return `rgb(${red} ${green} ${blue} / ${token.value.alpha ?? 1})`;
    }
    if (token.value?.hex) return token.value.hex.toUpperCase();
  }
  if (typeof token.value === 'number') {
    const value = Number(token.value.toFixed?.(4) ?? token.value);
    // font-weight is unitless in CSS; match it as a path SEGMENT (the old
    // /weight$/ anchor missed tokens like global/typography/font-weight/base
    // and emitted "400px", which browsers drop).
    return /(?:^|\/)(?:font-)?weight(?:\/|$)/.test(token.path) ? String(value) : `${value}px`;
  }
  return String(token.value);
};
const cssValue = token => {
  const resolved = resolveAlias(token);
  return resolved.target ? `var(${variableName(resolved.target)})` : primitiveValue(token);
};
const declarationBlock = (selector, list) => {
  const declarations = list.map(token => `  ${variableName(token)}: ${cssValue(token)};`);
  return `${selector} {\n${[...new Set(declarations)].join('\n')}\n}`;
};
const tokensForLayer = layer => productionTokens.filter(token => token.collection.layer === layer);
const typographyMode = mode => tokensForLayer('typography').filter(token => token.mode.toLowerCase() === mode.toLowerCase());

const rootTokens = productionTokens.filter(token => ['primitive', 'semantic', 'brand', 'color-mode', 'size', 'effect', 'button'].includes(token.collection.layer));
const css = [
  '/** GENERATED FILE — DO NOT EDIT. Source: immutable Figma Variables export. */',
  declarationBlock(':root, :host', rootTokens),
  declarationBlock(':root, :host', typographyMode(contract.production.typographyModes[0])),
  `@media (min-width: ${contract.breakpoints.tablet}) {\n${declarationBlock(':root, :host', typographyMode(contract.production.typographyModes[1]))}\n}`,
  `@media (min-width: ${contract.breakpoints.desktop}) {\n${declarationBlock(':root, :host', typographyMode(contract.production.typographyModes[2]))}\n}`,
  ''
].join('\n\n');

const report = {
  contractVersion: contract.version,
  files: files.map(relative),
  fileCount: files.length,
  tokenCount: tokens.length,
  productionTokenCount: productionTokens.length,
  productionModes: contract.production,
  excluded: contract.excluded,
  jsonErrors,
  missingContractFiles,
  uncontractedFiles,
  duplicateFigmaVariableIds,
  normalizedCasingConflicts,
  nonCanonicalCasing,
  invalidValues,
  suspiciousButtonCtaZeros,
  aliasResolution: {
    total: aliasResults.length,
    byId: aliasResults.filter(result => result.method === 'id').length,
    byCollectionPath: aliasResults.filter(result => result.method === 'collection-path').length
  },
  unresolvedProductionAliases,
  generatedBytes: Buffer.byteLength(css)
};

if (reportPath) {
  fs.mkdirSync(path.dirname(path.resolve(reportPath)), { recursive: true });
  fs.writeFileSync(path.resolve(reportPath), `${JSON.stringify(report, null, 2)}\n`);
}
if (!dryRun) {
  fs.mkdirSync(path.dirname(path.resolve(output)), { recursive: true });
  fs.writeFileSync(path.resolve(output), css);
}
console.log(JSON.stringify(report));

const fatal = jsonErrors.length
  || missingContractFiles.length
  || uncontractedFiles.length
  || duplicateFigmaVariableIds.length
  || normalizedCasingConflicts.length
  || invalidValues.length
  || unresolvedProductionAliases.length;
if (fatal) process.exitCode = 1;
