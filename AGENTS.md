# AGENTS.md

« Ma Mini-Grammaire » — application web d'apprentissage de la grammaire française. PHP 8 + Fat-Free Framework (F3) en MVC, MariaDB, JS/CSS vanilla. Voir `DOCUMENTATION.md` pour l'aperçu des fonctionnalités et le tableau des routes.

## Environnement et exécution locale

- Le développement se fait dans **DDEV** (`.ddev/config.yaml` : PHP 8.2, MariaDB 11.8, nginx-fpm, docroot = racine du projet). Il n'y a pas de PHP sur l'hôte.
  - `ddev start` → l'application sur `https://mini-grammaire.ddev.site`
  - Toute commande composer/php/db s'exécute dans le conteneur : `ddev exec composer install`, `ddev mysql`, `ddev exec php -l App/Controllers/Foo.php`.
- Aucun test, lint ou CI n'existe. Vérifier via `ddev exec php -l` et une navigation manuelle.

## Configuration et base de données

- Chargement de l'env (`index.php:19-24`) : charge `.env.mini-gram.local` (dev) si présent, sinon `.env.mini-gram`. **Les deux sont commités** ; n'y ajoutez pas de secrets à la légère.
- `.env.mini-gram.local` pointe déjà vers la BD par défaut de DDEV (`mysql:host=db;dbname=db;user=db;pass=db`).
- Initialiser la BD à partir de `mini_gram.sql` (tables : `users`, `astuces`, `favoris`, `progression`). Les migrations de `migrations/` sont de **simples instructions ALTER, appliquées à la main** — aucun outil de migration (`first.sql` ne fait que créer la base `mini_gram`).
- Piège : la table `mini_grammaire_codes` utilisée par `App/Models/MiniGrammaire.php` (page `/mini_grammaire`) est créée par `mini_grammaire_codes.sql` commité à la racine (158 fiches au format `code`/`category`/`description`/`detail`/`example`, issues de `ui/test/data/document.json` ; plusieurs règles peuvent partager le même code, ex. P1 en 8 occurrences ; les champs `detail`/`example` sans valeur sont `NULL`, pas la chaîne `'None'`). La table `users` porte des colonnes de streak (`streak_count`, `consecutive_days`, `last_activity_date`, `streak_freezes_available`) ajoutées par des migrations.

## Architecture

- **`index.php` est le seul endroit où les routes sont enregistrées** (appels `route()` de F3). Pour ajouter une page/route, faites-le là ; les contrôleurs ne s'enregistrent pas eux-mêmes. Aucune route/page n'a de préfixe `/mini-grammaire` (contrairement à ce que dit `DOCUMENTATION.md`).
- Les contrôleurs sont dans `App/Controllers/`, les modèles dans `App/Models/`, les vues dans `ui/` (moteur de templates F3).
- La plupart des modèles étendent `\DB\SQL\Mapper` (le mini-ORM de F3) et sont construits avec la connexion `DB` : `new Foo($f3->get('DB'))`. **Exception :** `ConsecutiveDays` et `Streak` sont des classes simples qui exécutent du SQL brut via `$db->exec()`.
- Schéma de rendu d'une vue : rendre le template de la page, le définir comme `@content`, puis afficher `layout.html` : `$f3->set('content', \Template::instance()->render('pages/foo.html')); echo \Template::instance()->render('layout.html');`
- Les templates utilisent la syntaxe F3 : `{{ @var }}`, `<repeat group="@list" value="@item">`, `<check if="...">`. Le layout injecte les pages via `{{ @content | raw }}` et charge toujours `assets/js/script.js` + `assets/js/quiz.js`.
- Sessions : `session_start()` PHP natif est synchronisé avec le hive `SESSION` de F3 (`index.php:31-35`). `BaseController::beforeroute` charge l'utilisateur et définit `@userRole` (`'invite'` par défaut) ; `requireRole()` gère le RBAC. Les routes ne sont pas protégées autrement — les contrôleurs vérifient eux-mêmes les sessions.
- **Tout le code, les commentaires, les textes UI et les messages de commit sont en français.** Gardez le nouveau code et les commits en français.

## Fichiers parasites — ne pas toucher

- `tmp/` = cache des templates compilés de F3 (régénéré automatiquement).
- `migrations.zip`, `phpmyadmin_secret_xyz/` (un export source complet de phpMyAdmin), `ui/test/`, `ui/pages/tgy.html` et le fichier vide `ui/pages/auth_forgot.html` sont des vérifications obsolètes/accidentelles, pas du code applicatif.