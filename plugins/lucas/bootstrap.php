<?php

return function ($app, $events): void {
    $app->booted(function () use ($app) {
        // Plugin mantido (hidden), mas o dashboard do aluno agora é core em /area-membros.
        // Não registrar rotas/middleware com slug do plugin (evita expor /lucas/*).
    });
};
