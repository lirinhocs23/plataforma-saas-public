const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = readFileSync('public/assets/store-status.js', 'utf8');
async function scenario(initial, next, owner = false) {
    let callback, reloads = 0;
    const events = {};
    vm.runInNewContext(source, {
        document: {body: {dataset: {store: 'teste', storeOpen: initial ? '1' : '0', ownerEditor: owner ? '1' : '0'}}, hidden: false, addEventListener: (name, fn) => {events[name] = fn;}},
        window: {addEventListener: (name, fn) => {events[name] = fn;}},
        navigator: {onLine: true},
        location: {reload: () => reloads++},
        fetch: async () => ({ok: true, json: async () => ({store_open: next})}),
        setInterval: (fn, delay) => {assert.equal(delay, 30000); callback = fn;},
    });
    if (callback) await callback();
    return reloads;
}
(async () => {
    assert.equal(await scenario(true, false), 1, 'deve ocultar catálogo ao fechar');
    assert.equal(await scenario(false, true), 1, 'deve reabrir catálogo');
    assert.equal(await scenario(true, true), 0, 'não deve recarregar sem mudança');
    assert.equal(await scenario(false, false), 0);
    assert.equal(await scenario(false, true, true), 0, 'não deve interromper editor');
    console.log('OK - abertura, fechamento, estado estável e editor');
})().catch(error => {console.error(error); process.exitCode = 1;});

