<?php

// Charger l'autoloader de Composer
require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Dotenv\Dotenv;

// Chemin du répertoire parent (pour accéder aux fichiers .env)
$env = __DIR__;

// Choix du fichier .env selon l'environnement
if (file_exists($env . '/.env.mini-gram.local')) {
    // Environnement local (développement)
    $dotenv = Dotenv::createImmutable($env, '.env.mini-gram.local');
} else {
    // Environnement de production
    $dotenv = Dotenv::createImmutable($env, '.env.mini-gram');
}

// Charge les variables sans erreur si le fichier n'existe pas
$dotenv->safeLoad();

// Initialiser Fat-Free Framework
$f3 = \Base::instance();
// Démarrage natif de session PHP avant tout accès F3
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$f3->set('SESSION', $_SESSION);

// ═══════════════════════════════════════════════════════════════════════════
// 6) CONNEXION À LA BASE DE DONNÉES MYSQL
// ═══════════════════════════════════════════════════════════════════════════

// Configuration sécurisée de PDO
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lance des exceptions en cas d'erreur
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Retourne des tableaux associatifs
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Utilise les vraies requêtes préparées
];

// Récupération des credentials depuis les variables d'environnement
// Exemple de DSN : mysql:host=localhost;port=3306;dbname=logement;charset=utf8mb4
$dsn  = $_ENV['DB_DSN']  ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

try {
    // Création de la connexion PDO via le wrapper DB\SQL de Fat-Free
    $db = new DB\SQL($dsn, $user, $pass, $pdoOptions);

} catch (Throwable $e) {
    // Gestion des erreurs de connexion
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Erreur de connexion à la base de données. Code: 500');
}

// Rend la connexion DB accessible dans tout F3 via $f3->get('DB')
$f3->set('DB', $db);
// -------------------------------------------------------
// 6) Auth
// -------------------------------------------------------
$f3->route(['GET /login','POST /login'], 'App\Controllers\Auth->login');
$f3->route('GET /logout', 'App\Controllers\Auth->logout');
// -------------------------------------------------------
// 7) Zone protégée (profil)
// -------------------------------------------------------
$f3->route('GET /profile', 'App\Controllers\Auth->profil');

// Configuration de base
$f3->set('DEBUG', 3);
$f3->set('UI', 'ui/');

// Définition des routes de l'application
// Chaque route pointe vers une méthode du contrôleur App\Controllers\Page

// Route pour la page d'accueil (dashboard)
$f3->route('GET /', 'App\Controllers\Page->home');

// Routes pour l'authentification
//$f3->route('GET /login', 'App\Controllers\Page->login');
$f3->route('GET|POST /register', 'App\Controllers\Auth->register');
$f3->route('GET /forgot-password', 'App\Controllers\Page->forgotPassword');
$f3->route('GET /conditions', 'App\Controllers\Page->condition');

// Route pour le profil utilisateur
//$f3->route('GET /profile', 'App\Controllers\Page->profile');

// Route pour la mini-grammaire (tableau)
$f3->route('GET /mini_grammaire', 'App\Controllers\Page->grammaire');

// Route pour la page des astuces
$f3->route('GET /astuces', 'App\Controllers\Page->astuces');

// Routes génériques (si besoin)
$f3->route('GET /generic', 'App\Controllers\Page->generic');
$f3->route('GET /elements', 'App\Controllers\Page->elements');


$f3->set('ONERROR', 'App\Controllers\Error->handle');



// Lancer l'application
$f3->run();

?>