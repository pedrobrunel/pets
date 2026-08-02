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
  $mundos = bd()->query('SELECT id, nome, emoji, cor FROM mundos WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $st = bd()->prepare('SELECT id, emoji, titulo, serie, blocos FROM licoes WHERE mundo_id = ? AND publicado = 1 ORDER BY ordem, titulo');

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
    if ($licoes) $saidaMundos[] = ['id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'], 'licoes' => $licoes];
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

  $existe = [
    'cena'  => array_column(bd()->query('SELECT id FROM cenas')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'mundo' => array_column(bd()->query('SELECT id FROM mundos')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'licao' => array_column(bd()->query('SELECT id FROM licoes')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'npc'   => array_column(bd()->query('SELECT id FROM npcs')->fetchAll(PDO::FETCH_ASSOC), 'id'),
    'gatilho' => array_map(fn($m) => json_decode($m['objetivo'], true)['chave'] ?? '',
      array_filter(bd()->query("SELECT objetivo FROM missoes WHERE tipo = 'gatilho'")->fetchAll(PDO::FETCH_ASSOC))),
  ];
  $stPontos = bd()->prepare('SELECT rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica
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
      ];
    }
    $saidaCenas[] = [
      'id' => $c['id'], 'nome' => $c['nome'], 'inicial' => (bool)$c['inicial'],
      // imagem enviada pelo painel vive em assets/cenas/; a que veio no repositório, em assets/
      'imagem' => is_file($pastaCenas . '/' . $c['imagem']) ? 'assets/cenas/' . $c['imagem'] : 'assets/' . $c['imagem'],
      'pontos' => $pontos,
    ];
  }

  echo json_encode(['mundos' => $saidaMundos, 'cenas' => $saidaCenas, 'npcs' => $saidaNpcs, 'missoes' => $saidaMissoes], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[bichoteca-conteudo] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['mundos' => [], 'cenas' => [], 'npcs' => [], 'missoes' => []]);
}
