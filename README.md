# Splitlah

**Snap receipt. Split fairly. Collect faster.**

Mobile-first split bill app for Malaysian group payments. Organizers snap a receipt, split fairly, share payment links, and track who has paid via Fiuu sandbox.

## Features

- Receipt upload + client-side OCR (Tesseract.js) with server-side parsing
- Split modes: equal, manual, itemized, percentage
- Unique participant payment links (no login)
- QR code display, WhatsApp share & reminders
- Fiuu sandbox hosted payment + webhook verification
- Organizer tracker dashboard with audit timeline
- Demo bill after seeding

## Tech Stack

- Laravel 13, Inertia.js, Vue 3, Tailwind CSS 4, Vite
- SQLite (local default) or MariaDB (Docker)
- Integer cents for all money values

## Local Setup (Docker recommended)

```bash
cd splitlah
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app npm run build
```

Open http://localhost:8000

## Local Setup (without PHP on host)

```bash
docker run --rm -v "%cd%":/app -w /app composer:latest install
docker run --rm -v "%cd%":/app -w /app node:22-alpine npm install
docker run --rm -v "%cd%":/app -w /app php:8.4-cli php artisan migrate:fresh --seed
docker run --rm -v "%cd%":/app -w /app node:22-alpine npm run build
docker run --rm -p 8000:8000 -v "%cd%":/app -w /app php:8.4-cli php artisan serve --host=0.0.0.0
```

## Fiuu Sandbox Setup

1. Get sandbox Merchant ID, Verify Key, and Secret Key from Fiuu merchant portal.
2. Add them to `.env`:

```env
FIUU_ENABLED=true
FIUU_MERCHANT_ID=SB_YOUR_MERCHANT_ID
FIUU_VERIFY_KEY=your_verify_key
FIUU_SECRET_KEY=your_secret_key
FIUU_PAY_BASE_URL=https://sandbox.merchant.razer.com/RMS/pay
```

3. Configure portal URLs if required:
   - Return: `{APP_URL}/payments/fiuu/return`
   - Notification: `{APP_URL}/payments/fiuu/notify`
   - Cancel: `{APP_URL}/payments/fiuu/cancel`
4. For local testing, expose the app with ngrok and set `APP_URL` to the tunnel URL.
5. Keep `FIUU_PAYMENT_METHOD` empty for channel selection, or set a subscribed sandbox channel.
6. Set `FIUU_DUITNOW_CHANNEL` only if DuitNow is activated for your sandbox merchant.

## Testing

```bash
docker compose exec app php artisan test
docker compose exec app npm run build
```

## Demo Bill

After `php artisan migrate:fresh --seed`, visit **Try demo bill** on the home page or `/demo`.

## Known Limitations

- OCR quality depends on receipt image clarity
- Real DuitNow QR requires Fiuu merchant channel activation
- Return URL is UI only; Notification URL is the source of truth for paid status
- Fiuu callback requires a public URL during local development
