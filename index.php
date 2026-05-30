<?php
/**
 * FRONT CONTROLLER - Point d'entrée de l'application
 * 
 * Ce fichier initialise le framework Fat-Free (F3), configure l'environnement,
 * établit la connexion à la base de données et définit toutes les routes.
 */

// Charger l'autoloader de Composer pour gérer les dépendances
require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Dotenv\Dotenv;

// --- CONFIGURATION DE L'ENVIRONNEMENT ---
$env = __DIR__;

// Chargement du fichier .env approprié (local ou production)
if (file_exists($env . '/.env.mini-gram.local')) {
    $dotenv = Dotenv::createImmutable($env, '.env.mini-gram.local');
} else {
    $dotenv = Dotenv::createImmutable($env, '.env.mini-gram');
}
$dotenv->safeLoad();

// Initialisation de l'instance F3
$f3 = \Base::instance();

// --- GESTION DE LA SESSION ---
// Démarrage natif de la session PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Synchronisation de la session PHP avec la variable SESSION de F3
$f3->set('SESSION', $_SESSION);

// --- CONNEXION BASE DE DONNÉES ---
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$dsn  = $_ENV['DB_DSN']  ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

try {
    $db = new DB\SQL($dsn, $user, $pass, $pdoOptions);
} catch (Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Erreur de connexion à la base de données. Code: 500');
}

// Enregistrement de la connexion DB dans F3 pour y accéder partout
$f3->set('DB', $db);

// --- CONFIGURATION F3 ---
$f3->set('DEBUG', 3); // Niveau de débogage (3 = maximum)
$f3->set('UI', 'ui/'); // Dossier des vues

// --- DÉFINITION DES ROUTES ---

// Authentification
$f3->route(['GET /login','POST /login'], 'App\Controllers\Auth->login');
$f3->route('GET /logout', 'App\Controllers\Auth->logout');
$f3->route('GET|POST /register', 'App\Controllers\Auth->register');
$f3->route('GET /forgot-password', 'App\Controllers\Page->forgotPassword');

// Pages principales
$f3->route('GET /', 'App\Controllers\Page->home');
$f3->route('GET /profile', 'App\Controllers\Auth->profil');
$f3->route('GET /profile/edit', 'App\Controllers\Auth->editProfile'); // Route pour l'édition du profil
$f3->route('POST /profile/update', 'App\Controllers\Auth->updateProfile'); // Route pour la mise à jour du profil
$f3->route('GET /conditions', 'App\Controllers\Page->condition');

// Fonctionnalités
$f3->route('GET /mini_grammaire', 'App\Controllers\Page->grammaire');
//$f3->route('POST /minigrammaire/update-field', 'App\Controllers\MiniGrammaireController->updateCodeField'); // Mise à jour Mini-Grammaire
$f3->route('POST /update-field', 'App\Controllers\MiniGrammaireController->updateCodeField'); // Mise à jour Mini-Grammaire
$f3->route('GET /astuces', 'App\Controllers\AstucesController->getAstuces');
$f3->route('GET /astuces/add', 'App\Controllers\AstucesController->addAstuces'); // Route pour afficher le formulaire d'ajout d'astuce
$f3->route('POST /astuces/save', 'App\Controllers\AstucesController->save'); // Route pour sauvegarder la nouvelle astuce
$f3->route('GET /api/astuces', 'App\Controllers\AstucesApiController->getJsonAstuces'); // API Astuces

// Favoris
$f3->route('POST /favori/toggle/@id', 'App\Controllers\FavorisController->toggle');
$f3->route('GET /mes-favoris', 'App\Controllers\FavorisController->mesFavoris');

// Quiz et Test de niveau
$f3->route('GET /test-niveau', 'App\Controllers\Page->testNiveau');
$f3->route('POST /quiz/save-level', 'App\Controllers\QuizController->saveLevel');
$f3->route('GET /quiz', 'App\Controllers\QuizController->index');

// Pages génériques
$f3->route('GET /generic', 'App\Controllers\Page->generic');
$f3->route('GET /elements', 'App\Controllers\Page->elements');

// Documentation
$f3->route('GET /docs', function($f3) {
    echo \Template::instance()->render('pages/documentation.html');
});

// Gestion des erreurs (404, 500, etc.)
$f3->set('ONERROR', 'App\Controllers\Error->handle');

// Lancer l'application
$f3->run();

?>