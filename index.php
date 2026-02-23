<?php

// Charger l'autoloader de Composer
require __DIR__ . '/vendor/autoload.php';

// Initialiser Fat-Free Framework
$f3 = \Base::instance();
// Démarrage natif de session PHP avant tout accès F3
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$f3->set('SESSION', $_SESSION);
// -------------------------------------------------------
// 6) Auth
// -------------------------------------------------------
$f3->route(['GET /login','POST /login'], 'App\Controllers\Auth->login');
$f3->route('GET /logout', 'App\Controllers\Auth->logout');
// -------------------------------------------------------
// 7) Zone protégée (profil)
// -------------------------------------------------------
$f3->route('GET /profil', 'App\Controllers\Page->profil');

// Configuration de base
$f3->set('DEBUG', 3);
$f3->set('UI', 'ui/');

// Définition des routes de l'application
// Chaque route pointe vers une méthode du contrôleur App\Controllers\Page

// Route pour la page d'accueil (dashboard)
$f3->route('GET /', 'App\Controllers\Page->home');

// Routes pour l'authentification
$f3->route('GET /login', 'App\Controllers\Page->login');
$f3->route('GET /register', 'App\Controllers\Page->register');
$f3->route('GET /forgot-password', 'App\Controllers\Page->forgotPassword');

// Route pour le profil utilisateur
$f3->route('GET /profile', 'App\Controllers\Page->profile');

// Route pour la mini-grammaire (tableau)
$f3->route('GET /mini_grammaire', 'App\Controllers\Page->grammaire');

// Route pour la page des astuces
$f3->route('GET /astuces', 'App\Controllers\Page->astuces');

// Routes génériques (si besoin)
$f3->route('GET /generic', 'App\Controllers\Page->generic');
$f3->route('GET /elements', 'App\Controllers\Page->elements');


// Gestion de l'erreur 404
$f3->set('ONERROR', function($f3) {
    // On peut aussi déléguer à un contrôleur d'erreur si on veut
    echo \Template::instance()->render('ui/pages/404.html');
});


// Lancer l'application
$f3->run();

?>