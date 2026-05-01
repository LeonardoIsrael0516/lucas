# Eventos para plugins

Plugins podem escutar eventos Laravel declarando-os em `plugin.json` (chave `events`) e registrando listeners no `bootstrap.php`. Esta página lista os eventos emitidos pelo core e como usá-los.

## Registro no plugin

Em `plugin.json`:

```json
{
  "slug": "meu-plugin",
  "name": "Meu Plugin",
  "version": "1.0.0",
  "events": [
    "App\\Events\\OrderCompleted",
    "App\\Events\\DashboardLoading"
  ]
}
```

No `bootstrap.php`:

```php
<?php

use App\Events\OrderCompleted;
use Illuminate\Contracts\Events\Dispatcher;

return function ($app, Dispatcher $events): void {
    $events->listen(OrderCompleted::class, function (OrderCompleted $e): void {
        // $e->order
    });
};
```

---

## Eventos disponíveis

### OrderCompleted

**Classe:** `App\Events\OrderCompleted`

Emitido quando uma compra é concluída (checkout processado com sucesso).

| Propriedade | Tipo        | Descrição      |
|------------|-------------|----------------|
| `$order`   | `Order`     | Pedido criado. |

**Uso típico:** enviar e-mail, integrar CRM, liberar acesso em sistema externo.

---

### DashboardLoading

**Classe:** `App\Events\DashboardLoading`

Emitido antes de renderizar a página do dashboard do infoprodutor. Plugins podem adicionar dados ao payload que será enviado para o frontend.

| Propriedade | Tipo          | Descrição                                                                 |
|------------|---------------|----------------------------------------------------------------------------|
| `$data`    | `ArrayObject` | Dados do dashboard. Listeners podem adicionar chaves (ex.: `$data['plugin_widgets'] = [...]`). |

**Uso típico:** injetar widgets, métricas ou links extras no dashboard.

**Exemplo:**

```php
$events->listen(\App\Events\DashboardLoading::class, function (\App\Events\DashboardLoading $e): void {
    $e->data['plugin_widgets'] = [
        ['title' => 'Meu widget', 'content' => '...'],
    ];
});
```

---

### CheckoutBeforeProcess

**Classe:** `App\Events\CheckoutBeforeProcess`

Emitido antes de criar o pedido e o vínculo do aluno ao produto. Um listener pode abortar o checkout definindo `$event->abort`.

| Propriedade  | Tipo     | Descrição                                                                 |
|-------------|----------|----------------------------------------------------------------------------|
| `$product`  | `Product`| Produto da compra.                                                        |
| `$validated`| `array`  | Dados validados do request (`product_id`, `email`, `name`).               |
| `$abort`    | `?string`| Se definido pelo listener, o checkout é interrompido e a mensagem é exibida. |

**Uso típico:** validações extras, limite de compras, integração com antifraude.

**Exemplo:**

```php
$events->listen(\App\Events\CheckoutBeforeProcess::class, function (\App\Events\CheckoutBeforeProcess $e): void {
    if (/* alguma condição */) {
        $e->abort = 'Não foi possível processar a compra. Tente mais tarde.';
    }
});
```

---

### CheckoutPageLoading

**Classe:** `App\Events\CheckoutPageLoading`

Emitido antes de renderizar a página pública do checkout (rota `/c/{slug}`). Plugins podem alterar os dados enviados ao frontend (produto, config).

| Propriedade | Tipo          | Descrição                                                                 |
|------------|----------------|----------------------------------------------------------------------------|
| `$product` | `Product`      | Produto do checkout.                                                      |
| `$data`    | `ArrayObject`  | Payload da página. Contém `product` (array) e `config` (array). Listeners podem modificar `$data['product']` ou `$data['config']` para alterar o que será exibido. |

**Uso típico:** injetar dados extras no checkout, alterar configuração por tenant, integrar tracking ou A/B test.

**Exemplo:**

```php
$events->listen(\App\Events\CheckoutPageLoading::class, function (\App\Events\CheckoutPageLoading $e): void {
    $e->data['config']['appearance']['primary_color'] = '#1a1a1a'; // override cor
    $e->data['custom_field'] = 'valor'; // adicionar chave para o frontend
});
```

---

### MemberAreaLoaded

**Classe:** `App\Events\MemberAreaLoaded`

Emitido quando a área de membros é carregada (lista de produtos do aluno).

| Propriedade | Tipo         | Descrição                    |
|------------|--------------|------------------------------|
| `$user`    | `User`       | Usuário (aluno) logado.      |
| `$produtos`| `Collection` | Produtos que o aluno possui. |

**Uso típico:** analytics, personalização, integração com LMS externo.

---

### ProductCreated

**Classe:** `App\Events\ProductCreated`

Emitido após a criação de um produto (store com sucesso).

| Propriedade | Tipo      | Descrição       |
|------------|-----------|-----------------|
| `$product`| `Product` | Produto criado. |

**Uso típico:** enviar e-mail, sincronizar CRM, criar registro em sistema externo.

---

### ProductUpdated

**Classe:** `App\Events\ProductUpdated`

Emitido após a atualização de um produto (update com sucesso).

| Propriedade | Tipo      | Descrição         |
|------------|-----------|-------------------|
| `$product`| `Product` | Produto atualizado. |

**Uso típico:** sincronizar catálogo externo, invalidar cache.

---

### ProductDeleted

**Classe:** `App\Events\ProductDeleted`

Emitido antes da exclusão do produto (destroy). O model ainda está em memória.

| Propriedade | Tipo      | Descrição        |
|------------|-----------|------------------|
| `$product`| `Product` | Produto excluído. |

**Uso típico:** limpar dados em sistema externo, remover arquivos.

---

### ProductDuplicated

**Classe:** `App\Events\ProductDuplicated`

Emitido após duplicar um produto (duplicate com sucesso).

| Propriedade    | Tipo      | Descrição              |
|----------------|-----------|------------------------|
| `$original`   | `Product` | Produto original.      |
| `$newProduct` | `Product` | Novo produto (cópia).   |

**Uso típico:** replicar configurações em sistema externo.

---

### ProductIndexLoading

**Classe:** `App\Events\ProductIndexLoading`

Emitido antes de renderizar a página de listagem de produtos. Plugins podem injetar dados no payload enviado ao frontend.

| Propriedade | Tipo          | Descrição                                                                 |
|------------|---------------|----------------------------------------------------------------------------|
| `$data`    | `ArrayObject` | Dados da página. Contém `produtos`, `productTypes`, `exchange_rates`. Listeners podem adicionar, por exemplo: `$data['plugin_card_actions'][$productId] = [{ 'label' => '...', 'href' => '...', 'icon' => '...' }]` ou `$data['plugin_form_sections'] = [...]`. |

**Uso típico:** adicionar ações extras no menu do card (por produto) ou seções no formulário de criação.

**Exemplo:**

```php
$events->listen(\App\Events\ProductIndexLoading::class, function (\App\Events\ProductIndexLoading $e): void {
    foreach ($e->data['produtos'] ?? [] as $p) {
        $e->data['plugin_card_actions'][$p['id']][] = [
            'label' => 'Enviar para CRM',
            'href' => '/meu-plugin/sync/' . $p['id'],
        ];
    }
});
```

---

### ProductBeforeSave

**Classe:** `App\Events\ProductBeforeSave`

Emitido antes de persistir criação ou atualização de produto. Um listener pode abortar definindo `$event->abort`.

| Propriedade  | Tipo      | Descrição                                                                 |
|-------------|-----------|----------------------------------------------------------------------------|
| `$product`  | `Product` | Model do produto (em create pode estar vazio no DB).                       |
| `$validated`| `array`   | Dados validados do request.                                                |
| `$isCreate` | `bool`    | True se for criação, false se for atualização.                             |
| `$abort`    | `?string` | Se definido pelo listener, o save é interrompido e a mensagem é exibida.   |

**Uso típico:** validações extras, integração com catálogo externo.

**Exemplo:**

```php
$events->listen(\App\Events\ProductBeforeSave::class, function (\App\Events\ProductBeforeSave $e): void {
    if (/* alguma condição */) {
        $e->abort = 'Não foi possível salvar o produto.';
    }
});
```

---

## Boas práticas

- Não bloqueie a resposta por muito tempo; para tarefas pesadas use filas (queues).
- Em modo cloud, considere `app('current_tenant_id')` ao salvar dados por tenant.
- Não exponha dados sensíveis nos payloads que forem enviados ao frontend (ex.: `DashboardLoading`).
