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
