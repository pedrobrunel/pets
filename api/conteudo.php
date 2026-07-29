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

  /* cenas: só as publicadas, e só os pontos cujo destino existe de verdade. Um mundo
     em rascunho (ou apagado depois que o ponto foi criado) sairia como botão que não
     leva a nada — melhor esconder aqui do que deixar a criança tocando no vazio. */
  $cenas = bd()->query('SELECT id, nome, imagem, inicial FROM cenas WHERE publicado = 1 ORDER BY ordem, nome')
    ->fetchAll(PDO::FETCH_ASSOC);
  $cenasPublicadas = array_column($cenas, 'id');
  $stPontos = bd()->prepare('SELECT rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica
    FROM pontos WHERE cena_id = ? AND publicado = 1 ORDER BY id');

  $pastaCenas = dirname(__DIR__) . '/assets/cenas';
  $saidaCenas = [];
  foreach ($cenas as $c) {
    $stPontos->execute([$c['id']]);
    $pontos = [];
    foreach ($stPontos->fetchAll(PDO::FETCH_ASSOC) as $p) {
      $alvoOk = match ($p['tipo']) {
        'mundo' => in_array($p['destino'], $mundosPublicados, true),
        'cena'  => in_array($p['destino'], $cenasPublicadas, true),
        'licao' => isset($licoesPublicadas[$p['destino']]),
        default => true, // tela e aviso já foram validados na gravação
      };
      if (!$alvoOk) continue;
      $pontos[] = [
        'rotulo' => $p['rotulo'],
        'x' => (float)$p['x'], 'y' => (float)$p['y'],
        'largura' => (float)$p['largura'], 'altura' => (float)$p['altura'],
        'tipo' => $p['tipo'], 'destino' => $p['destino'],
        'mostrarSelo' => (bool)$p['mostrar_selo'], 'mostrarDica' => (bool)$p['mostrar_dica'],
      ];
    }
    $saidaCenas[] = [
      'id' => $c['id'], 'nome' => $c['nome'], 'inicial' => (bool)$c['inicial'],
      // imagem enviada pelo painel vive em assets/cenas/; a que veio no repositório, em assets/
      'imagem' => is_file($pastaCenas . '/' . $c['imagem']) ? 'assets/cenas/' . $c['imagem'] : 'assets/' . $c['imagem'],
      'pontos' => $pontos,
    ];
  }

  echo json_encode(['mundos' => $saidaMundos, 'cenas' => $saidaCenas], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[bichoteca-conteudo] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['mundos' => [], 'cenas' => []]);
}
