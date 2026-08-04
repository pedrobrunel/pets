<?php
/* Copie este arquivo para api/config.php e preencha com os dados do hPanel
   (Bancos de dados MySQL). O config.php está no .gitignore de propósito:
   senha de banco nunca vai para repositório, nem privado. */

const BD_HOST  = 'localhost';
const BD_NOME  = 'SEU_BANCO';
const BD_USER  = 'SEU_USUARIO';
const BD_SENHA = 'SUA_SENHA';

/* Conta do painel administrativo (api/admin.php e admin.html) — não é
   professor nem aluno, é quem hospeda o site. install.php lê essas duas
   constantes e cria (ou atualiza) a conta toda vez que roda: pra trocar a
   senha do painel, edite aqui e abra api/install.php de novo. */
const ADMIN_USUARIO = 'hostmaster';
const ADMIN_SENHA   = 'troque-esta-senha';

/* Notificação push (lembrete de sequência) — opcional. Gere seu próprio par de chaves
   rodando localmente, uma vez só:
     composer install
     php -r "require 'vendor/autoload.php'; print_r((new Minishlink\WebPush\VAPID)::createVapidKeys());"
   Guarde as duas aqui, nunca no Git. VAPID_SUBJECT é um contato seu (mailto: ou site) —
   os provedores de push (Chrome/Firefox) usam isso pra falar com você em caso de abuso. */
const VAPID_PUBLIC_KEY  = 'SUA_CHAVE_PUBLICA';
const VAPID_PRIVATE_KEY = 'SUA_CHAVE_PRIVADA';
const VAPID_SUBJECT     = 'mailto:voce@exemplo.com';

/* Segredo pro cron (api/cron-lembrete-streak.php) — só é checado se o Hostinger disparar
   o cron via URL (em vez de rodar o PHP direto); protege o endpoint de ser chamado por
   qualquer um na internet. Gere uma string aleatória qualquer, ex.: bin2hex(random_bytes(16)). */
const CRON_SECRET = 'troque-por-uma-string-aleatoria';
