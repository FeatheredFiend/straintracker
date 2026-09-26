# Strain Tracker

A log of cannabis strains tried: brand, type, THC, terpenes, genetics, price,
and a rating each from Anarlia and Martyn. It replaces the original
`WEED.xlsx` spreadsheet.

Symfony 8.1 (PHP 8.4) JSON API + React 19 (Vite) front end, served from one
origin like the warhammer app, on MariaDB/MySQL.

## Data model (3NF)

| Table            | Purpose                                                        |
| ---------------- | -------------------------------------------------------------- |
| `strain`         | One row per strain tried; unique on (brand, name)              |
| `brand`          | Lookup: who made it                                            |
| `strain_type`    | Lookup: Indica ↔ Sativa spectrum, ordered by `position`        |
| `terpene`        | Lookup: name, aroma, chip colour                               |
| `strain_terpene` | Many-to-many join: a strain's terpenes                         |
| `batch`          | A strain's batches: date + batch number, unique per strain     |
| `rating`         | Lookup: Fantastic / Nice / Mids / Terrible, with a score       |
| `app_user`       | Sign-in accounts (`ROLE_ADMIN` gets the admin section)         |

`strain.a_rating_id` and `strain.m_rating_id` both reference `rating`: they
are Anarlia's and Martyn's ratings (`aRating` / `mRating` in the API,
labelled by name only in the UI - see `frontend/src/raters.js`).

The migration seeds the ratings, types and terpenes.

## Local development

```bash
docker compose up -d                      # web :8083, MariaDB :3310
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php bin/console app:create-user you@example.com Martyn 'a-long-password' --admin

cd frontend && npm install && npm run build   # writes public/app/
```

Open http://localhost:8083.

For live-reloading front-end work, run `npm run dev` in `frontend/` and use
http://localhost:5173 - Vite proxies `/api` to the Docker stack.

### Importing the spreadsheet

Admin → Import takes an `.xlsx` or `.csv` laid out like `WEED.xlsx`
(Brand, Strain, Type, THC %, Terpenes, Genetics, Rating, Price). Preview
first; it lists everything it tidied up (e.g. terpenes and genetics in each
other's columns, duplicate rows). Re-importing updates matching
brand + strain rows instead of duplicating them. From the command line:

```bash
docker compose exec php bin/console app:import-strains path/to/WEED.xlsx --dry-run
```

Put files under `var/import/` (git-ignored) so the container can see them.
The spreadsheet itself is personal data and is never committed.

### Tests

```bash
docker compose exec php bin/phpunit
```

The `straintracker_test` database is created by `docker/db/init.sql` on a
fresh DB volume; run `bin/console doctrine:migrations:migrate --env=test`
once to build its schema.

## Deploying (Hostinger)

Same approach as the warhammer app: GitHub Actions builds everything and
uploads it over FTP, because the shared hosting has no SSH.

1. **Subdomain:** create it in hPanel. Hostinger serves its `public_html`
   folder; point the FTP account at the folder *containing* `public_html`.
   The workflow uploads the project there, with Symfony's `public/` folder
   renamed to `public_html/` - so the code and `.env.local` sit outside the
   web root.
2. **Database:** create a MySQL database and user in hPanel.
3. **Repo secrets** (Settings → Secrets and variables → Actions):
   `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `PROD_APP_SECRET`
   (any random 32+ character string) and `PROD_DATABASE_URL`, e.g.
   `mysql://USER:PASS@localhost:3306/DBNAME?serverVersion=10.11.18-MariaDB&charset=utf8mb4`.
4. **Schema:** run the *Generate migration SQL* workflow and paste its output
   into phpMyAdmin.
5. **First admin:** run locally and paste the output into phpMyAdmin:
   `docker compose exec php bin/console app:print-user-sql you@example.com Martyn 'a-long-password' --admin`
6. **Deploy:** run the *Deploy to Hostinger* workflow.
7. Sign in, then Admin → Import to load the spreadsheet, and Admin → Users to
   add Anarlia.
