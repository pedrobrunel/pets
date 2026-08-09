<?php
/* Helpers de banco compartilhados por estado.php, admin.php e conteudo.php.
   As credenciais ficam em api/config.php, que NÃO vai para o Git.
   Copie api/config.example.php para api/config.php e preencha. */
declare(strict_types=1);

if (!is_file(__DIR__ . '/config.php')) {
  http_response_code(500);
  exit('{"erro":"Falta o api/config.php. Copie o config.example.php e preencha."}');
}
require_once __DIR__ . '/config.php';

function bd(): PDO {
  static $pdo;
  return $pdo ??= new PDO(
    'mysql:host='.BD_HOST.';dbname='.BD_NOME.';charset=utf8mb4', BD_USER, BD_SENHA,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
}
function responder(array $dados, int $codigo = 200): never {
  http_response_code($codigo);
  echo json_encode($dados, JSON_UNESCAPED_UNICODE);
  exit;
}
function corpo(): array {
  return json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
}
/** sessão com cookie endurecido: HttpOnly sempre, Secure quando servido por HTTPS (local
    dev por HTTP continua funcionando), SameSite=Lax barra a maioria dos ataques de CSRF
    sem precisar de token — cookie não é reenviado em requisição de outro site */
function iniciarSessaoSegura(): void {
  $https = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'domain' => '',
    'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
  ]);
  session_start();
}
/** minúsculo + sem acento, pra comparar texto de forma tolerante a maiúscula/acento
    (ex.: "É", "e", "È" comparam igual) — usado tanto pra gravar a lista de palavras
    proibidas quanto pra checar mensagem/post contra ela */
function normalizarTexto(string $s): string {
  $s = mb_strtolower($s, 'UTF-8');
  static $mapa = [
    'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
    'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
    'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
    'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
    'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
    'ç'=>'c','ñ'=>'n',
  ];
  return strtr($s, $mapa);
}
/** barra palavrão (lista da tabela palavras_proibidas), link e número de telefone/celular —
    link e telefone são o clássico "vamos continuar a conversa em outro lugar" que um filtro
    de palavra sozinho nunca pega. @return string|null motivo da recusa, ou null se passou */
function textoProibido(string $texto): ?string {
  if (preg_match('/(https?:\/\/|www\.|\.com\b|\.br\b)/i', $texto)) {
    return 'Não dá pra mandar link aqui.';
  }
  if (preg_match('/(?:\d[\s.\-]*){8,}/', $texto)) {
    return 'Não dá pra mandar número de telefone aqui.';
  }
  $normalizado = normalizarTexto($texto);
  $normalizadoSemEspaco = preg_replace('/[^a-z0-9]/', '', $normalizado);
  $st = bd()->query('SELECT normalizada FROM palavras_proibidas');
  foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $proibida) {
    if ($proibida !== '' && (str_contains($normalizado, $proibida) || str_contains($normalizadoSemEspaco, $proibida))) {
      return 'Essa mensagem tem uma palavra que não é permitida aqui.';
    }
  }
  return null;
}
/** valida a grade de uma fase do Empurra-Caixas (Sokoban) — notação: espaço chão, # parede,
    @ jogador, $ caixa, . alvo, + jogador-no-alvo, * caixa-no-alvo, ^ espinho (bloqueia só o
    jogador), % gatilho (destranca as portas), D porta. Compartilhada entre admin.php (fase
    oficial, criada pelo hostmaster) e estado.php (mapa enviado por um jogador pra moderação)
    — mesmas regras estruturais nos dois casos, só quem pode publicar direto que muda.
    @return string|null motivo da recusa, ou null se a grade tá ok */
function validarSokobanGrade(string $grade): ?string {
  if (trim($grade) === '') return '"grade" não pode ficar vazia.';
  if (mb_strlen($grade) > 3000) return 'A grade tá grande demais (máximo 3000 caracteres).';
  $linhas = explode("\n", $grade);
  if (count($linhas) < 3 || count($linhas) > 30) return 'A grade precisa ter de 3 a 30 linhas.';
  foreach ($linhas as $i => $linha) {
    if (mb_strlen($linha) > 40) return 'Linha ' . ($i + 1) . ': no máximo 40 colunas.';
    if (preg_match('/[^ #@\$.+*^%D]/', $linha)) {
      return 'Linha ' . ($i + 1) . ': só pode ter espaço, # @ $ . + * ^ % D.';
    }
  }
  $jogadores = 0; $caixas = 0; $alvos = 0;
  foreach ($linhas as $linha) {
    $jogadores += substr_count($linha, '@') + substr_count($linha, '+');
    $caixas += substr_count($linha, '$') + substr_count($linha, '*');
    $alvos += substr_count($linha, '.') + substr_count($linha, '+') + substr_count($linha, '*');
  }
  if ($jogadores !== 1) return "A grade precisa ter exatamente 1 jogador (@ ou +) — tem $jogadores.";
  if ($alvos < 1) return 'A grade precisa ter pelo menos 1 alvo (.).';
  if ($caixas < $alvos) return "Tem menos caixas ($caixas) do que alvos ($alvos) — impossível de resolver.";
  return null;
}
/** notificação push em tempo real (mensagem nova, pedido de amizade aceito etc.) pra todos
    os aparelhos inscritos desse jogador — nunca deixa a ação principal falhar por causa
    disso: qualquer erro aqui (VAPID não configurado, biblioteca, rede) é engolido, é só
    melhor-esforço. $tag agrupa por TIPO de aviso (ex.: "social"), pra um não substituir o
    lembrete de sequência (tag "lembrete") nem outros tipos de notificação. */
function enviarPush(int $jogadorId, string $titulo, string $corpo, string $tag = 'social'): void {
  if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY') || VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') return;
  try {
    $st = bd()->prepare('SELECT endpoint, chave_p256dh, chave_auth FROM push_inscricoes WHERE jogador_id = ?');
    $st->execute([$jogadorId]);
    $inscricoes = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$inscricoes) return;

    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => [
      'subject' => VAPID_SUBJECT, 'publicKey' => VAPID_PUBLIC_KEY, 'privateKey' => VAPID_PRIVATE_KEY,
    ]]);
    $payload = json_encode(['titulo' => $titulo, 'corpo' => $corpo, 'tag' => 'bichoteca-' . $tag], JSON_UNESCAPED_UNICODE);
    foreach ($inscricoes as $i) {
      $sub = \Minishlink\WebPush\Subscription::create([
        'endpoint' => $i['endpoint'], 'publicKey' => $i['chave_p256dh'], 'authToken' => $i['chave_auth'],
      ]);
      $webPush->queueNotification($sub, $payload);
    }
    foreach ($webPush->flush() as $relatorio) {
      if ($relatorio->isSuccess()) continue;
      if ($relatorio->isSubscriptionExpired()) {
        bd()->prepare('DELETE FROM push_inscricoes WHERE endpoint = ?')->execute([$relatorio->getEndpoint()]);
      }
    }
  } catch (Throwable $e) {
    error_log('[bichoteca-push] ' . $e->getMessage());
  }
}
