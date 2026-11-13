(() => {
const reduce=matchMedia('(prefers-reduced-motion: reduce)');
document.querySelectorAll('.moment-card').forEach(card=>{
 card.addEventListener('pointerleave',()=>{card.style.setProperty('--rx','0deg');card.style.setProperty('--ry','0deg');});
 let raf=0;card.addEventListener('pointermove',event=>{if(reduce.matches||event.pointerType==='touch')return;cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{const r=card.getBoundingClientRect();card.style.setProperty('--mx',((event.clientX-r.left)/r.width*100)+'%');card.style.setProperty('--my',((event.clientY-r.top)/r.height*100)+'%');card.style.setProperty('--rx',((.5-(event.clientY-r.top)/r.height)*5)+'deg');card.style.setProperty('--ry',(((event.clientX-r.left)/r.width-.5)*5)+'deg');});});
});
const reviews=[
{title:'My favourite evening pause.',quote:'“After a long day, I keep it in the fridge so it\'s ready when I get home. The cool, soft fit gives me a few quiet minutes with my chai.”',name:'Ayesha R.',initials:'AR'},
{title:'A moment to reset.',quote:'“Work gets heavy and screens don\'t help. I wear it for ten minutes in a dark room and it helps me slow down. It\'s part of my routine now.”',name:'Hamza Q.',initials:'HQ'},
{title:'A gentler start to the day.',quote:'“Summer mornings can start with a heavy head. The cap is soft, fits well, and the cooling feels really comforting. Easy to store too.”',name:'Sana M.',initials:'SM'}
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

(() => {
 if(!('IntersectionObserver' in window))return;
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>entry.target.classList.toggle('outside-view',!entry.isIntersecting)),{rootMargin:'160px'});
 document.querySelectorAll('main>section:not(.film-story)').forEach(section=>observer.observe(section));
})();

(() => {
 const product=document.querySelector('.product-showcase');
 if(!product)return;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');
 let active=false,frame=0,px=0,py=0;
 function draw(){frame=0;if(reduced.matches){product.style.setProperty('--leaf-shift','0px');product.style.setProperty('--leaf-x','0px');return;}const r=product.getBoundingClientRect(),p=Math.max(0,Math.min(1,(innerHeight-r.top)/(innerHeight+r.height)));product.style.setProperty('--leaf-shift',((p-.5)*(innerWidth<701?35:90)+py*130).toFixed(2)+'px');}
 product.addEventListener('pointermove',e=>{if(e.pointerType!=='mouse'||reduced.matches)return;const r=product.getBoundingClientRect();px=(e.clientX-r.left)/r.width-.5;py=(e.clientY-r.top)/r.height-.5;product.style.setProperty('--leaf-x',(px*200)+'px');schedule();},{passive:true});
 product.addEventListener('pointerleave',()=>{px=py=0;product.style.setProperty('--leaf-x','0px');schedule();});
 function schedule(){if(active&&!frame)frame=requestAnimationFrame(draw);}
 new IntersectionObserver(entries=>{active=entries[0].isIntersecting;schedule();},{rootMargin:'100px'}).observe(product);
 addEventListener('scroll',schedule,{passive:true});addEventListener('resize',schedule,{passive:true});reduced.addEventListener('change',draw);
})();
