# Render deployment

The site can run on Render as a Docker web service. PHP and the existing Node.js payment endpoints run in the same service; Apache serves the site and proxies `/stkpush`, `/dpo-pay`, and `/callback` to Node. MySQL remains external because Render does not provide a managed MySQL database.

## Before deploying

1. Create a managed MySQL database that accepts connections from Render. Keep the existing live database unchanged while setting up the new one.
2. Take a database backup and import the schema and data into the new database. Do not put database credentials in this repository.
3. Confirm the repository contains the uploaded event and leader images that need to be seeded on first deploy. The Render persistent disk preserves later uploads between deploys.
4. Confirm the production M-Pesa credentials, callback URL, and payment-provider endpoints. The current M-Pesa code calls Safaricom's sandbox URL, and `/dpo-pay` is only a placeholder; payments are not production-ready until those are configured and tested.

## Create the Render service

1. Push the reviewed changes to the GitHub repository.
2. In Render, create a Blueprint from that repository and apply `render.yaml`.
3. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the Render dashboard. If the MySQL provider requires a custom PDO DSN or TLS options, set `DB_DSN` using that provider's documented connection string.
4. Configure the `MPESA_*` variables in Render. Set `MPESA_CALLBACK_URL` to the public Render URL plus `/callback`, or to the final custom-domain URL plus `/callback`.
5. Deploy to the Render URL first. Test the public pages, admin login, event registrations, contact/newsletter forms, image uploads, and payment callback routing before changing DNS.

The Blueprint uses a paid Starter web service because Render persistent disks require a paid service, mounts a 1 GB disk for `uploads/`, and disables automatic deploys during initial setup. After validation and cutover, enable automatic deploys in Render if desired.

## Cut over the live site

Keep the current site and database active while validating the Render copy. Before changing the domain, take a fresh backup and plan a short write freeze so new registrations, contacts, and settings are not missed during the final database sync. Change DNS only after the Render copy passes checks; keep the old host available for rollback until the new domain and data are confirmed.

Never deploy `check-admin.php`; it contains a hard-coded password reset. The Docker build excludes it, along with local diagnostic scripts and the SQL dump.
