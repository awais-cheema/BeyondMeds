import fs from 'node:fs/promises';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';
const root=path.dirname(fileURLToPath(import.meta.url));
const types={'.webp':'image/webp','.jpg':'image/jpeg','.png':'image/png','.mp4':'video/mp4','.svg':'image/svg+xml','.woff2':'font/woff2','.ttf':'font/ttf'};
const data=async p=>'data:'+(types[path.extname(p)]||'application/octet-stream')+';base64,'+(await fs.readFile(path.join(root,p))).toString('base64');
let css=await fs.readFile(path.join(root,'styles.css'),'utf8');
try{await fs.access(path.join(root,'assets/syne.woff2'));css=css.replace('src:local("Syne")','src:url("assets/syne.woff2") format("woff2")');}catch{}
for(const match of [...css.matchAll(/url\("([^"]+)"\)/g)])css=css.replace(match[0],'url("'+await data(match[1])+'")');
let html=await fs.readFile(path.join(root,'index.html'),'utf8');
html=html.replace('<link rel="stylesheet" href="styles.css">','<style>'+css+'</style>');
html=html.replace('<link rel="stylesheet" href="hero.css">','<style>'+await fs.readFile(path.join(root,'hero.css'),'utf8')+'</style>');
for(const name of ['vendor/lenis.css','polish.css']){const cssText=await fs.readFile(path.join(root,name),'utf8');html=html.replace('<link rel="stylesheet" href="'+name+'">',()=>'<style>'+cssText+'</style>');}
for(const name of ['vendor/lenis.min.js','polish.js'])html=html.replace('<script defer src="'+name+'"></script>','');
html=html.replace('<script defer src="hero.js"></script>','').replace('<script defer src="hero-frames.js"></script>','');
html=html.replaceAll('poster="assets/hero-poster.png"','poster="'+await data('assets/hero-poster.png')+'"');
html=html.replace('<script defer src="config.js"></script>','').replace('<script defer src="app.js"></script>','');
for(const match of [...html.matchAll(/src="(assets\/[^"]+)"/g)])html=html.replace(match[0],'src="'+await data(match[1])+'"');
html=html.replace('href="assets/favicon.jpg"','href="'+await data('assets/favicon.jpg')+'"');
const frameNames=(await fs.readdir(path.join(root,'assets/teal-frames'))).filter(n=>n.endsWith('.webp')).sort();
const embeddedFrames=await Promise.all(frameNames.map(n=>data('assets/teal-frames/'+n)));
const lenisCode=await fs.readFile(path.join(root,'vendor/lenis.min.js'),'utf8');
const polishCode=await fs.readFile(path.join(root,'polish.js'),'utf8');
const scripts='<script>'+lenisCode+'</script><script>window.BEYONDMEDS_HERO_FRAMES='+JSON.stringify(embeddedFrames)+'</script><script>'+await fs.readFile(path.join(root,'config.js'),'utf8')+'</script><script>'+await fs.readFile(path.join(root,'app.js'),'utf8')+'</script><script>'+await fs.readFile(path.join(root,'hero.js'),'utf8')+'</script><script>'+polishCode+'</script></body>';
html=html.replace('</body>',()=>scripts);
const previewPath=path.join(root,'..','BeyondMeds-Teal-Preview.html');
await fs.writeFile(previewPath,html);
const table=Array.from({length:256},(_,n)=>{let c=n;for(let k=0;k<8;k++)c=(c&1)?0xedb88320^(c>>>1):c>>>1;return c>>>0;});
const crc=b=>{let c=0xffffffff;for(const v of b)c=table[(c^v)&255]^(c>>>8);return (c^0xffffffff)>>>0;};
async function files(dir,prefix=''){let result=[];for(const e of await fs.readdir(dir,{withFileTypes:true})){if(e.name.startsWith('.'))continue;const p=path.join(dir,e.name),n=prefix+e.name;if(e.isDirectory())result.push(...await files(p,n+'/'));else result.push({name:'BeyondMeds-Updated/'+n,bytes:await fs.readFile(p)});}return result;}
const entries=await files(root),locals=[],centrals=[];let offset=0;
for(const e of entries){const name=Buffer.from(e.name),packed=zlib.deflateRawSync(e.bytes),sum=crc(e.bytes);const local=Buffer.alloc(30);local.writeUInt32LE(0x04034b50);local.writeUInt16LE(20,4);local.writeUInt16LE(0x800,6);local.writeUInt16LE(8,8);local.writeUInt16LE(0x5d55,12);local.writeUInt32LE(sum,14);local.writeUInt32LE(packed.length,18);local.writeUInt32LE(e.bytes.length,22);local.writeUInt16LE(name.length,26);locals.push(local,name,packed);const central=Buffer.alloc(46);central.writeUInt32LE(0x02014b50);central.writeUInt16LE(20,4);central.writeUInt16LE(20,6);central.writeUInt16LE(0x800,8);central.writeUInt16LE(8,10);central.writeUInt16LE(0x5d55,14);central.writeUInt32LE(sum,16);central.writeUInt32LE(packed.length,20);central.writeUInt32LE(e.bytes.length,24);central.writeUInt16LE(name.length,28);central.writeUInt32LE(offset,42);centrals.push(central,name);offset+=30+name.length+packed.length;}
const directory=Buffer.concat(centrals),end=Buffer.alloc(22);end.writeUInt32LE(0x06054b50);end.writeUInt16LE(entries.length,8);end.writeUInt16LE(entries.length,10);end.writeUInt32LE(directory.length,12);end.writeUInt32LE(offset,16);const zipPath=path.join(root,'..','BeyondMeds-Teal.zip');await fs.writeFile(zipPath,Buffer.concat([...locals,directory,end]));console.log(JSON.stringify({preview:previewPath,zip:zipPath,files:entries.length}));
