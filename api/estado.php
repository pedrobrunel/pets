<?php
/* =========================================================
   Bichoteca — back-end mínimo para hospedagem compartilhada
   PHP 8 + MySQL (o que a Hostinger já oferece no plano compartilhado).
   Suba este arquivo em /api/estado.php e crie a tabela do LEIA-ME.

   Ações:
     POST ?acao=entrar          {apelido, senha}       -> cria ou autentica
     GET  ?acao=carregar                               -> devolve o estado salvo
     POST ?acao=salvar          {...estado}            -> grava o estado
     POST ?acao=completar_licao {licaoId, respostas}   -> confere gabarito e credita moedas/XP
   ========================================================= */

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/bd.php';

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
    // sem checar. Isso só limita o absurdo (ex.: alguém injetando moedas:9999999
    // direto pelo console); a fonte real de crédito é o completar_licao abaixo.
    foreach (['moedas', 'xp'] as $campo) {
      $estado[$campo] = max(0, min(1_000_000, (int)($estado[$campo] ?? 0)));
    }
    $st = bd()->prepare('UPDATE jogadores SET estado = ?, atualizado_em = NOW() WHERE id = ?');
    $st->execute([json_encode($estado, JSON_UNESCAPED_UNICODE), $id]);
    responder(['ok' => true]);
  }

  if ($acao === 'completar_licao') {
    $d = corpo();
    $licaoId = (string)($d['licaoId'] ?? '');
    $respostas = is_array($d['respostas'] ?? null) ? $d['respostas'] : [];
    // gabarito é gravado pelo painel administrativo (api/admin.php) toda vez que uma
    // lição é salva — deriva do "certa:" de cada bloco tipo "pergunta", na ordem deles
    $st = bd()->prepare('SELECT gabarito FROM licoes WHERE id = ? AND publicado = 1');
    $st->execute([$licaoId]);
    $gabaritoJson = $st->fetchColumn();
    if ($gabaritoJson === false) responder(['erro' => 'Lição sem gabarito no servidor.'], 422);
    $gabarito = json_decode((string)$gabaritoJson, true) ?? [];

    $acertos = 0;
    foreach ($gabarito as $i => $certa) {
      if ((int)($respostas[$i] ?? -1) === $certa) $acertos++;
    }
    $total = count($gabarito);
    $meta = (int)ceil($total * 2 / 3);

    $st = bd()->prepare('SELECT estado FROM jogadores WHERE id = ?');
    $st->execute([$id]);
    $estado = json_decode((string)$st->fetchColumn(), true) ?: [];
    $estado['licoesFeitas'] = $estado['licoesFeitas'] ?? [];
    $primeiraVez = !in_array($licaoId, $estado['licoesFeitas'], true);

    $moedas = $acertos * ($primeiraVez ? 10 : 3);
    $xp = $acertos * ($primeiraVez ? 15 : 5);
    if ($primeiraVez && $acertos >= $meta) $estado['licoesFeitas'][] = $licaoId;
    $estado['moedas'] = max(0, min(1_000_000, (int)($estado['moedas'] ?? 0) + $moedas));
    $estado['xp'] = max(0, min(1_000_000, (int)($estado['xp'] ?? 0) + $xp));

    $st = bd()->prepare('UPDATE jogadores SET estado = ?, atualizado_em = NOW() WHERE id = ?');
    $st->execute([json_encode($estado, JSON_UNESCAPED_UNICODE), $id]);
    responder([
      'ok' => true, 'acertos' => $acertos, 'total' => $total,
      'moedas' => $moedas, 'xp' => $xp, 'licoesFeitas' => $estado['licoesFeitas'],
    ]);
  }

  responder(['erro' => 'Ação desconhecida.'], 404);

} catch (Throwable $e) {
  error_log('[bichoteca] ' . $e->getMessage());
  responder(['erro' => 'Deu ruim no servidor. Tente de novo.'], 500);
}
