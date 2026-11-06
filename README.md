# Web wrapper for AzerothCore

[Русская версия](README.ru.md)

PHP web portal + character armory for [AzerothCore](https://github.com/azerothcore/azerothcore-wotlk)
(WotLK 3.3.5) servers. It targets the stock AzerothCore 3.3.5 database structure,
but contains no hardcoded server: database credentials, realm names/addresses,
locale and template are all set at install time or in the config files.

## Features
- One-click **installer** (`/install`) — creates the web database (name of your
  choice, `site` by default), writes `config/config-protected.php`, and afterwards
  locks itself with `install/DISABLE_INSTALLER.php` so the install cannot be
  repeated from the browser
- **Armory**: character sheets (equipment, talents, achievements, reputation, skills),
  guilds, arena teams and rankings, creature/item pages, item search with tooltips,
  auction house view, honor/arena leaderboards
- **User accounts**: registration, account management, GM ticket submission
- Interface languages: English, German, French, Russian
- Read-only access to `acore_auth` / `acore_characters` / `acore_world` — the game
  databases are never modified; all web data lives in a separate web database

## Requirements
- PHP 8.0+ (mysqli, mbstring)
- MySQL 8.0
- **AzerothCore 3.3.5 only** (stock DB structure). Tested on master, revision
  `b704af465` (2026-11-04). Other cores of the same era are not supported:
  e.g. a schema comparison against TrinityCore 3.3.5 (TDB 335.26091) showed a
  different auth scheme, talent storage, creature models and some reference
  tables — this project is not adapted to TrinityCore

## Installation
See [INSTALL.md](INSTALL.md). Short version: point a virtual host at the project
root, open `http://your-site/install/`, follow the wizard.

## Structure
- `install/` — installer + SQL for the web database (`install/sql/`)
- `armory/` — armory engine (`armory/configuration/settings.php` — locale and options)
- `core/`, `templates/` — site framework and templates
- `config/` — site configuration (written by the installer)

## Credits
Based on MangosWeb Enhanced and the MBA armory, ported to PHP 8 and AzerothCore 3.3.5.

## License
GPL-3.0 (this project includes code derived from MangosWeb Enhanced / MBA armory).
See [LICENSE](LICENSE).
