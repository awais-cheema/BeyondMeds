import fs from 'node:fs/promises';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';
const root=path.dirname(fileURLToPath(import.meta.url));
let html=await fs.readFile(path.join(root,'index.html'),'utf8');
html=html.replace(/(src|href|poster|data-hd|data-mobile)="(?!https?:|#|data:)([^"\s]+)"/g,(_,attr,url)=>attr+'="BeyondMeds/'+url+'"');
html=html.replace(/srcset="([^"]+)"/g,(_,value)=>'srcset="'+value.split(',').map(s=>'BeyondMeds/'+s.trim()).join(', ')+'"');
const previewPath=path.join(root,'..','BeyondMeds-October26.html');
await fs.writeFile(previewPath,html);
const table=Array.from({length:256},(_,n)=>{let c=n;for(let k=0;k<8;k++)c=(c&1)?0xedb88320^(c>>>1):c>>>1;return c>>>0;});
const crc=b=>{let c=0xffffffff;for(const v of b)c=table[(c^v)&255]^(c>>>8);return (c^0xffffffff)>>>0;};
async function files(dir,prefix=''){let result=[];for(const e of await fs.readdir(dir,{withFileTypes:true})){if(e.name.startsWith('.')||['teal-frames','hero-playback','hero-scroll.mp4','hero-poster.png'].includes(e.name))continue;const p=path.join(dir,e.name),n=prefix+e.name;if(e.isDirectory())result.push(...await files(p,n+'/'));else result.push({name:'BeyondMeds-Updated/'+n,bytes:await fs.readFile(p)});}return result;}
const entries=await files(root),locals=[],centrals=[];let offset=0;
for(const e of entries){const name=Buffer.from(e.name),packed=zlib.deflateRawSync(e.bytes),sum=crc(e.bytes);const local=Buffer.alloc(30);local.writeUInt32LE(0x04034b50);local.writeUInt16LE(20,4);local.writeUInt16LE(0x800,6);local.writeUInt16LE(8,8);local.writeUInt16LE(0x5d55,12);local.writeUInt32LE(sum,14);local.writeUInt32LE(packed.length,18);local.writeUInt32LE(e.bytes.length,22);local.writeUInt16LE(name.length,26);locals.push(local,name,packed);const central=Buffer.alloc(46);central.writeUInt32LE(0x02014b50);central.writeUInt16LE(20,4);central.writeUInt16LE(20,6);central.writeUInt16LE(0x800,8);central.writeUInt16LE(8,10);central.writeUInt16LE(0x5d55,14);central.writeUInt32LE(sum,16);central.writeUInt32LE(packed.length,20);central.writeUInt32LE(e.bytes.length,24);central.writeUInt16LE(name.length,28);central.writeUInt32LE(offset,42);centrals.push(central,name);offset+=30+name.length+packed.length;}
const directory=Buffer.concat(centrals),end=Buffer.alloc(22);end.writeUInt32LE(0x06054b50);end.writeUInt16LE(entries.length,8);end.writeUInt16LE(entries.length,10);end.writeUInt32LE(directory.length,12);end.writeUInt32LE(offset,16);const zipPath=path.join(root,'..','BeyondMeds-October26.zip');await fs.writeFile(zipPath,Buffer.concat([...locals,directory,end]));console.log(JSON.stringify({preview:previewPath,zip:zipPath,files:entries.length}));
