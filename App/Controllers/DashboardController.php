<?php

namespace App\Controllers;

use App\Models\Astuces;
use App\Models\Favori;
use App\Models\MiniGrammaire;
use App\Models\Progression;
use App\Models\Quiz;
use App\Models\RequestLog;
use App\Models\User;

/**
 * Contrôleur DashboardController
 *
 * Point d'entrée unique des tableaux de bord : `/dashboard`.
 * Deux rendus sont possibles selon le rôle de l'utilisateur :
 *  - l'administration (admin) : contenu pédagogique + gestion des comptes utilisateurs ;
 *  - l'enseignant (admin ou enseignant) : contenu pédagogique uniquement.
 *
 * Le contenu pédagogique (astuces et fiches de mini-grammaire) est entièrement
 * modifiable, y compris la suppression. Les données utilisateurs sont
 * réservées à l'administration, et les comptes ne sont jamais supprimés
 * physiquement : ils sont désactivés (suppression logique).
 */
class DashboardController extends BaseController
{
    /**
     * Tableau de bord principal : la vue dépend du rôle.
     * Route: GET /dashboard
     *
     * @param \Base $f3 Instance du framework.
     */
    public function index(\Base $f3)
    {
        $tpl = \Template::instance();
        $role = (string)$f3->get('userRole');

        $this->requireRole($f3, self::EDITOR_ROLES);

        $isAdmin = in_array($role, self::ADMIN_ROLES, true);
        $db = $f3->get('DB');

        // Statistiques de contenu
        $stats = [
            'astuces' => (new Astuces($db))->countAll(),
            'codes' => (new MiniGrammaire($db))->countAll(),
        ];

        if ($isAdmin) {
            $stats['users'] = (new User($db))->countByRole();
            $stats['favoris'] = (new Favori($db))->countAll();
            $stats['progressions'] = (new Progression($db))->countAll();
            // Nombre de requêtes journalisées (carte du tableau de bord).
            // La table peut être absente si la migration n'est pas encore appliquée.
            $stats['logs'] = RequestLog::tableExiste($db) ? (new RequestLog($db))->statistiques()['total'] : 0;
        }
        $stats['quiz'] = (new Quiz($db))->countAll();

        $this->preparePage(
            $f3,
            $isAdmin ? 'Tableau de bord — Administration' : 'Tableau de bord — Enseignant',
            'Tableau de bord'
        );
        $f3->mset([
            'stats' => $stats,
            'roles' => User::ROLES,
            'softDeleteSupported' => User::softDeleteSupported(),
            'canUsers' => $isAdmin,
        ]);

        if ($isAdmin) {
            $f3->set('users', (new User($db))->listAll());
        }

        $f3->set('content', $tpl->render('pages/dashboard/' . ($isAdmin ? 'admin' : 'enseignant') . '.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Gestion du contenu pédagogique « Astuces » (création, modification, suppression).
     * Route: GET /dashboard/astuces
     *
     * @param \Base $f3 Instance du framework.
     */
    public function astuces(\Base $f3)
    {
        $tpl = \Template::instance();
        $this->requireRole($f3, self::EDITOR_ROLES);

        $db = $f3->get('DB');
        $astucesModel = new Astuces($db);
        $favoriModel = new Favori($db);

        $rows = $astucesModel->getAll();
        foreach ($rows as &$row) {
            $row['favoris_count'] = $favoriModel->countFavorisForAstuce((int)$row['id']);
        }
        unset($row);

        $this->preparePage($f3, 'Gestion des astuces', 'Astuces');
        $f3->mset([
            'astuces' => $rows,
            'astuces_count' => count($rows),
        ]);
        $f3->set('content', $tpl->render('pages/dashboard/astuces.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Crée une astuce depuis le tableau de bord.
     * Route: POST /dashboard/astuces/create
     *
     * @param \Base $f3 Instance du framework.
     */
    public function createAstuce(\Base $f3)
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/astuces')) {
            return;
        }

        $titre = trim((string)$f3->get('POST.titre'));
        $description = trim((string)$f3->get('POST.description'));
        $errors = [];
        if ($titre === '') {
            $errors[] = 'Le titre est requis.';
        }
        if ($description === '') {
            $errors[] = 'La description est requise.';
        }

        if (!$errors) {
            try {
                (new Astuces($f3->get('DB')))->addAstuce($titre, $description);
                $this->flash($f3, 'success', 'Astuce ajoutée.');
            } catch (\PDOException $e) {
                error_log('Erreur création astuce (dashboard): ' . $e->getMessage());
                $this->flash($f3, 'error', 'Erreur lors de l\'enregistrement en base de données.');
            }
        } else {
            $this->flash($f3, 'error', implode(' ', $errors));
        }

        $f3->reroute('/dashboard/astuces');
    }

    /**
     * Met à jour une astuce depuis le tableau de bord (édition en ligne).
     * Route: POST /dashboard/astuces/update/@id
     *
     * @param \Base $f3 Instance du framework.
     * @param array $args Paramètres de route (@id).
     */
    public function updateAstuce(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/astuces')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $titre = trim((string)$f3->get('POST.titre'));
        $description = trim((string)$f3->get('POST.description'));

        if ($titre === '' || $description === '') {
            $this->flash($f3, 'error', 'Le titre et la description sont requis.');
            $f3->reroute('/dashboard/astuces');
            return;
        }

        try {
            $ok = (new Astuces($f3->get('DB')))->updateAstuce($id, $titre, $description);
            $this->flash($f3, $ok ? 'success' : 'error', $ok ? 'Astuce mise à jour.' : 'Astuce introuvable.');
        } catch (\PDOException $e) {
            error_log('Erreur mise à jour astuce (dashboard): ' . $e->getMessage());
            $this->flash($f3, 'error', 'Erreur lors de l\'enregistrement en base de données.');
        }

        $f3->reroute('/dashboard/astuces');
    }

    /**
     * Supprime définitivement une astuce (et ses favoris).
     * Route: POST /dashboard/astuces/delete/@id
     *
     * @param \Base $f3 Instance du framework.
     */
    public function deleteAstuce(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/astuces')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $ok = (new Astuces($f3->get('DB')))->deleteAstuce($id);
        $this->flash($f3, $ok ? 'success' : 'error', $ok ? 'Astuce supprimée.' : 'Astuce introuvable.');

        $f3->reroute('/dashboard/astuces');
    }

    /**
     * Gestion du contenu pédagogique « Codes de mini-grammaire ».
     * Route: GET /dashboard/codes
     *
     * @param \Base $f3 Instance du framework.
     */
    public function codes(\Base $f3)
    {
        $tpl = \Template::instance();
        $this->requireRole($f3, self::EDITOR_ROLES);

        $recherche = trim((string)$f3->get('GET.q'));
        $rows = (new MiniGrammaire($f3->get('DB')))->search($recherche);

        $this->preparePage($f3, 'Gestion des codes de mini-grammaire', 'Codes de mini-grammaire');
        $f3->mset([
            'codes_list' => $rows,
            'codes_count' => count($rows),
            'categories' => MiniGrammaire::CATEGORIES,
            'recherche' => $recherche,
        ]);
        $f3->set('content', $tpl->render('pages/dashboard/codes.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Crée une fiche de mini-grammaire.
     * Route: POST /dashboard/codes/create
     *
     * @param \Base $f3 Instance du framework.
     */
    public function createCode(\Base $f3)
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/codes')) {
            return;
        }

        $result = (new MiniGrammaire($f3->get('DB')))->createEntry(
            (string)$f3->get('POST.code'),
            (string)$f3->get('POST.category'),
            (string)$f3->get('POST.description'),
            (string)$f3->get('POST.detail'),
            (string)$f3->get('POST.example')
        );

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Fiche créée.');
        }

        $f3->reroute('/dashboard/codes');
    }

    /**
     * Met à jour une fiche de mini-grammaire (édition en ligne).
     * Route: POST /dashboard/codes/update/@id
     *
     * @param \Base $f3 Instance du framework.
     */
    public function updateCode(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/codes')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $result = (new MiniGrammaire($f3->get('DB')))->saveEntry(
            $id,
            (string)$f3->get('POST.code'),
            (string)$f3->get('POST.category'),
            (string)$f3->get('POST.description'),
            (string)$f3->get('POST.detail'),
            (string)$f3->get('POST.example')
        );

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Fiche mise à jour.');
        }

        $f3->reroute('/dashboard/codes');
    }

    /**
     * Supprime une fiche de mini-grammaire.
     * Route: POST /dashboard/codes/delete/@id
     *
     * @param \Base $f3 Instance du framework.
     */
    public function deleteCode(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/codes')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $result = (new MiniGrammaire($f3->get('DB')))->deleteEntry($id);

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Fiche supprimée.');
        }

        $f3->reroute('/dashboard/codes');
    }

    /**
     * Gestion des questions de quiz (création, modification, suppression).
     * Route: GET /dashboard/quiz
     *
     * @param \Base $f3 Instance du framework.
     */
    public function quiz(\Base $f3)
    {
        $tpl = \Template::instance();
        $this->requireRole($f3, self::EDITOR_ROLES);

        $model = new Quiz($f3->get('DB'));

        $categorie = mb_strtolower(trim((string)$f3->get('GET.categorie')));
        $niveau = mb_strtolower(trim((string)$f3->get('GET.niveau')));
        $recherche = trim((string)$f3->get('GET.q'));

        $questions = $this->prepareQuizList(
            $model->listQuestions(
                $categorie !== '' ? $categorie : null,
                $niveau !== '' ? $niveau : null,
                300,
                $recherche
            )
        );
        // Quatre propositions pré-remplies dans le formulaire de création : le
        // nombre est ensuite ajusté par assets/js/quiz_admin.js.
        $initiales = range(0, 3);

        $this->preparePage($f3, 'Gestion des quiz', 'Quiz');
        $f3->mset([
            'quiz_questions' => $questions,
            'quiz_groupes' => $this->groupQuizByCategorie($questions),
            'quiz_count' => count($questions),
            'quiz_count_label' => Quiz::questionsAfficheesLabel(count($questions)),
            'quiz_total_label' => Quiz::questionsLabel($model->countAll()),
            'quiz_total' => $model->countAll(),
            'quiz_categories' => $model->listCategories(),
            'quiz_niveaux' => $this->quizNiveaux(),
            'quiz_difficultes' => $this->quizDifficultes(),
            'quiz_nombres_propositions' => $this->quizNombresPropositions(),
            'quiz_min_propositions' => Quiz::MIN_PROPOSITIONS,
            'quiz_max_propositions' => Quiz::MAX_PROPOSITIONS,
            'quiz_props_initiales' => array_map(
                static function ($index) {
                    return ['lettre' => chr(65 + $index), 'index' => $index];
                },
                $initiales
            ),
            'quiz_filter_categorie' => $categorie,
            'quiz_filter_niveau' => $niveau,
            'quiz_recherche' => $recherche,
        ]);
        $f3->set('content', $tpl->render('pages/dashboard/quiz.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Crée une question de quiz avec ses propositions.
     * Route: POST /dashboard/quiz/create
     *
     * @param \Base $f3 Instance du framework.
     */
    public function createQuizQuestion(\Base $f3)
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/quiz')) {
            return;
        }

        $result = (new Quiz($f3->get('DB')))->createQuestion($this->quizPayload($f3));

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Question ajoutée au quiz.');
        }

        $f3->reroute('/dashboard/quiz');
    }

    /**
     * Met à jour une question de quiz et ses propositions.
     * Route: POST /dashboard/quiz/update/@id
     *
     * @param \Base $f3 Instance du framework.
     * @param array $args Tokens de la route (id)
     */
    public function updateQuizQuestion(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/quiz')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $result = (new Quiz($f3->get('DB')))->updateQuestion($id, $this->quizPayload($f3));

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Question mise à jour.');
        }

        $f3->reroute('/dashboard/quiz');
    }

    /**
     * Supprime définitivement une question de quiz (et ses propositions).
     * Route: POST /dashboard/quiz/delete/@id
     *
     * @param \Base $f3 Instance du framework.
     * @param array $args Tokens de la route (id)
     */
    public function deleteQuizQuestion(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::EDITOR_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/quiz')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $result = (new Quiz($f3->get('DB')))->deleteQuestion($id);

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Question supprimée.');
        }

        $f3->reroute('/dashboard/quiz');
    }

    /**
     * Lit les données d'une question envoyées par le formulaire du tableau de bord.
     * La bonne réponse est transmise par sa lettre (A, B, C...) et convertie en index.
     *
     * @param \Base $f3 Instance du framework.
     * @return array
     */
    private function quizPayload(\Base $f3): array
    {
        $propositions = $f3->get('POST.propositions');
        $propositions = is_array($propositions) ? $propositions : [];
        $index = self::indexCorrect($f3->get('POST.correct'));

        return [
            'libelle_categorie' => (string)$f3->get('POST.libelle_categorie'),
            'categorie' => (string)$f3->get('POST.categorie'),
            'niveau' => (string)$f3->get('POST.niveau'),
            'difficulte' => (string)$f3->get('POST.difficulte'),
            'question' => (string)$f3->get('POST.question'),
            'explication' => (string)$f3->get('POST.explication'),
            'propositions' => $propositions,
            'correct' => $index,
        ];
    }

    /**
     * Convertit la bonne réponse reçue en index de proposition.
     * Accepte soit une lettre (A à F), soit directement un index (0 à 5) :
     * `quiz_admin.js` envoie l'index, une URL peut être appelée à la main
     * avec la lettre.
     *
     * @param mixed $valeur Valeur reçue du formulaire
     * @return int Index de la proposition, -1 si la valeur est inexploitable
     */
    private static function indexCorrect($valeur): int
    {
        $valeur = trim((string)$valeur);
        if ($valeur === '') {
            return -1;
        }
        if (ctype_digit($valeur)) {
            return (int)$valeur;
        }

        $position = strpos('ABCDEF', strtoupper($valeur));

        return $position === false ? -1 : $position;
    }

    /**
     * Niveaux de quiz proposés dans les formulaires (valeur + libellé).
     *
     * @return array [['valeur' => 'tous', 'libelle' => 'Tous niveaux (test de niveau)'], ...]
     */
    private function quizNiveaux(): array
    {
        $niveaux = [];
        foreach (Quiz::QUIZ_NIVEAUX as $niveau) {
            $niveaux[] = [
                'valeur' => $niveau,
                'libelle' => $niveau === 'tous' ? 'Tous niveaux (test de niveau)' : strtoupper($niveau),
            ];
        }

        return $niveaux;
    }

    /**
     * Regroupe les questions par libellé de catégorie pour les afficher par
     * blocs séparés : sans cela les questions d'un même quiz et celles d'un
     * autre sont melangées dans une seule liste.
     *
     * @param array $questions Questions déjà préparées par prepareQuizList()
     * @return array Liste de groupes (libelle, categorie, nombre, questions)
     */
    private function groupQuizByCategorie(array $questions): array
    {
        $groupes = [];
        foreach ($questions as $question) {
            $cle = $question['categorie'];
            if (!isset($groupes[$cle])) {
                $groupes[$cle] = [
                    'categorie' => $cle,
                    'libelle' => $question['libelle_categorie'],
                    'libelle_questions' => '',
                    'questions' => [],
                ];
            }
            $groupes[$cle]['questions'][] = $question;
        }

        foreach ($groupes as &$groupe) {
            $groupe['libelle_questions'] = Quiz::questionsLabel(count($groupe['questions']));
        }
        unset($groupe);

        return array_values($groupes);
    }

    /**
     * Liste des nombres de propositions possibles, avec leur libellé.
     *
     * @return array Liste de ['valeur' => int, 'libelle' => string]
     */
    private function quizNombresPropositions(): array
    {
        $nombres = [];
        for ($nombre = Quiz::MIN_PROPOSITIONS; $nombre <= Quiz::MAX_PROPOSITIONS; $nombre++) {
            $nombres[] = [
                'valeur' => $nombre,
                'libelle' => $nombre . ' proposition' . ($nombre > 1 ? 's' : ''),
            ];
        }

        return $nombres;
    }

    /**
     * Niveaux CECRL proposés pour chaque question (valeur + libellé).
     *
     * @return array [['valeur' => 'a1', 'libelle' => 'A1'], ...]
     */
    private function quizDifficultes(): array
    {
        $difficultes = [];
        foreach (Quiz::LEVELS as $niveau) {
            $difficultes[] = ['valeur' => $niveau, 'libelle' => strtoupper($niveau)];
        }

        return $difficultes;
    }

    /**
     * Enrichit les questions avec les libellés affichés dans le tableau de bord
     * (lettres des propositions, nombre de propositions, résumé des réponses).
     *
     * @param array $questions Questions renvoyées par le modèle
     * @return array
     */
    private function prepareQuizList(array $questions): array
    {
        foreach ($questions as &$question) {
            $affiches = [];
            $resume = [];
            foreach ($question['propositions'] as $index => $proposition) {
                $lettre = chr(65 + $index);
                $affiches[] = [
                    'index' => $index,
                    'lettre' => $lettre,
                    'libelle' => $proposition['libelle'],
                    'correct' => $proposition['est_correcte'],
                ];
                $resume[] = $proposition['est_correcte']
                    ? $lettre . ') ' . $proposition['libelle'] . ' ✓'
                    : $lettre . ') ' . $proposition['libelle'];
            }

            $question['propositions_affichees'] = $affiches;
            $question['nb_propositions'] = count($affiches);
            $question['resume_propositions'] = $resume ? implode(' · ', $resume) : '—';
            $question['quiz_titre'] = $question['libelle_categorie'] . ' ' . Quiz::niveauLabel($question['niveau']);
            $question['difficulte_libelle'] = strtoupper($question['difficulte']);
        }
        unset($question);

        return $questions;
    }

    /**
     * Journal des requêtes — réservée à l'administration.
     * Route: GET /dashboard/logs
     *
     * @param \Base $f3 Instance du framework.
     */
    public function logs(\Base $f3)
    {
        $tpl = \Template::instance();
        $this->requireRole($f3, self::ADMIN_ROLES);

        $db = $f3->get('DB');
        $tableExiste = RequestLog::tableExiste($db);
        $model = $tableExiste ? new RequestLog($db) : null;

        $filtres = [
            'recherche' => (string)$f3->get('GET.q'),
            'user_id' => (int)$f3->get('GET.user_id'),
            'statut' => (string)$f3->get('GET.statut'),
            'depuis' => (string)$f3->get('GET.depuis'),
            'jusqua' => (string)$f3->get('GET.jusqua'),
        ];
        $page = (int)$f3->get('GET.page');
        $stats = $tableExiste
            ? $model->statistiques()
            : ['total' => 0, 'aujourdhui' => 0, 'erreurs' => 0, 'utilisateurs' => 0];
        $resultat = $tableExiste
            ? $model->liste($filtres, $page > 0 ? $page : 1)
            : ['lignes' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

        $this->preparePage($f3, 'Journal des requêtes', 'Journal des requêtes');
        $f3->mset([
            'logs_table' => $tableExiste,
            'logs_stats' => $stats,
            'logs_lignes' => $this->prepareLogRows($resultat['lignes']),
            'logs_utilisateurs' => $tableExiste ? $model->listeUtilisateurs() : [],
            'logs_filtres' => $this->prepareLogFiltres($filtres),
            'logs_page' => $resultat['page'],
            'logs_pages' => $resultat['pages'],
            'logs_total' => $resultat['total'],
            'logs_total_titre' => RequestLog::requetesLabel($resultat['total'], 'affichée')
                . ' sur ' . $stats['total'] . ' au total',
            'logs_stats_titre' => RequestLog::requetesLabel($stats['total'], 'enregistrée'),
            'logs_par_page' => RequestLog::PAR_PAGE,
            'logs_page_precedente' => $this->logPageLien($filtres, max(1, $resultat['page'] - 1)),
            'logs_page_suivante' => $this->logPageLien($filtres, min($resultat['pages'], $resultat['page'] + 1)),
            'logs_reinitialiser' => $this->logPageLien([
                'recherche' => '', 'user_id' => 0, 'statut' => '', 'depuis' => '', 'jusqua' => '',
            ], 1),
            'logs_filtre_actif' => $this->logFiltreActif($filtres),
            'logs_retencion' => RequestLog::RETENTION_JOURS,
        ]);
        $f3->set('content', $tpl->render('pages/dashboard/logs.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Purge le journal des requêtes — réservée à l'administration.
     * Route: POST /dashboard/logs/purge
     *
     * @param \Base $f3 Instance du framework.
     */
    public function purgeLogs(\Base $f3)
    {
        $this->requireRole($f3, self::ADMIN_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/logs')) {
            return;
        }

        $jours = (int)$f3->get('POST.jours');
        if (!in_array($jours, [7, 30, 90, 365, 0], true)) {
            $jours = 30;
        }

        if (!RequestLog::tableExiste($f3->get('DB'))) {
            $this->flash($f3, 'error', 'Journal indisponible : la table request_logs est absente. '
                . 'Appliquez la migration migrations/2026_09_29_add_request_logs.sql.');
            $f3->reroute('/dashboard/logs');
            return;
        }

        $supprimees = (new RequestLog($f3->get('DB')))->purger($jours);
        $this->flash(
            $f3,
            'success',
            $supprimees > 0
                ? $supprimees . ' entrée(s) de journal supprimée(s).'
                : 'Aucune entrée à supprimer.'
        );

        $f3->reroute('/dashboard/logs');
    }

    /**
     * Prépare l'affichage des lignes du journal.
     *
     * @param array $lignes Lignes brutes de request_logs
     * @return array Lignes enrichies (libellés et classes CSS)
     */
    private function prepareLogRows(array $lignes): array
    {
        foreach ($lignes as &$ligne) {
            $statut = (int)($ligne['statut'] ?? 0);
            $duree = (int)($ligne['duree_ms'] ?? 0);
            $estInvite = $ligne['user_id'] === null;

            $ligne['statut_libelle'] = (string)$statut;
            $ligne['statut_class'] = $statut >= 500 ? 'off' : ($statut >= 400 ? 'enseignant' : 'etudiant');
            $ligne['utilisateur_libelle'] = $estInvite ? 'visiteur' : (string)($ligne['username'] ?? '');
            $ligne['role_libelle'] = $estInvite ? 'invite' : (string)($ligne['role'] ?? 'etudiant');
            $ligne['date_libelle'] = date('d/m/Y H:i:s', strtotime((string)($ligne['cree_le'] ?? 'now')));
            $ligne['duree_libelle'] = $duree >= 1000
                ? number_format($duree / 1000, 1, ',', ' ') . ' s'
                : $duree . ' ms';
            // Un chemin long est tronqué pour la lecture, l'URI complète est
            // conservée dans l'attribut title de la cellule.
            $chemin = (string)($ligne['chemin'] ?? '');
            $ligne['chemin_libelle'] = mb_strlen($chemin) > 60 ? mb_substr($chemin, 0, 57) . '…' : $chemin;
            $ligne['uri_libelle'] = (string)($ligne['uri'] ?? '');
            $ligne['agent_libelle'] = mb_substr((string)($ligne['user_agent'] ?? ''), 0, 80);
            $ligne['referer_libelle'] = (string)($ligne['referer'] ?? '');
        }
        unset($ligne);

        return $lignes;
    }

    /**
     * Normalise les filtres du journal pour l'affichage (valeurs déjà
     * validées par le modèle, remise au format attendu par le formulaire).
     *
     * @param array $filtres Filtres bruts
     * @return array Libellés et valeurs des filtres
     */
    private function prepareLogFiltres(array $filtres): array
    {
        return [
            'recherche' => mb_substr(trim((string)($filtres['recherche'] ?? '')), 0, 100),
            'user_id' => (int)($filtres['user_id'] ?? 0),
            'statut' => in_array((string)($filtres['statut'] ?? ''), ['400', '500'], true)
                ? (string)$filtres['statut']
                : '',
            'depuis' => (string)($filtres['depuis'] ?? ''),
            'jusqua' => (string)($filtres['jusqua'] ?? ''),
        ];
    }

    /**
     * Construit le lien d'une page du journal en conservant les filtres.
     *
     * @param array $filtres Filtres courants
     * @param int $page Page visée
     * @return string URL du tableau de bord des logs
     */
    private function logPageLien(array $filtres, int $page): string
    {
        $params = [];
        foreach (['q' => $filtres['recherche'] ?? '', 'user_id' => $filtres['user_id'] ?? 0,
                  'statut' => $filtres['statut'] ?? '', 'depuis' => $filtres['depuis'] ?? '',
                  'jusqua' => $filtres['jusqua'] ?? ''] as $cle => $valeur) {
            $valeur = trim((string)$valeur);
            if ($valeur !== '' && $valeur !== '0') {
                $params[$cle] = $valeur;
            }
        }
        if ($page > 1) {
            $params['page'] = $page;
        }

        return '/dashboard/logs' . ($params ? '?' . http_build_query($params) : '');
    }

    /**
     * Vrai si au moins un filtre est appliqué.
     *
     * @param array $filtres Filtres courants
     * @return bool
     */
    private function logFiltreActif(array $filtres): bool
    {
        foreach ($filtres as $valeur) {
            if (trim((string)$valeur) !== '' && (string)$valeur !== '0') {
                return true;
            }
        }

        return false;
    }

    /**
     * Gestion des comptes utilisateurs — réservée à l'administration.
     * Route: GET /dashboard/utilisateurs
     *
     * @param \Base $f3 Instance du framework.
     */
    public function utilisateurs(\Base $f3)
    {
        $tpl = \Template::instance();
        $this->requireRole($f3, self::ADMIN_ROLES);

        $this->preparePage($f3, 'Gestion des utilisateurs', 'Utilisateurs');

        // L'état d'affichage de chaque compte est calculé ici pour alléger la vue
        $currentId = (int)$f3->get('SESSION.user_id');
        $users = (new User($f3->get('DB')))->listAll();
        foreach ($users as &$user) {
            $user['is_deleted'] = isset($user['deleted_at']);
            $user['is_self'] = (int)$user['id'] === $currentId;
            $user['row_class'] = $user['is_deleted'] ? 'dash-row-off' : '';
            $user['state_label'] = $user['is_deleted'] ? 'désactivé' : 'actif';
            $user['state_class'] = $user['is_deleted'] ? 'off' : 'etudiant';
            $user['state_date'] = $user['is_deleted'] ? 'le ' . $user['deleted_at'] : '';
            $user['toggle_label'] = $user['is_deleted'] ? 'Réactiver' : 'Désactiver';
            $user['toggle_class'] = $user['is_deleted'] ? 'secondary' : 'danger';
            $user['toggle_confirm'] = $user['is_deleted']
                ? 'Confirmer la réactivation de ce compte ?'
                : 'Confirmer la désactivation de ce compte ?';
        }
        unset($user);

        $f3->mset([
            'users' => $users,
            'roles' => User::ROLES,
            'soft_delete_supported' => User::softDeleteSupported(),
            'current_user_id' => $currentId,
        ]);
        $f3->set('content', $tpl->render('pages/dashboard/utilisateurs.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Met à jour un compte utilisateur (identité, rôle, mot de passe).
     * Réservé à l'administration. Un administrateur ne peut pas modifier son propre rôle
     * (sinon il se retire l'accès à ce tableau de bord).
     * Route: POST /dashboard/utilisateurs/update/@id
     *
     * @param \Base $f3 Instance du framework.
     */
    public function updateUser(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::ADMIN_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/utilisateurs')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        $currentId = (int)$f3->get('SESSION.user_id');
        $data = [
            'nom' => (string)$f3->get('POST.nom'),
            'prenom' => (string)$f3->get('POST.prenom'),
            'username' => (string)$f3->get('POST.username'),
            'email' => (string)$f3->get('POST.email'),
            'role' => (string)$f3->get('POST.role'),
            'password' => (string)$f3->get('POST.password'),
            'confirm_password' => (string)$f3->get('POST.confirm_password'),
        ];

        if ($id === $currentId && $data['role'] !== (string)$f3->get('userRole')) {
            $this->flash($f3, 'error', 'Vous ne pouvez pas modifier votre propre rôle.');
            $f3->reroute('/dashboard/utilisateurs');
            return;
        }

        $result = (new User($f3->get('DB')))->updateByAdmin($id, $data);

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash($f3, 'success', 'Compte mis à jour.');
            // Si l'administrateur modifie son propre profil, la session doit suivre
            if ($id === $currentId) {
                $f3->set('SESSION.user', $result['user']['username']);
            }
        }

        $f3->reroute('/dashboard/utilisateurs');
    }

    /**
     * Active / désactive un compte (suppression logique, jamais de suppression physique).
     * Réservé à l'administration. Un administrateur ne peut pas se désactiver lui-même.
     * Route: POST /dashboard/utilisateurs/toggle/@id
     *
     * @param \Base $f3 Instance du framework.
     */
    public function toggleUser(\Base $f3, array $args = [])
    {
        $this->requireRole($f3, self::ADMIN_ROLES);
        if (!$this->csrfGuard($f3, '/dashboard/utilisateurs')) {
            return;
        }

        $id = (int)($args['id'] ?? 0);
        if ($id === (int)$f3->get('SESSION.user_id')) {
            $this->flash($f3, 'error', 'Vous ne pouvez pas désactiver votre propre compte.');
            $f3->reroute('/dashboard/utilisateurs');
            return;
        }

        $userModel = new User($f3->get('DB'));
        $user = $userModel->getById($id);
        if (!$user) {
            $this->flash($f3, 'error', 'Utilisateur introuvable.');
            $f3->reroute('/dashboard/utilisateurs');
            return;
        }

        $result = User::isSoftDeleted($user)
            ? $userModel->restore($id)
            : $userModel->softDelete($id);

        if (!empty($result['errors'])) {
            $this->flash($f3, 'error', implode(' ', $result['errors']));
        } else {
            $this->flash(
                $f3,
                'success',
                User::isSoftDeleted($user) ? 'Compte réactivé.' : 'Compte désactivé.'
            );
        }

        $f3->reroute('/dashboard/utilisateurs');
    }

    /**
     * Prépare les variables communes aux pages de gestion (titre, intitulé).
     * Le message flash est consommé par BaseController et affiché par le layout.
     *
     * @param \Base $f3 Instance du framework.
     * @param string $title Titre de la page.
     * @param string $heading Titre affiché dans la vue.
     */
    private function preparePage(\Base $f3, string $title, string $heading)
    {
        $f3->mset(['title' => $title, 'heading' => $heading]);
    }

    /**
     * Positionne un message flash pour la prochaine page.
     *
     * @param \Base $f3 Instance du framework.
     * @param string $type 'success' ou 'error'.
     * @param string $message Message affiché.
     */
    private function flash(\Base $f3, string $type, string $message)
    {
        $f3->set('SESSION.flash', ['type' => $type, 'message' => $message]);
    }
}
