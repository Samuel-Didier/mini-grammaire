<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Progression;
use App\Models\MiniGrammaire;

class Page {

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
        // Vérification de l'authentification : rediriger si non connecté
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }
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

    // Page de la mini-grammaire (liste des codes par catégorie)
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
        $miniGrammaireModel = new MiniGrammaire($f3->get('DB'));
        $codesByCategory = $miniGrammaireModel->getParentCodesGroupedByCategory();

        // Mappage des catégories vers les classes CSS existantes (g/p/s/u/v)
        $letterMap = [
            'Orthographe grammaticale' => 'g',
            'Ponctuation et typographie' => 'p',
            'Syntaxe' => 's',
            "Orthographe d'usage" => 'u',
            'Vocabulaire' => 'v',
        ];

        $categories = [];
        foreach ($codesByCategory as $name => $codes) {
            $categories[] = [
                'name'   => $name,
                'letter' => $letterMap[$name] ?? strtolower(substr($name, 0, 1)),
                'codes'  => $codes,
            ];
        }
        $f3->set('categories', $categories);
        $f3->set('allCategoryNames', array_keys($letterMap));
        $f3->set('userRole', $userRole);
        $f3->set('canEdit', $userRole !== 'etudiant' && $userRole !== 'invite');
        $content = $tpl->render('pages/mini_grammaire.html');
        $f3->set('title', 'Mini-Grammaire');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    // Page de détail d'un code de la mini-grammaire (sous-codes, règles, exemples)
    public function grammaireDetail(\Base $f3, array $args)
    {
        $tpl = \Template::instance();
        $parent = strtoupper($args['code'] ?? '');
        $parent = preg_replace('/[^A-Z0-9]/', '', $parent);

        $miniGrammaireModel = new MiniGrammaire($f3->get('DB'));
        $codeEntries = $miniGrammaireModel->getByParentCode($parent);
        if (empty($codeEntries)) {
            $f3->reroute('/mini_grammaire');
            return;
        }
        $categoryName = $codeEntries[0]['category'];

        $userRole = 'etudiant';
        if ($f3->exists('SESSION.user')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->findByUsername($f3->get('SESSION.user'));
            if ($user) {
                $userRole = $user['role'];
            }
        }

        $letterMap = [
            'Orthographe grammaticale' => 'g',
            'Ponctuation et typographie' => 'p',
            'Syntaxe' => 's',
            "Orthographe d'usage" => 'u',
            'Vocabulaire' => 'v',
        ];

        $f3->set('codeEntries', $codeEntries);
        $f3->set('parent', $parent);
        $f3->set('categoryName', $categoryName);
        $f3->set('categoryLetter', $letterMap[$categoryName] ?? strtolower(substr($categoryName, 0, 1)));
        $f3->set('allCategoryNames', array_keys($letterMap));
        $f3->set('canEdit', $userRole !== 'etudiant' && $userRole !== 'invite');
        $content = $tpl->render('pages/mini_grammaire_detail.html');
        $f3->set('title', 'Code ' . $parent . ' — Mini-Grammaire');
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