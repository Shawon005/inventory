// Synthetic controller fixtures only. This server never forwards commands to the application database.
const http = require('http'), fs = require('fs'), path = require('path');
const D = require('../../public/modules/manufacturing/decimal.js');
const publicRoot = path.resolve(__dirname, '../../public'), preview = path.resolve(__dirname, '../../storage/framework/testing/b2b-frontend-preview');
const responses = JSON.parse(fs.readFileSync(path.join(preview, 'api.json'), 'utf8')), calls = [];
const pages = {'/b2b':'dashboard','/b2b/products':'products','/b2b/inventory':'inventory','/b2b/transfers':'transfers','/b2b/pos':'pos','/b2b/sales':'sales','/b2b/customers':'customers','/b2b/reports':'reports'};
const invoicePath = Object.keys(responses).find(k => k.startsWith('/b2b/invoices/'));
let transferAttempt = 0;
const server = http.createServer((request,response) => {
    const url = new URL(request.url,'http://127.0.0.1:8781');
    if(url.pathname === '/__requests'){response.setHeader('Content-Type','application/json');return response.end(JSON.stringify(calls));}
    if(url.pathname === '/__shutdown'){response.end('closed');return server.close();}
    if(['POST','PATCH'].includes(request.method)){
        let raw='';request.on('data',c=>raw+=c);request.on('end',()=>{
            const payload=JSON.parse(raw);calls.push({path:url.pathname,key:request.headers['idempotency-key'],payload});response.setHeader('Content-Type','application/json');
            if(url.pathname === '/b2b/transfers' && ++transferAttempt === 1){response.statusCode=503;return response.end(JSON.stringify({message:'Temporary fixture failure. Retry the transfer.'}));}
            if(url.pathname === '/b2b/transfers'){
                for(const item of payload.items){const product=responses['/b2b/data/products'].data.find(p=>p.id===item.variation_id);product.main_quantity=D.subtract(product.main_quantity,item.quantity);product.b2b_quantity=D.sum([product.b2b_quantity,item.quantity]);}
                response.statusCode=201;return response.end(JSON.stringify({transfer_id:2,reference:'B2B-T-FIXTURE'}));
            }
            if(url.pathname === '/b2b/sales'){
                let total=0n;for(const item of payload.items){const product=responses['/b2b/data/products'].data.find(p=>p.id===item.variation_id);product.b2b_quantity=D.subtract(product.b2b_quantity,item.quantity);total+=(D.parse(item.quantity)*D.parse(item.unit_price)+5000n)/10000n;}
                response.statusCode=201;return response.end(JSON.stringify({transaction_id:Number(invoicePath.split('/').pop()),invoice_no:'B2B-INV-FIXTURE',total:D.format(total),due:'0.0000'}));
            }
            response.statusCode=422;response.end(JSON.stringify({message:'Fixture command not supported.'}));
        });return;
    }
    if((pages[url.pathname] || url.pathname===invoicePath) && !String(request.headers.accept).includes('application/json')){
        response.setHeader('Content-Type','text/html');return fs.createReadStream(path.join(preview,(pages[url.pathname] || 'invoice')+'.html')).pipe(response);
    }
    if(responses[url.pathname]){let data=JSON.parse(JSON.stringify(responses[url.pathname]));if(url.pathname==='/b2b/data/products'&&url.searchParams.get('id')){data.data=data.data.filter(p=>String(p.id)===url.searchParams.get('id'));data.total=data.data.length;}response.setHeader('Content-Type','application/json');return response.end(JSON.stringify(data));}
    const file=path.resolve(publicRoot,'.'+decodeURIComponent(url.pathname));
    if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){response.statusCode=404;return response.end();}
    const mime={'.js':'application/javascript','.css':'text/css','.woff2':'font/woff2','.woff':'font/woff','.ttf':'font/ttf','.png':'image/png'};response.setHeader('Content-Type',mime[path.extname(file)]||'application/octet-stream');fs.createReadStream(file).pipe(response);
});
server.listen(8781,'127.0.0.1',()=>process.stdout.write('B2B synthetic browser fixtures: http://127.0.0.1:8781\n'));
const deadline=setTimeout(()=>server.close(),300000);deadline.unref();
