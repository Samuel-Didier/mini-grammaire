<?php

namespace App\Controllers;

use App\Models\Progression;
use App\Models\Quiz;
use App\Models\User;

class QuizController extends BaseController
{
    /**
     * Enregistre le résultat du test de niveau
     * Route: POST /quiz/save-level
     */
    public function saveLevel(\Base $f3)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$f3->exists('SESSION.user')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Non connecté']);
            exit;
        }

        // Récupérer les données JSON envoyées
        $data = json_decode($f3->get('BODY'), true);
        $niveau = $data['level'] ?? 'A1';
        $score = $data['score'] ?? 0;

        // Récupérer l'ID utilisateur
        $userModel = new User($f3->get('DB'));
        $user = $userModel->findByUsername($f3->get('SESSION.user'));

        if ($user) {
            $progressionModel = new Progression($f3->get('DB'));
            $progressionModel->saveTestResult($user['id'], $niveau, $score);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Niveau enregistré']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
        }
        exit;
    }

    /**
     * Affiche la page principale des quiz (Menu)
     * La liste des quiz est lue en base : le menu suit donc exactement les
     * questions saisies dans le tableau de bord.
     * Route: GET /quiz
     */
    public function index(\Base $f3)
    {
        $tpl = \Template::instance();
        // Initialiser la variable pour éviter l'erreur "Undefined variable"
        $f3->set('userLevel', null);

         // Récupérer le niveau de l'utilisateur s'il est connecté
         if ($f3->exists('SESSION.user')) {
             $userModel = new User($f3->get('DB'));
             $user = $userModel->findByUsername($f3->get('SESSION.user'));

             if ($user) {
                 $progressionModel = new Progression($f3->get('DB'));
                 $progression = $progressionModel->getByUser($user['id']);

                 if ($progression) {
                     $f3->set('userLevel', $progression['niveau_global']);
                 }
             }
         }

        $quizModel = new Quiz($f3->get('DB'));
        $f3->set('quizzes', $quizModel->listQuizzesParCategorie());
        $f3->set('connecte', $f3->exists('SESSION.user'));

        $content = $tpl->render('pages/quiz/menu.html');
        $f3->set('title', 'Quiz & Exercices');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    /**
     * Affiche la page de jeu d'un quiz (identifié par sa catégorie et son niveau).
     * Les questions sont ensuite chargées par assets/js/quiz.js depuis
     * /api/quiz/questions, elles ne sont donc plus figées dans le JavaScript.
     * Route: GET /quiz/jouer/@categorie/@niveau
     */
    public function jouer(\Base $f3, array $args = [])
    {
        // Vérification de l'authentification : rediriger si non connecté
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        // Les tokens de la route arrivent dans le second argument (convention F3)
        $categorie = Quiz::slugify((string)($args['categorie'] ?? 'niveau'));
        $niveau = mb_strtolower(trim((string)($args['niveau'] ?? 'tous')));

        $quizModel = new Quiz($f3->get('DB'));
        $quizzes = $quizModel->listQuizzes();

        $trouve = null;
        foreach ($quizzes as $quiz) {
            if ($quiz['categorie'] === $categorie && $quiz['niveau'] === $niveau) {
                $trouve = $quiz;
                break;
            }
        }

        // Quiz inexistant : on renvoie l'utilisateur vers le menu plutôt que d'afficher une page vide
        if ($trouve === null) {
            $f3->set('SESSION.flash', [
                'type' => 'error',
                'message' => 'Ce quiz n\'existe pas ou ne contient aucune question.',
            ]);
            $f3->reroute('/quiz');
            return;
        }

        $tpl = \Template::instance();
        $f3->set('quiz', $trouve);
        $f3->set('title', $trouve['libelle'] . ' ' . $trouve['niveau_libelle']);
        $f3->set('content', $tpl->render('pages/quiz/test_niveau.html'));
        echo $tpl->render('layout.html');
    }
}