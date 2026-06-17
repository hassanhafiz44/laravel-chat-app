# chtbotlara

A Laravel-based chat application with async message processing via queued jobs. AI integration is scaffolded and ready to wire up.

## Stack

- **PHP 8.5 / Laravel 13**
- **Laravel Queues** — async message processing
- **Pest v4** — testing
- **Tailwind CSS v4** — frontend

## Getting Started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`:

```env
DB_CONNECTION=sqlite        # or mysql/pgsql
```

Run migrations and seed:

```bash
php artisan migrate --seed
```

Start the development server:

```bash
composer run dev
```

Start the queue worker (required for chat responses):

```bash
php artisan queue:work
```

## API Endpoints

### Chat

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/chat` | Send a message (queues AI response, returns 202) |
| `GET`  | `/api/chat/{conversation}/messages` | Fetch all messages in a conversation |

**POST `/api/chat`** — request body:

```json
{
  "user_id": 1,
  "message": "Hello!"
}
```

Returns `202 Accepted` with `conversation_id`, `message_id`, and `status: "queued"`. Poll the messages endpoint to retrieve the AI response once the queue job completes.

### Demo Endpoints

Relationship and job queue demos are available under `/api/demo/` (see `routes/api.php`).

## Testing

```bash
php artisan test --compact
```

## Models

`User`, `Conversation`, `Message`, `Profile`, `Team`, `Post`, `Tag`, `Media`, `StripeAccount`, `Subscription` — with full Eloquent relationships, factories, and seeders.

## Queue Jobs

- `ProcessChatMessage` — calls Groq API and stores assistant reply
- `ValidateMessage` — pre-processing validation step
- `DeliverToChannel` — downstream delivery
- `FlakyJob` — demonstrates `release()` vs `fail()` vs `throw()` failure modes

## License

MIT
