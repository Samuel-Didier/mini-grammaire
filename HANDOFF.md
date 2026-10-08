# HANDOFF — état d'avancement (à lire en premier au retour)

Applicatif PHP 8 + Fat-Free Framework (F3), MVC, MariaDB, JS/CSS vanilla. Développement en **DDEV** (pas de PHP sur l'hôte) ; la prod est sur un serveur externe (`/home/da202430155/public_html/minigrammaire/`), synchronisée **manuellement/partiellement** — c'est la source d'incidents d'incohérence.

## Dernière tâche terminée : connexion persistante (« Se souvenir de moi »)

Case à cocher sur `/login` : l'utilisateur reste connecté 7 jours après la fermeture du navigateur. Comme pour le journal, l'application fonctionne normalement si la migration n'est pas appliquée (la case est simplement masquée).

### Ce qui est en place
- **SQL** : `migrations/2026_09_29_add_remember_me.sql` → `users.remember_token` VARCHAR(255) NULL + `users.remember_expires` DATETIME NULL + index. **À appliquer en prod**, sinon la case n'apparaît pas.
- **Modèle** `User` : `creerSouvenir($id)` tire un jeton de 32 octets (`bin2hex(random_bytes(32))`), n'en stocke que l'empreinte `hash('sha256', …)` avec l'expiration, et rend le jeton en clair au contrôleur. `validerSouvenir($token)` retrouve le compte, rejette (et purge) un jeton expiré, inconnu ou désactivé. `oublierSouvenir($id)` révoque.
- **Cookie** : `mg_souvenir` (`BaseController::SOUVENIR_COOKIE`), 7 jours (`User::REMEMBER_JOURS`). Posé/retiré via l'API F3 `$f3->set('COOKIE.…', $token, $ttl)` / `$f3->clear('COOKIE.…')` → `path`, `secure`, `httponly` et `samesite` hérités du cookie de session (vérifié : `HttpOnly` et `Secure` bien présents en HTTPS).
- **Reconnexion silencieuse** : `BaseController::beforeroute` restaure la session **avant** la résolution du rôle, donc `userRole`, `canDashboard` et le journal des requêtes sont corrects dès la première requête restaurée. Un cookie invalide est purgé côté navigateur (`Set-Cookie: mg_souvenir=deleted`).
- **Sécurité** : `session_regenerate_id(true)` à la connexion et à la reconnexion silencieuse. Révocation automatique du jeton à la déconnexion, à tout changement de mot de passe (`updateProfile`, `updateByAdmin`, `resetPassword`) et à la désactivation d'un compte (`softDelete`).

### Fichiers concernés (à déployer ensemble)
`App/Models/User.php`, `App/Controllers/BaseController.php`, `App/Controllers/Auth.php`, `ui/pages/auth/login.html`, `migrations/2026_09_29_add_remember_me.sql`.

### Scénarios vérifiés en DDEV (11 cas)
Connexion avec/sans la case, fermeture du navigateur (reconnexion silencieuse + RBAC conservé), jeton falsifié, jeton expiré (cookie purgé), jeton révoqué en base, changement de mot de passe, déconnexion, mot de passe réinitialisé, colonnes absentes (dégradation propre), compte désactivé, et contrôle responsive de la case (44 px, sans débordement de 280 à 1024 px).

## Tâche précédente : pages rendues responsive

`assets/css/responsive.css` (chargée par le layout sur toutes les pages) porte désormais la couche « ergonomie tactile » (`min-height: 44px` sur les liens et boutons cliquables sous 768 px). `/docs`, qui a son propre `<style>`, a été retraité dans sa media query interne. Audit Chrome headless : 0 débordement et 0 cible < 30 px sur 154 mesures (22 pages × 7 largeurs de 280 à 1920 px). Script réutilisable : `/tmp/opencode/audit_responsive.js`.

## Tâche précédente : journal des requêtes (`/dashboard/logs`)

Une ligne par requête PHP, consultable et purgable par le seul administrateur. L'application reste pleinement fonctionnelle si la migration n'est pas appliquée (le journal est alors simplement ignoré).

### Ce qui est en place
- **1 table** : `request_logs` (`user_id`/`username`/`role` **figés** = instantané, `methode`, `chemin`, `uri`, `statut`, `duree_ms`, `ip`, `user_agent`, `referer`, `cree_le`), créée par `migrations/2026_09_29_add_request_logs.sql` (à appliquer à la main).
- `App/Models/RequestLog.php` : `demarrer()` (pose le `register_shutdown_function()`), `setUtilisateur()`, `ecrireRequete()`, `statistiques()`, `liste()`, `listeUtilisateurs()`, `purger()`, `tableExiste()`, `requetesLabel()`.
- Démarrage dans `index.php` juste après la connexion ; `BaseController::beforeroute` appelle `RequestLog::setUtilisateur()`. Le modèle se rabat sur la session au shutdown, donc une connexion ou une inscription est attribuée au bon compte.
- `/dashboard/logs` (admin seul) : 4 cartes (total, aujourd'hui, erreurs 4xx-5xx, comptes distincts), filtres `q` / `user_id` / `statut` (seuil `>=`) / `depuis` / `jusqua`, pagination 50 lignes, et formulaire de purge (`POST /dashboard/logs/purge`, 7/30/90/365 jours ou **tout**).
- Purge automatique : `RETENTION_JOURS = 30`, déclenchée sur une requête sur 50 (`PURGE_CHANCE`).
- Carte « Requêtes journalisées » + lien dans le dashboard admin (`ui/partials/dashboard_contenu.html`).

### Points d'attention
- ⚠️ `DB\SQL\Mapper::__construct` fait un `$db->schema()` et **plante si la table est absente** : appeler `RequestLog::tableExiste($db)` **avant** `new RequestLog($db)`. C'est ce qui permet à `/dashboard/logs` d'afficher un avertissement au lieu d'un 500 tant que la migration manque. Le shutdown, lui, est déjà dans un `try/catch`.
- Aucun corps de requête n'est journalisé (volontairement : les mots de passe ne doivent pas atterrir en base).
- `statut` est un seuil (`>=`), pas une égalité : `400` = 4xx, `500` = 5xx.
- Le libellé affiché dans la colonne « requête » est le chemin ; l'URI complète est dans la colonne suivante.

### Vérifié en réel (DDEV, cache purgé)
- Journalisation : GET anonyme `200`, 404, POST de connexion (`302` → `exit`/`reroute`) et pages `/dashboard/*` bien enregistrées, avec le bon `username`/`role`.
- Rendu `/dashboard/logs` : 200, 4 cartes, tableau, pagination (« Page 1 sur 2 ») ; libellés accordés (« 1 requête affichée », « 0 requête »).
- Filtres : `q`, `user_id`, `statut=400` (retourne bien le 404 créé pour le test), `depuis`/`jusqua`, `page` hors bornes (bornée) ; valeurs invalides (`statut=abc`, `depuis=2026-13-45`, `page=-4`) ignorées sans erreur SQL.
- RBAC/CSRF : `etudiant` et `enseignant` → 302 sur `/dashboard/logs` et sur la purge ; purge sans jeton ou avec jeton invalide → rien n'est supprimé.
- Purge : 7 jours sans rien d'ancien → « Aucune entrée à supprimer » ; 2 lignes de 40 et 90 jours + purge 30 jours → supprimées ; `purger(0)` vide tout.
- **Absence de la table** (table renommée) : `/`, `/astuces`, `/mini_grammaire`, `/quiz`, `/login`, `/register`, `/dashboard`, `/dashboard/quiz` et `/dashboard/logs` répondent **200** ; la page affiche l'avertissement de migration et la carte affiche `0`. Aucune ligne perdue ni 500.
- Base remise dans son état : journal purgé, comptes de test supprimés.

### Fichiers à déployer ensemble en prod (migration incluse)
- Modèle : `App/Models/RequestLog.php` (nouveau)
- Contrôleur : `App/Controllers/DashboardController.php`, `App/Controllers/BaseController.php`
- Vue : `ui/pages/dashboard/logs.html` (nouvelle)
- Partiel + CSS : `ui/partials/dashboard_contenu.html`, `assets/css/dashboard.css`
- Routage : `index.php`
- **SQL** : `migrations/2026_09_29_add_request_logs.sql`

## Tâche précédente : quiz pilotés par la base (`/dashboard/quiz`)

Les 20 questions qui étaient figées dans `assets/js/quiz.js` (et dans un vieux `ui/pages/quiz.html` mort) sont désormais en base et gérables par les enseignants comme par l'administration, avec un nombre de propositions choisi à la création.

### Ce qui est en place
- **2 tables** : `quiz_questions` (`categorie` = slug, `libelle_categorie`, `niveau`, `difficulte`, `question`, `explication`) et `quiz_propositions` (`question_id`, `libelle`, `ordre`, `est_correcte`). Créées par `migrations/2026_09_29_add_quiz_tables.sql`, remplies par `migrations/2026_09_29_seed_quiz_questions.sql` — **à appliquer dans cet ordre, à la main** (le seed n'est pas idempotent : ne pas le relancer). 20 questions / 80 propositions.
- Un quiz = couple `(categorie, niveau)`. `niveau = 'tous'` = test de niveau (mélange des 20 questions, algorithme CECRL inchangé) ; sinon `niveau` est le niveau du quiz et `difficulte` celui de la question. Niveaux `a1`…`c2`.
- `App/Models/Quiz.php` : `listQuizzes()` (cache `quiz.menu`), `categories()`, `questionsFor()` (mélange pour `tous`, 50 max), `questionsDashboard()`, `createQuestion()`, `updateQuestion()`, `deleteQuestion()`, `validateQuestion()` (2–6 propositions), `slugify()` (via `Normalizer`). Écritures transactionnelles + vidage du cache.
- `GET /api/quiz/questions?categorie=…&niveau=…` (`QuizApiController`, connecté requis) : `{ success, categorie, niveau, quiz, message, questions: [ { question, level, options, correct, explication } ] }`, `correct` = index de la bonne option. La bonne réponse reste envoyée au client (inchangé, le quiz n'a jamais été sécurisé).
- `GET /quiz/jouer/@categorie/@niveau` (`QuizController::jouer`, connecté requis) rend `ui/pages/quiz/test_niveau.html` pour **tous** les quiz, y compris le test de niveau ; `data-test="1"` seulement pour `niveau/tous`. `GET /test-niveau` redirige vers `/quiz/jouer/niveau/tous`. `assets/js/quiz.js` (déjà chargé par le layout) charge l'API, gère 2–6 options, affiche le récapitulatif et POSTe toujours le test de niveau sur `/quiz/save-level`.
- `GET /quiz` : menu **généré depuis la base**, donc une catégorie créée au dashboard apparaît sans code.
- `/dashboard/quiz` (admin + enseignant) : compteur, recherche, filtres, création, édition en ligne (`<details>` par carte) et suppression. `assets/js/quiz_admin.js` fait basculer le nombre de propositions de 2 à 6. Un POST sans jeton n'écrit rien.
- La liste est **regroupée par libellé de catégorie** : bandeau `.quiz-groupe` (nom de la catégorie + nombre de questions) et espacement entre les blocs. Le tri de la liste est `categorie ASC, niveau ASC, id ASC` ; le regroupement est fait par `DashboardController::groupQuizByCategorie()`. Renommer un libellé déplace le groupe, l'URL reste valable (c'est le slug qui identifie la catégorie).
- `ui/pages/quiz/quiz.html` (autonome, obsolète) et `Page::quiz()` ont été **supprimés** : plus de double source de vérité.

### Points d'attention
- `DashboardController::quizPayload()` / `indexCorrect()` acceptent `POST.correct` sous forme de lettre (`A`…`F`) **ou** d'index (`0`…`5`) : `quiz_admin.js` envoie un index.
- `Quiz::validate()` retire les propositions vides **et recalcule l'index de la bonne réponse** pour qu'il suive ce déplacement ; sans cela, cocher une ligne puis en laisser d'autres vides désignait la mauvaise réponse. Une bonne réponse vide ou hors bornes est refusée.
- L'API renvoie `401` si l'appelant n'est pas connecté, `400` si la catégorie est vide ou le niveau inconnu, `200` sinon ; `quiz.js` n'utilise que `success` + `message`.
- F3 passe les tokens de route dans un **2ᵉ argument** `array $args` (`@categorie` et `@niveau` dans `args[0]['@categorie']`), pas via `$f3->get(...)`.
- Un `<check if="...">` écrit sur une seule ligne ne rend rien → les libellés pluriels sont calculés en PHP.
- `Quiz::slugify()` passe par `Normalizer` : les accents d'une catégorie libre deviennent un slug ASCII (`Compréhension orale` → `comprehension-orale`) ; vérifier le slug après création.

### Vérifié en réel (DDEV, cache purgé)
- CRUD complet : création 5 propositions → modification (5→3, changement de niveau) → suppression ; la cascade des propositions et le vidage du cache sont propres (retour à 20 questions / 80 propositions).
- 6 propositions acceptées (bonne réponse `F` → `correct = 5`) ; 7 propositions refusées ; catégorie/énoncé/niveau invalides refusés avec message flash.
- `POST.correct` : `0`, `1`, `2`, `A` acceptés et correctement interpretés ; `F` sur 3 propositions, `9` et vide refusés.
- Décalage d'index : 6 lignes saisies dont 3 vides, bonne réponse en 4ᵉ ligne → c'est bien la 4ᵉ ligne conservée qui est marquée correcte.
- Nouvelle catégorie visible immédiatement dans `/quiz` et dans l'API, absente après suppression.
- `determineLevel` (CECRL) rejoué sur 6 profils → A1…C1 conformes ; récapitulatif : HTML échappé, réponse manquante gérée.
- RBAC : `etudiant` → 302 sur GET et POST (aucune écriture) ; `enseignant` → 200 sur `/dashboard/quiz` et 302 sur `/dashboard/utilisateurs` ; `admin` → 200 partout.
- Regroupement : 4 groupes (Compréhension 3, Grammaire 4, Test de niveau 10, Vocabulaire 3) pour 20 cartes ; création d'une catégorie → nouveau bandeau ; renommage → le groupe change de libellé sans casser l'URL `/quiz/jouer/expression-orale/b1` ; filtres catégorie et niveau et recherche testés (0 résultat → « 0 question affichée »), HTML équilibré.
- Base laissée dans son état initial : 20 questions, 80 propositions, comptes et progressions d'origine ; comptes de test supprimés.

### Fichiers à déployer ensemble en prod (migrations dans l'ordre)
- Contrôleurs : `App/Controllers/QuizApiController.php` (nouveau), `DashboardController.php`, `QuizController.php`, `Page.php`
- Modèle : `App/Models/Quiz.php` (nouveau)
- Vues : `ui/pages/dashboard/quiz.html` (nouvelle), `ui/pages/quiz/menu.html`, `ui/pages/quiz/test_niveau.html`, `ui/partials/dashboard_contenu.html` ; **supprimer** `ui/pages/quiz/quiz.html` s'il existe sur le serveur
- Assets : `assets/js/quiz.js`, `assets/js/quiz_admin.js` (nouveau), `assets/css/quiz.css`, `assets/css/dashboard.css`
- Routage : `index.php`
- **SQL** : `migrations/2026_09_29_add_quiz_tables.sql` puis `migrations/2026_09_29_seed_quiz_questions.sql`

## Tâche précédente : tableau de bord unique `/dashboard` (RBAC + suppression logique)

Une seule URL `/dashboard`, deux rendus selon le rôle ; contenu pédagogique entièrement gérable, gestion des comptes réservée à l'administrateur, aucun compte jamais supprimé physiquement.

### Ce qui est en place
- `GET /dashboard` → `pages/dashboard/admin.html` (admin) ou `pages/dashboard/enseignant.html` (admin/enseignant), avec cartes de statistiques (`Astuces`, `Fiches mini-grammaire` + `Comptes`, `Comptes désactivés`, `Favoris`, `Tests de niveau` pour l'admin).
- `GET /dashboard/astuces` : création + édition en ligne + suppression définitive (suppression des favoris liés dans la même transaction, `Astuces::deleteAstuce`).
- `GET /dashboard/codes` : création, édition complète (code, catégorie, règle, détail, exemple), filtre de recherche, suppression. Catégories en liste blanche (`MiniGrammaire::CATEGORIES`).
- `GET /dashboard/utilisateurs` : identité, rôle, mot de passe (8 caractères minimum + confirmation), activation/désactivation. Un administrateur ne peut ni se désactiver ni modifier son propre rôle.
- Suppression logique : `users.deleted_at` (migration `migrations/2026_09_28_add_user_soft_delete.sql`, **appliquée à la main**, à faire aussi en prod). `User::softDeleteSupported()` détecte l'absence de colonne → l'app fonctionne mais les boutons de désactivation sont masqués.
- Un compte désactivé ne peut plus se connecter (`User::login`) et sa session estvidée au requête suivante (`BaseController::beforeroute`) avec un message explicite.
- Message flash **centralisé** : `BaseController::beforeroute` lit et vide `SESSION.flash`, `ui/layout.html` l'affiche (`.flash*` ajoutées à `assets/style.css`). Les contrôleurs ne doivent plus consommer le flash eux-mêmes. Le message « compte désactivé » est désormais visible sur la page courante.
- `Page`, `QuizController` et `AstucesApiController` héritent désormais de `BaseController` ; `ui/partials/header.html` affiche le lien « Tableau de bord » si `canDashboard`.

### Piège corrigé en cours de route
`mini_grammaire_codes.code` n'est pas unique (158 fiches, 133 codes distincts, `G1.1` en 4 occurrences). Le contrôle d'unicité ajouté à `MiniGrammaire::validateEntry()` a été retiré : il empêchait d'enregistrer une fiche existante sans changer son code.

### Matrice RBAC validée en réel (DDEV)
| rôle | `/dashboard` | `/dashboard/astuces` | `/dashboard/codes` | `/dashboard/utilisateurs` | POST sans jeton |
|------|--------------|----------------------|--------------------|---------------------------|----------------|
| invite | 302 → `/login` | 302 | 302 | 302 | aucune écriture |
| etudiant | 302 → `/astuces` | 302 | 302 | 302 | aucune écriture |
| enseignant | 200 | 200 | 200 | 302 | aucune écriture |
| admin | 200 | 200 | 200 | 200 | aucune écriture |

Vérifié en plus : CRUD astuce et fiche (création → modification → suppression), refus de catégorie invalide, désactivation/réactivation, refus d'auto-désactivation, refus d'un courriel ou identifiant déjà pris, renommage + changement de courriel, message « Jeton de sécurité invalide » affiché.

### Fichiers à déployer ensemble en prod (migration incluse)
- Contrôleurs : `App/Controllers/DashboardController.php` (nouveau), `BaseController.php`, `Page.php`, `QuizController.php`, `AstucesApiController.php`, `AstucesController.php`
- Modèles : `App/Models/User.php`, `Astuces.php`, `MiniGrammaire.php`, `Favori.php`, `Progression.php`
- Vues : `ui/pages/dashboard/` (5 fichiers), `ui/partials/dashboard_contenu.html`, `ui/partials/header.html`, `ui/layout.html`, `ui/pages/astuces/astuces.html`
- Styles : `assets/css/dashboard.css` (nouveau), `assets/style.css`
- Routage : `index.php`
- **SQL** : `migrations/2026_09_28_add_user_soft_delete.sql` → `ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL;` (+ index)
- `ui/partials/dashboard_flash.html` a été supprimé (flash rendu globalement par le layout).

### Points ouverts
- La migration soft-delete **doit** être appliquée en prod, sinon les comptes ne sont pas désactivables.
- `/docs` renvoie toujours 500 (`ui/pages/documentation.html:262`, `{{ @variable }}` interprété par F3) — non corrigé.
- Un administrateur peut désactiver ou rétrograder le dernier compte `admin` : pas de garde-fou.
- `Auth::login` n'appelle pas `session_regenerate_id()` → **corrigé** (voir la section « Connexion persistante »). Les formulaires `/login`, `/register` et `/profile/update` n'ont toujours pas de champ CSRF (routes non protégées par `csrfValidate`).
- `/docs` renvoie 500 → **corrigé** (`{{ '@variable' }}` échappé) et page rendue responsive.

## Tâche précédente : durcissement des autorisations (+ CSRF)

Problème prod remonté : `Internal Server Error — Undefined field getAstucesIdsByUser` → déploiement partiel (contrôleur à jour, modèle `Favori` ancien). Corrigé + sécurisation RBAC.

### Ce qui est en place
- `BaseController::EDITOR_ROLES = ['admin', 'enseignant']` — **liste blanche stricte**, utilisée partout. Un rôle inconnu/erroné (`enseignent`, NULL, …) est refusé.
- `BaseController::beforeroute` : charge l'utilisateur en session et pose `@userRole` (`admin|enseignant|etudiant|invite`).
- CSRF : jeton en session (`SESSION.csrf_token`, hex 64), exposé aux vues (`const CSRF_TOKEN`), validé via `csrfValidate()` (`POST.csrf_token` ou en-tête `X-CSRF-Token`), `hash_equals`.
- Astuces : `requireRole(EDITOR_ROLES)` sur add/save/edit/update ; listes en lecture pour tous ; favoris (toggle + mes-favoris) pour tout utilisateur connecté.
- Mini-grammaire : les 3 endpoints AJAX (`POST /update-field`, `/update-parent-code`, `/update-code-entry`) exigent éditeur + CSRF ; `Page::grammaire`/`grammaireDetail` utilisent `canEdit = in_array($userRole, EDITOR_ROLES)`.
- `AstucesController::getAstuces` a un **repli** `method_exists(Favori,'getAstucesIdsByUser')` → retombe sur `isFavori()` par astuce si le modèle déployé est ancien (évite le 500 même en déploiement partiel).

### Matrix RBAC validée en réel (DDEV)
|          | astuces add/edit              | astuces save/update   | mini-grammaire AJAX |
|----------|-------------------------------|-----------------------|---------------------|
| invite   | 302 → `/login`                | 302 → `/login`        | refusé              |
| etudiant | 302 → `/astuces` + flash      | refusé                | refusé              |
| enseignant | 200 OK                      | éditeur + CSRF requise | éditeur + CSRF     |
| admin    | 200 OK                        | éditeur + CSRF requise | éditeur + CSRF     |

## Fichiers concernés par le recent travail (à déployer ensemble en prod)
- `index.php` (routes astuces edit/update)
- `App/Controllers/BaseController.php`, `AstucesController.php`, `FavorisController.php`, `MiniGrammaireController.php`, `Page.php`
- `App/Models/Astuces.php`, `App/Models/Favori.php`
- `ui/pages/astuces/*` (ajout de `astuces_form.html`, suppression de `astuces_add.html`), `ui/pages/mini_grammaire*.html`
- `assets/css/astuces.css`, `assets/js/script_search.js`
- `HANDOFF.md` (ce fichier)

## Points ouverts / signalés (non corrigés)
- **forgotPassword** : mot de passe temporaire affiché à l'écran, pas d'e-mail envoyé, pas de vérification du claim → prise de contrôle de compte. Corriger en priorité.
- Debug prod : `DEBUG_LEVEL=1` (ligne `index.php:26`) → à mettre à `0` en prod.
- `session_regenerate_id()` absent au login (fixation de session). → **corrigé** : appelé à la connexion et à la reconnexion silencieuse (voir « Connexion persistante » plus haut). Le champ CSRF manque toujours sur `/login`, `/register` et `/profile/update`.
- Migration `migrations/2026_09_23_add_perf_indexes.sql` (index uniques) probablement **non appliquée en prod**.
- `request_logs` grossit d'une ligne par requête (≈ rien pour ce trafic, mais la rétention automatique ne supprime que ce qui dépasse 30 jours, et seulement sur 1 requête sur 50 → la purge manuelle reste le filet de sécurité).
- `.env.mini-gram.local` est commité et chargé en priorité ; `.env.mini-gram` (non versionné) est chargé sinon — attention aux secrets.
- `ui/pages/auth_forgot.html` est un fichier vide parasite (page stockée ailleurs ?).
- Travail local **non commité** (voir `git status`). Vérifier que la prod a bien reçu tous les fichiers du récent travail (les déployer ensemble).

## Vérifications utiles en local
- Lint : `ddev exec php -l <fichier>`
- Pages : curl sur `https://mini-grammaire.ddev.site/...`
- Tests RBAC : créer un utilisateur temporaire (register), modifier son rôle via `ddev mysql`, tester les routes, puis nettoyer les données de test (users/favoris/astuces créés).
- Journal : `ddev mysql -e "SELECT * FROM request_logs ORDER BY id DESC LIMIT 20"` pour voir ce qui est réellement enregistré ; `ddev mysql -e "TRUNCATE request_logs"` pour repartir à zéro (chaque requête de test ajoute une ligne).