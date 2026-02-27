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
*   **Quiz** : Des tests de niveau et des exercices ciblés (Grammaire, Vocabulaire, Compréhension).
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
│   │   ├── QuizController.php # Gestion des quiz et progression
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
*   **`astuces`** :
    *   `id` (PK), `titre`, `description`.
*   **`favoris`** :
    *   `id` (PK), `user_id` (FK), `astuces_id` (FK).
*   **`progression`** :
    *   `id` (PK), `user_id` (FK), `niveau_global` (ex: 'B1'), `score_test_initial`, `date_test`.

---

## 5. Guide du Développeur <a name="guide-du-développeur"></a>

### Contrôleurs <a name="contrôleurs"></a>

*   **`Auth.php`** : Gère `login`, `register`, `logout` et l'affichage du `profil` (avec calcul des stats).
*   **`Page.php`** : Gère l'affichage des pages "simples" (`home`, `grammaire`, `testNiveau`, `conditions`). Il vérifie aussi les sessions pour rediriger si nécessaire.
*   **`QuizController.php`** : Gère l'affichage du menu des quiz (`index`) et la sauvegarde des résultats via AJAX (`saveLevel`).
*   **`FavorisController.php`** : Gère l'ajout/retrait de favoris (`toggle`) et l'affichage de la liste (`mesFavoris`).

### Modèles <a name="modèles"></a>

Tous les modèles héritent de `\DB\SQL\Mapper` de F3 pour faciliter les opérations CRUD.

*   **`User.php`** : Méthodes `findByUsername`, `register`, `findData`.
*   **`Favori.php`** : Méthodes `isFavori`, `toggle`, `getFavorisByUser` (avec jointure sur `astuces`).
*   **`Progression.php`** : Méthodes `saveTestResult`, `getByUser`.

### Vues <a name="vues"></a>

Le moteur de template de F3 est utilisé.
*   **Syntaxe** : `{{ @variable }}`, `<check if="...">`, `<repeat group="...">`.
*   **Layout** : `ui/layout.html` est le squelette. Il inclut `header.html` et `footer.html`, et injecte le contenu spécifique via `{{ @content | raw }}`.

### Assets (CSS/JS) <a name="assets-cssjs"></a>

*   **CSS** : Découpé par fonctionnalité (`auth.css`, `profile.css`, `quiz.css`, `mini_grammaire.css`, `astuces.css`). `style.css` contient les styles globaux.
*   **JS** :
    *   `quiz.js` : Logique complète du quiz (questions, progression, résultats, AJAX).
    *   `script_search.js` : Logique de recherche et d'édition pour la mini-grammaire.

---

## 6. Routes de l'Application <a name="routes-de-lapplication"></a>

Toutes les routes sont préfixées par `/mini-grammaire`.

| Méthode | URL | Contrôleur | Description |
| :--- | :--- | :--- | :--- |
| GET | `/` | `Page->home` | Tableau de bord |
| GET/POST | `/login` | `Auth->login` | Connexion |
| GET/POST | `/register` | `Auth->register` | Inscription |
| GET | `/logout` | `Auth->logout` | Déconnexion |
| GET | `/profile` | `Auth->profil` | Profil utilisateur |
| GET | `/mini_grammaire` | `Page->grammaire` | Tableau des codes |
| GET | `/astuces` | `AstucesController->getAstuces` | Liste des astuces |
| POST | `/favori/toggle/@id` | `FavorisController->toggle` | Ajouter/Retirer favori |
| GET | `/mes-favoris` | `FavorisController->mesFavoris` | Liste des favoris |
| GET | `/test-niveau` | `Page->testNiveau` | Page du test initial |
| POST | `/quiz/save-level` | `QuizController->saveLevel` | Sauvegarde résultat test |
| GET | `/quiz` | `QuizController->index` | Menu des quiz |

---

*Documentation générée automatiquement le 25/02/2026.*
