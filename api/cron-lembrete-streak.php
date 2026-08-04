<?php
/* =========================================================
   Bichoteca — lembrete de sequência (streak), disparado 1x por dia por um
   Cron Job da Hostinger (hPanel > Avançado > Cron Jobs). Manda notificação push
   só pra quem ativou o lembrete no Perfil E ainda não jogou hoje.

   Configure no hPanel um dos dois jeitos:
   - Comando PHP direto (mais simples e mais seguro, sem precisar de segredo):
       php /home/SEU_USUARIO/domains/pets.pedro.marketing/public_html/api/cron-lembrete-streak.php
   - Ou uma URL (se o painel só permitir "visitar uma URL"), com o segredo do config.php:
       https://pets.pedro.marketing/api/cron-lembrete-streak.php?segredo=SEU_CRON_SECRET
   Horário sugerido: 19h (todo dia).
   ========================================================= */
declare(strict_types=1);
require __DIR__ . '/bd.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

// via CLI (cron rodando o PHP direto) não precisa de segredo — só quem tem acesso ao
// servidor consegue rodar CLI. Via HTTP (cron "visita uma URL"), exige o segredo.
if (PHP_SAPI !== 'cli') {
  if (!defined('CRON_SECRET') || !hash_equals(CRON_SECRET, (string)($_GET['segredo'] ?? ''))) {
    http_response_code(403);
    exit("acesso negado\n");
  }
}

if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY') || VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
  exit("VAPID não configurado em api/config.php — nada a fazer.\n");
}

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d');

$linhas = bd()->query('SELECT j.apelido, j.estado, p.endpoint, p.chave_p256dh, p.chave_auth
  FROM jogadores j JOIN push_inscricoes p ON p.jogador_id = j.id')->fetchAll(PDO::FETCH_ASSOC);

$webPush = new WebPush([
  'VAPID' => ['subject' => VAPID_SUBJECT, 'publicKey' => VAPID_PUBLIC_KEY, 'privateKey' => VAPID_PRIVATE_KEY],
]);

$enviados = 0;
$pulados = 0;
foreach ($linhas as $l) {
  $estado = json_decode((string)$l['estado'], true) ?: [];
  $streakAtual = (int)($estado['streak']['atual'] ?? 0);
  $ultimoDia = (string)($estado['streak']['ultimoDia'] ?? '');
  if ($streakAtual < 1 || $ultimoDia === $hoje) {
    $pulados++;
    continue;
  }

  $subscription = Subscription::create([
    'endpoint' => $l['endpoint'],
    'publicKey' => $l['chave_p256dh'],
    'authToken' => $l['chave_auth'],
  ]);
  $dias = $streakAtual === 1 ? '1 dia' : "$streakAtual dias";
  $payload = json_encode([
    'titulo' => '🔥 Não perca sua sequência!',
    'corpo' => "{$l['apelido']}, você tá com $dias seguidos — dá uma passadinha hoje!",
  ], JSON_UNESCAPED_UNICODE);
  $webPush->queueNotification($subscription, $payload);
  $enviados++;
}

$removidos = 0;
foreach ($webPush->flush() as $relatorio) {
  if ($relatorio->isSuccess()) continue;
  // inscrição expirada/inválida (404/410): apaga, não faz sentido tentar de novo depois
  if ($relatorio->isSubscriptionExpired()) {
    bd()->prepare('DELETE FROM push_inscricoes WHERE endpoint = ?')->execute([$relatorio->getEndpoint()]);
    $removidos++;
  }
}

echo "Lembrete de sequência: $enviados enviado(s), $pulados pulado(s) (sem sequência ou já jogou hoje), $removidos inscrição(ões) inválida(s) removida(s).\n";
