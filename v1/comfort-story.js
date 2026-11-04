(() => {
  const section=document.querySelector('.comfort-story');
  if(!section)return;
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const panels=[...section.querySelectorAll('.comfort-panel')],arts=[...section.querySelectorAll('.comfort-art')],buttons=[...section.querySelectorAll('.comfort-nav button')];
  const video=section.querySelector('video'),film=section.querySelector('.comfort-film');
  const accents=[...section.querySelectorAll('.comfort-accent')];let pointerX=0,pointerY=0;
  film.addEventListener('pointermove',e=>{if(reduced.matches||e.pointerType==='touch')return;const b=film.getBoundingClientRect();pointerX=(e.clientX-b.left)/b.width-.5;pointerY=(e.clientY-b.top)/b.height-.5;schedule();},{passive:true});
  film.addEventListener('pointerleave',()=>{pointerX=pointerY=0;schedule();});
  let start=0,travel=1,frame=0,current=-1,visible=false;
  function playback(){if(!video)return;if(visible&&!document.hidden&&!reduced.matches){if(!video.getAttribute('src')){video.src=matchMedia('(max-width:700px)').matches?video.dataset.mobile:video.dataset.hd;video.load();}video.muted=true;video.play().catch(()=>{});}else video.pause();}
  new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;playback();},{threshold:0}).observe(section);
  document.addEventListener('visibilitychange',playback);
  const clamp=x=>Math.min(1,Math.max(0,x));
  function measure(){const stage=section.querySelector('.comfort-stage');const inset=parseFloat(getComputedStyle(stage).top)||0;start=section.getBoundingClientRect().top+scrollY-inset;travel=Math.max(1,section.offsetHeight-stage.offsetHeight);schedule();}
  function render(){frame=0;if(reduced.matches)return;const p=clamp((scrollY-start)/travel),position=Math.min(3,Math.max(0,p*4-.5)),index=Math.min(3,Math.floor(position+.5));
    accents.forEach((a,i)=>{const depth=12+i*5;a.style.transform=`translate3d(${pointerX*depth}px,${pointerY*depth+(p-.5)*(i%2?36:-36)}px,0) rotate(${(p-.5)*(i%2?32:-32)+pointerX*8}deg)`;});
    section.style.setProperty('--story-progress',p);section.style.setProperty('--nerve-offset',-p*240);section.style.setProperty('--cool-scale',1+Math.max(0,position-2)*.14);
    panels.forEach((panel,i)=>{const distance=Math.abs(position-i),opacity=clamp((.6-distance)/.2);panel.style.opacity=opacity;panel.style.visibility=opacity>0?'visible':'hidden';panel.style.transform=`translateY(${(i-position)*40}px)`;panel.inert=i!==index;panel.setAttribute('aria-hidden',String(i!==index));});
    if(index!==current){current=index;buttons.forEach((b,i)=>{if(i===index)b.setAttribute('aria-current','step');else b.removeAttribute('aria-current');});section.dataset.chapter=index;}
  }
  function schedule(){if(!frame)frame=requestAnimationFrame(render);}
  function setup(){section.classList.toggle('is-enhanced',!reduced.matches);if(reduced.matches){accents.forEach(a=>a.style.transform='none');panels.forEach(p=>{p.removeAttribute('style');p.removeAttribute('aria-hidden');p.inert=false;});}measure();playback();}
  buttons.forEach((button,i)=>button.addEventListener('click',()=>{const y=start+travel*(i+.5)/4;if(window.beyondMedsLenis)window.beyondMedsLenis.scrollTo(y,{duration:1});else window.scrollTo({top:y,behavior:reduced.matches?'instant':'smooth'});}));
  addEventListener('scroll',schedule,{passive:true});addEventListener('resize',measure,{passive:true});addEventListener('load',measure);new ResizeObserver(measure).observe(document.querySelector('main'));reduced.addEventListener('change',setup);setup();
})();
