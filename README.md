# Home Assistant Temperature Dashboard

Self-hosted temperature chart dashboard for Home Assistant sensors.

## Setup

1. Edit `www/config.php` with your Home Assistant URL and long-lived access token.
2. Set `default_sensors` to your preferred temperature sensor entity IDs.
3. Push to `main` — the self-hosted runner will deploy automatically.

## Ports

- `8080` — Dashboard

## Config changes

`config.php` is read on every request. No container restart required.
