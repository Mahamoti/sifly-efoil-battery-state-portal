# SiFly Battery Monitor

A small, practical portal to check the status of your SiFly PowerCells — without opening the full app first.

## Why this exists

If you work with multiple PowerCells, you mainly want one answer: **how full are they?** Not because they should always be charged to 100% — often they shouldn’t — but because you want to see at a glance which batteries are ready for the next session, and which can wait a bit longer.

The official MySiFly app can do that too. Sometimes faster is better: a quick status check from your phone, via a bookmark or home-screen shortcut. No app to launch, no digging around. Open, look, done.

That’s exactly what this portal is for.

## Sign in

Sign in with your existing MySiFly account. You’ll then see your batteries live, from the same source as the official environment.

![SiFly Battery Monitor sign-in screen](docs/images/inlogscherm.png)

## Status at a glance

After signing in you get an overview of all your batteries: charge level, status (charging, discharging, or idle), capacity, and when the last reading came in. In seconds you can see whether a pack is sitting at 80% — often exactly where you want it — or needs a top-up.

![Dashboard with battery status and charge percentages](docs/images/dashboard-overzicht.png)

For each battery you can open details: live telemetry, BMS state, and recent measurements. Useful when you want a closer look without disrupting the rest of your workflow.

## What it is (and isn’t)

- **Is:** a lightweight status portal using your MySiFly login
- **Is:** meant to open quickly on desktop or mobile
- **Isn’t:** a replacement for the full SiFly app or management environment

## Technical

Simple setup: a static frontend (`index.html`) with a PHP proxy to the MySiFly API.

| File | Role |
|---|---|
| `index.html` | UI + API client |
| `proxy.php` | Auth/API proxy to `my.sifly.global` |
| `.htaccess` | Pass Authorization header through to PHP |

### Running locally

Host the folder on a PHP web server (Apache/Plesk or similar) and open the site in your browser. Make sure `proxy.php` can reach `my.sifly.global`.

## License / use

Personal helper around SiFly PowerCells. Not affiliated with SiFly; uses the MySiFly API for your own status overview.
