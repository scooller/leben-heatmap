(function(){

  // Compatibilidad: necesita sendBeacon o fetch
  if (!('sendBeacon' in navigator) && !('fetch' in window)) return;

  // Verificar que WordPress pasó el nonce
  if (!window.HeatmapLeben || !HeatmapLeben.enabled) return;

  const ajaxUrl = HeatmapLeben.ajaxUrl;
  const nonce = HeatmapLeben.nonce;
  const pageId = HeatmapLeben.pageId || 0;
  const page = location.origin + location.pathname + location.search;

  // Session ID sin PII
  const sessKey = 'hm_lbn_sess';
  let session = localStorage.getItem(sessKey);
  if (!session) {
    session = Math.random().toString(36).slice(2) + Date.now().toString(36);
    localStorage.setItem(sessKey, session);
  }

  const queue = [];
  let timer = null;
  const maxBatch = 50;
  const flushInterval = 2000;

  // Detectar tipo de dispositivo (mobile/desktop)
  function getDeviceType() {
    if (window.innerWidth <= 768) {
      return 'mobile';
    }
    return 'desktop';
  }

  // 🎯 LEER ESTADO CADA VEZ - CON ALTURA REAL DE PÁGINA
  function getState() {
    return {
      vw: Math.max(document.documentElement.clientWidth, window.innerWidth || 0),
      vh: Math.max(document.documentElement.clientHeight, window.innerHeight || 0),
      sx: window.pageXOffset || document.documentElement.scrollLeft || 0,
      sy: window.pageYOffset || document.documentElement.scrollTop || 0,
      // 🎯 NUEVO: ALTURA REAL DE LA PÁGINA
      ph: Math.max(
        document.documentElement.scrollHeight,
        document.body.scrollHeight,
        document.documentElement.offsetHeight,
        document.body.offsetHeight
      ),
      // 🎯 TIPO DE DISPOSITIVO
      dt: getDeviceType()
    };
  }

  function enqueue(item) {
    queue.push(item);
    if (queue.length >= maxBatch) flush();
    if (!timer) timer = setTimeout(flush, flushInterval);
  }

  function flush() {
    if (!queue.length) {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      return;
    }

    const batch = queue.splice(0, queue.length);
    const form = new FormData();

    form.append('action', 'hm_leben_event');
    form.append('nonce', nonce);
    form.append('batch', JSON.stringify(batch));

    // Usar sendBeacon si está disponible (mejor para unload)
    if (navigator.sendBeacon) {
      const b = new Blob(
        [new URLSearchParams(Array.from(form.entries())).toString()],
        { type: 'application/x-www-form-urlencoded' }
      );
      navigator.sendBeacon(ajaxUrl, b);
    }
    // Sino, usar fetch
    else if (window.fetch) {
      fetch(ajaxUrl, {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
          } else {
          }
        })
        .catch(e => {});
    }

    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
  }

  // Click tracking
  document.addEventListener('click', function(e) {
    try {
      const st = getState();
      enqueue({
        t: 'click',
        x: e.clientX,
        y: e.clientY,
        ...st,
        page,
        pageId,
        d: 5,
        s: session
      });
    } catch(_) {
      // Silenciar errores
    }
  }, { passive: true });

  // Mouse move sampling (throttled)
  let lastMove = 0;
  document.addEventListener('mousemove', function(e) {
    const now = Date.now();
    if (now - lastMove < 120) return; // throttle a 120ms
    lastMove = now;

    try {
      const st = getState();
      enqueue({
        t: 'move',
        x: e.clientX,
        y: e.clientY,
        ...st,
        page,
        pageId,
        d: 1,
        s: session
      });
    } catch(_) {
      // Silenciar errores
    }
  }, { passive: true });

  // Flush on unload/visibility change
  window.addEventListener('beforeunload', flush);
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden') flush();
  });

})();
