(() => {
"use strict";
const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
const reduced=matchMedia("(prefers-reduced-motion: reduce)");
let paused=reduced.matches, selected="black";
if("IntersectionObserver" in window&&!paused){document.documentElement.classList.add("motion");const observer=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add("visible");observer.unobserve(e.target);}}),{threshold:.08});$$(".reveal").forEach(el=>observer.observe(el));}else $$(".reveal").forEach(el=>el.classList.add("visible"));

const menu=$(".menu-toggle");function closeMenu(){menu.setAttribute("aria-expanded","false");menu.setAttribute("aria-label","Open menu");$("#navigation").classList.remove("open");}
menu.onclick=()=>{const open=menu.getAttribute("aria-expanded")!=="true";menu.setAttribute("aria-expanded",String(open));menu.setAttribute("aria-label",open?"Close menu":"Open menu");$("#navigation").classList.toggle("open",open);};
$$("#navigation a").forEach(a=>a.onclick=closeMenu);addEventListener("keydown",e=>{if(e.key==="Escape")closeMenu();});
$$(".detail-copy details").forEach(d=>d.addEventListener("toggle",()=>{if(d.open)$$(".detail-copy details").forEach(other=>{if(other!==d)other.open=false;});}));
$$("[data-temp]").forEach(b=>b.onclick=()=>{const warm=b.dataset.temp==="warm";$("#ritual").classList.toggle("warm",warm);$$("[data-temp]").forEach(x=>x.setAttribute("aria-pressed",String(x===b)));$("#temperature-description").textContent=warm?"Gentle warmth, for a comforting pause.":"Cooling contact, for a quieter moment.";});
$$("[data-color]").forEach(b=>b.onclick=()=>{selected=b.dataset.color;$$("[data-color]").forEach(x=>x.setAttribute("aria-pressed",String(x===b)));$(".product-image").dataset.selectedColor=selected;$(".product-image").setAttribute("aria-label",selected+" migraine relief cap");$("#selected-color").textContent=selected.charAt(0).toUpperCase()+selected.slice(1);});
const quantity=$("#quantity");function clamp(){quantity.value=String(Math.max(1,Math.min(10,Math.trunc(Number(quantity.value)||1))));}
$("#minus").onclick=()=>{quantity.value=Number(quantity.value)-1;clamp();};$("#plus").onclick=()=>{quantity.value=Number(quantity.value)+1;clamp();};quantity.onchange=clamp;
const dialog=$("#shop-dialog");$("#shop-now").onclick=()=>{clamp();const url=(window.BEYONDMEDS_CONFIG?.checkoutUrls||{})[selected]||window.BEYONDMEDS_CONFIG?.shopUrl;if(url){try{const dest=new URL(url,location.href);if(dest.protocol==="https:"||dest.protocol==="http:"){dest.searchParams.set("quantity",quantity.value);location.assign(dest.href);return;}}catch{}}$("#order-summary").textContent=quantity.value+" × BeyondMeds Migraine Relief Cap · "+selected.charAt(0).toUpperCase()+selected.slice(1);dialog.showModal();};
$(".close-dialog").onclick=$(".close-action").onclick=()=>dialog.close();dialog.addEventListener("click",e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
$("#year").textContent=new Date().getFullYear();
})();