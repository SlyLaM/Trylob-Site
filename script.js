// Keep the video shade independent of the navigation and video playback.
{
  const spacer = document.querySelector('.video-space');
  if (spacer) {
    let frame = 0;
    const updateShade = () => {
      frame = 0;
      const distance = Math.min(340, Math.max(100, spacer.offsetHeight * 0.7));
      const progress = Math.min(1, Math.max(0, window.scrollY / distance));
      const opacity = 0.76 * progress * progress * (3 - 2 * progress);
      document.documentElement.style.setProperty('--video-shade', String(opacity));
    };
    window.addEventListener('scroll', () => {
      if (!frame) frame = requestAnimationFrame(updateShade);
    }, {passive: true});
    window.addEventListener('resize', updateShade);
    window.addEventListener('pageshow', updateShade);
    updateShade();
  }
}

const menu=document.querySelector('.menu');const nav=document.querySelector('nav');menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('open',open);menu.querySelector('span').textContent=open?'−':'+'});nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.setAttribute('aria-expanded','false');nav.classList.remove('open');menu.querySelector('span').textContent='+'}));document.addEventListener('keydown',e=>{if(e.key==='Escape'&&nav.classList.contains('open')){menu.click();menu.focus()}});document.querySelector('#year').textContent=new Date().getFullYear();
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
const backgroundVideo = document.querySelector('#background-video');
const playButton = document.querySelector('.background-play');
const defaultVideoUrl = new URL(backgroundVideo.dataset.defaultVideo, document.baseURI).href;
let posterOnly = true;
let manualPlayback = false;
let playbackRequest = 0;

async function syncPlayback() {
  const request = ++playbackRequest;
  if (posterOnly) {
    playButton.hidden = true;
    return;
  }
  if (document.hidden || (reduced.matches && !manualPlayback)) {
    backgroundVideo.pause();
    playButton.hidden = document.hidden;
    return;
  }

  backgroundVideo.muted = true;
  try {
    await backgroundVideo.play();
    if (request === playbackRequest) playButton.hidden = true;
  } catch {
    if (request === playbackRequest) playButton.hidden = false;
  }
}

playButton.addEventListener('click', () => {
  manualPlayback = true;
  void syncPlayback();
});
document.addEventListener('visibilitychange', syncPlayback);
window.addEventListener('pageshow', syncPlayback);
const onVideoMotionChange = () => {
  manualPlayback = false;
  void syncPlayback();
};
if (reduced.addEventListener) reduced.addEventListener('change', onVideoMotionChange);
else reduced.addListener(onVideoMotionChange);
function concertRow(c,past){const row=document.createElement(c.url?'a':'div');row.className='date-row';if(c.url){row.href=c.url;row.target='_blank';row.rel='noopener'}const time=document.createElement('time');time.dateTime=c.date;const strong=document.createElement('strong');strong.textContent=c.date.slice(8,10)+'.'+c.date.slice(5,7);time.append(strong,document.createTextNode(c.date.slice(0,4)));const info=document.createElement('div'),title=document.createElement('h4'),city=document.createElement('p');title.textContent=c.venue;city.textContent=c.city;info.append(title,city);const status=document.createElement('span');status.className='past';status.textContent=past?'PASSÉ':c.url?'INFOS':'À VENIR';row.append(time,info,status);return row}
async function loadSite(){let settings={};try{const r=await fetch('api/public.php');if(!r.ok)throw Error('Unavailable');const d=await r.json();settings=d.settings;const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Paris'}).format(new Date());const future=d.concerts.filter(c=>c.date>=today),past=d.concerts.filter(c=>c.date<today).reverse();const upcoming=document.querySelector('#upcoming-concerts');if(future.length){const title=document.createElement('h3');title.textContent='PROCHAINES DATES';upcoming.append(title,...future.map(c=>concertRow(c,false)))}document.querySelector('#added-past-concerts').append(...past.map(c=>concertRow(c,true)));document.querySelectorAll('[data-clip-link]').forEach(a=>{if(settings.clip)a.href=settings.clip});document.querySelectorAll('[data-social]').forEach(a=>{if(settings[a.dataset.social]){a.href=settings[a.dataset.social];a.hidden=false}})}catch{const note=document.createElement('p');note.textContent='Les prochaines dates sont momentanément indisponibles. Réessayez plus tard.';document.querySelector('#upcoming-concerts').append(note)}
  if (settings.backgroundPosterOnly) return;

  posterOnly = false;
  let url = defaultVideoUrl;
  if (settings.background) {
    try {
      url = new URL(settings.background, document.baseURI).href;
    } catch {
      // An invalid legacy link must not prevent the default video from playing.
    }
  }
  if (url !== defaultVideoUrl) {
    backgroundVideo.addEventListener('error', () => {
      backgroundVideo.src = defaultVideoUrl;
      void syncPlayback();
    }, {once: true});
  }
  backgroundVideo.src = url;
  void syncPlayback();
}
loadSite();
