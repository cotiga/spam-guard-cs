# cotiga/spam-guard-cs

Antispam, ban IP et journalisation d'erreurs HTTP pour le socle **CotiCMS Core/Starter** — Laravel 13 + Filament v5, **admin Filament intégré**.

> Pour les installs legacy (Laravel 10/11/12 + STRAdmin/Voyager), voir [`cotiga/spam-guard-lr10`](https://github.com/cotiga/spam-guard-lr10).

- Rate limiting par IP
- Filtrage email : TLD, domaine, patterns suspects
- Détection nom avec chiffres / charabia, message générique de bot, message charabia
- Validation téléphone (format date, lettres, longueur hors norme)
- Géoblocage (via `stevebauman/location`)
- Log et ban automatique des IP abusives
- Email d'alerte sur erreurs récurrentes
- **Administration Filament** : refus antispam, IP bannies, erreurs HTTP, URLs ignorées

## Installation

```bash
composer require cotiga/spam-guard-cs
php artisan migrate
```

### 1. Activer l'admin Filament

Dans le `AdminPanelProvider`, enregistrer le plugin (auto-détecté s'il est présent) :

```php
use Cotiga\SpamGuard\SpamGuardPlugin;

->when(class_exists(SpamGuardPlugin::class), fn ($panel) => $panel->plugin(SpamGuardPlugin::make()))
```

Les 4 Resources `SG*` apparaissent alors dans le groupe **Configuration** du panel.

### 2. Brancher la journalisation d'erreurs (optionnel)

Dans `bootstrap/app.php` — c'est l'équivalent Laravel 13 de l'ancien `app/Exceptions/Handler.php` :

```php
->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
    \Cotiga\SpamGuard\SpamGuardExceptions::register($exceptions);
})
```

Sans ce branchement, l'antispam des formulaires (`FormSpamGuard`) et l'admin fonctionnent ; seules la journalisation HTTP et le ban automatique sur erreurs récurrentes sont désactivés.

## Configuration

```bash
php artisan vendor:publish --tag=spam-guard-config
```

`config/spam-guard.php` : tous les seuils, listes de pays, TLD, patterns, etc.

## FormSpamGuard dans les contrôleurs

```php
use Cotiga\SpamGuard\Services\FormSpamGuard;

class ContactController extends Controller
{
    public function store(ContactRequest $request, FormSpamGuard $guard)
    {
        if ($guard->isSpam($request->mel, $request->ip(), $request->only(['nom', 'tel', 'msg']))) {
            return $this->fakeSuccessResponse($request);
        }
        // traitement normal...
    }
}
```

## Vues d'erreur

Le package fournit `spam-guard::errors.generic` (HTML autonome, sans layout). Priorité de résolution : `errors.{status}` → `errors.generic` (de l'app) → `spam-guard::errors.generic`. Pour personnaliser :

```bash
php artisan vendor:publish --tag=spam-guard-views
```

## Tables créées

| Table                         | Description                           |
|-------------------------------|---------------------------------------|
| `spam_guard_banned_ips`       | IP bannies automatiquement            |
| `spam_guard_errors`           | Erreurs HTTP loguées                  |
| `spam_guard_error_ignoreds`   | Motifs d'URL à ignorer                |
| `spam_guard_refused_contacts` | Soumissions de formulaires refusées   |

## Variables d'environnement

```dotenv
SPAM_GUARD_ALERT_EMAIL=support@example.com
SPAM_GUARD_ALERT_THRESHOLD=10
SPAM_GUARD_BAN_THRESHOLD=30
```
