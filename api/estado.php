<?php
/* =========================================================
   Bichoteca — back-end mínimo para hospedagem compartilhada
   PHP 8 + MySQL (o que a Hostinger já oferece no plano compartilhado).
   Suba este arquivo em /api/estado.php e crie a tabela do LEIA-ME.

   Ações:
     POST ?acao=entrar          {apelido, senha}       -> cria ou autentica
     GET  ?acao=carregar                               -> devolve o estado salvo
     POST ?acao=salvar          {...estado}            -> grava o estado
     GET  ?acao=ver_casa        ?apelido=xx            -> casa (só isso) de outro jogador, pra visitar
     POST ?acao=completar_licao {licaoId, respostas}   -> confere gabarito e credita moedas/XP
     POST ?acao=item_loja_comprar {itemId}             -> decremento atômico de estoque limitado
     GET  ?acao=resumo_responsavel                     -> painel de acompanhamento (mesmo login do jogador)
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

  /* visitar a casa de outro jogador: só o necessário pra desenhar o quarto dele — nunca
     moedas, XP, e-mail ou qualquer outra coisa. Sem chat, sem contato: é só olhar,
     igual ao mural (recados), que já mostra apelido de quem publicou pra todo mundo. */
  if ($acao === 'ver_casa') {
    $apelido = trim((string)($_GET['apelido'] ?? ''));
    if (!preg_match('/^[\p{L}0-9_]{3,14}$/u', $apelido)) responder(['erro' => 'Apelido inválido.'], 422);
    $st = bd()->prepare('SELECT estado FROM jogadores WHERE apelido = ?');
    $st->execute([$apelido]);
    $linha = $st->fetchColumn();
    if ($linha === false) responder(['erro' => 'Não achamos ninguém com esse apelido.'], 404);
    $estadoAlheio = json_decode((string)$linha, true) ?: [];
    responder([
      'apelido' => $apelido,
      'tipo' => (string)($estadoAlheio['tipo'] ?? 'capivara'),
      'equipado' => $estadoAlheio['equipado'] ?? [],
      'casaDesbloqueada' => (bool)($estadoAlheio['casaDesbloqueada'] ?? false),
      'casaMoveis' => $estadoAlheio['casaMoveis'] ?? [],
    ]);
  }

  /* estoque limitado por quantidade (aba Lojas, "estoqueTotal" > 0): decremento atômico
     numa linha só, sem transação explícita — o UPDATE com a condição na cláusula WHERE
     já serializa concorrência pelo lock de linha do InnoDB, então dois alunos comprando
     ao mesmo tempo nunca vendem mais do que o limite. Preço/moedas continuam 100%
     confiados ao cliente (igual ao resto da loja) — só o estoque global precisa dessa
     garantia, porque é compartilhado entre todo mundo que joga. */
  if ($acao === 'item_loja_comprar') {
    $itemId = (string)(corpo()['itemId'] ?? '');
    $st = bd()->prepare('UPDATE itens_loja SET estoque_vendido = estoque_vendido + 1
      WHERE id = ? AND estoque_total > 0 AND estoque_vendido < estoque_total');
    $st->execute([$itemId]);
    if ($st->rowCount() === 0) {
      $chk = bd()->prepare('SELECT estoque_total, estoque_vendido FROM itens_loja WHERE id = ?');
      $chk->execute([$itemId]);
      $linha = $chk->fetch(PDO::FETCH_ASSOC);
      if (!$linha) responder(['erro' => 'Item não encontrado.'], 404);
      if ((int)$linha['estoque_total'] === 0) responder(['ok' => true, 'estoqueRestante' => null]);
      responder(['erro' => 'Esgotado! Não sobrou nenhuma unidade.'], 409);
    }
    $chk = bd()->prepare('SELECT estoque_total - estoque_vendido AS restante FROM itens_loja WHERE id = ?');
    $chk->execute([$itemId]);
    responder(['ok' => true, 'estoqueRestante' => (int)$chk->fetchColumn()]);
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

    // grava cada resposta (todas as tentativas, não só a última) — é o que alimenta
    // "onde a criança mais erra" no painel do responsável e no painel do Hostmaster
    $insResposta = bd()->prepare('INSERT INTO respostas (jogador_id, licao_id, indice_pergunta, resposta, acertou) VALUES (?, ?, ?, ?, ?)');
    $acertos = 0;
    foreach ($gabarito as $i => $certa) {
      $respondida = (int)($respostas[$i] ?? -1);
      $acertou = $respondida === $certa;
      if ($acertou) $acertos++;
      $insResposta->execute([$id, $licaoId, $i, $respondida, $acertou ? 1 : 0]);
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

  if ($acao === 'resumo_responsavel') {
    $st = bd()->prepare('SELECT apelido, estado, criado_em, atualizado_em FROM jogadores WHERE id = ?');
    $st->execute([$id]);
    $jogador = $st->fetch(PDO::FETCH_ASSOC);
    $estado = json_decode((string)$jogador['estado'], true) ?: [];
    $licoesFeitas = $estado['licoesFeitas'] ?? [];

    $licoesInfo = [];
    if ($licoesFeitas) {
      $marcas = implode(',', array_fill(0, count($licoesFeitas), '?'));
      $st = bd()->prepare("SELECT l.id, l.titulo, l.emoji, m.nome AS mundo FROM licoes l
        JOIN mundos m ON m.id = l.mundo_id WHERE l.id IN ($marcas)");
      $st->execute($licoesFeitas);
      $licoesInfo = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // desempenho por pergunta: taxa de acerto de cada pergunta que essa criança já
    // respondeu, cruzado com o texto da pergunta pra dar contexto (pior primeiro)
    $st = bd()->prepare('SELECT licao_id, indice_pergunta, COUNT(*) tentativas, SUM(acertou) acertos
      FROM respostas WHERE jogador_id = ? GROUP BY licao_id, indice_pergunta');
    $st->execute([$id]);
    $porPergunta = $st->fetchAll(PDO::FETCH_ASSOC);
    $blocosPorLicao = [];
    $desempenho = [];
    foreach ($porPergunta as $linha) {
      $licaoId = $linha['licao_id'];
      if (!isset($blocosPorLicao[$licaoId])) {
        $stB = bd()->prepare('SELECT titulo, blocos FROM licoes WHERE id = ?');
        $stB->execute([$licaoId]);
        $l = $stB->fetch(PDO::FETCH_ASSOC);
        $blocosPorLicao[$licaoId] = $l ? [
          'titulo' => $l['titulo'],
          'perguntas' => array_values(array_filter(json_decode($l['blocos'], true) ?: [], fn($b) => $b['tipo'] === 'pergunta')),
        ] : null;
      }
      $info = $blocosPorLicao[$licaoId];
      $pergunta = $info['perguntas'][(int)$linha['indice_pergunta']]['p'] ?? null;
      if (!$info || !$pergunta) continue; // lição/pergunta editada ou apagada depois da tentativa
      $tentativas = (int)$linha['tentativas'];
      $acertos = (int)$linha['acertos'];
      $desempenho[] = [
        'licaoTitulo' => $info['titulo'], 'pergunta' => $pergunta,
        'tentativas' => $tentativas, 'acertos' => $acertos,
        'taxaAcerto' => round($acertos / $tentativas * 100),
      ];
    }
    usort($desempenho, fn($a, $b) => $a['taxaAcerto'] <=> $b['taxaAcerto']);

    responder([
      'apelido' => $jogador['apelido'],
      'nivel' => intdiv((int)($estado['xp'] ?? 0), 120) + 1,
      'moedas' => (int)($estado['moedas'] ?? 0), 'xp' => (int)($estado['xp'] ?? 0),
      'streakAtual' => (int)($estado['streak']['atual'] ?? 0), 'streakMelhor' => (int)($estado['streak']['melhor'] ?? 0),
      'criadoEm' => $jogador['criado_em'], 'atualizadoEm' => $jogador['atualizado_em'],
      'licoesFeitas' => $licoesInfo,
      'desempenho' => array_slice($desempenho, 0, 10),
    ]);
  }

  responder(['erro' => 'Ação desconhecida.'], 404);

} catch (Throwable $e) {
  error_log('[bichoteca] ' . $e->getMessage());
  responder(['erro' => 'Deu ruim no servidor. Tente de novo.'], 500);
}
