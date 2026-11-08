(() => {
const root=document.querySelector("#entrance");
  if(!root)return;
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  const animations=[];
  const timers=[];
  const content=Array.from(document.querySelectorAll('header,main,footer'));
  const previous=content.map(node=>node.inert);
  const wasOnSkip=()=>root.contains(document.activeElement);
  let stopped=false;
  const finish=()=>{
   if(stopped)return;
   stopped=true;
   const restoreFocus=wasOnSkip();
   timers.forEach(clearTimeout);
   animations.forEach(animation=>animation.cancel());
   document.documentElement.classList.remove('intro-running');
   content.forEach((node,i)=>{node.inert=previous[i]});
   root.hidden=true;
   document.removeEventListener("keydown",onKey);
   reduced.removeEventListener("change",onMotion);
   if(restoreFocus)document.querySelector('header .brand')?.focus({preventScroll:true});
  };
  root.querySelector("button").addEventListener("click",finish);
  const onKey=(event)=>{if(event.key==='Escape')finish()};
  const onMotion=()=>{if(reduced.matches)finish()};
  document.addEventListener('keydown',onKey);
  reduced.addEventListener('change',onMotion);
  // Hash links, restored scroll positions and reduced motion open straight on the content.
  if(reduced.matches||window.location.hash||window.scrollY>10){finish()}
  else{
   root.hidden=false;
   document.documentElement.classList.add('intro-running');
   content.forEach(node=>{node.inert=true});
   // Always release the page, including slow image loads or unsupported animation APIs.
   timers.push(setTimeout(finish,4800));
   const start=async()=>{
    const image=new Image();image.src='assets/trylob-logo-stage.png';
    await Promise.race([image.decode().catch(()=>{}),new Promise(resolve=>timers.push(setTimeout(resolve,900)))]);
    if(stopped)return;
    if(!root.animate){finish();return;}
    const mark=root.querySelector('.entrance-mark');
    const target=document.querySelector('header .brand');
    if(!mark||!target){finish();return;}
    root.querySelectorAll('.entrance-strip').forEach((strip,i)=>{
     animations.push(strip.animate([{transform:`translateX(${i%2===0?'-':' '}105%)`,opacity:0},{transform:'translateX(0)',opacity:1}],{duration:680,delay:i*100,easing:'cubic-bezier(.22,1,.36,1)',fill:'both'}));
    });
    timers.push(setTimeout(()=>{
     if(stopped)return;
     const from=mark.getBoundingClientRect(),to=target.getBoundingClientRect();
     const scale=to.width/from.width;
     animations.push(mark.animate([
      {transform:'translate(-50%,-50%) scale(1)'},
      {transform:`translate(calc(-50% + ${to.left-from.left}px),calc(-50% + ${to.top-from.top}px)) scale(${scale})`}
     ],{duration:1100,easing:'cubic-bezier(.76,0,.24,1)',fill:'forwards'}));
     root.querySelectorAll('.entrance-shutter').forEach((shutter,i)=>{
      animations.push(shutter.animate([{transform:'translateX(0)'},{transform:`translateX(${i%2===0?'-':''}102%)`}],{duration:1050,delay:i*35,easing:'cubic-bezier(.76,0,.24,1)',fill:'forwards'}));
     });
     const button=root.querySelector('button');
     if(button)animations.push(button.animate([{opacity:1},{opacity:0}],{duration:220,fill:'forwards'}));
     timers.push(setTimeout(finish,1350));
    },1300));
   };
   void start().catch(finish);
  }

})();

const contactDialog = document.querySelector('dialog');
const contactForm = document.querySelector('#contact-form');
let contactTrigger;
document.querySelectorAll('a[href="#contact-form"]').forEach(link => {
  link.addEventListener('click', event => {
    event.preventDefault();
    contactTrigger = link;
    contactDialog.showModal();
  });
});
contactDialog.querySelector('.modal-close').addEventListener('click', () => contactDialog.close());
contactDialog.addEventListener('close', () => contactTrigger?.focus());
function syncDialogViewport() {
  const viewport = window.visualViewport;
  document.documentElement.style.setProperty('--dialog-height', `${viewport?.height || window.innerHeight}px`);
  document.documentElement.style.setProperty('--dialog-top', `${viewport?.offsetTop || 0}px`);
}
window.visualViewport?.addEventListener('resize', syncDialogViewport);
window.visualViewport?.addEventListener('scroll', syncDialogViewport);
syncDialogViewport();
contactForm.addEventListener('submit', async event => {
  event.preventDefault();
  const button = contactForm.querySelector('[type="submit"]');
  const status = contactForm.querySelector('[role="status"]');
  button.disabled = true;
  status.textContent = 'Envoi en cours…';
  try {
    const response = await fetch('api/contact.php', {method: 'POST', body: new FormData(contactForm)});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error);
    contactForm.reset();
    status.textContent = 'Merci ! Votre demande a été enregistrée dans l’espace privé de Trylob.';
  } catch (error) {
    status.textContent = error.message || 'Envoi impossible. Réessayez.';
  } finally {
    button.disabled = false;
  }
});
