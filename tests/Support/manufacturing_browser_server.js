// Local browser fixtures only. No requests are forwarded to the application database.
const http = require('http'), fs = require('fs'), path = require('path');
const publicRoot = path.resolve(__dirname, '../../public');
const fixtures = path.resolve(__dirname, '../../storage/framework/testing/mfg-frontend-preview');
const responses = JSON.parse(fs.readFileSync(path.join(fixtures, 'api.json'), 'utf8'));
const calls = [];
const pages = {'/manufacturing': 'dashboard', '/manufacturing/boms': 'boms', '/manufacturing/raw-materials': 'materials', '/manufacturing/production-orders': 'orders', '/manufacturing/production-orders/1': 'order'};
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://127.0.0.1:8769');
    if (url.pathname === '/__requests') { response.setHeader('Content-Type', 'application/json'); return response.end(JSON.stringify(calls)); }
    if (url.pathname === '/__shutdown') { response.end('closed'); return server.close(); }
    if (request.method === 'POST' || request.method === 'PATCH') {
        let body = ''; request.on('data', chunk => body += chunk); request.on('end', () => {
            calls.push({path: url.pathname, key: request.headers['idempotency-key'], payload: JSON.parse(body)});
            response.setHeader('Content-Type', 'application/json'); response.statusCode = calls.length === 1 ? 503 : 422;
            response.end(JSON.stringify(calls.length === 1 ? {message: 'Temporary fixture failure. Retry the same operation.'} : {errors: {items: ['Fixture validation: review the material quantities.']}}));
        }); return;
    }
    if (pages[url.pathname] && !String(request.headers.accept).includes('application/json')) {
        response.setHeader('Content-Type', 'text/html'); return fs.createReadStream(path.join(fixtures, pages[url.pathname] + '.html')).pipe(response);
    }
    const key = Object.keys(responses).find(key => key === url.pathname + url.search) || Object.keys(responses).find(key => key.split('?')[0] === url.pathname);
    if (key) {
        let data = JSON.parse(JSON.stringify(responses[key]));
        if (url.pathname === '/manufacturing/products' && url.searchParams.get('type')) { data.data = data.data.filter(p => p.manufacturing_type === url.searchParams.get('type')); data.total = data.data.length; }
        response.setHeader('Content-Type', 'application/json'); return response.end(JSON.stringify(data));
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { response.statusCode = 404; return response.end(); }
    const mime = {'.js': 'application/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf', '.png': 'image/png'};
    response.setHeader('Content-Type', mime[path.extname(file)] || 'application/octet-stream'); fs.createReadStream(file).pipe(response);
});
server.listen(8769, '127.0.0.1', () => process.stdout.write('Manufacturing browser fixtures: http://127.0.0.1:8769\n'));
const deadline = setTimeout(() => server.close(), 300000); deadline.unref();
