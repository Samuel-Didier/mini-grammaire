<?php

namespace App\Controllers;

use App\Models\Progression;
use App\Models\User;

class QuizController
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
        $content = $tpl->render('pages/quiz_menu.html');
        $f3->set('title', 'Quiz & Exercices');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
}