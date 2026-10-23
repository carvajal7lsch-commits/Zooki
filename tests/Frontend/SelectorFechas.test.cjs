const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('Calendario compartido conserva nacimiento y permite fecha/hora futura para ausencias', () => {
    const llamadas = [];
    const contexto = { window: {}, flatpickr: (selector, config) => { llamadas.push({selector,config}); return 'calendario'; }, console };
    vm.runInNewContext(fs.readFileSync('public/js/selector-fechas.js','utf8'),contexto);
    const iniciar = contexto.window.SelectorFechas.iniciar;
    assert.equal(iniciar(),'calendario');
    assert.equal(llamadas[0].selector,'.flatpickr-date');
    assert.equal(llamadas[0].config.maxDate,'today');
    assert.equal(llamadas[0].config.dateFormat,'Y-m-d');
    assert.equal(llamadas[0].config.disableMobile,true);
    iniciar('ausencia',{maxDate:null,enableTime:true,enableSeconds:true,dateFormat:'Y-m-d\\TH:i:S',static:true});
    assert.equal(llamadas[1].config.maxDate,null);
    assert.equal(llamadas[1].config.enableTime,true);
    assert.equal(llamadas[1].config.enableSeconds,true);
    assert.equal(llamadas[1].config.static,true);
    assert.equal(typeof llamadas[1].config.onReady,'function');
    assert.equal(llamadas[1].config.dateFormat,'Y-m-d\\TH:i:S');
    const vista=fs.readFileSync('views/partials/ausencias_agenda.php','utf8');
    assert.doesNotMatch(vista,/type="datetime-local"|\s(?:style|onclick)=/);
    assert.equal((vista.match(/data-fecha-ausencia/g)||[]).length,2);
    assert.match(fs.readFileSync('public/js/medical-module.js','utf8'),/window.SelectorFechas\?\.iniciar\(\)/);
});
