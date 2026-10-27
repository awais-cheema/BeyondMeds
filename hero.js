(() => {
'use strict';
const story=document.querySelector('.film-story'),stage=story.querySelector('.film-stage'),canvas=document.querySelector('#comfort-frames'),ctx=canvas.getContext('2d',{alpha:true});
const sources=window.BEYONDMEDS_HERO_FRAMES||[],frames=new Map(),pending=new Set(),anchors=new Set(),reduce=matchMedia('(prefers-reduced-motion: reduce)'),loader=document.querySelector('.site-loader');
const mobile=matchMedia('(max-width:700px)').matches,modest=(navigator.deviceMemory||8)<=4||(navigator.hardwareConcurrency||8)<=4;
const limit=sources.length||144,workers=modest?2:4;
let ready=false,start=0,travel=1,display=0,last=-1,lastVersion=-1,version=0,targetIndex=0,raf=0,lastTime=0,lenis,queue=[],running=0,decoded=0,active=true;
const clamp=n=>Math.max(0,Math.min(1,n));
canvas.width=mobile?622:910;canvas.height=mobile?525:768;ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='medium';
function geometry(){start=story.getBoundingClientRect().top+scrollY;travel=Math.max(1,story.offsetHeight-stage.offsetHeight);}
function setup(){lenis?.destroy();lenis=null;if(window.Lenis&&!reduce.matches){lenis=new Lenis({autoRaf:false,lerp:.11,wheelMultiplier:1,smoothWheel:true,syncTouch:false,anchors:true,allowNestedScroll:true});if(!ready)lenis.stop();}window.beyondMedsLenis=lenis;}
function stats(){window.beyondMedsHero={ready,loaded:decoded,cached:frames.size,total:sources.length,width:canvas.width,height:canvas.height};}
function trim(){while(frames.size>limit){let far=-1,d=-1;for(const i of frames.keys()){if(anchors.has(i))continue;const distance=Math.abs(i-targetIndex);if(distance>d){d=distance;far=i;}}if(far<0)break;frames.get(far).close();frames.delete(far);}stats();}
async function decode(i){if(frames.has(i))return;const img=new Image();img.decoding='async';img.src=sources[i];try{await img.decode();const bitmap=await createImageBitmap(img);frames.get(i)?.close();frames.set(i,bitmap);decoded++;version++;}finally{img.src='';}trim();}
function pump(){if(document.hidden||!active||reduce.matches)return;while(running<workers&&queue.length){const i=queue.shift();if(frames.has(i)||pending.has(i))continue;pending.add(i);running++;decode(i).catch(()=>{}).finally(()=>{pending.delete(i);running--;pump();});}}
function requestNear(i){targetIndex=i;queue=[];for(let d=0;d<=12;d++)for(const n of d?[i+d,i-d]:[i])if(n>=0&&n<sources.length&&!frames.has(n)&&!pending.has(n))queue.push(n);pump();}
function draw(p){const value=p*(sources.length-1),index=Math.floor(value);let chosen=index;if(!frames.has(chosen)){let distance=Infinity;for(const i of frames.keys())if(Math.abs(i-value)<distance){distance=Math.abs(i-value);chosen=i;}}const first=frames.get(chosen);if(!first)return;const mix=chosen===index&&frames.has(index+1)?value-index:0;ctx.clearRect(0,0,canvas.width,canvas.height);ctx.globalAlpha=1-mix;ctx.globalCompositeOperation='source-over';ctx.drawImage(first,0,0,canvas.width,canvas.height);if(mix>.001){ctx.globalAlpha=mix;ctx.globalCompositeOperation='lighter';ctx.drawImage(frames.get(index+1),0,0,canvas.width,canvas.height);}ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';canvas.dataset.frame=String(chosen);last=p;lastVersion=version;}
function tick(time){raf=requestAnimationFrame(tick);lenis?.raf(time);if(!ready||!active)return;const target=reduce.matches?0:clamp((scrollY-start)/travel),dt=Math.min(50,time-(lastTime||time));lastTime=time;display+=(target-display)*(1-Math.exp(-dt/36));if(Math.abs(target-display)<.0001)display=target;const i=Math.round(target*(sources.length-1));if(i!==targetIndex)requestNear(i);if(display!==last||version!==lastVersion)draw(display);}
function unlock(){if(ready)return;loader?.style.setProperty("--loaded",1);ready=true;stats();stage.classList.add('film-ready');document.documentElement.classList.remove('hero-loading');document.querySelector('main').inert=false;document.querySelector('footer').inert=false;lenis?.start();loader?.classList.add('is-ready');setTimeout(()=>loader?.remove(),700);}
async function boot(){
  try{
    if(!sources.length)throw Error('No hero frames');
    const startLoad=performance.now(),MIN_LOADER_MS=2400;
    pending.add(0);
    await decode(0);
    pending.delete(0);
    anchors.add(0);
    draw(0);

    function updateProgress(){
      if(ready)return;
      const elapsed=performance.now()-startLoad;
      const timeRatio=Math.min(1,elapsed/MIN_LOADER_MS);
      const frameRatio=sources.length?Math.min(1,decoded/sources.length):1;
      const progress=Math.min(0.99,Math.max(timeRatio*0.75,frameRatio*0.95));
      loader?.style.setProperty('--loaded',progress.toFixed(3));
      if(!ready)requestAnimationFrame(updateProgress);
    }
    requestAnimationFrame(updateProgress);

    if(!reduce.matches){
      const concurrency=navigator.hardwareConcurrency?Math.min(8,Math.max(4,navigator.hardwareConcurrency)):6;
      let nextIndex=1;
      async function preloadWorker(){
        while(nextIndex<sources.length){
          const i=nextIndex++;
          if(!frames.has(i)&&!pending.has(i)){
            pending.add(i);
            try{await decode(i);}catch(e){}finally{pending.delete(i);}
          }
        }
      }
      const workersList=Array.from({length:concurrency},()=>preloadWorker());
      await Promise.race([
        Promise.all(workersList),
        new Promise(r=>setTimeout(r,4500))
      ]);
    }

    const elapsed=performance.now()-startLoad;
    if(elapsed<MIN_LOADER_MS){
      await new Promise(r=>setTimeout(r,MIN_LOADER_MS-elapsed));
    }

    loader?.style.setProperty('--loaded','1');
    setTimeout(()=>unlock(),120);
  }catch(error){
    console.error('Hero frame unavailable',error);
    unlock();
    stage.classList.remove('film-ready');
  }
}
new IntersectionObserver(e=>{active=e[0].isIntersecting;if(active){lastTime=0;requestNear(targetIndex);}},{rootMargin:'150px'}).observe(story);
document.documentElement.classList.add('hero-loading');document.querySelector('main').inert=true;document.querySelector('footer').inert=true;setup();geometry();addEventListener('resize',geometry,{passive:true});reduce.addEventListener('change',()=>{setup();if(!reduce.matches)requestNear(targetIndex);});document.addEventListener('visibilitychange',()=>{if(document.hidden){cancelAnimationFrame(raf);raf=0;}else if(!raf){lastTime=0;raf=requestAnimationFrame(tick);pump();}});raf=requestAnimationFrame(tick);boot();
})();
