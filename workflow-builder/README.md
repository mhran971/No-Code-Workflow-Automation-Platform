# Workflow Builder Tester

Simple React UI for exercising the Laravel workflow verification API.

## What it does

- Loads the node catalog from `GET /api/v1/workflows/nodes`
- Lets you pick a trigger, add nodes to a canvas, configure fields, and connect edges
- Sends the assembled definition to `POST /api/v1/workflows/validate`
- Shows backend validation results (no client-side workflow rules)

## Setup

1. Start the Laravel API (default: `http://localhost:8000`).
2. Seed node definitions if needed:

```bash
php artisan db:seed --class=Modules\\Workflows\\Database\\Seeders\\NodeDefinitionSeeder
```

3. Log in as a **Manager** or **Business Owner** and copy the JWT access token.
4. Paste the token in the UI, or hard-code it in `src/config.ts`:

```ts
export const DEFAULT_ACCESS_TOKEN = 'your-jwt-here';
```

If you previously opened the app with an empty token, click **Reset saved settings** so `localStorage` does not override `src/config.ts`.

5. Install and run the frontend:

```bash
cd workflow-builder
npm install
npm run dev
```

Open `http://localhost:5173`.

By default the dev server proxies `/api` to `http://localhost:8000`, so the API base URL is `/api/v1`. You can also use the full URL `http://localhost:8000/api/v1` if you prefer.

## Troubleshooting 401 Unauthorized

Your token is valid if this works:

```bash
curl -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/v1/workflows/nodes
```

Common frontend causes:

- Empty token saved in browser `localStorage` (use **Reset saved settings**)
- Pasting `Bearer eyJ...` into the token field (the app adds `Bearer` automatically)
- Wrong API base URL (must end with `/api/v1`, not just `http://localhost:8000`)

## API endpoints used

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/workflows/nodes` | Node library |
| POST | `/api/v1/workflows/validate` | Verify workflow definition |

Both require `Authorization: Bearer <token>`.
