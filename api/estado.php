<?php
/* =========================================================
   Bichoteca — back-end mínimo para hospedagem compartilhada
   PHP 8 + MySQL (o que a Hostinger já oferece no plano compartilhado).
   Suba este arquivo em /api/estado.php e crie a tabela do LEIA-ME.

   Ações:
     POST ?acao=cadastrar       {apelido, senha}       -> cria uma conta nova (erro se o nome já existe)
     POST ?acao=entrar          {apelido, senha}       -> autentica uma conta existente (nunca cria)
     GET  ?acao=carregar                               -> devolve o estado salvo
     POST ?acao=salvar          {...estado}            -> grava o estado
     GET  ?acao=ver_casa        ?apelido=xx            -> casa (só isso) de outro jogador, pra visitar
     POST ?acao=completar_licao {licaoId, respostas}   -> confere gabarito e credita moedas/XP
     POST ?acao=item_loja_comprar {itemId}             -> decremento atômico de estoque limitado
     POST ?acao=presentear_item {itemId, varianteId, apelidoDestino} -> transfere 1 unidade da mochila
     GET  ?acao=ranking                                -> top 10 por nível e por sequência (só apelido/bicho/número)
     POST ?acao=push_inscrever  {endpoint, p256dh, auth} -> ativa lembrete de sequência nesse aparelho
     POST ?acao=push_desinscrever {endpoint}            -> desativa nesse aparelho
     GET  ?acao=resumo_responsavel                     -> painel de acompanhamento (mesmo login do jogador)
   ========================================================= */

declare(strict_types=1);
require __DIR__ . '/bd.php';
iniciarSessaoSegura();
header('Content-Type: application/json; charset=utf-8');

$acao = $_GET['acao'] ?? '';

/* apelido: só letras, números e _ — nada de nome real, e-mail ou telefone. Senha sem
   exigir maiúscula/número/símbolo (é jogo infantil, não banco), só o tamanho. Mesma
   checagem pros dois fluxos (cadastrar/entrar). */
function validarApelidoSenha(string $apelido, string $senha): ?string {
  if (!preg_match('/^[\p{L}0-9_]{3,14}$/u', $apelido)) {
    return 'Use um usuário de 3 a 14 letras, números ou _.';
  }
  if (mb_strlen($senha) < 4 || mb_strlen($senha) > 60) {
    return 'A senha precisa ter de 4 a 60 caracteres.';
  }
  return null;
}

try {
  if ($acao === 'cadastrar') {
    $d = corpo();
    $apelido = trim((string)($d['apelido'] ?? ''));
    $senha   = (string)($d['senha'] ?? '');
    $erro = validarApelidoSenha($apelido, $senha);
    if ($erro) responder(['erro' => $erro], 422);

    $st = bd()->prepare('SELECT id FROM jogadores WHERE apelido = ?');
    $st->execute([$apelido]);
    if ($st->fetch()) {
      responder(['erro' => 'Esse nome de usuário já existe. Tente entrar, ou escolha outro nome.'], 422);
    }

    // "pin_hash" é o nome antigo da coluna (a tabela já existe em produção);
    // guarda o hash da senha normalmente, sem precisar mudar o banco
    $st = bd()->prepare('INSERT INTO jogadores (apelido, pin_hash, estado) VALUES (?, ?, ?)');
    $st->execute([$apelido, password_hash($senha, PASSWORD_DEFAULT), '{}']);
    $id = (int)bd()->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['jogador_id'] = $id;
    $_SESSION['versao_sessao'] = 0;
    responder(['ok' => true, 'apelido' => $apelido]);
  }

  if ($acao === 'entrar') {
    $d = corpo();
    $apelido = trim((string)($d['apelido'] ?? ''));
    $senha   = (string)($d['senha'] ?? '');
    $erro = validarApelidoSenha($apelido, $senha);
    if ($erro) responder(['erro' => $erro], 422);

    $st = bd()->prepare('SELECT id, pin_hash, tentativas_falhas, bloqueado_ate, versao_sessao FROM jogadores WHERE apelido = ?');
    $st->execute([$apelido]);
    $jogador = $st->fetch(PDO::FETCH_ASSOC);

    // mensagem igual pros dois casos (usuário não existe / senha errada) — não dá pista de
    // qual dos dois é, prática recomendada contra enumeração de contas
    $erroGenerico = ['erro' => 'Usuário ou senha incorretos.'];

    if (!$jogador) {
      usleep(400000); // mesmo atraso do caso "senha errada" abaixo, pra não dar pista pelo tempo de resposta
      responder($erroGenerico, 401);
    }

    if ($jogador['bloqueado_ate'] !== null && $jogador['bloqueado_ate'] > date('Y-m-d H:i:s')) {
      responder(['erro' => 'Muitas tentativas erradas. Tente de novo em alguns minutos, ou peça pro professor resetar sua senha.'], 429);
    }

    if (!password_verify($senha, $jogador['pin_hash'])) {
      $tentativas = (int)$jogador['tentativas_falhas'] + 1;
      // bloqueia por 5 minutos a partir da 6ª tentativa errada seguida — segura força
      // bruta mesmo com pedidos em paralelo (o usleep sozinho não segurava isso)
      $bloqueioAte = $tentativas >= 6 ? date('Y-m-d H:i:s', time() + 300) : null;
      bd()->prepare('UPDATE jogadores SET tentativas_falhas = ?, bloqueado_ate = ? WHERE id = ?')
        ->execute([$tentativas, $bloqueioAte, $jogador['id']]);
      usleep(400000);
      responder($erroGenerico, 401);
    }

    bd()->prepare('UPDATE jogadores SET tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = ?')->execute([$jogador['id']]);

    session_regenerate_id(true);
    $_SESSION['jogador_id'] = (int)$jogador['id'];
    $_SESSION['versao_sessao'] = (int)$jogador['versao_sessao'];
    responder(['ok' => true, 'apelido' => $apelido]);
  }

  $id = $_SESSION['jogador_id'] ?? null;
  if (!$id) responder(['erro' => 'Entre com seu usuário e senha primeiro.'], 401);

  // sessão fica inválida se a senha foi resetada (painel do professor) depois do login —
  // sem isso, uma sessão antiga continuaria valendo mesmo depois de trocar a senha
  $st = bd()->prepare('SELECT versao_sessao FROM jogadores WHERE id = ?');
  $st->execute([$id]);
  $versaoAtual = $st->fetchColumn();
  if ($versaoAtual === false || (int)$versaoAtual !== (int)($_SESSION['versao_sessao'] ?? -1)) {
    session_unset();
    session_destroy();
    responder(['erro' => 'Sua sessão expirou (a senha foi trocada). Entre de novo.'], 401);
  }

  if ($acao === 'carregar') {
    $st = bd()->prepare('SELECT estado FROM jogadores WHERE id = ?');
    $st->execute([$id]);
    $estado = json_decode((string)$st->fetchColumn(), true) ?: [];

    // presentes pendentes (ver "presentear_item" abaixo): só o dono da linha entrega,
    // aqui mesmo no carregar — evita duas escritas concorrentes na mesma linha por causa
    // de um presente chegando enquanto o próprio jogador também está salvando algo seu
    $stPresentes = bd()->prepare('SELECT id, item_chave, remetente_apelido FROM presentes WHERE destinatario_id = ?');
    $stPresentes->execute([$id]);
    $presentes = $stPresentes->fetchAll(PDO::FETCH_ASSOC);
    $presentesRecebidos = [];
    if ($presentes) {
      $estado['inventario'] = $estado['inventario'] ?? [];
      foreach ($presentes as $p) {
        $estado['inventario'][$p['item_chave']] = (int)($estado['inventario'][$p['item_chave']] ?? 0) + 1;
        $presentesRecebidos[] = ['itemChave' => $p['item_chave'], 'de' => $p['remetente_apelido']];
      }
      bd()->prepare('UPDATE jogadores SET estado = ? WHERE id = ?')->execute([json_encode($estado, JSON_UNESCAPED_UNICODE), $id]);
      $idsPresentes = array_column($presentes, 'id');
      $marcas = implode(',', array_fill(0, count($idsPresentes), '?'));
      bd()->prepare("DELETE FROM presentes WHERE id IN ($marcas)")->execute($idsPresentes);
    }
    $estado['presentesRecebidos'] = $presentesRecebidos; // efêmero — o cliente lê e descarta, não faz parte do estado salvo de verdade
    responder($estado);
  }

  /* ranking entre quem joga — só apelido, bicho e um número (nível ou sequência), nunca
     moedas ou qualquer outra coisa mais sensível. Filtra fora quem tá zerado (conta nova,
     nunca jogou) pra não poluir a lista; cada métrica é uma consulta separada porque um
     jogador pode estar bem numa e mal na outra. */
  if ($acao === 'ranking') {
    $porNivel = bd()->query("SELECT apelido,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(estado, '$.tipo')), 'capivara') AS tipo,
        COALESCE(JSON_EXTRACT(estado, '$.xp'), 0) AS xp
      FROM jogadores HAVING xp > 0 ORDER BY xp DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $porStreak = bd()->query("SELECT apelido,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(estado, '$.tipo')), 'capivara') AS tipo,
        COALESCE(JSON_EXTRACT(estado, '$.streak.atual'), 0) AS streak_atual,
        COALESCE(JSON_EXTRACT(estado, '$.streak.melhor'), 0) AS streak_melhor
      FROM jogadores HAVING streak_atual > 0 ORDER BY streak_atual DESC, streak_melhor DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    responder([
      'porNivel' => array_map(fn($l) => [
        'apelido' => $l['apelido'], 'tipo' => $l['tipo'], 'nivel' => intdiv((int)$l['xp'], 120) + 1, 'xp' => (int)$l['xp'],
      ], $porNivel),
      'porStreak' => array_map(fn($l) => [
        'apelido' => $l['apelido'], 'tipo' => $l['tipo'], 'streakAtual' => (int)$l['streak_atual'], 'streakMelhor' => (int)$l['streak_melhor'],
      ], $porStreak),
    ]);
  }

  /* inscrição de notificação push (lembrete de sequência, opcional — ativado no Perfil).
     Um aparelho = uma inscrição; se o mesmo endpoint já existir pra esse jogador, apaga a
     antiga antes (evita duplicar notificação no mesmo aparelho quando ele reativa). */
  if ($acao === 'push_inscrever') {
    $d = corpo();
    $endpoint = (string)($d['endpoint'] ?? '');
    $p256dh = (string)($d['p256dh'] ?? '');
    $auth = (string)($d['auth'] ?? '');
    if ($endpoint === '' || $p256dh === '' || $auth === '') responder(['erro' => 'Inscrição incompleta.'], 422);
    bd()->prepare('DELETE FROM push_inscricoes WHERE jogador_id = ? AND endpoint = ?')->execute([$id, $endpoint]);
    bd()->prepare('INSERT INTO push_inscricoes (jogador_id, endpoint, chave_p256dh, chave_auth) VALUES (?, ?, ?, ?)')
      ->execute([$id, $endpoint, $p256dh, $auth]);
    responder(['ok' => true]);
  }

  if ($acao === 'push_desinscrever') {
    $endpoint = (string)(corpo()['endpoint'] ?? '');
    bd()->prepare('DELETE FROM push_inscricoes WHERE jogador_id = ? AND endpoint = ?')->execute([$id, $endpoint]);
    responder(['ok' => true]);
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

  /* presentear: manda 1 unidade de um item da mochila pra outro jogador, pelo apelido.
     Só mexe na PRÓPRIA linha do remetente (decrementa a mochila) — nunca escreve direto
     na linha do destinatário. O item fica pendente na tabela "presentes" até o
     destinatário abrir o jogo de novo; é aí que ele mesmo aplica o +1 na própria linha
     (ver "carregar" acima). Isso evita a corrida clássica de duas escritas concorrentes
     na mesma linha: se escrevêssemos direto na mochila do destinatário aqui, e o
     autosave (debounce de 2s) do PRÓPRIO destinatário disparasse logo depois com um
     estado desatualizado (sem saber do presente), ele sobrescreveria o presente e o
     item sumia sem ninguém perceber. */
  if ($acao === 'presentear_item') {
    $d = corpo();
    $itemId = (string)($d['itemId'] ?? '');
    $varianteId = trim((string)($d['varianteId'] ?? ''));
    $apelidoDestino = trim((string)($d['apelidoDestino'] ?? ''));
    if (!preg_match('/^[\p{L}0-9_]{3,14}$/u', $apelidoDestino)) responder(['erro' => 'Apelido inválido.'], 422);

    $st = bd()->prepare('SELECT id, apelido FROM jogadores WHERE apelido = ?');
    $st->execute([$apelidoDestino]);
    $destino = $st->fetch(PDO::FETCH_ASSOC);
    if (!$destino) responder(['erro' => 'Não achamos ninguém com esse apelido.'], 404);
    if ((int)$destino['id'] === $id) responder(['erro' => 'Você não pode presentear você mesmo :)'], 422);

    $chave = $varianteId !== '' ? "{$itemId}__{$varianteId}" : $itemId;
    $st = bd()->prepare('SELECT apelido, estado FROM jogadores WHERE id = ?');
    $st->execute([$id]);
    $linhaRemetente = $st->fetch(PDO::FETCH_ASSOC);
    $estadoRemetente = json_decode((string)$linhaRemetente['estado'], true) ?: [];
    if ((int)($estadoRemetente['inventario'][$chave] ?? 0) < 1) {
      responder(['erro' => 'Você não tem esse item pra presentear.'], 422);
    }
    $estadoRemetente['inventario'][$chave]--;
    bd()->prepare('UPDATE jogadores SET estado = ?, atualizado_em = NOW() WHERE id = ?')
      ->execute([json_encode($estadoRemetente, JSON_UNESCAPED_UNICODE), $id]);
    bd()->prepare('INSERT INTO presentes (destinatario_id, item_chave, remetente_apelido) VALUES (?, ?, ?)')
      ->execute([(int)$destino['id'], $chave, $linhaRemetente['apelido']]);
    responder(['ok' => true, 'apelidoDestino' => $destino['apelido']]);
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
