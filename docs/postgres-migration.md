# Moving an existing install from MySQL to PostgreSQL

LinkerLee runs on PostgreSQL only. If your install still runs on MySQL (the old default for
production and for the Docker stack), this runbook copies your data to Postgres with
[pgloader](https://pgloader.io). The app stays in maintenance mode for the whole copy, and MySQL is
never written to, so you can roll back by pointing `.env` at MySQL again.

Allow a few minutes of downtime per hundred thousand links.

## What you need

- The MySQL database as it is now: host, name and credentials.
- An empty PostgreSQL 15+ database, and a role that owns it. During the load, pgloader
  drops and re-creates the foreign keys and disables triggers, so it can load tables in any
  order. That needs the role to be a superuser, or else the owner of every table.
- The `pgvector` extension must be available on the target Postgres (the `pgvector/pgvector`
  Docker image bundles it; a managed Postgres host needs it added to its allow-list of
  extensions). Step 2 below runs `CREATE EXTENSION IF NOT EXISTS vector`, which itself needs
  either a superuser role or one a host has explicitly granted `CREATE EXTENSION` to.
- `pgloader` 3.6 or newer (`brew install pgloader`, `apt install pgloader`, or the
  `dimitri/pgloader` Docker image).
- This version of LinkerLee checked out, with `composer install` done.

## 1. Freeze and back up

```bash
php artisan down
# stop the long-running worker (supervisor / systemd), then drain what is left.
# --force is required: a worker does not process jobs while the app is down.
php artisan queue:work --queue=default,ingestion,enrichment,health --stop-when-empty --force
mysql linkerlee -Ne "SELECT COUNT(*) FROM jobs"      # must print 0

mysqldump --single-transaction --routines linkerlee > linkerlee-mysql-$(date +%F).sql
```

Keep the dump. It is your rollback if anything below goes wrong.

The `jobs` table is not copied, which is why the queue is drained first. A metadata job
lost here would leave its link without a title, favicon or preview for good.

## 2. Create the schema on Postgres

Let Laravel build the tables rather than pgloader. That way the column types (boolean,
json, timestamp) and the generated `links.search_vector` column are exactly what the app
expects.

```bash
DB_CONNECTION=pgsql DB_HOST=… DB_PORT=5432 DB_DATABASE=linkerlee \
DB_USERNAME=… DB_PASSWORD=… \
php artisan migrate --force
```

## 3. Copy the data

Save this as `linkerlee.load`, and fill in both connection strings:

```lisp
LOAD DATABASE
     FROM mysql://MYSQL_USER:MYSQL_PASSWORD@MYSQL_HOST:3306/linkerlee
     INTO postgresql://PG_USER:PG_PASSWORD@PG_HOST:5432/linkerlee

WITH data only,
     on error stop,
     truncate,
     disable triggers,
     reset sequences,
     create no tables,
     create no indexes

SET PostgreSQL PARAMETERS timezone TO 'UTC'

-- pgloader writes into a Postgres schema named after the MySQL database.
-- Laravel's tables live in `public`. Use your MySQL database name here.
ALTER SCHEMA 'linkerlee' RENAME TO 'public'

CAST type tinyint when (= precision 1) to boolean using tinyint-to-boolean

-- `migrations` already holds the rows `php artisan migrate` wrote in step 2.
-- Sessions, cache and queue state are disposable: users sign in again.
EXCLUDING TABLE NAMES MATCHING 'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches';
```

Then run:

```bash
pgloader linkerlee.load
```

pgloader prints a per-table summary at the end. Every row in the `errors` column must be
`0`. `on error stop` makes pgloader abort at the first rejected row instead of skipping it.
Fix the cause and run step 3 again: `truncate` makes it safe to repeat.

`users.email` and `users.inbox_token` are case-insensitive (`citext`), just as they were
under MySQL's `_ci` collation. MySQL's unique indexes already ruled out two addresses that
differ only in case, so the load cannot collide on them.
Warnings like `constraint "links_user_id_foreign" … does not exist, skipping` are expected.
They come from pgloader dropping the foreign keys before the copy, and it re-creates them
all afterwards.

**Do not set a MySQL time zone in the load file.** Laravel never sets one, so the app has
always read `TIMESTAMP` columns in the MySQL server's default zone. pgloader must read them
the same way. Forcing `time_zone` to `'+00:00'` on a server whose default is `SYSTEM` shifts
every timestamp by the server's UTC offset. The dry run below saw 3 hours on a UTC+3 host.

## 4. Verify before going live

**Row counts must match.** Run the same query on both sides:

```sql
SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'links', COUNT(*) FROM links
UNION ALL SELECT 'tags', COUNT(*) FROM tags
UNION ALL SELECT 'taggables', COUNT(*) FROM taggables
UNION ALL SELECT 'groups', COUNT(*) FROM `groups`   -- on Postgres: FROM groups
UNION ALL SELECT 'groupables', COUNT(*) FROM groupables
UNION ALL SELECT 'public_links', COUNT(*) FROM public_links
UNION ALL SELECT 'personal_access_tokens', COUNT(*) FROM personal_access_tokens
UNION ALL SELECT 'failed_jobs', COUNT(*) FROM failed_jobs
UNION ALL SELECT 'password_reset_tokens', COUNT(*) FROM password_reset_tokens;
```

**Sequences must be ahead of the data.** Otherwise the next insert fails on a duplicate key.
`pg_sequences.last_value` is NULL until a sequence has been used, so an unset sequence
counts as 0 here. On Postgres, this query must return no rows:

```sql
SELECT t, max_id, last_value FROM (
          SELECT 'links' AS t, (SELECT MAX(id) FROM links) AS max_id, 'links_id_seq' AS seq
UNION ALL SELECT 'users', (SELECT MAX(id) FROM users), 'users_id_seq'
UNION ALL SELECT 'tags', (SELECT MAX(id) FROM tags), 'tags_id_seq'
UNION ALL SELECT 'groups', (SELECT MAX(id) FROM groups), 'groups_id_seq'
UNION ALL SELECT 'public_links', (SELECT MAX(id) FROM public_links), 'public_links_id_seq'
UNION ALL SELECT 'personal_access_tokens', (SELECT MAX(id) FROM personal_access_tokens), 'personal_access_tokens_id_seq'
UNION ALL SELECT 'failed_jobs', (SELECT MAX(id) FROM failed_jobs), 'failed_jobs_id_seq'
) m JOIN pg_sequences s ON s.sequencename = m.seq
WHERE COALESCE(s.last_value, 0) < COALESCE(m.max_id, 0);
```

**Spot-check the converted types.** Pick one user's newest link in both databases and compare:

- `is_favorite` must be `true` or `false`, not 0/1.
- `created_at` must be the same wall-clock value that MySQL shows under its default
  session time zone. If it is shifted by whole hours, a MySQL `time_zone` was set somewhere
  in the load (see step 3).
- `search_vector` must be filled in, not NULL.

Also check that `tags.name` and `groups.query_options` read as valid JSON:

```sql
SELECT name->>'en' FROM tags LIMIT 5;
SELECT query_options FROM groups WHERE query_options IS NOT NULL LIMIT 5;
```

## 5. Switch over

Point `.env` at Postgres:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=…
DB_PORT=5432
DB_DATABASE=linkerlee
DB_USERNAME=…
DB_PASSWORD=…
```

Then:

```bash
php artisan config:cache
php artisan up
# start the queue worker again
```

Smoke-test the running app:

- Sign in, typing your email in a different case from the one you registered with.
- Search for a word that appears only in a link's page text.
- Open a `/share/…` page.
- Make an API request with an existing personal access token.
- Save a new link, and check that the worker enriches it.
- If you use save-by-email, forward a link to a real inbox address (Settings shows it).

### Docker installs

The compose file now runs a `postgres` service in place of `mysql`, which means the old
MySQL volume is no longer attached. To copy the data:

1. Before pulling this version, do step 1 inside the running stack:
   ```bash
   docker compose exec app php artisan down
   docker compose stop queue
   docker compose exec app php artisan queue:work --queue=default,ingestion,enrichment,health --stop-when-empty --force
   # -T keeps the TTY out of the dump; the password is read inside the container
   docker compose exec -T mysql sh -c \
     'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines linkerlee' \
     > linkerlee-mysql-$(date +%F).sql
   head -c 300 linkerlee-mysql-*.sql   # must start with "-- MySQL dump", not a warning
   ```
2. Pull this version, and switch `.env` to the new database **before** starting anything.
   Set `DB_CONNECTION=pgsql`, `DB_HOST=postgres` and `DB_PORT=5432`, and remove
   `DB_ROOT_PASSWORD`. Then run `docker compose up -d --remove-orphans postgres app`.
   `--remove-orphans` stops the old `mysql` container, and the app container creates the
   Postgres schema itself (step 2).
3. Restore the dump into a temporary MySQL on the same compose network:
   ```bash
   docker run -d --name linkerlee-oldmysql --network linkerlee_default \
     -e MYSQL_ROOT_PASSWORD=temp -e MYSQL_DATABASE=linkerlee mysql:8.0
   docker exec -i linkerlee-oldmysql mysql -uroot -ptemp linkerlee < linkerlee-mysql-*.sql
   ```
4. The `postgres` service publishes no port, so run pgloader inside the same network. Use
   `mysql://root:temp@linkerlee-oldmysql/linkerlee` as `FROM`, and
   `postgresql://$DB_USERNAME:$DB_PASSWORD@postgres/linkerlee` as `INTO`:
   ```bash
   docker run --rm --network linkerlee_default -v "$PWD:/w" dimitri/pgloader pgloader /w/linkerlee.load
   ```
   The compose `DB_USERNAME` role owns every table, so `disable triggers` works. Timestamps
   are read correctly because the old `mysql` service and this temporary container both run
   in UTC, which is the `mysql:8.0` image default. Use `linkerlee` as the schema name in
   the `ALTER SCHEMA` line, since that is the temporary database's name.
5. Do step 4 as above, remove the temporary container with
   `docker rm -f linkerlee-oldmysql`, then start the full stack with `docker compose up -d`.


## Rolling back

MySQL was only read from, so it is unchanged. Put the old `DB_*` values back in `.env`,
deploy the previous LinkerLee release (it is the one with MySQL support), then run
`php artisan config:cache` and `php artisan up`. Anything written after the switch exists
only in Postgres.

## Dry-run record

This runbook was run end to end on 2026-09-25, with the load file above:
- **Source:** MySQL 8.0.33, `time_zone=SYSTEM`, at UTC+3, built from the last MySQL-era
  commit.
- **Target:** PostgreSQL 17.0, with pgloader 3.6.10.
- **Test data:** favourites, NULL descriptions, a 1900-character URL, emoji and Romanian
  diacritics, a soft-deleted link, tags, a group with tag rules, a share link, an API token
  and a failed job.

**Results**
- Every row count matched, and every timestamp column matched exactly.
- The sequences equalled `max(id)`.
- The schema and foreign keys were identical to a fresh `migrate`.
- `search_vector` was generated for the loaded rows.
- The app found links by page text, and an old password still verified. A new link received
  the next id.
- A second run, after review fixes, added `on error stop` and the `citext` columns. A
  mixed-case email signed in when typed in lowercase, a lowercased inbox token resolved, and
  the sequence check caught a sequence that was set back deliberately.
