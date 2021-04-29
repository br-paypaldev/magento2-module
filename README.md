# Módulo PayPal para Magento2
![](https://raw.githubusercontent.com/wiki/paypal/PayPal-PHP-SDK/images/homepage.jpg)

Página oficial do módulo PayPal com as soluções utilizadas no mercado Brasileiro para Magento 2.

## Descrição

Este módulo contém os principais produtos PayPal para o mercado Brasileiro:
- **Smart Payment Button (Novo Express Checkout)**: Solução de carteira digital aonde o cliente paga com a sua conta PayPal ou cria uma no momento da compra.
- **PayPal Plus**: Checkout transparente PayPal aonde o cliente paga somente utilizando o seu cartão de crédito, sem a necessidade de ter uma conta PayPal.
- **PayPal Login**: O cliente utiliza a sua conta PayPal para fazer login e comprar com PayPal;
- **PayPal no Carrinho**: O cliente utiliza a sua conta PayPal para comprar diretamente do carrinho;

**É recomendado que o PayPal Plus seja utilizado juntamente com o Smart Payment Button, oferecendo assim ao cliente uma experiência de checkout completa com as soluções transparente e de carteira.**

## Requisitos

Para o correto funcionamento das soluções, é necessário verificar que a sua loja e servidor suporte alguns recursos:
1. Para o checkout transparente (PayPal Plus), a sua loja precisa ter suporte ao TAX_VAT, portanto antes de ativar a solução garanta que a sua loja está devidamente configurada para suportar este campo;
2. O servidor precisa ter suporte à TLS 1.2 ou superior e HTTPS 1.1 [(Referência Oficial)](https://www.paypal.com/sg/webapps/mpp/tls-http-upgrade).
3. O servidor precisa ter suporte à PHP 7.0 ou superior;

**Checkout Transparente (PayPal Plus)**
O Checkout Transparente está disponível apenas para contas PayPal cadastradas com CNPJ (Conta Empresa), caso a sua conta seja de pessoa física, você deve abrir uma conta PayPal de pessoa jurídica por este link.

A solução requer aprovação comercial, entre em contato pelo 0800 721 6959 e solicite agora mesmo.

**O PayPal Plus só irá funcionar caso tenha sido aprovado pelo PayPal.**

## Compatibilidade

Este módulo é compatível com as versões do Magento 2.2.2 até 2.4.2.

## Módulos OneStepCheckout

Atualmente este módulo é compatível trabalha com os seguintes módulos OneStepCheckout:
1. Firecheckout


## Instalação

Este módulo está disponível através do Composer, você não precisa mais especificar o repositório.

Para instalar, adicione as seguintes linhas ao seu composer.json:

```
...
"require":{
    ...
    "br-paypaldev/magento2-module":"^1.0"
 }
```
Ou simplesmente digite  o comando abaixo:
```
composer require br-paypaldev/magento2-module --no-update

```

Em seguida, digite os seguintes comandos da sua raiz do Magento:

```
$ composer update br-paypaldev/magento2-module

$ ./bin/magento setup:upgrade
$ ./bin/magento setup:di:compile
```
Para visualizar os modulos ativos:
```
    $ ./bin/magento module:status
```
Você verá o **PayPalBR_PayPal** na lista de ativos.

## Configuração
### - Credenciais de API
Para configurar as soluções PayPal, você deverá gerar as credenciais de API do tipo REST, no caso o Client ID e o Secret ID. Para obtê-las siga este passo-a-passo:

1. Efetuar o login com sua conta PayPal em https://developer.paypal.com e clicar no link na parte superior "Dashboard";
2. Clique em "My Apps & Credentials";
3. Abaixo de "Rest API apps" clique "Create App";
4. Em seguida, insira o termo "ppplus" no campo "App Name" e clique em "Create App";
5. No canto superior direito da tela, clique em "Live";
6. Você deve copiar os códigos que aparecerem em "Client ID" e em "Secret" (Para visualizar o "Secret" será necessário clicar em "Show") e colar estes códigos na página de configuração da solução que irá utilizar.

### - PayPal Plus
Para o PayPal Plus, o campo CPF/CNPJ é obrigatório, para habilitá-lo siga os passos abaixo dentro do painel administrativo do Magento:

**Habilitar o VAT Number no Front-end:**
- STORES -> Settings -> Configuration -> Customers -> Customer Configuration -> Create New Account Options -> Show VAT Number on Storefront (Habilitar como "Yes")

**Habilitar como obrigatório o Tax/VAT Number no endereço do Cliente:**
- STORES -> Settings -> Configuration -> Customers -> Customer Configuration -> Name and Address Options -> Show Tax/VAT Number	 (Habilitar como "Required")

## Atualização

Para atualizar o módulo rode os comandos abaixo no Composer:

```
    $ composer require br-paypaldev/magento2-module --no-update
    $ composer update
    $ ./bin/magento setup:upgrade
    $ ./bin/magento setup:di:compile
```

## Dúvidas/Suporte

Caso a sua dúvida não tenha sido respondida aqui, entre em contato com o PayPal pelo número 0800 047 4482.

E caso necessite de algum suporte técnico e/ou acredita ter encontrado algum problema com este módulo acesse o nosso [portal de suporte técnico](https://www.paypal-support.com/s/?language=pt_BR) e abra um ticket detalhando o seu problema na seção "Fale Conosco".

## Changelog

Para visulizar as últimas atualizações acesse o [**CHANGELOG.md**](CHANGELOG.md).
