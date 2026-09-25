// Extracts Virastar.js's own test cases into tests/fixtures/virastar-js-cases.json (I11).
// Usage: git clone https://github.com/brothersincode/virastar /tmp/virastar-js
//        cd /tmp/virastar-js && node <this repo>/tests/tools/extract-virastar-js-cases.js > <this repo>/tests/fixtures/virastar-js-cases.json
const Module = require('module');
const path = require('path');
const cases = [];
let current = [];
const fakeVirastar = function () {};
fakeVirastar.prototype.cleanup = (input, options) => ({ __case: true, input, options: options || {} });
const fakeAssert = {
  strictEqual(actual, expected) {
    if (actual && actual.__case) cases.push({ name: current.join(' › '), input: actual.input, options: actual.options, expected });
  },
  equal(actual, expected) { fakeAssert.strictEqual(actual, expected); },
  ok() {},
};
const sprintf = (fmt, ...args) => { let i = 0; return fmt.replace(/%s/g, () => String(args[i++])); };
const origLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'assert') return fakeAssert;
  if (request.endsWith('lib/virastar.js') || request.endsWith('/virastar')) return fakeVirastar;
  if (request === 'sprintf-js') return { sprintf };
  return origLoad.apply(this, arguments);
};
global.describe = (name, fn) => { current.push(name); fn(); current.pop(); };
global.it = (name, fn) => { current.push(name); try { fn(); } catch (e) { console.error('skip', name, e.message); } current.pop(); };
require(path.resolve('test/virastar.js'));
process.stdout.write(JSON.stringify({ source: 'https://github.com/brothersincode/virastar test/virastar.js', version: require(path.resolve('package.json')).version, cases }, null, 1));
