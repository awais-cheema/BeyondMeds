(() => {
"use strict";
const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
const reduced=matchMedia("(prefers-reduced-motion: reduce)");
let paused=reduced.matches, selected="black";
if("IntersectionObserver" in window&&!paused){document.documentElement.classList.add("motion");const observer=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add("visible");observer.unobserve(e.target);}}),{threshold:.08});$$(".reveal").forEach(el=>observer.observe(el));}else $$(".reveal").forEach(el=>el.classList.add("visible"));

const menu=$(".menu-toggle"), menuPanel=$("#menu-panel");function closeMenu(){menu.setAttribute("aria-expanded","false");menu.setAttribute("aria-label","Open menu");menuPanel.setAttribute("aria-hidden","true");menuPanel.inert=true;}
menu.onclick=()=>{const open=menu.getAttribute("aria-expanded")!=="true";menu.setAttribute("aria-expanded",String(open));menu.setAttribute("aria-label",open?"Close menu":"Open menu");menuPanel.setAttribute("aria-hidden",String(!open));menuPanel.inert=!open;};
$$("#navigation a, #menu-panel a").forEach(a=>a.onclick=closeMenu);addEventListener("keydown",e=>{if(e.key==="Escape")closeMenu();});
document.addEventListener("click",e=>{if(menu.getAttribute("aria-expanded")==="true"&&!menu.parentElement.contains(e.target))closeMenu();});
$$(".detail-copy details").forEach(d=>d.addEventListener("toggle",()=>{if(d.open)$$(".detail-copy details").forEach(other=>{if(other!==d)other.open=false;});}));
$$("[data-temp]").forEach(b=>b.onclick=()=>{const warm=b.dataset.temp==="warm";$("#ritual").classList.toggle("warm",warm);$$("[data-temp]").forEach(x=>x.setAttribute("aria-pressed",String(x===b)));$("#temperature-description").textContent=warm?"Gentle warmth, for a comforting pause.":"Cooling contact, for a quieter moment.";});
const quantity=$("#quantity");function clamp(){quantity.value=String(Math.max(1,Math.min(10,Math.trunc(Number(quantity.value)||1))));}
$("#minus").onclick=()=>{quantity.value=Number(quantity.value)-1;clamp();};$("#plus").onclick=()=>{quantity.value=Number(quantity.value)+1;clamp();};quantity.onchange=clamp;
const dialog=$("#shop-dialog");
$(".close-dialog").onclick=$(".close-action").onclick=()=>dialog.close();dialog.addEventListener("click",e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
dialog.addEventListener("close",()=>window.beyondMedsLenis?.start());
$("#year").textContent=new Date().getFullYear();
const contactForm=$("#contact-form");
if(contactForm){
  contactForm.onsubmit=(e)=>{
    e.preventDefault();
    const status=$("#contact-status"), btn=$("#contact-submit");
    const name=$("#contact-name")?.value.trim(), phone=$("#contact-phone")?.value.trim(), message=$("#contact-message")?.value.trim();
    if(!name||!phone||!message){
      status.hidden=false;
      status.className="contact-status error";
      status.textContent="Please provide your name, phone number, and message.";
      return;
    }
    btn.disabled=true;
    btn.innerHTML="<span>Sending...</span>";
    setTimeout(()=>{
      btn.disabled=false;
      btn.innerHTML="<span>Message Sent ✓</span>";
      status.hidden=false;
      status.className="contact-status success";
      status.innerHTML=`<strong>Thank you, ${name}!</strong><p>Your message has been received. Our team will get back to you shortly via WhatsApp or email.</p>`;
      contactForm.reset();
      setTimeout(()=>{
        btn.innerHTML='<span>Send Message</span><span class="btn-arrow" aria-hidden="true">↗</span>';
      },4000);
    },600);
  };
}
})();