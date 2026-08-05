/* Service worker da Bichoteca — cache simples, funciona offline depois da 1ª visita */
const CACHE = 'bichoteca-v1';
const ARQUIVOS = ['./', './index.html', './app.html', './manifest.json', './icone-192.png', './icone-512.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks =>
    Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  if (e.request.url.includes('/api/')) return; // dados da conta: sempre da rede, nunca em cache
  e.respondWith(
    caches.match(e.request).then(hit => hit || fetch(e.request).then(res => {
      const copia = res.clone();
      caches.open(CACHE).then(c => c.put(e.request, copia)).catch(() => {});
      return res;
    }).catch(() => caches.match('./app.html')))
  );
});

/* notificação push — o payload já vem pronto (título + corpo + tag) de quem disparou:
   lembrete de sequência (cron em api/cron-lembrete-streak.php) ou eventos em tempo real
   (mensagem nova, pedido de amizade aceito, em api/estado.php). A tag vem do remetente pra
   cada TIPO de aviso substituir só o anterior do mesmo tipo, sem apagar os outros. */
self.addEventListener('push', e => {
  let dados = {titulo: 'Bichoteca', corpo: 'Seu bicho sentiu sua falta hoje!', tag: 'bichoteca-lembrete'};
  try { dados = {...dados, ...e.data.json()}; } catch (err) {}
  e.waitUntil(self.registration.showNotification(dados.titulo, {
    body: dados.corpo,
    icon: './icone-192.png',
    badge: './icone-192.png',
    tag: dados.tag,
  }));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(
    self.clients.matchAll({type: 'window', includeUncontrolled: true}).then(lista => {
      const aberta = lista.find(c => c.url.includes('app.html'));
      if (aberta) return aberta.focus();
      return self.clients.openWindow('./app.html');
    })
  );
});
