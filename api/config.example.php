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
