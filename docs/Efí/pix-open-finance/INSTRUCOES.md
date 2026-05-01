# Documentação de Integração - Pagamento Pix via Open Finance

Este sistema exemplifica como realizar pagamentos Pix utilizando a integração com API Open Finance do Efí Bank. 

---

## **Requisitos**
Certifique-se de atender aos seguintes requisitos antes de iniciar:
- **PHP** >= 7.2.5
- **Guzzle** >= 7.0
- **Symfony/Cache** >= 5.0 || >= 6.0
- **efipay/sdk-php-apis-efi** >= 1.10.0
- **Extensão openssl** habilitada no PHP

---

## **Início Rápido**

### **1. Configuração das Credenciais**
- Edite o arquivo `credentials.json` com suas credenciais fornecidas pela API Efí e outras informações da conta.
- Consulte a [documentação oficial](https://dev.efipay.com.br/) para detalhes sobre como obter essas credenciais.

### **2. Configuração do Webhook e Redirecionamento**
1. Hospede o código do sistema em um servidor web.
2. Edite o arquivo `/webhook/configurar.php` para definir:
   - **`redirectURL`**: URL de redirecionamento após a ação no banco.
   - **`webhookURL`**: URL que receberá notificações da API.
   - **`hash`**: Código de verificação de segurança.
3. Acesse o arquivo via navegador: `https://exemplo.seuservidor/webhook/configurar.php`.  
   Isso enviará as configurações para a API Efí e as salvará.

### **3. Testes de Pagamento**
1. Acesse a URL: `https://exemplo.seuservidor/index.php`.
2. Utilize a aplicação para simular um pagamento Pix via Open Finance.

---

## **Funcionamento Detalhado do funcionamento da aplicação**

### **Fluxo de Pagamento**
#### **1. Tela de Checkout (`index.php`)**
- O cliente escolhe a forma de pagamento, preenche as informações e seleciona o banco.

#### **2. Gerar Link de Iniciação de Pagamento**
- Ao clicar em "Pagar com meu banco", o arquivo `emitir_pix_open_finance.php`:
  - Consomen o endpoint da API Efí, para realizar a iniciação de pagamento, que gera um link para o pagamento.
  - Baseia-se nos dados preenchidos pelo cliente.

#### **3. Redirecionamento para o Banco**
- O cliente é redirecionado para o ambiente do banco escolhido para confirmar a transação.

#### **4. Conferência do Pagamento**
- Após a confirmação, o cliente retorna à página `aguardando_pagamento.php`.
- Um script Ajax chama `verificar_pagamento.php` a cada 3 segundos para consultar o status no banco de dados (`históricoPagamentos.json`).

#### **5. Conclusão do Pagamento**
- Quando o pagamento é identificado, devido atualização via webhook, o cliente é redirecionado para `pagamento_concluido.php`, que exibe os detalhes da transação.

### **Fluxo do Webhook**
#### **1. Receber Notificação**
- O arquivo `/webhook/index.php` aguarda notificações da API.

#### **2. Atualizar Status**
- As notificações recebidas atualizam o status da transação no banco de dados (`históricoPagamentos.json`).

---

## **Fluxograma do Processo**
Para visualizar o fluxo completo, consulte o fluxograma disponível na pasta do projeto:
![Fluxograma](./assets/img/fluxograma-open-finance.jpg)

---

## **Documentação Adicional**
- Documentação completa da API: [https://dev.efipay.com.br/](https://dev.efipay.com.br/)
- SDK para PHP: [https://github.com/efipay/sdk-php-apis-efi](https://github.com/efipay/sdk-php-apis-efi)
- Abra sua conta digital Efí: [https://sejaefi.com.br/](https://sejaefi.com.br/)
- Participe da nossa comunidade no Discord: [https://comunidade.sejaefi.com.br/](https://comunidade.sejaefi.com.br/)

---

## **Suporte**
Se você tiver dúvidas ou dificuldades, entre em contato com nosso suporte técnico ou participe da comunidade para suporte colaborativo.  [comunidade.sejaefi.com.br](https://comunidade.sejaefi.com.br/)
