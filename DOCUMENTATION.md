# 📘 Documentation Technique - Ma Mini-Grammaire

Bienvenue dans la documentation technique du projet **Ma Mini-Grammaire**. Cette application web a pour but d'aider les utilisateurs à améliorer leur français via des fiches de grammaire, des astuces, des quiz interactifs et un suivi de progression.

---

## 📑 Table des Matières

1. [Présentation du Projet](#présentation-du-projet)
2. [Architecture Technique](#architecture-technique)
3. [Installation et Configuration](#installation-et-configuration)
4. [Base de Données](#base-de-données)
5. [Guide du Développeur](#guide-du-développeur)
    - [Contrôleurs](#contrôleurs)
    - [Modèles](#modèles)
    - [Vues](#vues)
    - [Assets (CSS/JS)](#assets-cssjs)
6. [Routes de l'Application](#routes-de-lapplication)

---

## 1. Présentation du Projet <a name="présentation-du-projet"></a>

**Ma Mini-Grammaire** est une plateforme éducative offrant :
*   **Mini-Grammaire** : Un tableau interactif des codes de correction (Grammaire, Syntaxe, etc.) avec recherche en temps réel.
*   **Astuces** : Des conseils pratiques pour éviter les erreurs fréquentes, avec un système de favoris.
*   **Quiz** : Des tests de niveau et des exercices ciblés (Grammaire, Vocabulaire, Compréhension). Les questions sont stockées en base et gérables depuis le tableau de bord (`/dashboard/quiz`), avec 2 à 6 propositions par question.
*   **Suivi** : Un tableau de bord personnel avec statistiques et progression.
*   **Authentification** : Inscription, connexion et gestion de profil.

---

## 2. Architecture Technique <a name="architecture-technique"></a>

Le projet utilise le **Fat-Free Framework (F3)**, un micro-framework PHP léger et performant, suivant le modèle **MVC (Modèle-Vue-Contrôleur)**.

### Structure des dossiers

```
mini-grammaire/
├── App/                    # Cœur de l'application (Logique métier)
│   ├── Controllers/        # Contrôleurs (Gèrent les requêtes)
│   │   ├── Auth.php        # Authentification et Profil
│   │   ├── Page.php        # Pages statiques et navigation
│   │   ├── QuizController.php # Menu des quiz, page de jeu, progression
│   │   ├── QuizApiController.php # API JSON des questions de quiz
│   │   ├── FavorisController.php # Gestion des favoris
│   │   └── AstucesController.php # Affichage des astuces
│   └── Models/             # Modèles (Accès aux données)
│       ├── User.php        # Gestion des utilisateurs
│       ├── Favori.php      # Gestion des favoris
│       ├── Astuces.php     # Gestion des astuces
│       └── Progression.php # Gestion des résultats de quiz
├── ui/                     # Vues (Templates HTML)
│   ├── layout.html         # Template principal (Header + Footer)
│   ├── pages/              # Pages spécifiques (Dashboard, Login, Quiz...)
│   └── partials/           # Fragments réutilisables (Header, Footer)
├── assets/                 # Ressources statiques
│   ├── css/                # Feuilles de style (auth.css, quiz.css...)
│   └── js/                 # Scripts JavaScript (quiz.js, script_search.js...)
├── vendor/                 # Dépendances Composer (F3, Dotenv)
├── index.php               # Front Controller (Point d'entrée unique)
├── .htaccess               # Configuration Apache (Réécriture d'URL)
├── .env.mini-gram.local    # Configuration locale (DB, Debug)
└── composer.json           # Définition des dépendances
```

---

## 3. Installation et Configuration <a name="installation-et-configuration"></a>

### Prérequis
*   PHP 7.4 ou supérieur
*   MySQL / MariaDB
*   Composer
*   Serveur Web (Apache avec mod_rewrite activé)

### Étapes
1.  **Cloner le dépôt** :
    ```bash
    git clone https://github.com/votre-repo/mini-grammaire.git
    ```
2.  **Installer les dépendances** :
    ```bash
    composer install
    ```
3.  **Configurer la base de données** :
    *   Créez une base de données nommée `mini_gram`.
    *   Importez le fichier `mini_gram.sql` (structure et données initiales).
    *   Importez `migration_progression.sql` (table progression).
4.  **Configurer l'environnement** :
    *   Vérifiez le fichier `.env.mini-gram.local`.
    *   Assurez-vous que les identifiants DB (`DB_USER`, `DB_PASS`) sont corrects.

---

## 4. Base de Données <a name="base-de-données"></a>

### Tables Principales

*   **`users`** :
    *   `id` (PK), `username`, `email`, `password` (hashé), `role` ('etudiant', 'enseignant', 'admin').
    *   `deleted_at` : suppression logique (compte désactivé, jamais effacé).
    *   `remember_token` / `remember_expires` : connexion persistante. Seule l'empreinte SHA-256 du jeton est stockée ; le jeton en clair ne vit que dans le cookie `mg_souvenir` (7 jours). Colonnes créées par `migrations/2026_09_29_add_remember_me.sql`.
    *   `image` : avatar choisi dans le catalogue. Ne contient que l'**identifiant** de l'avatar (ex. `chat.svg`), jamais un chemin ou une URL ; vide = initiales affichées. Toute entrée hors catalogue est rejetée côté serveur.
*   **Avatars** : catalogue de 24 émojis défini dans `User::catalog()` (`identifiant => émoji`). Choix possible depuis la popup de `/profile` (AJAX → `POST /profile/avatar`, CSRF + liste blanche) ou via la grille de radios de `/profile/edit` (envoyé avec `updateProfile`). `User::normalizeAvatar()` n'accepte qu'un identifiant présent dans le catalogue ; `''` retire l'avatar. L'émoji est rendu en texte HTML et non dans une image SVG : chargé par `<img>`, un SVG ne peut pas utiliser la police émoji du système, et le glyphe s'affiche alors en monochrome ou vide sur les navigateurs de bureau. Ajouter un avatar = une ligne dans `User::catalog()`.
*   **`astuces`** :
    *   `id` (PK), `titre`, `description`.
*   **`favoris`** :
    *   `id` (PK), `user_id` (FK), `astuces_id` (FK).
*   **`progression`** :
    *   `id` (PK), `user_id` (FK), `niveau_global` (ex: 'B1'), `score_test_initial`, `date_test`.
*   **`request_logs`** : journal des requêtes, une ligne par requête PHP.
    *   `id` (PK), `user_id` (sans FK : la ligne survit à la suppression du compte), `username` et `role` (instantanés), `methode`, `chemin`, `uri`, `statut`, `duree_ms`, `ip`, `user_agent`, `referer`, `cree_le`.
    *   Créée par `migrations/2026_09_29_add_request_logs.sql`. Aucun corps de requête n'est enregistré. Purge automatique au-delà de 30 jours, plus une purge manuelle depuis `/dashboard/logs`.

---

## 5. Guide du Développeur <a name="guide-du-développeur"></a>

### Contrôleurs <a name="contrôleurs"></a>

*   **`Auth.php`** : Gère `login`, `register`, `logout` et l'affichage du `profil` (avec calcul des stats). `login` pose le cookie de connexion persistante si la case « Se souvenir de moi » est cochée, `logout` le révoque.
*   **`BaseController.php`** : Rôle disponible sur toutes les pages, jeton CSRF, message flash. Son `beforeroute` rétablit la session depuis le cookie `mg_souvenir` lorsqu'aucune session n'est active (reconnexion silencieuse).
*   **`Page.php`** : Gère l'affichage des pages "simples" (`home`, `grammaire`, `testNiveau`, `conditions`). Il vérifie aussi les sessions pour rediriger si nécessaire.
*   **`QuizController.php`** : Gère le menu des quiz (`index`, généré depuis la base), la page de jeu (`jouer`) et la sauvegarde des résultats du test de niveau via AJAX (`saveLevel`).
*   **`QuizApiController.php`** : Fournit les questions d'un quiz au format JSON (`GET /api/quiz/questions?categorie=…&niveau=…`).
*   **`FavorisController.php`** : Gère l'ajout/retrait de favoris (`toggle`) et l'affichage de la liste (`mesFavoris`).

### Modèles <a name="modèles"></a>

Tous les modèles héritent de `\DB\SQL\Mapper` de F3 pour faciliter les opérations CRUD.

*   **`User.php`** : Méthodes `findByUsername`, `register`, `findData`, plus la connexion persistante (`creerSouvenir`, `validerSouvenir`, `oublierSouvenir`).
*   **`Favori.php`** : Méthodes `isFavori`, `toggle`, `getFavorisByUser` (avec jointure sur `astuces`).
*   **`Progression.php`** : Méthodes `saveTestResult`, `getByUser`.
*   **`RequestLog.php`** : Journal des requêtes. `demarrer()` enregistre une fonction de fin de script (seul moyen de couvrir les contrôleurs qui se terminent par `exit()` ou `reroute()`), `setUtilisateur()` rattache la requête au compte courant, puis `statistiques()`, `liste()` (filtres + pagination), `listeUtilisateurs()` et `purger()` servent la page d'administration. `tableExiste()` permet à l'application de fonctionner même si la migration n'est pas appliquée.

### Vues <a name="vues"></a>

Le moteur de template de F3 est utilisé.
*   **Syntaxe** : `{{ @variable }}`, `<check if="...">`, `<repeat group="...">`.
*   **Layout** : `ui/layout.html` est le squelette. Il inclut `header.html` et `footer.html`, et injecte le contenu spécifique via `{{ @content | raw }}`.

### Assets (CSS/JS) <a name="assets-cssjs"></a>

*   **CSS** : Découpé par fonctionnalité (`auth.css`, `profile.css`, `quiz.css`, `mini_grammaire.css`, `astuces.css`). `style.css` contient les styles globaux.
*   **JS** :
    *   `quiz.js` : Logique du quiz côté client : appel de l'API des questions, 2 à 6 propositions par question, progression, résultats, récapitulatif et enregistrement du test de niveau.
    *   `script_search.js` : Logique de recherche et d'édition pour la mini-grammaire.

---

## 6. Routes de l'Application <a name="routes-de-lapplication"></a>

Toutes les routes sont préfixées par `/mini-grammaire`.

| Méthode | URL | Contrôleur | Description |
| :--- | :--- | :--- | :--- |
| GET | `/` | `Page->home` | Tableau de bord |
| GET/POST | `/login` | `Auth->login` | Connexion (case « Se souvenir de moi » → reconnexion automatique pendant 7 jours) |
| GET/POST | `/register` | `Auth->register` | Inscription |
| GET | `/logout` | `Auth->logout` | Déconnexion |
| GET | `/profile` | `Auth->profil` | Profil utilisateur (choix d'un avatar du catalogue via la popup) |
| POST | `/profile/avatar` | `Auth->updateAvatar` | Enregistre l'avatar choisi (AJAX, liste blanche du catalogue, CSRF requis) |
| GET | `/mini_grammaire` | `Page->grammaire` | Tableau des codes |
| GET | `/astuces` | `AstucesController->getAstuces` | Liste des astuces |
| POST | `/favori/toggle/@id` | `FavorisController->toggle` | Ajouter/Retirer favori |
| GET | `/mes-favoris` | `FavorisController->mesFavoris` | Liste des favoris |
| GET | `/test-niveau` | `Page->testNiveau` | Redirige vers le test de niveau |
| POST | `/quiz/save-level` | `QuizController->saveLevel` | Sauvegarde résultat test |
| GET | `/quiz` | `QuizController->index` | Menu des quiz (généré depuis la base) |
| GET | `/quiz/jouer/@categorie/@niveau` | `QuizController->jouer` | Page de jeu d'un quiz (connecté requis) |
| GET | `/api/quiz/questions` | `QuizApiController->questions` | Questions d'un quiz au format JSON |
| GET | `/dashboard/quiz` | `DashboardController->quiz` | Gestion des questions de quiz (admin/enseignant) |
| GET | `/dashboard/logs` | `DashboardController->logs` | Journal des requêtes (admin) |
| POST | `/dashboard/logs/purge` | `DashboardController->purgeLogs` | Purge du journal (admin + CSRF) |

---

*Documentation générée automatiquement le 25/02/2026.*
