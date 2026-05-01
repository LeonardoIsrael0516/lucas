# Migrations em plugins

Plugins podem adicionar e rodar suas próprias migrations, criando tabelas ou alterando o banco sem modificar o core.

## Configuração

No `plugin.json`, declare o caminho da pasta de migrations (relativo à raiz do plugin):

```json
{
  "slug": "meu-plugin",
  "name": "Meu Plugin",
  "version": "1.0.0",
  "migrations": "database/migrations"
}
```

Estrutura de pastas:

```
plugins/
  meu-plugin/
    plugin.json
    bootstrap.php
    database/
      migrations/
        2025_02_20_100000_plugin_meu_plugin_create_minha_tabela.php
```

As migrations são carregadas apenas para **plugins ativados**. Ao rodar `php artisan migrate`, o Laravel executa as migrations do app e as de todos os plugins ativados.

## Nomenclatura

- **Nome do arquivo:** use o prefixo `plugin_{slug}_` para evitar conflito com outras migrations e com o core (ex.: `plugin_example_2025_02_20_200000_create_demo_table.php`).
- **Tabelas:** prefira prefixo nas tabelas (ex.: `plugin_example_demo`) para não colidir com tabelas do app ou de outros plugins.

## Exemplo

```php
// plugins/meu-plugin/database/migrations/plugin_meu_plugin_2025_02_20_100000_create_log_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_meu_plugin_log', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_meu_plugin_log');
    }
};
```

## Comandos

- **Rodar todas as migrations (app + plugins ativados):** `php artisan migrate`
- **Reverter:** `php artisan migrate:rollback`
- **Status:** `php artisan migrate:status`

As migrations de plugins entram na mesma fila do Laravel; não há comando separado para “migrar só plugins”.

## Modo cloud (multi-tenant)

Se o plugin criar tabelas com dados por tenant, use a coluna `tenant_id` (nullable quando a tabela for global) e o scope do app para filtrar por `app('current_tenant_id')`.
