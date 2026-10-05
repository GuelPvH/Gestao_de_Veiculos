import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const diretorio=process.env.FLEET_UI_EXPORT_DIR;
const destino=process.env.FLEET_UI_REPORT_DIR;
if(!diretorio || !destino) throw new Error('Defina diretórios privados de exportação e relatório, fora do Git.');
await fs.mkdir(destino,{recursive:true});
const inventario=JSON.parse(await fs.readFile(path.join(diretorio,'pages.json'),'utf8'));
const filtro=process.env.FLEET_UI_FILTER ? new RegExp(process.env.FLEET_UI_FILTER) : null;
const paginas=inventario.pages.filter(tela=>!filtro || filtro.test(`${tela.profile}:${tela.name}`));
if(!paginas.length) throw new Error('Nenhuma tela corresponde ao filtro solicitado.');
const argumentos=process.env.FLEET_CHROMIUM_PATH ? ['--no-sandbox','--single-process','--no-zygote','--disable-gpu','--disable-software-rasterizer','--disable-dev-shm-usage'] : [];
const navegador=await chromium.launch({executablePath:process.env.FLEET_CHROMIUM_PATH || undefined,args:argumentos});
const contexto=await navegador.newContext({viewport:{width:1440,height:900},reducedMotion:'reduce'});
const pagina=await contexto.newPage();
const erros=[];const checagens=[];const acessibilidade=[];let atual=paginas[0];
const assets=new Map();
pagina.on('pageerror',erro=>erros.push({screen:atual.name,error:erro.message}));
pagina.on('response',resposta=>{if(resposta.status()>=400 && /\/(build|images|icons)\//.test(resposta.url())) erros.push({screen:atual.name,error:`Asset ${resposta.status()}: ${new URL(resposta.url()).pathname}`});});
const tipos={'.css':'text/css','.js':'application/javascript','.woff':'font/woff','.woff2':'font/woff2','.png':'image/png','.svg':'image/svg+xml'};
await contexto.route('**/*',async rota=>{
 const url=new URL(rota.request().url());
 if(url.hostname!=='localhost') return rota.abort();
 if(rota.request().method()!=='GET') return rota.fulfill({status:405,body:'Operação não persistida na revisão de interface.'});
 if(url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/') || url.pathname.startsWith('/icons/')) {
  const arquivo=path.resolve('public','.'+url.pathname);
  if(!arquivo.startsWith(path.resolve('public')+path.sep)) return rota.abort();
  try{if(!assets.has(arquivo)) assets.set(arquivo,fs.readFile(arquivo));return await rota.fulfill({body:await assets.get(arquivo),contentType:tipos[path.extname(arquivo)] || 'application/octet-stream'});}catch{return rota.fulfill({status:404,body:'Asset ausente'});}
 }
 const principal=rota.request().isNavigationRequest() && rota.request().frame()===pagina.mainFrame();
 const alvo=principal && url.pathname===new URL(atual.url,'http://localhost').pathname ? atual : paginas.find(item=>item.profile===atual.profile && new URL(item.url,'http://localhost').pathname===url.pathname);
 return rota.fulfill({body:await fs.readFile((alvo || atual).file),contentType:'text/html'});
});
let aberta=null;
async function abrir(tela,largura,tema,zoom=1) {
 atual=tela;await pagina.setViewportSize({width:largura,height:900});
 if(aberta!==tela.file) {
  const url=new URL(tela.url,'http://localhost');url.searchParams.set('tema',tema);
  await pagina.goto(url.toString(),{waitUntil:'load'});await pagina.evaluate(()=>document.fonts.ready);aberta=tela.file;
 }
 await pagina.evaluate(({tema,zoom})=>{document.documentElement.dataset.bsTheme=tema;document.documentElement.style.zoom=String(zoom);},{tema,zoom});
 await pagina.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
}
const limite=Number(process.env.FLEET_UI_LIMIT || paginas.length);
for(const tela of paginas.slice(0,limite)) {
 for(const largura of [1440,768,390,320]) for(const tema of ['light','dark']) {
  await abrir(tela,largura,tema);
  const estado=await pagina.evaluate(()=>{
   const ids=[...document.querySelectorAll('[id]')].map(no=>no.id);
   const duplicados=ids.filter((id,i)=>ids.indexOf(id)!==i);
   const larguraPagina=Math.max(document.documentElement.scrollWidth,document.body.scrollWidth);
   const textos=document.body.innerText;
   const quebrados=[...document.images].filter(imagem=>!imagem.complete || imagem.naturalWidth===0).map(imagem=>imagem.getAttribute('src'));
   const estilos=[...document.querySelectorAll('link[rel=stylesheet]')].every(link=>link.sheet!==null);
   return {overflow:larguraPagina>innerWidth+2,width:larguraPagina,duplicates:duplicados,brokenImages:quebrados,stylesLoaded:estilos,removedLabels:/(demonstração|Sejus-RO|Restaurar dados|Ajuda e orientações)/i.test(textos),theme:document.documentElement.dataset.bsTheme};
  });
  checagens.push({profile:tela.profile,screen:tela.name,width:largura,theme:tema,...estado});
 }
 if(checagens.length%80===0) process.stdout.write(JSON.stringify({checked:checagens.length,screen:tela.name})+'\n');
}
const representativas=paginas.filter(tela=>['login','dashboard','requests-index','requests-create','trips-detail','fines-proof','expenses-create','users-detail','roles-detail','account','reports','review'].includes(tela.name));
for(const tela of representativas) for(const tema of ['light','dark']) {
 process.stdout.write(JSON.stringify({accessibility:tela.name,profile:tela.profile,theme:tema})+'\n');
 await abrir(tela,390,tema);
 const analise=await new AxeBuilder({page:pagina}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
 acessibilidade.push({profile:tela.profile,screen:tela.name,theme:tema,violations:analise.violations.map(item=>({id:item.id,impact:item.impact,nodes:item.nodes.map(no=>no.target)}))});
 if(['login','dashboard','requests-create','account','roles-detail'].includes(tela.name)) await pagina.screenshot({path:path.join(destino,`${tela.profile}-${tela.name}-${tema}-390.png`),fullPage:true});
 await abrir(tela,1440,tema);await pagina.screenshot({path:path.join(destino,`${tela.profile}-${tela.name}-${tema}-1440.png`),fullPage:true});
 for(const largura of [1440,320]) {
  await abrir(tela,largura,tema,2);
  const estado=await pagina.evaluate(()=>({overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+2,excess:[...document.querySelectorAll('body *')].filter(no=>{const caixa=no.getBoundingClientRect();return caixa.width && caixa.right>innerWidth+2;}).map(no=>({tag:no.tagName,id:no.id,class:no.className,right:Math.round(no.getBoundingClientRect().right)})).slice(0,12)}));
  checagens.push({profile:tela.profile,screen:tela.name,width:largura,theme:tema,zoom:2,...estado});
  if(estado.overflow) await pagina.screenshot({path:path.join(destino,`${tela.profile}-${tela.name}-${tema}-${largura}-zoom200.png`),fullPage:true});
 }
}
const interacoes=[];
const gestor=paginas.find(tela=>tela.profile==='gestor' && tela.name==='dashboard');
await abrir(gestor,390,'light');
await pagina.getByRole('button',{name:'Abrir menu',exact:true}).click();
await pagina.waitForSelector('#fleet-menu.show');
interacoes.push({test:'offcanvas opens',passed:await pagina.locator('#fleet-menu').evaluate(no=>no.contains(document.activeElement))});
await pagina.keyboard.press('Escape');await pagina.waitForSelector('#fleet-menu.show',{state:'hidden'});
interacoes.push({test:'offcanvas Escape returns focus',passed:await pagina.getByRole('button',{name:'Abrir menu',exact:true}).evaluate(no=>no===document.activeElement)});
await pagina.getByRole('button',{name:'Opções da conta',exact:true}).click();
await pagina.keyboard.press('ArrowDown');
interacoes.push({test:'dropdown keyboard',passed:await pagina.locator('.dropdown-menu.show').evaluate(no=>no.contains(document.activeElement))});
await pagina.keyboard.press('Escape');
interacoes.push({test:'dropdown Escape returns focus',passed:await pagina.getByRole('button',{name:'Opções da conta',exact:true}).evaluate(no=>no===document.activeElement)});
const formulario=paginas.find(tela=>tela.profile==='servidor' && tela.name==='requests-create');
await abrir(formulario,390,'light');
await pagina.getByRole('button',{name:'Continuar',exact:true}).click();
interacoes.push({test:'wizard rejects empty stage',passed:await pagina.locator('#field-finalidade').evaluate(no=>no===document.activeElement)});
for(const [nome,valor] of [['finalidade','Atividade institucional'],['origem','Sede'],['destino','Unidade'],['saida_prevista','2026-10-05T09:00'],['retorno_previsto','2026-10-05T18:00']]) await pagina.locator(`[name="${nome}"]`).fill(valor);
await pagina.getByRole('button',{name:'Continuar',exact:true}).click();await pagina.locator('[name=necessita_motorista]').selectOption('1');
await pagina.getByRole('button',{name:'Continuar',exact:true}).click();if(await pagina.locator('[name=veiculo_pretendido_id]').count()) await pagina.locator('[name=veiculo_pretendido_id]').selectOption('1');await pagina.getByRole('button',{name:'Continuar',exact:true}).click();
await pagina.getByRole('button',{name:'Revisar',exact:true}).click();await pagina.waitForSelector('#operation-review.show');
interacoes.push({test:'review modal traps focus and confirmation enabled',passed:await pagina.locator('#operation-review').evaluate(no=>no.contains(document.activeElement)) && await pagina.locator('#operation-review').getByRole('button',{name:'Confirmar',exact:true}).isEnabled()});
await pagina.keyboard.press('Escape');await pagina.waitForSelector('#operation-review.show',{state:'hidden'});
await pagina.waitForFunction(()=>document.querySelector('[data-review-submit]')===document.activeElement);
interacoes.push({test:'modal Escape returns focus',passed:await pagina.getByRole('button',{name:'Revisar',exact:true}).evaluate(no=>no===document.activeElement)});
await pagina.getByRole('link',{name:'Cancelar',exact:true}).click();await pagina.waitForSelector('#discard-changes.show');
interacoes.push({test:'dirty form discard dialog',passed:await pagina.locator('#discard-changes').evaluate(no=>no.contains(document.activeElement))});
await pagina.getByRole('button',{name:'Continuar editando',exact:true}).click();
const resultado={browser:navegador.version(),source:'Laravel Blade + fixtures de leitura isoladas; não comprova sessão/procedures MySQL',method:'Cada documento é carregado com assets locais; a viewport, o tema Bootstrap e o zoom variam sobre o DOM carregado.',screens:paginas.length,checks:checagens,accessibility:acessibilidade,interactions:interacoes,errors:erros};
await fs.writeFile(path.join(destino,'browser-report.json'),JSON.stringify(resultado,null,2));
await navegador.close();
const falhas=checagens.filter(item=>item.overflow || item.duplicates?.length || item.brokenImages?.length || item.removedLabels || item.stylesLoaded===false).length+acessibilidade.filter(item=>item.violations.length).length+interacoes.filter(item=>!item.passed).length+erros.length;
process.stdout.write(JSON.stringify({screens:paginas.length,checks:checagens.length,axe:acessibilidade.length,interactions:interacoes.length,failures:falhas})+'\n');
process.exitCode=falhas ? 1 : 0;
