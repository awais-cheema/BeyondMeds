(() => {
const reduce=matchMedia('(prefers-reduced-motion: reduce)');
document.querySelectorAll('.moment-card').forEach(card=>{
 card.addEventListener('pointerleave',()=>{card.style.setProperty('--rx','0deg');card.style.setProperty('--ry','0deg');});
 let raf=0;card.addEventListener('pointermove',event=>{if(reduce.matches||event.pointerType==='touch')return;cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{const r=card.getBoundingClientRect();card.style.setProperty('--mx',((event.clientX-r.left)/r.width*100)+'%');card.style.setProperty('--my',((event.clientY-r.top)/r.height*100)+'%');card.style.setProperty('--rx',((.5-(event.clientY-r.top)/r.height)*5)+'deg');card.style.setProperty('--ry',(((event.clientX-r.left)/r.width-.5)*5)+'deg');});});
});
const reviews=[
{title:'My favourite evening pause.',quote:'“I keep it ready for the end of a long day. The cool feel and soft fit make those few quiet minutes on the sofa something I really look forward to.”',name:'Amelia M.',initials:'AM'},
{title:'A little space, just for me.',quote:'“Between work and everything else, I rarely stop. Putting on the cap is my reminder to put my phone down, get comfortable and enjoy a quiet break.”',name:'Jordan L.',initials:'JL'},
{title:'A softer start to the day.',quote:'“I like how soft the fabric feels and how easy it is to make part of my morning routine. A little time to myself before the day gets busy.”',name:'Sofia K.',initials:'SK'}
];let current=0;
const panel=document.querySelector('.story-panel');
function show(index){current=(index+reviews.length)%reviews.length;const r=reviews[current];document.querySelector('#story-theme').textContent=r.title;document.querySelector('#story-quote').textContent=r.quote;document.querySelector('#story-author').textContent=r.name;document.querySelector('#story-avatar').textContent=r.initials;document.querySelector('#story-count').textContent=String(current+1).padStart(2,'0');panel.style.setProperty('--review-progress',String((current+1)/reviews.length));document.querySelectorAll('[data-story]').forEach(b=>b.setAttribute('aria-pressed',String(+b.dataset.story===current)));panel.classList.remove('changing');void panel.offsetWidth;panel.classList.add('changing');}
document.querySelectorAll('[data-story]').forEach(b=>b.onclick=()=>show(+b.dataset.story));document.querySelectorAll('[data-story-step]').forEach(b=>b.onclick=()=>show(current+ +b.dataset.storyStep));
panel.addEventListener('keydown',e=>{if(e.key==='ArrowRight'){e.preventDefault();show(current+1);}if(e.key==='ArrowLeft'){e.preventDefault();show(current-1);}});
let touchStart=null;
panel.addEventListener('touchstart',e=>{touchStart={x:e.touches[0].clientX,y:e.touches[0].clientY};},{passive:true});
panel.addEventListener('touchend',e=>{if(!touchStart)return;const dx=e.changedTouches[0].clientX-touchStart.x,dy=e.changedTouches[0].clientY-touchStart.y;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy)*1.4)show(current+(dx<0?1:-1));touchStart=null;},{passive:true});
panel.addEventListener('pointermove',e=>{if(reduce.matches||e.pointerType!=='mouse')return;const r=panel.getBoundingClientRect();panel.style.setProperty('--spot-x',((e.clientX-r.left)/r.width*100)+'%');panel.style.setProperty('--spot-y',((e.clientY-r.top)/r.height*100)+'%');});
show(0);
})();

// Wait for the opening image, with a bounded, non-blocking exit.
(() => {
 const loader=document.querySelector('.site-loader');if(!loader)return;
 const poster=document.querySelector('.film-poster');
 const ready=poster?.decode?poster.decode().catch(()=>{}):Promise.resolve();
 const minimum=new Promise(resolve=>setTimeout(resolve,matchMedia('(prefers-reduced-motion: reduce)').matches?0:650));
 const finish=()=>{loader.classList.add('is-ready');setTimeout(()=>loader.remove(),700);};
 Promise.race([Promise.all([ready,minimum]),new Promise(resolve=>setTimeout(resolve,2500))]).then(finish);
})();
