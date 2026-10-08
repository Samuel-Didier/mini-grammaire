<?php

namespace App\Controllers;

use App\Models\Quiz;

/**
 * Contrôleur QuizApiController
 * Alimente les pages de jeu des quiz : les questions sont lues en base, ce qui
 * permet de les modifier depuis le tableau de bord sans toucher au JavaScript.
 * Route: GET /api/quiz/questions[@categorie][@niveau]
 */
class QuizApiController extends BaseController
{
    /**
     * Nombre maximal de questions renvoyées pour un quiz.
     */
    private const MAX_QUESTIONS = 50;

    /**
     * Renvoie les questions d'un quiz au format JSON.
     * Paramètres : categorie (slug, ex : grammaire) et niveau (a1, tous...).
     * Réservé aux utilisateurs connectés, comme la page /test-niveau.
     *
     * @param \Base $f3 Instance du framework.
     * @return void Retourne une réponse JSON.
     */
    public function questions(\Base $f3)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$f3->exists('SESSION.user')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Non connecté', 'questions' => []],
                JSON_UNESCAPED_UNICODE);
            exit;
        }

        $categorie = trim((string)$f3->get('GET.categorie'));
        $niveau = mb_strtolower(trim((string)$f3->get('GET.niveau')));

        if ($categorie === '' || !in_array($niveau, Quiz::QUIZ_NIVEAUX, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Quiz non identifié.',
                'questions' => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $quiz = new Quiz($f3->get('DB'));
        $lignes = $quiz->listQuestions($categorie, $niveau, self::MAX_QUESTIONS);

        $questions = [];
        foreach ($lignes as $ligne) {
            $options = [];
            $correct = -1;
            foreach ($ligne['propositions'] as $index => $proposition) {
                $options[] = $proposition['libelle'];
                if ($proposition['est_correcte']) {
                    $correct = $index;
                }
            }
            if ($correct < 0) {
                // Question sans bonne réponse : elle ne peut pas être jouée.
                error_log("Question de quiz {$ligne['id']} sans proposition correcte, ignorée.");
                continue;
            }

            $questions[] = [
                'question' => $ligne['question'],
                'level' => $ligne['difficulte'],
                'options' => $options,
                'correct' => $correct,
                'explication' => $ligne['explication'],
            ];
        }

        echo json_encode([
            'success' => true,
            'categorie' => $categorie,
            'niveau' => $niveau,
            'quiz' => $lignes ? ($lignes[0]['libelle_categorie'] . ' ' . Quiz::niveauLabel($niveau)) : '',
            'message' => $questions
                ? ''
                : 'Ce quiz ne contient aucune question pour le moment.',
            'questions' => $questions,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
