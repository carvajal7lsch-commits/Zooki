const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const raiz = path.resolve(__dirname, '../../..');
const php = process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php';
const entorno = { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' };
async function servidorInicioMovil() {
    const cache = new Map();
    const llamadas = [];
    const pendientes = [];
    let retener = false;
    function vista(caso, modo = 'pagina', consulta = '') {
        const clave = caso + '/' + modo + '/' + consulta;
        if (!cache.has(clave)) cache.set(clave, execFileSync(php, ['tests/Frontend/apoyo/inicio-admin-vista.php', caso, modo, consulta], { cwd: raiz, encoding: 'utf8', env: entorno }));
        return cache.get(clave);
    }
    const servidor = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            let html = vista(url.searchParams.get('caso') || 'dos');
            html = html.replace('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css','/__bootstrap.css');
            html = html.replace(/<script[^>]+src="https:[^>]*><\/script>/g,'').replace(/<link[^>]+href="https:[^>]*>/g,'');
            html = html.replace('</head>','<style>*{animation:none!important;transition:none!important}#global-loader{display:none!important}</style></head>');
            res.setHeader('Content-Type','text/html; charset=utf-8');
            res.end(html);
            return;
        }
        if (url.pathname === '/index.php') {
            res.setHeader('Content-Type','application/json');
            if (url.searchParams.get('action') !== 'panel_admin_dia_ajax') { res.end(JSON.stringify({success:true,notificaciones:[]})); return; }
            llamadas.push(url.search);
            const enviar = () => res.end(vista('dos','ajax',url.searchParams.toString()));
            if (retener && url.searchParams.has('fecha')) pendientes.push(enviar);
            else enviar();
            return;
        }
        if (url.pathname.startsWith('/__bootstrap')) {
            const css = url.pathname.endsWith('.css');
            const archivo = path.join(raiz,'scratch/usuarios-mockup',css?'bootstrap-icons.css':'bootstrap-icons.woff2');
            if (!fs.existsSync(archivo)) { res.end(''); return; }
            res.setHeader('Content-Type',css?'text/css':'font/woff2');
            const contenido = fs.readFileSync(archivo);
            res.end(css?contenido.toString().replace(/url\([^)]*\)/g,'url(/__bootstrap.woff2)'):contenido);
            return;
        }
        const archivo = path.resolve(raiz,'public','.'+url.pathname);
        if (!archivo.startsWith(path.join(raiz,'public')+path.sep) || !fs.existsSync(archivo)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type',archivo.endsWith('.css')?'text/css':archivo.endsWith('.js')?'application/javascript':'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r=>servidor.listen(0,'127.0.0.1',r));
    return { url:'http://127.0.0.1:'+servidor.address().port, llamadas, pendientes, retener: valor=>{retener=valor;}, cerrar:async()=>{servidor.closeAllConnections();await new Promise(r=>servidor.close(r));} };
}
module.exports = { servidorInicioMovil };
