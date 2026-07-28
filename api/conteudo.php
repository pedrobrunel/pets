<?php
/* =========================================================
   Bichoteca — conteúdo publicado (mundos + lições)
   Endpoint público (sem login): o app.html busca aqui na
   inicialização pra saber quais mundos e lições existem hoje.
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

  $saida = [];
  foreach ($mundos as $m) {
    $st->execute([$m['id']]);
    $licoes = array_map(fn($l) => [
      'id' => $l['id'], 'emoji' => $l['emoji'], 'titulo' => $l['titulo'], 'serie' => $l['serie'],
      'blocos' => json_decode($l['blocos'], true),
    ], $st->fetchAll(PDO::FETCH_ASSOC));
    if ($licoes) $saida[] = ['id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'], 'licoes' => $licoes];
  }
  echo json_encode($saida, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[bichoteca-conteudo] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([]);
}
