# Installation

[Русская версия](INSTALL.ru.md)

## 1. Requirements
- PHP 8.0+ with `mysqli` and `mbstring` extensions
- MySQL 8.0
- Apache (nginx + php-fpm also works) with a virtual host pointed at the project root
- A running AzerothCore 3.3.5 server with stock `acore_auth` / `acore_characters` /
  `acore_world` databases

A MySQL user that can **create databases** is needed for the installer (it creates
the web database). After installation the site itself only needs read/write on the
web database and read-only on the three game databases.

## 2. Files
Put the whole project into the web root of your virtual host, e.g. `/var/www/wow`.
Make sure these paths are writable by the web server user:
- `config/` (the installer writes `config/config-protected.php`)
- `install/` (the installer writes `install/DISABLE_INSTALLER.php`)

## 3. Run the installer
Open `http://your-site/install/` in a browser and fill in the single form:

- MySQL credentials (host, port, user, password) and the database names: auth,
  characters, world — plus the web database (`site` by default, created if missing);
- site name, email, cookie name, client type (expansion).

The installer imports the SQL into the web database (site tables + armory lookup
tables), **registers the realms itself** — one entry per realm in the auth
database's `realmlist` (or realm id 1 when `realmlist` is empty), using the
credentials you provided — then writes `config/config-protected.php` and creates
`install/DISABLE_INSTALLER.php`, which locks the installer. Leave that file in
place; deleting it re-enables the installer (only do this on a fresh reinstall).

## 4. Site administrators
The website groups `Administrators`/`Root Admins` grant access to the admin
panel. How to get one:

- **Automatically on login**, from the in-game gmlevel (`account_access`):
  - 1–2 (moderator/GM) → **Moderators** group (forum and gallery moderation,
    no access to the website admin panel);
  - 3 (game administrator) → **Root Admins** (full website admin panel);
  - 0 — untouched. There is no automatic demotion (manually assigned groups
    and bans are never overwritten).
- Manually (e.g. for a user without gmlevel): register on the site, then run
  in the web database:
  ```sql
  UPDATE website_accounts SET g_id = 3
  WHERE account_id = (SELECT id FROM <auth-db>.account WHERE username = 'YOUR_LOGIN');
  ```
  `g_id`: 2 = Members, 3 = Administrators, 4 = Root Admins
  (full list in the `website_account_groups` table).

Log out and back in after a group change. There is no admin-panel UI for
changing website groups yet. Website groups control the website only and
are independent from the in-game gmlevel.

## 5. Optional settings
- **Armory locale / options** — `armory/configuration/settings.php`
  (`"Lang"` = `en_us`, `de_de`, `fr_fr`, `ru_ru`).
- **Site template** — `templates/` folder, selected in `config/config.xml`.
- **Armory lookup data** (instance/boss/zone names) ships with the installer in the
  web database; zone names for non-Russian locales come from client DBCs
  (`AreaTable.dbc`), Russian names from the ruRU client locales.

## 6. Updating the site
When a new version is released: overwrite the site files (keep
`config/config-protected.php`), then open `install/update.php` and confirm
with the MySQL password the site uses (it is stored in
`config/config-protected.php`). The updater applies all SQL files from
`install/sql/updates/` whose number is greater than the current schema version
(`website_version` table) and records the new version. Dump the web database
before updating. The update file format is documented in
`install/sql/updates/README.txt`.

## 7. Reinstall / uninstall
- Reinstall: delete `config/config-protected.php`, delete `install/DISABLE_INSTALLER.php`,
  drop the web database, rerun the installer.
- The game databases are never modified, so removing the web database removes all
  traces of the website.
