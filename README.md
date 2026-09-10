# Knox & Gable — Hostinger deployment

This repository contains both the original Node.js implementation and the PHP
production implementation for Hostinger Business Web Hosting.

## Current production deployment

Deploy `php-production` archive contents as a static site. It contains the
pre-built front end, PHP API, and Apache rewrite rules; no Node.js runtime is
required. Before packaging, copy `api/config.php.example` to `api/config.php`
and set real credentials. `api/config.php` is intentionally ignored by Git.

The PHP API creates the registrations table automatically on its first request.

## Requirements
- Node.js >= 18
- MySQL (if using the registrations API)

## Run locally
1. Install dependencies:

```bash
npm install
```

2. Provide environment variables (example `.env`):

```
DATABASE_URL=mysql://USER:PASSWORD@HOST:3306/DATABASE
ADMIN_PASSWORD=your-admin-password
PORT=3000
```

3. Start the app:

```bash
npm start
```

Open `http://localhost:3000`.

## Deploy from Git to Hostinger
1. Initialize a repo and push to GitHub/GitLab/Bitbucket (see below).
2. In Hostinger Site Tools → Git / Deploy from Git, connect your repository and branch.
3. Set build command (if any) and start command: `node server.mjs`.
4. Set Node.js version to >=18 in Site Tools and add environment variables:
   - `DATABASE_URL`
   - `ADMIN_PASSWORD`
   - `PORT` (optional)
5. Deploy and check logs in Site Tools.

## Git quick commands
From project root:

```bash
git init
echo "node_modules\n.env\n.env.local\n.DS_Store" > .gitignore
git add .
git commit -m "Initial commit"
# then add your remote and push:
# git remote add origin git@github.com:USERNAME/REPO.git
# git branch -M main
# git push -u origin main
```

## Notes
- The app serves static files from `public/` and exposes API endpoints under `/api`.
- If you use Hostinger-managed Postgres, create the DB in Site Tools → Databases and use the provided credentials for `DATABASE_URL`.
# Knox & Gable — Hostinger package

This folder is a complete production package for the Knox & Gable website. It contains the built React website in `public/`, a single Express server in `server.mjs`, and the SQL needed for the registration database.

## What is included

- Responsive Knox & Gable website
- Amazon listing links and product gallery
- Office and car-seat product demonstrations
- Uploaded car image and MP4 demo
- Product registration form
- Admin login and registration table
- PostgreSQL database schema

## Before uploading

1. Create a MySQL database in Hostinger or use another MySQL provider.
2. The app creates the registration table automatically at startup. `database.sql`
   is retained for manual imports.
3. Keep the database connection string ready. It normally looks like:

   `mysql://username:password@hostname:3306/database_name`

4. Choose a strong value for `ADMIN_PASSWORD`.

Do not upload a `.env` file containing real credentials inside the archive. Add environment variables through Hostinger instead.

## Hostinger setup

Hostinger's current Node.js web-app flow supports uploading a compressed project archive.

1. In hPanel, open **Websites → Add Website → Node.js web app**.
2. Choose **Upload your files**.
3. Upload the ZIP archive supplied with this package.
4. Choose Node.js **20.x** or **22.x**.
5. Choose **Express.js** if Hostinger asks for a framework. If Express.js is not available in the selector, choose **Other**.
6. Set the application startup file to:

   `server.mjs`

7. Set the application root to the folder that contains `package.json`, `server.mjs`, and `public/`.
8. Use `npm install` as the install step if Hostinger asks. No build command is required because the React site is already built in `public/`.
9. Add these environment variables:

   - `DATABASE_URL` — the full PostgreSQL connection string
   - `ADMIN_PASSWORD` — the admin password you chose
   - `NODE_ENV` — `production`

10. Start or redeploy the application.
11. Connect your domain to the Node.js application in Hostinger and enable SSL.

Hostinger supplies `PORT` automatically. The server reads it from `process.env.PORT`; do not hardcode a public port.

## Admin dashboard

Open:

`https://your-domain.com/admin`

Use the value configured as `ADMIN_PASSWORD`. The dashboard lists all submitted registrations and their Amazon order IDs.

## Registration database

The website writes to the `registrations` table. The SQL file creates these columns:

- `id`
- `full_name`
- `email`
- `phone`
- `amazon_order_id`
- `created_at`

## Local test

From this folder:

```bash
npm install
DATABASE_URL="your-postgres-url" ADMIN_PASSWORD="your-admin-password" npm start
```

Then open `http://localhost:3000`.

## Important notes

- The product page currently links to `https://www.amazon.in/dp/B0HBB657ML`.
- Amazon gallery images are loaded from their public listing URLs. The uploaded Knox & Gable media is bundled in `public/`.
- The included `public/` directory is the production build. Do not delete it.
- Never commit or share real database credentials or the admin password.
