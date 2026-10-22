(() => {
'use strict';
const story=document.querySelector('.film-story'),stage=story.querySelector('.film-stage'),canvas=story.querySelector('#comfort-frames'),ctx=canvas.getContext('2d');ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
const intro=story.querySelector('.film-intro'),outro=story.querySelector('.film-outro'),indicators=[...story.querySelectorAll('.comfort-indicator')],buttons=[...story.querySelectorAll('[data-film-progress]')],reduced=matchMedia('(prefers-reduced-motion: reduce)');
const sources=window.BEYONDMEDS_HERO_FRAMES||[],cache=new Map();
let paused=false,target=0,progress=0,raf=0,wanted=0,drawn=-1;
const clamp=n=>Math.max(0,Math.min(1,n)),ease=n=>{n=clamp(n);return n*n*(3-2*n);};
function paint(p){
 stage.style.setProperty('--progress',p);
 const opening=1-ease((p-.04)/.16),ending=ease((p-.88)/.10);
 intro.style.opacity=opening;intro.inert=opening<.1;outro.style.opacity=ending;outro.inert=ending<.5;outro.style.pointerEvents=ending>.5?'auto':'none';
 indicators.forEach((el,i)=>{const phase=(p-(.28+i*.075))/.22,alpha=ease(phase/.16)*(1-ease((phase-.78)/.22)),cool=ease((phase-.22)/.34);el.style.opacity=alpha;el.style.color=`rgb(${Math.round(155-132*cool)},${Math.round(43+48*cool)},${Math.round(48+65*cool)})`;el.style.background=`rgb(${Math.round(255-43*cool)},${Math.round(227+16*cool)},${Math.round(225+30*cool)})`;el.style.boxShadow=`0 0 ${10+18*cool}px rgba(${Math.round(215-125*cool)},${Math.round(80+120*cool)},${Math.round(90+150*cool)},${alpha*.4})`;el.style.transform=`translate(-50%,-50%) scale(${.96+.04*alpha})`;});
 buttons.forEach((b,i)=>b.setAttribute('aria-pressed',String(i===(p<.28?0:p<.88?1:2))));
}
function draw(index){const img=cache.get(index);if(!img||!img.complete||!img.naturalWidth)return;const fraction=progress*(sources.length-1)-index,next=cache.get(index+1),blend=next&&next.complete&&next.naturalWidth&&fraction>0;ctx.clearRect(0,0,canvas.width,canvas.height);ctx.globalCompositeOperation='source-over';ctx.globalAlpha=blend?1-fraction:1;ctx.drawImage(img,0,0,canvas.width,canvas.height);if(blend){ctx.globalCompositeOperation='lighter';ctx.globalAlpha=fraction;ctx.drawImage(next,0,0,canvas.width,canvas.height);}ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';drawn=index;stage.classList.add('film-ready');canvas.dataset.frame=String(index);paint(progress);}
function load(index){if(index<0||index>=sources.length||cache.has(index))return;const img=new Image();cache.set(index,img);img.onload=()=>{if(index===wanted||index===wanted+1)draw(wanted);};img.src=sources[index];}
function render(){wanted=Math.floor(progress*(sources.length-1));load(wanted);draw(wanted);for(let d=1;d<=10;d++){load(wanted+d);load(wanted-d);}for(const index of cache.keys())if(Math.abs(index-wanted)>18)cache.delete(index);}
function tick(){raf=0;if(paused)return;progress=target;if(Math.abs(progress-target)<.0005)progress=target;render();if(progress!==target)schedule();}
function schedule(){if(!raf&&!paused)raf=requestAnimationFrame(tick);}
function measure(){const h=0;story.style.setProperty('--film-header','0px');const rect=story.getBoundingClientRect(),travel=Math.max(1,story.offsetHeight-stage.offsetHeight);target=clamp(-rect.top/travel);const exit=ease((-rect.top-travel)/(stage.offsetHeight*.75));stage.style.opacity=String(1-exit);schedule();}
addEventListener('scroll',measure,{passive:true});addEventListener('resize',measure,{passive:true});addEventListener('pageshow',measure);reduced.addEventListener('change',measure);
let lenis;
function setupScroll(){if(lenis){lenis.destroy();lenis=null;}if(window.Lenis&&!reduced.matches){lenis=new Lenis({autoRaf:true,lerp:.065,wheelMultiplier:.85,smoothWheel:true,syncTouch:false,anchors:true,allowNestedScroll:true});lenis.on('scroll',measure);}window.beyondMedsLenis=lenis;}
setupScroll();reduced.addEventListener('change',setupScroll);
paint(0);render();measure();
})();
