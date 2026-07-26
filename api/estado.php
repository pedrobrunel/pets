<?php
/* =========================================================
   Bichoteca — back-end mínimo para hospedagem compartilhada
   PHP 8 + MySQL (o que a Hostinger já oferece no plano compartilhado).
   Suba este arquivo em /api/estado.php e crie a tabela do LEIA-ME.

   Ações:
     POST ?acao=entrar   {apelido, senha}  -> cria ou autentica
     GET  ?acao=carregar                   -> devolve o estado salvo
     POST ?acao=salvar   {...estado}       -> grava o estado
   ========================================================= */

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

/* As credenciais ficam em api/config.php, que NÃO vai para o Git.
   Copie api/config.example.php para api/config.php e preencha. */
if (!is_file(__DIR__ . '/config.php')) {
  http_response_code(500);
  exit('{"erro":"Falta o api/config.php. Copie o config.example.php e preencha."}');
}
require __DIR__ . '/config.php';

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

$acao = $_GET['acao'] ?? '';

try {
  if ($acao === 'entrar') {
    $d = corpo();
    $apelido = trim((string)($d['apelido'] ?? ''));
    $senha   = (string)($d['senha'] ?? '');

    // apelido: só letras, números e _ — nada de nome real, e-mail ou telefone
    // senha: sem exigir número/maiúscula/símbolo — é jogo infantil, não banco
    if (!preg_match('/^[\p{L}0-9_]{3,14}$/u', $apelido) || mb_strlen($senha) < 4 || mb_strlen($senha) > 60) {
      responder(['erro' => 'Use um usuário de 3 a 14 letras e uma senha de pelo menos 4 caracteres.'], 422);
    }

    // "pin_hash" é o nome antigo da coluna (a tabela já existe em produção);
    // guarda o hash da senha normalmente, sem precisar mudar o banco
    $st = bd()->prepare('SELECT id, pin_hash FROM jogadores WHERE apelido = ?');
    $st->execute([$apelido]);
    $jogador = $st->fetch(PDO::FETCH_ASSOC);

    if ($jogador) {
      if (!password_verify($senha, $jogador['pin_hash'])) {
        usleep(400000); // atrasa tentativa de força bruta
        responder(['erro' => 'Usuário já existe e a senha não confere.'], 401);
      }
      $id = (int)$jogador['id'];
    } else {
      $st = bd()->prepare('INSERT INTO jogadores (apelido, pin_hash, estado) VALUES (?, ?, ?)');
      $st->execute([$apelido, password_hash($senha, PASSWORD_DEFAULT), '{}']);
      $id = (int)bd()->lastInsertId();
    }

    session_regenerate_id(true);
    $_SESSION['jogador_id'] = $id;
    responder(['ok' => true, 'apelido' => $apelido]);
  }

  $id = $_SESSION['jogador_id'] ?? null;
  if (!$id) responder(['erro' => 'Entre com seu usuário e senha primeiro.'], 401);

  if ($acao === 'carregar') {
    $st = bd()->prepare('SELECT estado FROM jogadores WHERE id = ?');
    $st->execute([$id]);
    responder(json_decode((string)$st->fetchColumn(), true) ?: []);
  }

  if ($acao === 'salvar') {
    $estado = corpo();
    // o servidor é a fonte da verdade: nunca aceite moedas/XP direto do navegador
    // sem checar. Aqui só limitamos o absurdo; o ideal é calcular a recompensa no PHP.
    foreach (['moedas', 'xp'] as $campo) {
      $estado[$campo] = max(0, min(1_000_000, (int)($estado[$campo] ?? 0)));
    }
    $st = bd()->prepare('UPDATE jogadores SET estado = ?, atualizado_em = NOW() WHERE id = ?');
    $st->execute([json_encode($estado, JSON_UNESCAPED_UNICODE), $id]);
    responder(['ok' => true]);
  }

  responder(['erro' => 'Ação desconhecida.'], 404);

} catch (Throwable $e) {
  error_log('[bichoteca] ' . $e->getMessage());
  responder(['erro' => 'Deu ruim no servidor. Tente de novo.'], 500);
}
