<?php
/* =========================================================
   Bichoteca — painel do Hostmaster (back-end)
   Conta separada da conta de jogador: quem hospeda o site, não
   professor nem aluno. A conta é criada/atualizada pelo install.php
   a partir de ADMIN_USUARIO/ADMIN_SENHA em config.php.

   Ações (todas exigem sessão de admin, exceto admin_entrar):
     POST ?acao=admin_entrar          {usuario, senha}
     POST ?acao=admin_sair
     GET  ?acao=admin_eu
     GET  ?acao=mundos_listar
     POST ?acao=mundo_salvar          {id, nome, emoji, cor, ordem, publicado}
     POST ?acao=mundo_excluir         {id}
     GET  ?acao=licoes_listar         [?mundo=xx]
     GET  ?acao=licao_obter           ?id=xx
     POST ?acao=licao_salvar          {id, mundoId, titulo, emoji, serie, ordem, publicado, blocos:[...]}
     POST ?acao=licao_excluir         {id}
     GET  ?acao=jogadores_listar      [?busca=]
     POST ?acao=jogador_resetar_senha {id, novaSenha}
     POST ?acao=jogador_excluir       {id}
     GET  ?acao=metricas
     GET  ?acao=exportar
     POST ?acao=importar              {mundos:[...]}   (mesmo formato do exportar)
   ========================================================= */

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/bd.php';

const TIPOS_BLOCO = ['texto', 'flashcard', 'video', 'cloze', 'cacapalavras', 'pergunta'];
const SERIES_VALIDAS = ['6º ano', '7º ano', '8º ano', '9º ano', '1º ano do médio', '2º ano do médio', '3º ano do médio'];
const CORES_VALIDAS = ['var(--manga)', 'var(--rosa)', 'var(--mata)', 'var(--ceu)', 'var(--jabuti)', 'var(--moeda)', 'var(--erro)'];

function validarId(string $id): bool {
  return (bool)preg_match('/^[a-z0-9]{2,24}$/', $id);
}

/* Espelha exatamente o que cada INICIAR_BLOCO de app.html espera — um bloco
   com formato errado não trava o admin aqui, trava o aluno lá na hora de
   estudar. É por isso que essa validação existe: pra travar aqui. */
function validarBloco(array $b, int $i): ?string {
  $tipo = $b['tipo'] ?? null;
  if (!in_array($tipo, TIPOS_BLOCO, true)) return "Bloco $i: \"tipo\" ausente ou desconhecido (use: " . implode(', ', TIPOS_BLOCO) . ').';

  if ($tipo === 'texto') {
    if (!is_array($b['paragrafos'] ?? null) || !$b['paragrafos']) return "Bloco $i (texto): precisa de ao menos 1 parágrafo em \"paragrafos\".";
    foreach ($b['paragrafos'] as $p) if (!is_string($p) || $p === '') return "Bloco $i (texto): cada parágrafo precisa ser um texto não vazio.";
  }

  if ($tipo === 'flashcard') {
    if (!is_array($b['cartas'] ?? null) || !$b['cartas']) return "Bloco $i (flashcard): precisa de ao menos 1 item em \"cartas\".";
    foreach ($b['cartas'] as $c) {
      if (!is_array($c) || !is_string($c['frente'] ?? null) || !is_string($c['verso'] ?? null) || $c['frente'] === '' || $c['verso'] === '')
        return "Bloco $i (flashcard): cada carta precisa de \"frente\" e \"verso\" preenchidos.";
    }
  }

  if ($tipo === 'video') {
    if (!is_string($b['videoId'] ?? null) || !preg_match('/^[\w-]{6,15}$/', $b['videoId']))
      return "Bloco $i (video): \"videoId\" precisa ser o ID do vídeo no YouTube (6 a 15 caracteres, sem a URL toda).";
    if (!is_string($b['titulo'] ?? null) || $b['titulo'] === '') return "Bloco $i (video): falta \"titulo\".";
  }

  if ($tipo === 'cloze') {
    if (!is_string($b['frase'] ?? null) || substr_count($b['frase'], '___') !== 1)
      return "Bloco $i (cloze): \"frase\" precisa ter exatamente uma lacuna, marcada como ___.";
    if (!is_array($b['opcoes'] ?? null) || count($b['opcoes']) < 2 || count($b['opcoes']) > 6)
      return "Bloco $i (cloze): \"opcoes\" precisa ter de 2 a 6 alternativas.";
    if (!is_string($b['certa'] ?? null) || !in_array($b['certa'], $b['opcoes'], true))
      return "Bloco $i (cloze): \"certa\" precisa ser um texto idêntico a uma das \"opcoes\".";
  }

  if ($tipo === 'cacapalavras') {
    if (!is_array($b['palavras'] ?? null) || count($b['palavras']) < 2 || count($b['palavras']) > 8)
      return "Bloco $i (cacapalavras): \"palavras\" precisa ter de 2 a 8 itens.";
    foreach ($b['palavras'] as $p) {
      if (!is_string($p) || !preg_match('/^[\p{Lu}]{3,10}$/u', $p))
        return "Bloco $i (cacapalavras): cada palavra precisa ter de 3 a 10 letras MAIÚSCULAS, sem espaço nem número.";
    }
  }

  if ($tipo === 'pergunta') {
    if (!is_string($b['p'] ?? null) || $b['p'] === '') return "Bloco $i (pergunta): falta o texto da pergunta em \"p\".";
    if (!is_array($b['alts'] ?? null) || count($b['alts']) < 2 || count($b['alts']) > 6)
      return "Bloco $i (pergunta): \"alts\" precisa ter de 2 a 6 alternativas.";
    foreach ($b['alts'] as $a) if (!is_string($a) || $a === '') return "Bloco $i (pergunta): toda alternativa em \"alts\" precisa ser texto não vazio.";
    if (!is_int($b['certa'] ?? null) || $b['certa'] < 0 || $b['certa'] >= count($b['alts']))
      return "Bloco $i (pergunta): \"certa\" precisa ser o índice (número, começando em 0) de uma alternativa de \"alts\".";
  }

  return null;
}

/** @return array{erro:string}|array{gabarito:array<int>} */
function validarLicao(array $l): array {
  if (!validarId((string)($l['id'] ?? ''))) return ['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números, sem espaço nem acento.'];
  if (!is_string($l['mundoId'] ?? null) || !validarId($l['mundoId'])) return ['erro' => '"mundoId" inválido.'];
  $titulo = trim((string)($l['titulo'] ?? ''));
  if ($titulo === '' || mb_strlen($titulo) > 120) return ['erro' => '"titulo" precisa ter de 1 a 120 caracteres.'];
  $emoji = (string)($l['emoji'] ?? '');
  if ($emoji === '' || mb_strlen($emoji) > 8) return ['erro' => '"emoji" é obrigatório.'];
  if (!in_array($l['serie'] ?? null, SERIES_VALIDAS, true)) return ['erro' => '"serie" precisa ser uma destas: ' . implode(', ', SERIES_VALIDAS)];
  if (!is_array($l['blocos'] ?? null) || !$l['blocos']) return ['erro' => '"blocos" precisa ser uma lista com ao menos 1 item.'];
  foreach ($l['blocos'] as $i => $b) {
    if (!is_array($b)) return ['erro' => "Bloco $i inválido: precisa ser um objeto."];
    $erro = validarBloco($b, $i);
    if ($erro) return ['erro' => $erro];
  }
  $gabarito = array_values(array_map(
    fn($b) => $b['certa'],
    array_filter($l['blocos'], fn($b) => $b['tipo'] === 'pergunta')
  ));
  return ['gabarito' => $gabarito];
}

$acao = $_GET['acao'] ?? '';

try {
  if ($acao === 'admin_entrar') {
    $d = corpo();
    $usuario = trim((string)($d['usuario'] ?? ''));
    $senha = (string)($d['senha'] ?? '');
    $st = bd()->prepare('SELECT id, senha_hash FROM admins WHERE usuario = ?');
    $st->execute([$usuario]);
    $admin = $st->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !password_verify($senha, $admin['senha_hash'])) {
      usleep(400000); // atrasa tentativa de força bruta
      responder(['erro' => 'Usuário ou senha incorretos.'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_usuario'] = $usuario;
    responder(['ok' => true, 'usuario' => $usuario]);
  }

  if ($acao === 'admin_sair') {
    unset($_SESSION['admin_id'], $_SESSION['admin_usuario']);
    responder(['ok' => true]);
  }

  if ($acao === 'admin_eu') {
    responder(['logado' => isset($_SESSION['admin_id']), 'usuario' => $_SESSION['admin_usuario'] ?? null]);
  }

  if (!isset($_SESSION['admin_id'])) responder(['erro' => 'Entre no painel primeiro.'], 401);

  if ($acao === 'mundos_listar') {
    $linhas = bd()->query('SELECT m.id, m.nome, m.emoji, m.cor, m.ordem, m.publicado, COUNT(l.id) AS licoes
      FROM mundos m LEFT JOIN licoes l ON l.mundo_id = m.id GROUP BY m.id ORDER BY m.ordem, m.nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['mundos' => array_map(fn($m) => [
      'id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'],
      'ordem' => (int)$m['ordem'], 'publicado' => (bool)$m['publicado'], 'licoes' => (int)$m['licoes'],
    ], $linhas)]);
  }

  if ($acao === 'mundo_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 60) responder(['erro' => '"nome" precisa ter de 1 a 60 caracteres.'], 422);
    $emoji = (string)($d['emoji'] ?? '');
    if ($emoji === '' || mb_strlen($emoji) > 8) responder(['erro' => '"emoji" é obrigatório.'], 422);
    $cor = (string)($d['cor'] ?? '');
    if (!in_array($cor, CORES_VALIDAS, true)) responder(['erro' => '"cor" precisa ser uma das cores do tema: ' . implode(', ', CORES_VALIDAS)], 422);
    $ordem = (int)($d['ordem'] ?? 0);
    $publicado = !empty($d['publicado']) ? 1 : 0;
    bd()->prepare('INSERT INTO mundos (id, nome, emoji, cor, ordem, publicado) VALUES (?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), cor=VALUES(cor), ordem=VALUES(ordem), publicado=VALUES(publicado)')
      ->execute([$id, $nome, $emoji, $cor, $ordem, $publicado]);
    responder(['ok' => true]);
  }

  if ($acao === 'mundo_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM mundos WHERE id = ?')->execute([(string)($d['id'] ?? '')]);
    responder(['ok' => true]);
  }

  if ($acao === 'licoes_listar') {
    $mundo = $_GET['mundo'] ?? null;
    if ($mundo) {
      $st = bd()->prepare('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado FROM licoes WHERE mundo_id = ? ORDER BY ordem, titulo');
      $st->execute([(string)$mundo]);
    } else {
      $st = bd()->query('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado FROM licoes ORDER BY mundo_id, ordem, titulo');
    }
    responder(['licoes' => array_map(fn($l) => [
      'id' => $l['id'], 'mundoId' => $l['mundo_id'], 'titulo' => $l['titulo'], 'emoji' => $l['emoji'],
      'serie' => $l['serie'], 'ordem' => (int)$l['ordem'], 'publicado' => (bool)$l['publicado'],
    ], $st->fetchAll(PDO::FETCH_ASSOC))]);
  }

  if ($acao === 'licao_obter') {
    $st = bd()->prepare('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos FROM licoes WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) responder(['erro' => 'Lição não encontrada.'], 404);
    responder([
      'id' => $l['id'], 'mundoId' => $l['mundo_id'], 'titulo' => $l['titulo'], 'emoji' => $l['emoji'],
      'serie' => $l['serie'], 'ordem' => (int)$l['ordem'], 'publicado' => (bool)$l['publicado'],
      'blocos' => json_decode($l['blocos'], true),
    ]);
  }

  if ($acao === 'licao_desempenho') {
    $licaoId = (string)($_GET['id'] ?? '');
    $st = bd()->prepare('SELECT titulo, blocos FROM licoes WHERE id = ?');
    $st->execute([$licaoId]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) responder(['erro' => 'Lição não encontrada.'], 404);
    $perguntas = array_values(array_filter(json_decode($l['blocos'], true) ?: [], fn($b) => $b['tipo'] === 'pergunta'));

    $st = bd()->prepare('SELECT indice_pergunta, COUNT(*) tentativas, SUM(acertou) acertos
      FROM respostas WHERE licao_id = ? GROUP BY indice_pergunta');
    $st->execute([$licaoId]);
    $porIndice = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $linha) $porIndice[(int)$linha['indice_pergunta']] = $linha;

    $saida = [];
    foreach ($perguntas as $i => $p) {
      $linha = $porIndice[$i] ?? null;
      $tentativas = $linha ? (int)$linha['tentativas'] : 0;
      $acertos = $linha ? (int)$linha['acertos'] : 0;
      $saida[] = [
        'indice' => $i, 'pergunta' => $p['p'], 'alts' => $p['alts'], 'certa' => $p['certa'],
        'tentativas' => $tentativas, 'acertos' => $acertos,
        'taxaAcerto' => $tentativas ? round($acertos / $tentativas * 100) : null,
      ];
    }
    responder(['titulo' => $l['titulo'], 'perguntas' => $saida]);
  }

  if ($acao === 'licao_salvar') {
    $d = corpo();
    $st = bd()->prepare('SELECT COUNT(*) FROM mundos WHERE id = ?');
    $st->execute([(string)($d['mundoId'] ?? '')]);
    if (!$st->fetchColumn()) responder(['erro' => '"mundoId" não existe. Crie o mundo antes da lição.'], 422);
    $resultado = validarLicao($d);
    if (isset($resultado['erro'])) responder(['erro' => $resultado['erro']], 422);
    bd()->prepare('INSERT INTO licoes (id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos, gabarito)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE mundo_id=VALUES(mundo_id), titulo=VALUES(titulo), emoji=VALUES(emoji), serie=VALUES(serie),
        ordem=VALUES(ordem), publicado=VALUES(publicado), blocos=VALUES(blocos), gabarito=VALUES(gabarito)')
      ->execute([
        (string)$d['id'], (string)$d['mundoId'], trim((string)$d['titulo']), (string)$d['emoji'], (string)$d['serie'],
        (int)($d['ordem'] ?? 0), !empty($d['publicado']) ? 1 : 0,
        json_encode($d['blocos'], JSON_UNESCAPED_UNICODE), json_encode($resultado['gabarito'], JSON_UNESCAPED_UNICODE),
      ]);
    responder(['ok' => true, 'totalPerguntas' => count($resultado['gabarito'])]);
  }

  if ($acao === 'licao_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM licoes WHERE id = ?')->execute([(string)($d['id'] ?? '')]);
    responder(['ok' => true]);
  }

  if ($acao === 'jogadores_listar') {
    $busca = trim((string)($_GET['busca'] ?? ''));
    if ($busca !== '') {
      $st = bd()->prepare('SELECT id, apelido, estado, criado_em, atualizado_em FROM jogadores WHERE apelido LIKE ? ORDER BY atualizado_em DESC LIMIT 200');
      $st->execute(['%' . $busca . '%']);
      $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
      $linhas = bd()->query('SELECT id, apelido, estado, criado_em, atualizado_em FROM jogadores ORDER BY atualizado_em DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    }
    responder(['jogadores' => array_map(function ($j) {
      $estado = json_decode((string)$j['estado'], true) ?: [];
      return [
        'id' => (int)$j['id'], 'apelido' => $j['apelido'],
        'moedas' => (int)($estado['moedas'] ?? 0), 'xp' => (int)($estado['xp'] ?? 0),
        'licoesFeitas' => count($estado['licoesFeitas'] ?? []), 'streak' => (int)($estado['streak']['atual'] ?? 0),
        'criadoEm' => $j['criado_em'], 'atualizadoEm' => $j['atualizado_em'],
      ];
    }, $linhas)]);
  }

  if ($acao === 'jogador_resetar_senha') {
    $d = corpo();
    $novaSenha = (string)($d['novaSenha'] ?? '');
    if (mb_strlen($novaSenha) < 4 || mb_strlen($novaSenha) > 60) responder(['erro' => 'Senha precisa ter de 4 a 60 caracteres.'], 422);
    bd()->prepare('UPDATE jogadores SET pin_hash = ? WHERE id = ?')->execute([password_hash($novaSenha, PASSWORD_DEFAULT), (int)($d['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'jogador_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM jogadores WHERE id = ?')->execute([(int)($d['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'metricas') {
    $bdc = bd();
    $totalConcluidas = 0;
    foreach ($bdc->query('SELECT estado FROM jogadores') as $linha) {
      $e = json_decode((string)$linha['estado'], true) ?: [];
      $totalConcluidas += count($e['licoesFeitas'] ?? []);
    }
    $totalLicoes = (int)$bdc->query('SELECT COUNT(*) FROM licoes')->fetchColumn();
    $licoesPublicadas = (int)$bdc->query('SELECT COUNT(*) FROM licoes WHERE publicado = 1')->fetchColumn();
    responder([
      'totalJogadores' => (int)$bdc->query('SELECT COUNT(*) FROM jogadores')->fetchColumn(),
      'ativos7d' => (int)$bdc->query('SELECT COUNT(*) FROM jogadores WHERE atualizado_em >= NOW() - INTERVAL 7 DAY')->fetchColumn(),
      'totalMundos' => (int)$bdc->query('SELECT COUNT(*) FROM mundos')->fetchColumn(),
      'totalLicoes' => $totalLicoes, 'licoesPublicadas' => $licoesPublicadas, 'licoesRascunho' => $totalLicoes - $licoesPublicadas,
      'totalConclusoes' => $totalConcluidas,
    ]);
  }

  if ($acao === 'exportar') {
    $mundos = bd()->query('SELECT id, nome, emoji, cor, ordem, publicado FROM mundos ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $porMundo = [];
    foreach (bd()->query('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos FROM licoes ORDER BY ordem, titulo') as $l) {
      $porMundo[$l['mundo_id']][] = [
        'id' => $l['id'], 'titulo' => $l['titulo'], 'emoji' => $l['emoji'], 'serie' => $l['serie'],
        'ordem' => (int)$l['ordem'], 'publicado' => (bool)$l['publicado'], 'blocos' => json_decode($l['blocos'], true),
      ];
    }
    responder(['mundos' => array_map(fn($m) => [
      'id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'],
      'ordem' => (int)$m['ordem'], 'publicado' => (bool)$m['publicado'], 'licoes' => $porMundo[$m['id']] ?? [],
    ], $mundos)]);
  }

  if ($acao === 'importar') {
    $d = corpo();
    $mundos = $d['mundos'] ?? null;
    if (!is_array($mundos)) responder(['erro' => 'Formato inválido: esperado {"mundos": [...]} — o mesmo do botão "Baixar backup".'], 422);

    // valida tudo antes de gravar qualquer coisa: ou importa inteiro, ou não muda nada
    foreach ($mundos as $m) {
      if (!validarId((string)($m['id'] ?? ''))) responder(['erro' => 'Mundo com "id" inválido: ' . json_encode($m['id'] ?? null)], 422);
      if (!in_array($m['cor'] ?? null, CORES_VALIDAS, true)) responder(['erro' => 'Mundo "' . $m['id'] . '": cor inválida.'], 422);
      foreach (($m['licoes'] ?? []) as $l) {
        $l['mundoId'] = $m['id'];
        $resultado = validarLicao($l);
        if (isset($resultado['erro'])) responder(['erro' => 'Lição "' . ($l['id'] ?? '?') . '": ' . $resultado['erro']], 422);
      }
    }

    $bdc = bd();
    $bdc->beginTransaction();
    try {
      $insMundo = $bdc->prepare('INSERT INTO mundos (id, nome, emoji, cor, ordem, publicado) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), cor=VALUES(cor), ordem=VALUES(ordem), publicado=VALUES(publicado)');
      $insLicao = $bdc->prepare('INSERT INTO licoes (id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos, gabarito) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE mundo_id=VALUES(mundo_id), titulo=VALUES(titulo), emoji=VALUES(emoji), serie=VALUES(serie),
          ordem=VALUES(ordem), publicado=VALUES(publicado), blocos=VALUES(blocos), gabarito=VALUES(gabarito)');
      foreach ($mundos as $m) {
        $insMundo->execute([$m['id'], $m['nome'], $m['emoji'], $m['cor'], (int)($m['ordem'] ?? 0), !empty($m['publicado']) ? 1 : 0]);
        foreach (($m['licoes'] ?? []) as $l) {
          $l['mundoId'] = $m['id'];
          $resultado = validarLicao($l);
          $insLicao->execute([
            $l['id'], $m['id'], trim((string)$l['titulo']), $l['emoji'], $l['serie'], (int)($l['ordem'] ?? 0), !empty($l['publicado']) ? 1 : 0,
            json_encode($l['blocos'], JSON_UNESCAPED_UNICODE), json_encode($resultado['gabarito'], JSON_UNESCAPED_UNICODE),
          ]);
        }
      }
      $bdc->commit();
    } catch (Throwable $e) {
      $bdc->rollBack();
      throw $e;
    }
    responder(['ok' => true, 'mundos' => count($mundos)]);
  }

  responder(['erro' => 'Ação desconhecida.'], 404);

} catch (Throwable $e) {
  error_log('[bichoteca-admin] ' . $e->getMessage());
  responder(['erro' => 'Deu ruim no servidor. Tente de novo.'], 500);
}
