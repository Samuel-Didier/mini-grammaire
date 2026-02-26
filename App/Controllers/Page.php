<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Progression;

class Page {
//    public function generic(\Base $f3)
//    {
//        $tpl = \Template::instance();
//        $content = $tpl->render('pages/generic.html');
//        $f3->set('title', 'Generic');
//        $f3->set('content', $content);
//        echo $tpl->render('layout.html');
//    }



    // Page d'accueil (Dashboard)
    public function home(\Base $f3)
    {
        $tpl = \Template::instance();
        $userModel = new User($f3->get('DB'));
        $userSession = $f3->get('SESSION.user');
        $data = $userModel->findData($userSession);
        $f3->set('etudiant', $data);
        $f3->set('user', $userSession);
        $content = $tpl->render('pages/home.html');
        $f3->set('title', 'Tableau de Bord');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
    /**
     * Affiche la page des conditions d'utilisation.
     */
    public function condition(\Base $f3) {
        $tpl = \Template::instance();

        $content = $tpl->render('pages/conditions.html');

        $f3->set('title', "Conditions d'utilisation");
        $f3->set('content', $content);

        echo $tpl->render('layout.html');
    }

//     Page de connexion
    public function testNiveau(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/test_niveau.html');
        $f3->set('title', 'Test de Niveau');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page d'inscription
    public function register(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/auth_register.html');
        $f3->set('title', 'Inscription');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page mot de passe oublié
    public function forgotPassword(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/auth_forgot.html');
        $f3->set('title', 'Mot de passe oublié');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page de profil (avec vérification de session)
    public function profile(\Base $f3)
    {
        $tpl = \Template::instance();
        // Vérification de l’authentification (à décommenter quand la session sera gérée)

        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }
        $user = $f3->get('SESSION.user');
        $f3->set('user', $user);

        $content = $tpl->render('pages/profile.html');
        $f3->set('title', 'Mon Profil');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page de la mini-grammaire (tableau)
    public function grammaire(\Base $f3)
    {
        $tpl = \Template::instance();
        $userRole = 'etudiant'; // Par défaut

        if ($f3->exists('SESSION.user')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->findByUsername($f3->get('SESSION.user'));
            if ($user) {
                $userRole = $user['role'];
            }
        }
        
        $f3->set('userRole', $userRole);
        $content = $tpl->render('pages/mini_grammaire.html');
        $f3->set('title', 'Mini-Grammaire');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page des astuces
    public function astuces(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/astuces.html');
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page générique (si besoin)
    public function quiz(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/quiz.html');
        $f3->set('title', 'Generic');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page éléments (si besoin)
    public function elements(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/elements.html');
        $f3->set('title', 'Éléments');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    public function profil($f3) {
// Vérification de l’authentification
        if (!$f3->exists('SESSION.user')) {
// Pas connecté → redirection vers login
            $f3->reroute('/login');
            return;
        }
        $tpl = \Template::instance();
        $user = $f3->get('SESSION.user');
        $f3->set('user', $user );
        $content = $tpl->render('pages/profil.html');
        $f3->set('title', 'Generic');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
}