/** TASK-QX93G3 verify helpers */
const fs = require('fs');
const OUT = __dirname;

const seen = new Set();
function log(name, pass, detail = '') {
    const key = name;
    if (seen.has(key)) return;
    seen.add(key);
    console.log(`${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`);
    fs.appendFileSync(`${OUT}/results.log`, `${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}\n`);
}

function trackConsole(page, bucket) {
    page.on('console', m => m.type() === 'error' && bucket.push(m.text()));
}

module.exports = { BASE: 'http://slaunchpad.localhost', OUT, log, trackConsole, sleep: ms => new Promise(r => setTimeout(r, ms)) };
