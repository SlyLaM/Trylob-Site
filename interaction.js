(() => {
const root=document.querySelector("#entrance");
  if(!root)return;
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  const skipButton = root.querySelector('.entrance-skip');
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
   if (reduced.removeEventListener) reduced.removeEventListener("change",onMotion);
   else reduced.removeListener(onMotion);
   if(restoreFocus)document.querySelector('header .brand')?.focus({preventScroll:true});
  };
  skipButton.addEventListener("click",finish);
  const onKey=(event)=>{if(event.key==='Escape')finish()};
  const onMotion=()=>{if(reduced.matches)finish()};
  document.addEventListener('keydown',onKey);
  if (reduced.addEventListener) reduced.addEventListener('change',onMotion);
  else reduced.addListener(onMotion);
  const sectionLink = window.location.hash && window.location.hash !== '#accueil';
  if(reduced.matches||sectionLink){finish()}
  else{
   root.hidden=false;
   document.documentElement.classList.add('intro-running');
   content.forEach(node=>{node.inert=true});
   // Always release the page, including slow image loads or unsupported animation APIs.
   timers.push(setTimeout(finish,4800));
   const start=async()=>{
    const image=new Image();image.src='assets/trylob-logo-stage.png';
    const ready = typeof image.decode === 'function' ? image.decode() : Promise.resolve();
    await Promise.race([ready.catch(() => {}), new Promise(resolve => timers.push(setTimeout(resolve, 900)))]);
    if(stopped)return;
    if(!root.animate){finish();return;}
    const mark=root.querySelector('.entrance-mark');
    const target=document.querySelector('header .brand');
    if(!mark||!target){finish();return;}
    root.querySelectorAll('.entrance-strip').forEach((strip,i)=>{
     animations.push(strip.animate([{transform:`translateX(${i%2===0?'-':''}105%)`,opacity:0},{transform:'translateX(0)',opacity:1}],{duration:680,delay:i*100,easing:'cubic-bezier(.22,1,.36,1)',fill:'both'}));
    });
    timers.push(setTimeout(()=>{
     if(stopped)return;
     const from=mark.getBoundingClientRect(),to=target.getBoundingClientRect();
     const scaleX = to.width / from.width;
     const scaleY = to.height / from.height;
     animations.push(mark.animate([
      {transform:'translate(-50%,-50%) scale(1)'},
      {transform:`translate(calc(-50% + ${to.left-from.left}px),calc(-50% + ${to.top-from.top}px)) scale(${scaleX}, ${scaleY})`}
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
const contactNotice = document.createElement('div');
contactNotice.setAttribute('role', 'status');
const contactNoticeText = document.createElement('span');
Object.assign(contactNoticeText.style, {
  position: 'fixed',
  top: '50%',
  left: '50%',
  transform: 'translate(-50%, -50%)',
  width: 'min(26rem, calc(100vw - 2rem))',
  textAlign: 'center',
  fontSize: 'clamp(1.25rem, 5vw, 1.75rem)',
  fontWeight: '700',
  zIndex: '50',
  padding: 'clamp(1.5rem, 6vw, 2.5rem) 1rem',
  border: '1px solid #ffda2d',
  background: '#080808',
  color: '#ffda2d',
  pointerEvents: 'none',
});
contactNoticeText.hidden = true;
contactNotice.append(contactNoticeText);
document.body.append(contactNotice);
let contactNoticeTimer;
let contactNoticeFade;
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
const captchaCheck = document.querySelector('#contact-captcha-check');
const captchaContainer = document.querySelector('#contact-captcha-widget');
const captchaStatus = document.querySelector('#contact-captcha-status');
let captchaWidget;
let captchaToken = '';

function clearCaptcha(message = '') {
  captchaToken = '';
  captchaCheck.checked = false;
  captchaCheck.disabled = false;
  captchaStatus.textContent = message;
}

function loadTurnstile() {
  if (window.turnstile) return Promise.resolve();

  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    const timeout = setTimeout(fail, 8000);
    function fail() {
      clearTimeout(timeout);
      delete window.onTrylobTurnstileReady;
      script.remove();
      reject(new Error('Turnstile indisponible'));
    }
    window.onTrylobTurnstileReady = () => {
      clearTimeout(timeout);
      delete window.onTrylobTurnstileReady;
      resolve();
    };
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTrylobTurnstileReady';
    script.async = true;
    script.onerror = fail;
    document.head.append(script);
  });
}

captchaCheck.addEventListener('change', async () => {
  if (!captchaCheck.checked) {
    clearCaptcha();
    return;
  }

  captchaCheck.disabled = true;
  captchaStatus.textContent = 'Vérification en cours…';
  contactForm.querySelector('.contact-status').textContent = '';
  try {
    if (captchaWidget === undefined) {
      const response = await fetch('api/contact.php', {signal: AbortSignal.timeout(8000)});
      if (!response.ok) throw new Error('Configuration Turnstile indisponible');
      const {siteKey} = await response.json();
      await loadTurnstile();
      captchaWidget = window.turnstile.render(captchaContainer, {
        sitekey: siteKey,
        action: 'contact',
        theme: 'dark',
        size: 'compact',
        execution: 'execute',
        appearance: 'execute',
        retry: 'never',
        'refresh-expired': 'never',
        'response-field': false,
        callback(token) {
          captchaToken = token;
          captchaCheck.disabled = false;
          captchaStatus.textContent = 'Vérification réussie.';
        },
        'error-callback': () => clearCaptcha('Vérification indisponible. Cochez à nouveau la case.'),
        'timeout-callback': () => clearCaptcha('Vérification expirée. Cochez à nouveau la case.'),
        'expired-callback': () => clearCaptcha('Vérification expirée. Cochez à nouveau la case.'),
      });
    } else {
      window.turnstile.reset(captchaWidget);
    }
    window.turnstile.execute(captchaWidget);
  } catch {
    clearCaptcha('Vérification indisponible. Cochez à nouveau la case.');
  }
});

contactForm.addEventListener('submit', async event => {
  event.preventDefault();
  const button = contactForm.querySelector('[type="submit"]');
  const status = contactForm.querySelector('.contact-status');
  if (button.disabled) return;
  if (!captchaToken) {
    status.textContent = captchaCheck.disabled
      ? 'Attendez la fin de la vérification anti-robot.'
      : 'Cochez la case de vérification anti-robot.';
    return;
  }
  button.disabled = true;
  status.textContent = 'Envoi en cours…';
  try {
    const body = new FormData(contactForm);
    body.set('captcha_token', captchaToken);
    const response = await fetch('api/contact.php', {method: 'POST', body});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error);
    contactForm.reset();
    status.textContent = '';
    contactDialog.close();
    clearTimeout(contactNoticeTimer);
    contactNoticeFade?.cancel();
    contactNoticeText.hidden = false;
    contactNoticeText.textContent = 'Message envoyé';
    const fadeDuration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450;
    if (fadeDuration && contactNoticeText.animate) {
      contactNoticeFade = contactNoticeText.animate([{opacity: 1}, {opacity: 0}], {
        delay: 4000,
        duration: fadeDuration,
        easing: 'ease-out',
        fill: 'forwards',
      });
    }
    contactNoticeTimer = setTimeout(() => {
      contactNoticeText.textContent = '';
      contactNoticeText.hidden = true;
      contactNoticeFade?.cancel();
    }, 4000 + (contactNoticeText.animate ? fadeDuration : 0));
  } catch (error) {
    status.textContent = error.message || 'Envoi impossible. Réessayez.';
  } finally {
    window.turnstile.reset(captchaWidget);
    clearCaptcha();
    button.disabled = false;
  }
});
