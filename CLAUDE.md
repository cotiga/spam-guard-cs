# cotiga/spam-guard-cs — Package Laravel/Filament

## Description

Antispam formulaires, ban IP et journalisation d'erreurs HTTP pour le socle **CotiCMS Core/Starter** : Laravel 13 + Filament v5, **admin Filament intégré**.

Issu de la scission de `cotiga/spam-guard`. La ligne legacy (Laravel 10/11/12 + STRAdmin) reste `cotiga/spam-guard-lr10`. Namespace partagé `Cotiga\SpamGuard\` (les deux packages ne cohabitent jamais dans un projet).

## Repo et publication

- **GitHub** : `https://github.com/cotiga/spam-guard-cs`
- **Packagist** : `cotiga/spam-guard-cs`
- **Branche** : `main`

## Architecture

```
config/spam-guard.php                  # Config publiable (seuils, pays, TLD, patterns...)
database/migrations/                   # 4 tables spam_guard_*
resources/views/errors/generic.blade   # Vue d'erreur standalone (fallback)
src/
├── Models/                            # BannedIp, HttpError, ErrorIgnored, RefusedContact
├── Services/
│   ├── FormSpamGuard.php              # Antispam formulaires (injecté dans les controllers)
│   └── HttpErrorGuard.php             # Logique log + ban auto + vue d'erreur (Response|null)
├── Filament/Resources/               # 4 Resources SG* (découvertes par le Plugin)
│   ├── RefusedContacts/SGRefusedContactResource
│   ├── BannedIps/SGBannedIpResource
│   ├── HttpErrors/SGHttpErrorResource
│   └── ErrorIgnoreds/SGErrorIgnoredResource
├── SpamGuardPlugin.php               # Plugin Filament (discoverResources)
├── SpamGuardExceptions.php           # Entrée bootstrap/app.php (->withExceptions)
└── SpamGuardServiceProvider.php
```

## Principes Laravel 13 (à respecter)

- **Pas de `app/Exceptions/Handler.php`** : la gestion d'exceptions passe par `bootstrap/app.php` `->withExceptions(...)`. Le package expose `SpamGuardExceptions::register($exceptions)`.
- `HttpErrorGuard::handle()` renvoie une `Response` **ou `null`** (null = laisser Laravel gérer nativement, ex. validation/auth).
- Détection HTTP via `Symfony\...\HttpExceptionInterface`, jamais via une classe Handler héritée.

## Principes Filament v5 (à respecter)

- Resources nommées `SG*` (préfixe explicite, auto-documenté).
- Admin lecture seule sauf `SGErrorIgnoredResource` (CRUD — c'est de la config).
- `canViewAny()` : `HttpErrors` et `ErrorIgnoreds` = admin only ; `RefusedContacts` et `BannedIps` = admin **ou** manager (gestion quotidienne des faux positifs / IP). Pas d'override de `canAccess()`.
- Schéma réel : `spam_guard_banned_ips` ne contient que `ip` (pas de `raison`).
- Imports v5 : `Filament\Schemas\Schema`, `Filament\Actions\*`, `recordActions()`/`toolbarActions()`.

## Tables

| Table                         | Description                          | Resource                  |
|-------------------------------|--------------------------------------|---------------------------|
| `spam_guard_refused_contacts` | Formulaires refusés                  | SGRefusedContactResource  |
| `spam_guard_banned_ips`       | IP bannies                           | SGBannedIpResource        |
| `spam_guard_errors`           | Erreurs HTTP loguées                 | SGHttpErrorResource       |
| `spam_guard_error_ignoreds`   | Motifs d'URL à ignorer               | SGErrorIgnoredResource    |

## Intégration dans un projet (core/starter)

1. `composer require cotiga/spam-guard-cs` + `php artisan migrate`
2. `AdminPanelProvider` : `->plugin(SpamGuardPlugin::make())`
3. `bootstrap/app.php` : `->withExceptions(fn ($e) => SpamGuardExceptions::register($e))`

## Workflow de développement

1. Modifier dans `/Users/boss/GIT/spam-guard-cs/`
2. Commiter et tagger (`v1.0.x`)
3. `git push origin main --tags` → Packagist auto
4. Côté projet : `composer update cotiga/spam-guard-cs`

## Règles importantes

- NE PAS publier les migrations en hook de déploiement — chargées via `loadMigrationsFrom`
- `composer install --no-dev` en production
- IP : toujours `$request->ip()`, jamais `$_SERVER`
