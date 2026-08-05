<?php
/* =========================================================
   Bichoteca — conteúdo publicado (mundos, lições e cenas)
   Endpoint público (sem login): o app.html busca aqui na
   inicialização pra saber quais mundos, lições e mapas existem hoje.
   Sem banco configurado, o front-end usa o conteúdo de exemplo
   que já vem embutido nele — este arquivo é opcional.
   ========================================================= */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store'); // conteúdo muda a qualquer edição no painel; nunca serve versão velha
require __DIR__ . '/bd.php';

try {
  $mundos = bd()->query('SELECT id, nome, emoji, cor, capa_imagem, capa_ativa,
      capa_dias_semana, capa_hora_inicio, capa_hora_fim, capa_data_inicio, capa_data_fim, capa_ancora
    FROM mundos WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $st = bd()->prepare('SELECT id, emoji, titulo, serie, blocos FROM licoes WHERE mundo_id = ? AND publicado = 1 ORDER BY ordem, titulo');
  // cabeçalho opcional com imagem de capa (aba Mundos/Lojas do admin) — mesma pasta usada
  // pelo upload do painel, url só sai preenchida se o arquivo realmente existir
  $pastaMundos = dirname(__DIR__) . '/assets/mundos';
  $urlMundo = fn($nome) => $nome !== '' && is_file($pastaMundos . '/' . $nome) ? 'assets/mundos/' . $nome : '';

  $saidaMundos = [];
  $licoesPublicadas = [];
  foreach ($mundos as $m) {
    $st->execute([$m['id']]);
    $licoes = array_map(function ($l) use (&$licoesPublicadas) {
      $licoesPublicadas[$l['id']] = true;
      return [
        'id' => $l['id'], 'emoji' => $l['emoji'], 'titulo' => $l['titulo'], 'serie' => $l['serie'],
        'blocos' => json_decode($l['blocos'], true),
      ];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
    if ($licoes) $saidaMundos[] = [
      'id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'], 'licoes' => $licoes,
      'capaImagemUrl' => $urlMundo($m['capa_imagem']), 'capaAtiva' => (bool)$m['capa_ativa'],
      'capaDiasSemana' => $m['capa_dias_semana'] === '' ? [] : array_map('intval', explode(',', $m['capa_dias_semana'])),
      'capaHoraInicio' => $m['capa_hora_inicio'], 'capaHoraFim' => $m['capa_hora_fim'],
      'capaDataInicio' => $m['capa_data_inicio'], 'capaDataFim' => $m['capa_data_fim'], 'capaAncora' => $m['capa_ancora'],
    ];
  }
  $mundosPublicados = array_column($saidaMundos, 'id');

  /* cenas: só as publicadas. Sobre os pontos, três situações diferentes:
     - destino publicado    -> vai normal;
     - destino em rascunho  -> o ponto FICA, virando um avisinho "está sendo preparado".
       Antes ele era removido, e aí tocar nele não fazia nada: parecia defeito tanto pro
       aluno quanto pra quem montou o mapa. Melhor dar retorno do que sumir;
     - destino que não existe mais (apagado) -> aí sim removido, é lixo. */
  $cenas = bd()->query('SELECT id, nome, imagem, inicial FROM cenas WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $cenasPublicadas = array_column($cenas, 'id');

  // NPCs: cada um tem uma imagem (png por enquanto) e um diálogo em árvore, só botões —
  // nunca campo de texto livre pro aluno digitar, isso é regra do projeto (ver README).
  $npcsLinhas = bd()->query('SELECT id, nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, dialogo FROM npcs WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $pastaNpcs = dirname(__DIR__) . '/assets/npcs';
  $npcsPublicados = array_column($npcsLinhas, 'id');
  // a agenda (dias/horário/data) é filtrada no app.html com a hora do aparelho de quem
  // está jogando — o servidor só entrega os critérios, não decide se "agora" está dentro
  $saidaNpcs = array_map(fn($n) => [
    'id' => $n['id'], 'nome' => $n['nome'], 'emoji' => $n['emoji'],
    'imagemUrl' => $n['imagem'] !== '' && is_file($pastaNpcs . '/' . $n['imagem']) ? 'assets/npcs/' . $n['imagem'] : '',
    'imagemTipo' => $n['imagem_tipo'], 'tela' => $n['tela'],
    'diasSemana' => $n['dias_semana'] === '' ? [] : array_map('intval', explode(',', $n['dias_semana'])),
    'horaInicio' => $n['hora_inicio'], 'horaFim' => $n['hora_fim'],
    'dataInicio' => $n['data_inicio'], 'dataFim' => $n['data_fim'],
    'dialogo' => json_decode($n['dialogo'], true),
  ], $npcsLinhas);

  // missões: catálogo central, publicadas só. O NPC não guarda nada dela — só o id,
  // nos botões do diálogo — então o app.html cruza os dois na hora de decidir o que mostrar.
  $missoesLinhas = bd()->query('SELECT id, titulo, descricao, tipo, objetivo, premio FROM missoes WHERE publicado = 1 ORDER BY ordem, titulo')
    ->fetchAll(PDO::FETCH_ASSOC);
  $saidaMissoes = array_map(fn($m) => [
    'id' => $m['id'], 'titulo' => $m['titulo'], 'descricao' => $m['descricao'], 'tipo' => $m['tipo'],
    'objetivo' => json_decode($m['objetivo'], true), 'premio' => json_decode($m['premio'], true),
  ], $missoesLinhas);
  $gatilhosPublicados = array_values(array_filter(array_map(
    fn($m) => $m['tipo'] === 'gatilho' ? (json_decode($m['objetivo'], true)['chave'] ?? '') : null,
    $missoesLinhas
  )));

  // itens colecionáveis: objetos soltos no mapa (tipo "item" nos pontos). Requisito
  // (requisito_item) é opcional em QUALQUER ponto — vai puro, quem decide se o jogador
  // já tem o item é o app.html, com o inventário dele, não o servidor
  $itensLinhas = bd()->query('SELECT id, nome, emoji, imagem, descricao FROM itens WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $pastaItens = dirname(__DIR__) . '/assets/itens';
  $itensPublicados = array_column($itensLinhas, 'id');
  $saidaItens = array_map(fn($it) => [
    'id' => $it['id'], 'nome' => $it['nome'], 'emoji' => $it['emoji'],
    'imagemUrl' => $it['imagem'] !== '' && is_file($pastaItens . '/' . $it['imagem']) ? 'assets/itens/' . $it['imagem'] : '',
    'descricao' => $it['descricao'],
  ], $itensLinhas);

  // móveis: catálogo da Loja de Móveis. imagemFrente é obrigatória; as outras 3 rotações
  // são opcionais — o app.html cai pra frente quando faltar uma (ver README do painel)
  $moveisLinhas = bd()->query('SELECT id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda,
    dias_semana, hora_inicio, hora_fim, data_inicio, data_fim FROM moveis WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $pastaMoveis = dirname(__DIR__) . '/assets/moveis';
  $moveisPublicados = array_column($moveisLinhas, 'id');
  $urlMovel = fn($nome) => $nome !== '' && is_file($pastaMoveis . '/' . $nome) ? 'assets/moveis/' . $nome : '';
  // agenda (estoque por tempo limitado) filtrada no app.html com a hora do aparelho de
  // quem está jogando — mesmo padrão dos NPCs (ver bloco acima)
  $saidaMoveis = array_map(fn($m) => [
    'id' => $m['id'], 'nome' => $m['nome'], 'preco' => (int)$m['preco'], 'rotativel' => (bool)$m['rotativel'],
    'imagemFrenteUrl' => $urlMovel($m['imagem_frente']), 'imagemDireitaUrl' => $urlMovel($m['imagem_direita']),
    'imagemVersoUrl' => $urlMovel($m['imagem_verso']), 'imagemEsquerdaUrl' => $urlMovel($m['imagem_esquerda']),
    'diasSemana' => $m['dias_semana'] === '' ? [] : array_map('intval', explode(',', $m['dias_semana'])),
    'horaInicio' => $m['hora_inicio'], 'horaFim' => $m['hora_fim'],
    'dataInicio' => $m['data_inicio'], 'dataFim' => $m['data_fim'],
  ], $moveisLinhas);

  // fundo e preço de desbloqueio da Casa: um valor só, configurado no painel (aba Móveis)
  // — vale pra todo mundo. Desbloqueado (ou não) é que fica no estado de cada jogador.
  $configCasa = bd()->query('SELECT casa_fundo, preco_casa, capa_lojamoveis_imagem, capa_lojamoveis_ativa,
      capa_lojamoveis_dias_semana, capa_lojamoveis_hora_inicio, capa_lojamoveis_hora_fim, capa_lojamoveis_data_inicio, capa_lojamoveis_data_fim, capa_lojamoveis_ancora,
      capa_mural_imagem, capa_mural_ativa, capa_mural_dias_semana, capa_mural_hora_inicio, capa_mural_hora_fim, capa_mural_data_inicio, capa_mural_data_fim, capa_mural_ancora,
      musica_fundo, musica_ativa
    FROM configuracoes WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
  if (!$configCasa) {
    $configCasa = ['casa_fundo' => '', 'preco_casa' => 5000, 'musica_fundo' => '', 'musica_ativa' => 0];
    foreach (['lojamoveis', 'mural'] as $campo) {
      foreach (['imagem', 'ativa', 'dias_semana', 'hora_inicio', 'hora_fim', 'data_inicio', 'data_fim'] as $sufixo) {
        $configCasa["capa_{$campo}_{$sufixo}"] = '';
      }
      $configCasa["capa_{$campo}_ancora"] = 'center';
    }
  }
  $casaConfig = ['fundoUrl' => $urlMovel($configCasa['casa_fundo']), 'precoCasa' => (int)$configCasa['preco_casa']];
  $diasCapaFixa = fn($csv) => $csv === '' ? [] : array_map('intval', explode(',', $csv));
  $capas = [
    'lojamoveisImagemUrl' => $urlMovel($configCasa['capa_lojamoveis_imagem']), 'lojamoveisAtiva' => (bool)$configCasa['capa_lojamoveis_ativa'],
    'lojamoveisDiasSemana' => $diasCapaFixa($configCasa['capa_lojamoveis_dias_semana']),
    'lojamoveisHoraInicio' => $configCasa['capa_lojamoveis_hora_inicio'], 'lojamoveisHoraFim' => $configCasa['capa_lojamoveis_hora_fim'],
    'lojamoveisDataInicio' => $configCasa['capa_lojamoveis_data_inicio'], 'lojamoveisDataFim' => $configCasa['capa_lojamoveis_data_fim'],
    'lojamoveisAncora' => $configCasa['capa_lojamoveis_ancora'],
    'muralImagemUrl' => $urlMovel($configCasa['capa_mural_imagem']), 'muralAtiva' => (bool)$configCasa['capa_mural_ativa'],
    'muralDiasSemana' => $diasCapaFixa($configCasa['capa_mural_dias_semana']),
    'muralHoraInicio' => $configCasa['capa_mural_hora_inicio'], 'muralHoraFim' => $configCasa['capa_mural_hora_fim'],
    'muralDataInicio' => $configCasa['capa_mural_data_inicio'], 'muralDataFim' => $configCasa['capa_mural_data_fim'],
    'muralAncora' => $configCasa['capa_mural_ancora'],
  ];
  $musica = ['url' => $urlMovel($configCasa['musica_fundo']), 'ativa' => (bool)$configCasa['musica_ativa']];

  // lojas genéricas (Lanchonete, Mercado, e o que o Hostmaster criar depois) — cada uma
  // vira um destino de verdade no mapa (ponto tipo "loja"). Os itens ficam numa tabela à
  // parte, com agenda (estoque por tempo limitado) igual aos móveis e variantes opcionais
  // (sabor/tamanho) que o aluno escolhe na hora de comprar.
  $lojasLinhas = bd()->query('SELECT id, nome, emoji, capa_imagem, capa_ativa,
      capa_dias_semana, capa_hora_inicio, capa_hora_fim, capa_data_inicio, capa_data_fim, capa_ancora
    FROM lojas WHERE publicado = 1 ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
  $lojasPublicadas = array_column($lojasLinhas, 'id');
  $pastaLojas = dirname(__DIR__) . '/assets/lojas';
  $urlLoja = fn($nome) => $nome !== '' && is_file($pastaLojas . '/' . $nome) ? 'assets/lojas/' . $nome : '';
  $saidaLojas = array_map(fn($l) => [
    'id' => $l['id'], 'nome' => $l['nome'], 'emoji' => $l['emoji'],
    'capaImagemUrl' => $urlLoja($l['capa_imagem']), 'capaAtiva' => (bool)$l['capa_ativa'],
    'capaDiasSemana' => $l['capa_dias_semana'] === '' ? [] : array_map('intval', explode(',', $l['capa_dias_semana'])),
    'capaHoraInicio' => $l['capa_hora_inicio'], 'capaHoraFim' => $l['capa_hora_fim'],
    'capaDataInicio' => $l['capa_data_inicio'], 'capaDataFim' => $l['capa_data_fim'], 'capaAncora' => $l['capa_ancora'],
  ], $lojasLinhas);
  $itensLojaLinhas = $lojasPublicadas ? bd()->query('SELECT * FROM itens_loja WHERE publicado = 1 ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC) : [];
  // só itens de loja publicada (excluir a loja não apaga o item, só o esconde)
  $itensLojaLinhas = array_values(array_filter($itensLojaLinhas, fn($it) => in_array($it['loja_id'], $lojasPublicadas, true)));
  $saidaItensLoja = array_map(fn($it) => [
    'id' => $it['id'], 'lojaId' => $it['loja_id'], 'nome' => $it['nome'], 'emoji' => $it['emoji'],
    'tipo' => $it['tipo'],
    'preco' => (int)$it['preco'], 'imagemUrl' => $urlLoja($it['imagem']),
    'fome' => (int)$it['fome'], 'alegria' => (int)$it['alegria'],
    'variantes' => array_map(fn($v) => [
      'id' => $v['id'], 'nome' => $v['nome'], 'imagemUrl' => $urlLoja($v['imagem'] ?? ''),
    ], json_decode($it['variantes'], true) ?: []),
    'diasSemana' => $it['dias_semana'] === '' ? [] : array_map('intval', explode(',', $it['dias_semana'])),
    'horaInicio' => $it['hora_inicio'], 'horaFim' => $it['hora_fim'],
    'dataInicio' => $it['data_inicio'], 'dataFim' => $it['data_fim'],
    // estoqueTotal 0 = sem limite (não expõe "vendido" pro cliente, só o que falta)
    'estoqueTotal' => (int)$it['estoque_total'],
    'estoqueRestante' => (int)$it['estoque_total'] > 0 ? max(0, (int)$it['estoque_total'] - (int)$it['estoque_vendido']) : null,
  ], $itensLojaLinhas);

  $existe = [
    'cena'  => array_column(bd()->query('SELECT id FROM cenas')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'mundo' => array_column(bd()->query('SELECT id FROM mundos')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'licao' => array_column(bd()->query('SELECT id FROM licoes')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'npc'   => array_column(bd()->query('SELECT id FROM npcs')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'item'  => array_column(bd()->query('SELECT id FROM itens')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'loja'  => array_column(bd()->query('SELECT id FROM lojas')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'gatilho' => array_map(fn($m) => json_decode($m['objetivo'], true)['chave'] ?? '',
      array_filter(bd()->query("SELECT objetivo FROM missoes WHERE tipo = 'gatilho'")->fetchAll(PDO::FETCH_ASSOC))),
  ];
  $stPontos = bd()->prepare('SELECT rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica, requisito_item
    FROM pontos WHERE cena_id = ? AND publicado = 1 ORDER BY id');

  $pastaCenas = dirname(__DIR__) . '/assets/cenas';
  $saidaCenas = [];
  foreach ($cenas as $c) {
    $stPontos->execute([$c['id']]);
    $pontos = [];
    foreach ($stPontos->fetchAll(PDO::FETCH_ASSOC) as $p) {
      $tipo = $p['tipo'];
      $destino = $p['destino'];
      $mostrarSelo = (bool)$p['mostrar_selo'];
      if (isset($existe[$tipo])) {
        $publicado = match ($tipo) {
          'mundo' => in_array($destino, $mundosPublicados, true),
          'cena'  => in_array($destino, $cenasPublicadas, true),
          'licao' => isset($licoesPublicadas[$destino]),
          'npc'   => in_array($destino, $npcsPublicados, true),
          'item'  => in_array($destino, $itensPublicados, true),
          'loja'  => in_array($destino, $lojasPublicadas, true),
          'gatilho' => in_array($destino, $gatilhosPublicados, true),
        };
        if (!$publicado) {
          if (!in_array($destino, $existe[$tipo], true)) continue; // alvo apagado: ponto é lixo
          // alvo existe mas está em rascunho: mantém o ponto, só avisa em vez de navegar
          $tipo = 'aviso';
          $destino = $p['rotulo'] . ' está sendo preparado — volte em breve! 🚧';
          $mostrarSelo = false;
        }
      }
      $pontos[] = [
        'rotulo' => $p['rotulo'],
        'x' => (float)$p['x'], 'y' => (float)$p['y'],
        'largura' => (float)$p['largura'], 'altura' => (float)$p['altura'],
        'tipo' => $tipo, 'destino' => $destino,
        'mostrarSelo' => $mostrarSelo, 'mostrarDica' => (bool)$p['mostrar_dica'],
        'requisitoItem' => $p['requisito_item'],
      ];
    }
    $saidaCenas[] = [
      'id' => $c['id'], 'nome' => $c['nome'], 'inicial' => (bool)$c['inicial'],
      // imagem enviada pelo painel vive em assets/cenas/; a que veio no repositório, em assets/
      'imagem' => is_file($pastaCenas . '/' . $c['imagem']) ? 'assets/cenas/' . $c['imagem'] : 'assets/' . $c['imagem'],
      'pontos' => $pontos,
    ];
  }

  // sprites dos minigames do Arcade — só os slots com imagem enviada (o cliente já
  // conhece o emoji padrão de cada um, então aqui basta url/escala, sem entulhar quem não subiu nada)
  $pastaMinigames = dirname(__DIR__) . '/assets/minigames';
  $urlMinigame = fn($nome) => $nome !== '' && is_file($pastaMinigames . '/' . $nome) ? 'assets/minigames/' . $nome : '';
  $spritesMinigame = [];
  foreach (bd()->query('SELECT chave, imagem, escala FROM minigame_sprites')->fetchAll(PDO::FETCH_ASSOC) as $l) {
    $url = $urlMinigame($l['imagem']);
    if ($url !== '') $spritesMinigame[$l['chave']] = ['url' => $url, 'escala' => (int)$l['escala']];
  }

  // temporadas: pacotes de sprite do Arcade por período (ex.: "Festa Junina"). Só manda
  // as que estão "ativa=1" (a chave geral, tipo rascunho/publicado) e que já têm ao menos
  // 1 sprite configurado — quem decide se está DENTRO do período/dia/hora é o cliente, com
  // a hora do aparelho de quem está jogando (mesmo padrão de NPCs/capas/móveis).
  $saidaTemporadas = [];
  foreach (bd()->query('SELECT id, nome, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim FROM temporadas WHERE ativa = 1')->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $sprites = [];
    $st = bd()->prepare('SELECT chave, imagem, escala FROM temporada_sprites WHERE temporada_id = ?');
    $st->execute([$t['id']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
      $url = $urlMinigame($l['imagem']);
      if ($url !== '') $sprites[$l['chave']] = ['url' => $url, 'escala' => (int)$l['escala']];
    }
    if (!$sprites) continue;
    $saidaTemporadas[] = [
      'id' => $t['id'], 'nome' => $t['nome'],
      'diasSemana' => $t['dias_semana'], 'horaInicio' => $t['hora_inicio'], 'horaFim' => $t['hora_fim'],
      'dataInicio' => $t['data_inicio'], 'dataFim' => $t['data_fim'],
      'sprites' => $sprites,
    ];
  }

  // fundo e sons (acerto/erro) de cada minigame — só os campos com arquivo enviado, pra não
  // entulhar quem não configurou nada (o jogo cai sozinho no visual/bipe padrão)
  $minigameConfig = [];
  foreach (bd()->query('SELECT jogo, fundo, som_acerto, som_erro FROM minigame_config')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $entrada = [];
    $fundoUrl = $urlMinigame($c['fundo']);
    if ($fundoUrl !== '') $entrada['fundoUrl'] = $fundoUrl;
    $somAcertoUrl = $urlMinigame($c['som_acerto']);
    if ($somAcertoUrl !== '') $entrada['somAcertoUrl'] = $somAcertoUrl;
    $somErroUrl = $urlMinigame($c['som_erro']);
    if ($somErroUrl !== '') $entrada['somErroUrl'] = $somErroUrl;
    if ($entrada) $minigameConfig[$c['jogo']] = $entrada;
  }

  echo json_encode([
    'mundos' => $saidaMundos, 'cenas' => $saidaCenas, 'npcs' => $saidaNpcs, 'missoes' => $saidaMissoes,
    'itens' => $saidaItens, 'moveis' => $saidaMoveis, 'casaConfig' => $casaConfig, 'capas' => $capas, 'musica' => $musica,
    // chave pública do lembrete de sequência (push) — só existe se o Hostmaster já gerou
    // o par VAPID no api/config.php; sem isso, o botão de lembrete some sozinho no cliente
    'vapidPublicKey' => defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '',
    'spritesMinigame' => $spritesMinigame, 'temporadas' => $saidaTemporadas, 'minigameConfig' => $minigameConfig,
    'lojas' => $saidaLojas, 'itensLoja' => $saidaItensLoja,
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[bichoteca-conteudo] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['mundos' => [], 'cenas' => [], 'npcs' => [], 'missoes' => [], 'itens' => [], 'moveis' => [], 'casaConfig' => ['fundoUrl' => ''], 'capas' => ['lojamoveisImagemUrl' => '', 'lojamoveisAtiva' => false, 'muralImagemUrl' => '', 'muralAtiva' => false], 'musica' => ['url' => '', 'ativa' => false], 'spritesMinigame' => [], 'temporadas' => [], 'minigameConfig' => [], 'lojas' => [], 'itensLoja' => []]);
}
