# 🍕 Don Vitto Pizza - Chat com Assistente de IA

Sistema de chat com assistente de IA para a pizzaria Don Vitto Pizza que ajuda clientes a montar pedidos completos.

## 🎯 Funcionalidades

- ✅ Chat interativo com assistente de IA (OpenAI/ChatGPT)
- ✅ Montagem completa de pedidos (sabores, tamanho, borda, bebida, endereço, pagamento)
- ✅ Geração automática de JSON estruturado com o pedido
- ✅ Envio automático via HTTP POST para webhook configurável
- ✅ Interface moderna e responsiva com Vue.js e Tailwind CSS

## 🏗️ Estrutura

- **Backend**: Laravel 12
- **Frontend**: Vue 3 com Inertia.js
- **Build Tool**: Vite
- **CSS**: Tailwind CSS
- **IA**: OpenAI API (GPT-4o-mini)

## 📦 Instalação

1. **Instalar dependências PHP:**
```bash
composer install
```

2. **Instalar dependências Node:**
```bash
npm install
```

3. **Configurar variáveis de ambiente:**

Crie um arquivo `.env` na raiz do projeto e adicione:

```env
APP_NAME="Don Vitto Pizza Chat"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
DB_DATABASE=/caminho/para/database/database.sqlite

# OpenAI Configuration
OPENAI_API_KEY=sua_chave_api_openai_aqui
OPENAI_MODEL=gpt-4o-mini

# Webhook Configuration (opcional)
WEBHOOK_URL=https://seu-webhook-url.com/api/pedidos
```

4. **Gerar chave da aplicação:**
```bash
php artisan key:generate
```

5. **Executar migrações:**
```bash
php artisan migrate
```

6. **Iniciar servidor de desenvolvimento:**

```bash
# Inicia backend e frontend simultaneamente
npm run dev

# OU separadamente:
# Terminal 1 - Backend
php artisan serve

# Terminal 2 - Frontend
npm run dev
```

Acesse: http://localhost:8000/chat

## 🔑 Obter Chave da OpenAI

1. Acesse https://platform.openai.com/
2. Crie uma conta ou faça login
3. Vá em "API Keys" e crie uma nova chave
4. Copie a chave e adicione no arquivo `.env` como `OPENAI_API_KEY`

## Estrutura de Arquivos

```
resources/
├── js/
│   ├── app.js          # Ponto de entrada do Inertia
│   └── Pages/         # Componentes Vue (páginas)
│       └── Index.vue  # Página inicial
├── css/
│   └── app.css        # Estilos Tailwind
└── views/
    └── app.blade.php  # Layout base do Inertia
```

## Criando Novas Páginas

1. Crie um componente Vue em `resources/js/Pages/`
2. Adicione a rota em `routes/web.php` usando `Inertia::render()`

Exemplo:

```php
Route::get('/about', function () {
    return Inertia::render('About');
});
```

## Comandos Úteis

```bash
# Compilar assets para produção
npm run build

# Executar migrações
php artisan migrate

# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```
