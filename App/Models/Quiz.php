<?php

namespace App\Models;

/**
 * Modèle Quiz
 * Gère les questions de quiz stockées dans 'quiz_questions' et leurs propositions
 * dans 'quiz_propositions'. Les questions sont donc modifiables depuis le tableau
 * de bord : plus rien n'est figé dans assets/js/quiz.js.
 *
 * Un quiz est identifié par le couple (categorie, niveau) :
 *   - categorie : slug technique dérivé du libellé saisi (ex : comprehension-orale)
 *   - niveau    : niveau CECRL du quiz, ou 'tous' pour un quiz qui mélange les niveaux
 *                 (c'est le cas du test de niveau).
 * La colonne `difficulte` porte le niveau CECRL de la question elle-même : c'est elle
 * que l'algorithme de détermination du niveau exploite.
 */
class Quiz extends \DB\SQL\Mapper
{
    /**
     * Niveaux CECRL acceptés, du plus simple au plus complexe.
     */
    public const LEVELS = ['a1', 'a2', 'b1', 'b2', 'c1', 'c2'];

    /**
     * Niveaux acceptés pour un quiz : les niveaux CECRL plus 'tous' (quiz mixte,
     * utilisé par le test de niveau).
     */
    public const QUIZ_NIVEAUX = ['tous', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2'];

    /**
     * Nombre minimal et maximal de propositions par question (choisi à la création).
     */
    public const MIN_PROPOSITIONS = 2;
    public const MAX_PROPOSITIONS = 6;

    /**
     * Longueur maximale d'un libellé de proposition.
     */
    private const PROPOSITION_MAX = 255;

    /**
     * Durée de vie du cache des quiz lus par les visiteurs.
     */
    private const CACHE_TTL = 300;

    /**
     * Constructeur
     * Initialise la connexion et mappe la table 'quiz_questions'.
     *
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'quiz_questions');
    }

    /**
     * Libellé lisible d'un niveau de quiz ('tous' -> 'Tous niveaux').
     */
    public static function niveauLabel(string $niveau): string
    {
        if ($niveau === 'tous') {
            return 'Tous niveaux';
        }
        return strtoupper($niveau);
    }

    /**
     * Libellé d'un nombre de questions avec le pluriel (3 questions).
     */
    public static function questionsLabel(int $nombre): string
    {
        return $nombre . ' question' . ($nombre > 1 ? 's' : '');
    }

    /**
     * Libellé du compteur du tableau de bord : « 1 question affichée ».
     *
     * @param int $nombre Nombre de questions
     * @return string Libellé accordé
     */
    public static function questionsAfficheesLabel(int $nombre): string
    {
        return $nombre . ' question' . ($nombre > 1 ? 's affichées' : ' affichée');
    }

    /**
     * Transforme un libellé de catégorie en slug technique utilisable dans une URL
     * et dans un filtre SQL (minuscules, sans accent, tirets).
     */
    public static function slugify(string $libelle): string
    {
        $slug = mb_strtolower(trim($libelle), 'UTF-8');
        // Les accents sont précomposés (é = U+00E9) : il faut d'abord décomposer le
        // texte (NFD) avant de supprimer les diacritiques, sinon 'Compréhension'
        // deviendrait 'compr-hension'.
        if (class_exists('Normalizer')) {
            $slug = \Normalizer::normalize($slug, \Normalizer::FORM_D) ?: $slug;
        }
        $slug = preg_replace('/\p{Mn}/u', '', $slug);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);

        return trim((string)$slug, '-');
    }

    /**
     * Libellé lisible d'un slug de catégorie.
     */
    public static function libelleFromSlug(string $slug): string
    {
        $parts = array_filter(explode('-', $slug), 'strlen');
        if (!$parts) {
            return $slug;
        }
        $parts = array_map(static function ($mot) {
            return mb_convert_case($mot, MB_CASE_TITLE, 'UTF-8');
        }, $parts);

        return implode(' ', $parts);
    }

    /**
     * Lit une entrée du cache F3 (retourne null si absente).
     */
    private function cacheGet(string $key): ?array
    {
        $value = \Cache::instance()->get($key);
        return $value === false ? null : $value;
    }

    /**
     * Vide le cache des quiz (à appeler après toute écriture).
     */
    public function cacheClear(): void
    {
        \Cache::instance()->clear('quiz.categories');
        \Cache::instance()->clear('quiz.menu');
    }

    /**
     * Liste des catégories présentes en base avec le nombre de questions.
     * Utilisée par le menu des quiz et par les filtres du tableau de bord.
     *
     * @return array [['categorie' => 'grammaire', 'libelle' => 'Grammaire', 'questions' => 4], ...]
     */
    public function listCategories(): array
    {
        $cache = $this->cacheGet('quiz.categories');
        if ($cache !== null) {
            return $cache;
        }

        $rows = $this->db->exec(
            'SELECT categorie, MIN(libelle_categorie) AS libelle, COUNT(*) AS questions
             FROM quiz_questions GROUP BY categorie ORDER BY libelle ASC'
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'categorie' => $row['categorie'],
                'libelle' => $row['libelle'] ?: self::libelleFromSlug($row['categorie']),
                'questions' => (int)$row['questions'],
            ];
        }

        \Cache::instance()->set('quiz.categories', $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Liste des quiz disponibles avec leur nombre de questions.
     * Un quiz = un couple (categorie, niveau).
     *
     * @return array [['categorie' => 'grammaire', 'libelle' => 'Grammaire', 'niveau' => 'a1',
     *                 'niveau_libelle' => 'A1', 'titre' => 'A1', 'questions' => 1,
     *                 'questions_libelle' => '1 question', 'test' => false], ...]
     */
    public function listQuizzes(): array
    {
        $cache = $this->cacheGet('quiz.menu');
        if ($cache !== null) {
            return $cache;
        }

        $rows = $this->db->exec(
            "SELECT categorie, MIN(libelle_categorie) AS libelle, niveau, COUNT(*) AS questions
             FROM quiz_questions
             GROUP BY categorie, niveau
             ORDER BY libelle ASC, FIELD(niveau, 'tous', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2')"
        );

        $result = [];
        foreach ($rows as $row) {
            $libelle = $row['libelle'] ?: self::libelleFromSlug($row['categorie']);
            $questions = (int)$row['questions'];
            $estTest = $row['niveau'] === 'tous';

            $result[] = [
                'categorie' => $row['categorie'],
                'libelle' => $libelle,
                'niveau' => $row['niveau'],
                'niveau_libelle' => self::niveauLabel($row['niveau']),
                // Titre du bouton : le test de niveau s'affiche par son nom, les
                // autres quiz par leur niveau.
                'titre' => $estTest ? $libelle : self::niveauLabel($row['niveau']),
                'questions' => $questions,
                'questions_libelle' => self::questionsLabel($questions),
                'test' => $estTest,
            ];
        }

        \Cache::instance()->set('quiz.menu', $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Icône du menu pour une catégorie (le test de niveau est mis en avant).
     */
    public static function categorieIcon(string $categorie): string
    {
        $icones = [
            'niveau' => '🎯',
            'grammaire' => '📖',
            'vocabulaire' => '💬',
            'comprehension' => '👂',
            'comprehension-orale' => '🎧',
            'expression' => '✍️',
        ];

        return $icones[$categorie] ?? '📝';
    }

    /**
     * Menu des quiz : les quiz regroupés par catégorie, le test de niveau en tête.
     *
     * @return array [['categorie' => 'niveau', 'libelle' => 'Test de niveau', 'icon' => '🎯',
     *                 'questions' => 10, 'quiz' => [ ... ]], ...]
     */
    public function listQuizzesParCategorie(): array
    {
        $groupes = [];
        foreach ($this->listQuizzes() as $quiz) {
            $cle = $quiz['categorie'];
            if (!isset($groupes[$cle])) {
                $groupes[$cle] = [
                    'categorie' => $cle,
                    'libelle' => $quiz['libelle'],
                'icon' => self::categorieIcon($cle),
                'test' => $quiz['test'],
                'questions' => 0,
                'questions_libelle' => '',
                'quiz' => [],
            ];
            }
            $groupes[$cle]['questions'] += $quiz['questions'];
            $groupes[$cle]['questions_libelle'] = self::questionsLabel($groupes[$cle]['questions']);
            $groupes[$cle]['quiz'][] = $quiz;
        }

        $groupes = array_values($groupes);
        usort($groupes, static function ($a, $b) {
            if ($a['test'] !== $b['test']) {
                return $a['test'] ? -1 : 1;
            }
            return strcmp($a['libelle'], $b['libelle']);
        });

        return $groupes;
    }

    /**
     * Charge les propositions d'une liste de questions en une seule requête.
     *
     * @param array $questions Questions déjà converties par cast()
     * @return array Les mêmes questions, avec la clé 'propositions'
     */
    private function attachPropositions(array $questions): array
    {
        if (!$questions) {
            return [];
        }

        $ids = array_map(static function ($q) {
            return (int)$q['id'];
        }, $questions);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->exec(
            "SELECT question_id, libelle, est_correcte
             FROM quiz_propositions
             WHERE question_id IN ($placeholders)
             ORDER BY question_id ASC, ordre ASC",
            $ids
        );

        $parQuestion = [];
        foreach ($rows as $row) {
            $parQuestion[(int)$row['question_id']][] = [
                'libelle' => (string)$row['libelle'],
                'est_correcte' => (int)$row['est_correcte'] === 1,
            ];
        }

        foreach ($questions as &$question) {
            $question['propositions'] = $parQuestion[(int)$question['id']] ?? [];
        }
        unset($question);

        return $questions;
    }

    /**
     * Questions d'un quiz, propositions comprises.
     * Si aucune catégorie ou aucun niveau n'est fourni, toutes les questions sont
     * renvoyées (utile au tableau de bord).
     *
     * @param string|null $categorie Slug de catégorie (ex : 'grammaire')
     * @param string|null $niveau Niveau du quiz (ex : 'a1', 'tous')
     * @param int $limit Nombre maximal de questions renvoyées
     * @param string $recherche Filtre texte sur l'énoncé (tableau de bord)
     * @return array
     */
    public function listQuestions(?string $categorie = null, ?string $niveau = null, int $limit = 200, string $recherche = ''): array
    {
        $conditions = [];
        if ($categorie !== null && $categorie !== '') {
            $conditions = ['categorie = ?', $categorie];
        }
        if ($niveau !== null && $niveau !== '') {
            $conditions = $conditions
                ? [$conditions[0] . ' AND niveau = ?', $categorie, $niveau]
                : ['niveau = ?', $niveau];
        }
        $recherche = trim($recherche);
        if ($recherche !== '') {
            $like = '%' . $recherche . '%';
            $conditions = $conditions
                ? [$conditions[0] . ' AND (question LIKE ? OR explication LIKE ?)', ...$conditions[1], $like, $like]
                : ['(question LIKE ? OR explication LIKE ?)', $like, $like];
        }

        $rows = $this->find($conditions ?: null, [
            // Trié par catégorie puis par niveau : le tableau de bord regrouppe
            // les questions par libellé de catégorie, l'ordre id ASC mélangerait
            // les questions d'un même quiz d'un bout à l'autre de la liste.
            'order' => 'categorie ASC, niveau ASC, id ASC',
            'limit' => max(1, $limit),
        ]);

        $questions = [];
        foreach ($rows as $row) {
            $data = $row->cast();
            $data['explication'] = (string)($data['explication'] ?? '');
            $questions[] = $data;
        }

        return $this->attachPropositions($questions);
    }

    /**
     * Récupère une question et ses propositions.
     *
     * @return array|null
     */
    public function findQuestion(int $id): ?array
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return null;
        }

        $data = $this->cast();
        $data['explication'] = (string)($data['explication'] ?? '');
        $questions = $this->attachPropositions([$data]);

        return $questions[0] ?? null;
    }

    /**
     * Compteur de questions, utilisé par le tableau de bord.
     */
    public function countAll(): int
    {
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM quiz_questions');

        return (int)($rows[0]['c'] ?? 0);
    }

    /**
     * Valide les données d'une question et renvoie les valeurs normalisées.
     *
     * @param array $data [libelle_categorie, categorie, niveau, difficulte, question,
     *                    explication, propositions[], correct]
     * @return array ['errors' => [...]] ou ['values' => [...]]
     */
    private function validate(array $data): array
    {
        $errors = [];

        $libelle = trim((string)($data['libelle_categorie'] ?? ''));
        $categorie = trim((string)($data['categorie'] ?? ''));
        if ($categorie === '') {
            $categorie = self::slugify($libelle);
        }
        if ($libelle === '') {
            $libelle = self::libelleFromSlug($categorie);
        }

        if ($libelle === '' || $categorie === '') {
            $errors[] = 'La catégorie est requise.';
        } elseif (mb_strlen($libelle) > 100 || strlen($categorie) > 60) {
            $errors[] = 'La catégorie ne doit pas dépasser 100 caractères.';
        }

        $niveau = mb_strtolower(trim((string)($data['niveau'] ?? 'tous')));
        if (!in_array($niveau, self::QUIZ_NIVEAUX, true)) {
            $errors[] = 'Niveau de quiz invalide (valeurs autorisées : tous, ' . implode(', ', self::LEVELS) . ').';
        }

        $difficulte = mb_strtolower(trim((string)($data['difficulte'] ?? '')));
        if (!in_array($difficulte, self::LEVELS, true)) {
            $errors[] = 'Niveau de la question invalide (valeurs autorisées : ' . implode(', ', self::LEVELS) . ').';
        }

        $question = trim((string)($data['question'] ?? ''));
        if ($question === '') {
            $errors[] = 'L\'énoncé de la question est requis.';
        }

        $explication = trim((string)($data['explication'] ?? ''));
        if (mb_strlen($explication) > 500) {
            $errors[] = 'L\'explication ne doit pas dépasser 500 caractères.';
        }

        // Les propositions vides sont retirees (l'enseignant peut choisir 6
        // propositions et n'en saisir que 3), mais l'index de la bonne reponse
        // doit suivre ce deplacement pour ne pas designer la mauvaise reponse.
        $brutes = array_values(array_map(static function ($p) {
            return trim((string)$p);
        }, $data['propositions'] ?? []));

        $correct = (int)($data['correct'] ?? -1);
        if ($correct < 0 || $correct >= count($brutes)) {
            $errors[] = 'Indiquez la bonne réponse parmi les propositions.';
        } elseif ($brutes[$correct] === '') {
            $errors[] = 'La bonne réponse ne peut pas être une proposition vide.';
        }

        $propositions = array_values(array_filter($brutes, 'strlen'));

        $nbPropositions = count($propositions);
        if ($nbPropositions < self::MIN_PROPOSITIONS || $nbPropositions > self::MAX_PROPOSITIONS) {
            $errors[] = 'Une question doit avoir entre ' . self::MIN_PROPOSITIONS . ' et '
                . self::MAX_PROPOSITIONS . ' propositions.';
        }
        foreach ($propositions as $proposition) {
            if (mb_strlen($proposition) > self::PROPOSITION_MAX) {
                $errors[] = 'Une proposition ne doit pas dépasser ' . self::PROPOSITION_MAX . ' caractères.';
                break;
            }
        }

        $correct = count(array_filter(array_slice($brutes, 0, max(0, $correct)), 'strlen'));

        return $errors ? ['errors' => $errors] : ['values' => [
            'libelle_categorie' => $libelle,
            'categorie' => $categorie,
            'niveau' => $niveau,
            'difficulte' => $difficulte,
            'question' => $question,
            'explication' => $explication,
            'propositions' => $propositions,
            'correct' => $correct,
        ]];
    }

    /**
     * Crée une question avec ses propositions.
     *
     * @return array ['success' => true, 'id' => int] ou ['errors' => [...]]
     */
    public function createQuestion(array $data): array
    {
        $validated = $this->validate($data);
        if (isset($validated['errors'])) {
            return $validated;
        }
        $values = $validated['values'];

        try {
            $this->db->begin();
            $this->reset();
            $this->libelle_categorie = $values['libelle_categorie'];
            $this->categorie = $values['categorie'];
            $this->niveau = $values['niveau'];
            $this->difficulte = $values['difficulte'];
            $this->question = $values['question'];
            $this->explication = $values['explication'] !== '' ? $values['explication'] : null;
            $this->save();
            $id = (int)$this->id;

            $this->replacePropositions($id, $values['propositions'], $values['correct']);
            $this->db->commit();
            $this->cacheClear();

            return ['success' => true, 'id' => $id];
        } catch (\PDOException $e) {
            $this->db->rollback();
            error_log('Erreur de création d\'une question de quiz: ' . $e->getMessage());
            return ['errors' => ['Erreur lors de l\'enregistrement en base de données.']];
        }
    }

    /**
     * Met à jour une question et ses propositions (transaction : soit tout passe,
     * soit rien n'est modifié).
     *
     * @return array ['success' => true, 'id' => int] ou ['errors' => [...]]
     */
    public function updateQuestion(int $id, array $data): array
    {
        $validated = $this->validate($data);
        if (isset($validated['errors'])) {
            return $validated;
        }
        $values = $validated['values'];

        try {
            $this->db->begin();
            $this->load(['id = ?', $id]);
            if ($this->dry()) {
                $this->db->rollback();
                return ['errors' => ['Question introuvable.']];
            }

            $this->libelle_categorie = $values['libelle_categorie'];
            $this->categorie = $values['categorie'];
            $this->niveau = $values['niveau'];
            $this->difficulte = $values['difficulte'];
            $this->question = $values['question'];
            $this->explication = $values['explication'] !== '' ? $values['explication'] : null;
            $this->save();

            $this->replacePropositions($id, $values['propositions'], $values['correct']);
            $this->db->commit();
            $this->cacheClear();

            return ['success' => true, 'id' => $id];
        } catch (\PDOException $e) {
            $this->db->rollback();
            error_log("Erreur de mise à jour de la question de quiz {$id}: " . $e->getMessage());
            return ['errors' => ['Erreur lors de l\'enregistrement en base de données.']];
        }
    }

    /**
     * Supprime définitivement une question et ses propositions.
     *
     * @return array ['success' => true] ou ['errors' => [...]]
     */
    public function deleteQuestion(int $id): array
    {
        try {
            $this->db->begin();
            $this->load(['id = ?', $id]);
            if ($this->dry()) {
                $this->db->rollback();
                return ['errors' => ['Question introuvable.']];
            }
            $this->db->exec('DELETE FROM quiz_propositions WHERE question_id = ?', [$id]);
            $this->erase();
            $this->db->commit();
            $this->cacheClear();

            return ['success' => true];
        } catch (\PDOException $e) {
            $this->db->rollback();
            error_log("Erreur de suppression de la question de quiz {$id}: " . $e->getMessage());
            return ['errors' => ['Erreur lors de la suppression en base de données.']];
        }
    }

    /**
     * Réécrit les propositions d'une question (appelé dans une transaction).
     *
     * @param int $questionId
     * @param array $propositions Libellés des propositions
     * @param int $correct Index de la bonne réponse
     */
    private function replacePropositions(int $questionId, array $propositions, int $correct): void
    {
        $this->db->exec('DELETE FROM quiz_propositions WHERE question_id = ?', [$questionId]);
        foreach ($propositions as $index => $libelle) {
            $this->db->exec(
                'INSERT INTO quiz_propositions (question_id, libelle, est_correcte, ordre) VALUES (?, ?, ?, ?)',
                [$questionId, $libelle, $index === $correct ? 1 : 0, $index + 1]
            );
        }
    }
}
