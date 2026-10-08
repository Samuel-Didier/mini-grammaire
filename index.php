<?php
/**
 * FRONT CONTROLLER - Point d'entrée de l'application
 * 
 * Ce fichier initialise le framework Fat-Free (F3), configure l'environnement,
 * établit la connexion à la base de données et définit toutes les routes.
 */

// Charger l'autoloader de Composer pour gérer les dépendances
require __DIR__ . '/vendor/autoload.php';

use App\Models\RequestLog;
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

// --- JOURNAL DES REQUETES ---
// Une ligne par requête, écrite en fin d'exécution PHP : les contrôleurs qui
// terminent par exit() ou reroute() sont donc journalisés eux aussi.
// Sans la table request_logs (migration non appliquée), l'écriture est ignorée.
RequestLog::demarrer($db);

// --- CONFIGURATION F3 ---
// Niveau de débogage (3 = maximum), piloté par DEBUG_LEVEL du .env (0 en production)
$f3->set('DEBUG', filter_var($_ENV['DEBUG_LEVEL'] ?? 0, FILTER_VALIDATE_INT) ?: 0);

// Cache F3 : cache de fichiers dans tmp/cache/ (déterministe, sans dépendance d'extension)
$f3->set('CACHE', 'folder=' . __DIR__ . '/tmp/cache/');
$f3->set('UI', 'ui/'); // Dossier des vues

// --- DÉFINITION DES ROUTES ---

// Authentification
$f3->route(['GET /login','POST /login'], 'App\Controllers\Auth->login');
$f3->route('GET /logout', 'App\Controllers\Auth->logout');
$f3->route('GET|POST /register', 'App\Controllers\Auth->register');
$f3->route('GET /forgot-password', 'App\Controllers\Page->forgotPassword');
$f3->route('POST /forgot-password-process', 'App\Controllers\Auth->forgotPasswordProcess');

// Pages principales
$f3->route('GET /', 'App\Controllers\Page->home');
$f3->route('GET /profile', 'App\Controllers\Auth->profil');
$f3->route('GET /profile/edit', 'App\Controllers\Auth->editProfile'); // Route pour l'édition du profil
$f3->route('POST /profile/update', 'App\Controllers\Auth->updateProfile'); // Route pour la mise à jour du profil
$f3->route('POST /profile/avatar', 'App\Controllers\Auth->updateAvatar'); // Choix d'un avatar du catalogue (AJAX)
$f3->route('GET /conditions', 'App\Controllers\Page->condition');

// Fonctionnalités
$f3->route('GET /mini_grammaire', 'App\Controllers\Page->grammaire');
$f3->route('GET /mini_grammaire/@code', 'App\Controllers\Page->grammaireDetail');
//$f3->route('POST /minigrammaire/update-field', 'App\Controllers\MiniGrammaireController->updateCodeField'); // Mise à jour Mini-Grammaire
$f3->route('POST /update-field', 'App\Controllers\MiniGrammaireController->updateCodeField'); // Mise à jour Mini-Grammaire
$f3->route('POST /update-parent-code', 'App\Controllers\MiniGrammaireController->updateParentCode'); // Renommage d'une famille
$f3->route('POST /update-code-entry', 'App\Controllers\MiniGrammaireController->updateCodeEntry'); // Modification d'un sous-code
$f3->route('GET /astuces', 'App\Controllers\AstucesController->getAstuces');
$f3->route('GET /astuces/add', 'App\Controllers\AstucesController->addAstuces'); // Route pour afficher le formulaire d'ajout d'astuce
$f3->route('POST /astuces/save', 'App\Controllers\AstucesController->save'); // Route pour sauvegarder la nouvelle astuce
$f3->route('GET /astuces/edit/@id', 'App\Controllers\AstucesController->editAstuce'); // Route pour afficher le formulaire d'édition d'astuce
$f3->route('POST /astuces/update/@id', 'App\Controllers\AstucesController->update'); // Route pour mettre à jour une astuce
$f3->route('GET /api/astuces', 'App\Controllers\AstucesApiController->getJsonAstuces'); // API Astuces

// Tableaux de bord (une seule URL, la vue dépend du rôle)
// Contenu pédagogique : admin + enseignant. Gestion des comptes : admin uniquement.
$f3->route('GET /dashboard', 'App\Controllers\DashboardController->index');
$f3->route('GET /dashboard/astuces', 'App\Controllers\DashboardController->astuces');
$f3->route('POST /dashboard/astuces/create', 'App\Controllers\DashboardController->createAstuce');
$f3->route('POST /dashboard/astuces/update/@id', 'App\Controllers\DashboardController->updateAstuce');
$f3->route('POST /dashboard/astuces/delete/@id', 'App\Controllers\DashboardController->deleteAstuce');
$f3->route('GET /dashboard/codes', 'App\Controllers\DashboardController->codes');
$f3->route('POST /dashboard/codes/create', 'App\Controllers\DashboardController->createCode');
$f3->route('POST /dashboard/codes/update/@id', 'App\Controllers\DashboardController->updateCode');
$f3->route('POST /dashboard/codes/delete/@id', 'App\Controllers\DashboardController->deleteCode');
$f3->route('GET /dashboard/quiz', 'App\Controllers\DashboardController->quiz');
$f3->route('POST /dashboard/quiz/create', 'App\Controllers\DashboardController->createQuizQuestion');
$f3->route('POST /dashboard/quiz/update/@id', 'App\Controllers\DashboardController->updateQuizQuestion');
$f3->route('POST /dashboard/quiz/delete/@id', 'App\Controllers\DashboardController->deleteQuizQuestion');
$f3->route('GET /dashboard/utilisateurs', 'App\Controllers\DashboardController->utilisateurs');
$f3->route('POST /dashboard/utilisateurs/update/@id', 'App\Controllers\DashboardController->updateUser');
$f3->route('POST /dashboard/utilisateurs/toggle/@id', 'App\Controllers\DashboardController->toggleUser');
// Journal des requêtes : administration seule
$f3->route('GET /dashboard/logs', 'App\Controllers\DashboardController->logs');
$f3->route('POST /dashboard/logs/purge', 'App\Controllers\DashboardController->purgeLogs');

// Favoris
$f3->route('POST /favori/toggle/@id', 'App\Controllers\FavorisController->toggle');
$f3->route('GET /mes-favoris', 'App\Controllers\FavorisController->mesFavoris');

// Quiz et Test de niveau
// Les questions sont lues en base : elles se gèrent depuis /dashboard/quiz.
$f3->route('GET /api/quiz/questions', 'App\Controllers\QuizApiController->questions');
$f3->route('GET /test-niveau', 'App\Controllers\Page->testNiveau');
$f3->route('POST /quiz/save-level', 'App\Controllers\QuizController->saveLevel');
$f3->route('GET /quiz', 'App\Controllers\QuizController->index');
$f3->route('GET /quiz/jouer/@categorie/@niveau', 'App\Controllers\QuizController->jouer');

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