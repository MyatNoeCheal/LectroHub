# LectroHub

PHP/MySQL electronics e-commerce site with Google OAuth, email notifications,
a Flask chatbot (LectroBot) and security-event reporting to Smart SIEM.

## Local setup

Secrets are kept in git-ignored files. Copy each example and fill it in:

| Copy this | To this (git-ignored) | Purpose |
|---|---|---|
| `lectrohub_db/connection.example.php` | `lectrohub_db/connection.php` | MySQL/MariaDB |
| `lectrohub_db/mailer_config.example.php` | `lectrohub_db/mailer_config.php` | SMTP (Gmail app password) |
| `auth/google_oauth_config.example.php` | `auth/google_oauth_config.php` | Google OAuth |
| `siem_config.local.example.php` | `siem_config.local.php` | Smart SIEM URL + API key |
| `chatbot-deployment/.env.example` | environment variables | LectroBot |

`SIEM_API_KEY` must equal `SIEM_API_KEY` in the SIEM backend's environment.
Never commit real credentials. If one is ever exposed, rotate it.
