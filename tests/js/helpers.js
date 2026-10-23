// Carrega as páginas reais do sistema (HTML salvo por tests/js_test.php em HTML_DIR) no jsdom
// e executa os scripts de public/assets/js neles.
'use strict';
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '..', '..');
const htmlDir = process.env.HTML_DIR;
if (!htmlDir) {
    throw new Error('HTML_DIR não definido: rode estes testes por "php tests/js_test.php" (ele gera as páginas).');
}

const js = (name) => fs.readFileSync(path.join(root, 'public', 'assets', 'js', name), 'utf8');

/**
 * @param {string} page   nome do arquivo em HTML_DIR (login, admin, user, trash)
 * @param {string[]} scripts  scripts de public/assets/js a executar, na ordem da página
 * @param {object} opts   { before(window) } roda antes dos scripts (stubs)
 */
function load(page, scripts, opts = {}) {
    const html = fs.readFileSync(path.join(htmlDir, page + '.html'), 'utf8');
    const dom = new JSDOM(html, { url: 'http://localhost/index.php', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window;
    if (opts.before) opts.before(w);
    for (const s of scripts) w.eval(js(s));
    return { dom, window: w, document: w.document };
}

const click = (el) => el.dispatchEvent(new el.ownerDocument.defaultView.MouseEvent('click', { bubbles: true, cancelable: true }));
const change = (el) => el.dispatchEvent(new el.ownerDocument.defaultView.Event('change', { bubbles: true }));
const input = (el) => el.dispatchEvent(new el.ownerDocument.defaultView.Event('input', { bubbles: true }));

module.exports = { load, click, change, input, js, root, htmlDir };
