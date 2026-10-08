<?php

namespace App\Models;

/**
 * Modèle RequestLog
 *
 * Journalise les requêtes traitées par l'application : une ligne par requête,
 * écrite en fin d'exécution PHP (fonction de rappel `register_shutdown_function`).
 * Ce mécanisme est choisi parce que plusieurs contrôleurs terminent la requête
 * par `exit` (API quiz) ou par un `reroute()` : un `afterRoute` de F3 ne serait
 * jamais appelé dans ces cas, et les redirections 302 ne seraient pas journalisées.
 *
 * Par convention, les corps de requête ne sont jamais enregistrés (un POST de
 * connexion contient un mot de passe) : seuls la méthode, le chemin et le
 * référent sont mémorisés.
 *
 * Le tableau de bord `/dashboard/logs` (administration) permet de consulter le
 * journal, de le filtrer et de le purger. Si la table n'existe pas (migration non
 * appliquée), l'écriture échoue silencieusement et l'application fonctionne
 * normalement : le drapeau interne `tableIndisponible` évite ensuite de
 * réessayer à chaque requête.
 */
class RequestLog extends \DB\SQL\Mapper
{
    /**
     * Nombre de lignes affichées par page dans le tableau de bord.
     */
    public const PAR_PAGE = 50;

    /**
     * Durée de conservation automatique du journal, en jours.
     * Le nettoyage est déclenché au hasard sur une requête sur
     * `PURGE_CHANCE` afin de ne pas ralentir chaque appel.
     */
    public const RETENTION_JOURS = 30;

    /**
     * Une purge automatique est tentée sur une requête sur N.
     */
    private const PURGE_CHANCE = 50;

    /**
     * Longueur maximale des champs texte tronqués avant stockage.
     */
    private const MAX_URI = 500;
    private const MAX_AGENT = 255;
    private const MAX_REFERER = 500;
    private const MAX_CHEMIN = 255;
    private const MAX_USERNAME = 100;

    /**
     * Utilisateur de la requête en cours, renseigné par BaseController::beforeroute.
     * null tant que la requête n'est pas encore passée par le contrôleur.
     *
     * @var array|null ['user_id' => int|null, 'username' => string, 'role' => string]
     */
    private static ?array $requeteCourante = null;

    /**
     * Vrai si l'écriture a déjà échoué faute de table : on arrête d'essayer.
     */
    private static bool $tableIndisponible = false;

    /**
     * Constructeur
     * Initialise la connexion et mappe la table 'request_logs'.
     *
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'request_logs');
    }

    /**
     * Mémorise l'utilisateur de la requête en cours pour éviter une requête
     * SQL supplémentaire au moment de l'écriture du journal.
     *
     * @param int|null $userId Identifiant de l'utilisateur, null si visiteur
     * @param string $username Identifiant de connexion
     * @param string $role Rôle de l'utilisateur
     */
    public static function setUtilisateur(?int $userId, string $username, string $role): void
    {
        self::$requeteCourante = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
        ];
    }

    /**
     * Ouvre le journal pour la requête courante.
     *
     * À appeler une seule fois, dans index.php, une fois la connexion
     * disponible : l'écriture différée a alors lieu même si un contrôleur
     * termine la requête par `exit`.
     */
    public static function demarrer(?\DB\SQL $db = null): void
    {
        self::$requeteCourante = null;
        register_shutdown_function(static function () use ($db) {
            self::ecrireRequete($db);
        });
    }

    /**
     * Enregistre la requête qui vient de se terminer.
     *
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    private static function ecrireRequete(?\DB\SQL $db = null): void
    {
        if (self::$tableIndisponible) {
            return;
        }

        try {
            $db = $db ?: \Base::instance()->get('DB');
            if (!$db instanceof \DB\SQL) {
                self::$tableIndisponible = true;
                return;
            }

            $utilisateur = self::utilisateurCourant($db);
            $debut = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
            $duree = (int)round((microtime(true) - $debut) * 1000);
            $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
            $chemin = (string)parse_url($uri, PHP_URL_PATH);
            $statut = http_response_code();
            if (!is_int($statut) || $statut <= 0) {
                $statut = 200;
            }

            $db->exec(
                'INSERT INTO request_logs
                    (user_id, username, role, methode, chemin, uri, statut, duree_ms, ip, user_agent, referer)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $utilisateur['user_id'],
                    mb_substr($utilisateur['username'], 0, self::MAX_USERNAME),
                    $utilisateur['role'],
                    mb_substr((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10),
                    mb_substr($chemin !== '' ? $chemin : '/', 0, self::MAX_CHEMIN),
                    mb_substr($uri, 0, self::MAX_URI),
                    $statut,
                    max(0, $duree),
                    self::adresseIp(),
                    mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, self::MAX_AGENT),
                    mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, self::MAX_REFERER),
                ]
            );

            if (random_int(1, self::PURGE_CHANCE) === 1) {
                (new self($db))->purger(self::RETENTION_JOURS);
            }
        } catch (\Throwable $e) {
            // Une table manquante ne doit jamais casser le site : on le signale
            // une fois dans les logs serveur et on arrête d'insister.
            self::$tableIndisponible = true;
            error_log('Journal des requêtes indisponible : ' . $e->getMessage());
        }
    }

    /**
     * Utilisateur de la requête en cours.
     * Utilise la valeur mémorisée par BaseController::beforeroute si elle existe,
     * sinon interroge la base à partir de la session.
     *
     * Cas particulier d'une connexion : la requête a commencé en anonyme mais
     * la session est ouverte à la fin (POST /login, /register). La ligne est
     * alors attribuée au compte qui vient d'être connecté, ce qui permet à
     * l'administration de voir qui s'est connecté et quand.
     *
     * @param \DB\SQL $db Instance de connexion DB
     * @return array ['user_id' => int|null, 'username' => string, 'role' => string]
     */
    private static function utilisateurCourant(\DB\SQL $db): array
    {
        $invite = ['user_id' => null, 'username' => '', 'role' => 'invite'];
        if (self::$requeteCourante !== null && self::$requeteCourante['user_id'] !== null) {
            return self::$requeteCourante;
        }

        $f3 = \Base::instance();
        $userId = $f3->get('SESSION.user_id');
        if (!is_numeric($userId)) {
            return $invite;
        }

        $ligne = $db->exec(
            'SELECT id, username, role FROM users WHERE id = ? LIMIT 1',
            [(int)$userId]
        );
        if (!$ligne) {
            return $invite;
        }

        return [
            'user_id' => (int)$ligne[0]['id'],
            'username' => (string)($ligne[0]['username'] ?? ''),
            'role' => (string)($ligne[0]['role'] ?? 'etudiant'),
        ];
    }

    /**
     * Adresse IP du visiteur.
     * X-Forwarded-For est prioritaire car l'application est servie derrière un
     * reverse proxy (nginx) : cette valeur sert uniquement à l'affichage.
     *
     * @return string Adresse IP, ou une chaîne vide si elle est absente
     */
    private static function adresseIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $cle) {
            if (empty($_SERVER[$cle])) {
                continue;
            }
            // X-Forwarded-For peut contenir une liste : on garde la première adresse.
            $candidat = trim(explode(',', (string)$_SERVER[$cle])[0]);
            if (filter_var($candidat, FILTER_VALIDATE_IP)) {
                return $candidat;
            }
        }

        return '';
    }

    /**
     * Libellé accordé pour les compteurs de l'interface.
     *
     * @param int $nombre Nombre à afficher
     * @param string $suffixe Qualificatif au singulier, ajouté avec l'accord
     *                       ('enregistrée' -> « 4 requêtes enregistrées »)
     * @return string Libellé
     */
    public static function requetesLabel(int $nombre, string $suffixe = ''): string
    {
        $pluriel = $nombre > 1;
        $libelle = $nombre . ' requête' . ($pluriel ? 's' : '');

        return $suffixe === '' ? $libelle : $libelle . ' ' . $suffixe . ($pluriel ? 's' : '');
    }
    /**
     * Vrai si la table du journal existe (migration appliquée).
     *
     * À appeler sur l'instance \DB\SQL et non sur le modèle : le constructeur
     * de `DB\SQL\Mapper` lit le schéma de la table et lève une exception si
     * elle est absente. Le tableau de bord vérifie donc la présence de la
     * table avant d'instancier le modèle, sinon l'absence de migration
     * rendrait /dashboard/logs inaccessible au lieu d'y afficher un avertissement.
     *
     * @param \DB\SQL $db Instance de connexion DB
     * @return bool True si la table request_logs existe
     */
    public static function tableExiste(\DB\SQL $db): bool
    {
        try {
            $db->exec('SELECT 1 FROM request_logs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Compteurs affichés en tête du tableau de bord des logs.
     *
     * @return array ['total' => int, 'aujourdhui' => int, 'erreurs' => int, 'utilisateurs' => int]
     */
    public function statistiques(): array
    {
        try {
            $lignes = $this->db->exec(
                'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN cree_le >= CURDATE() THEN 1 ELSE 0 END) AS aujourdhui,
                    SUM(CASE WHEN statut >= 500 THEN 1 ELSE 0 END) AS erreurs,
                    COUNT(DISTINCT user_id) AS utilisateurs
                 FROM request_logs'
            );
        } catch (\Throwable $e) {
            return ['total' => 0, 'aujourdhui' => 0, 'erreurs' => 0, 'utilisateurs' => 0];
        }

        $ligne = $lignes[0] ?? [];

        return [
            'total' => (int)($ligne['total'] ?? 0),
            'aujourdhui' => (int)($ligne['aujourdhui'] ?? 0),
            'erreurs' => (int)($ligne['erreurs'] ?? 0),
            'utilisateurs' => (int)($ligne['utilisateurs'] ?? 0),
        ];
    }

    /**
     * Liste paginée des requêtes, avec filtres.
     *
     * @param array $filtres ['recherche' => string, 'user_id' => int, 'famille' => string,
     *                       'statut' => string, 'depuis' => string, 'jusqua' => string]
     * @param int $page Page demandée (1 par défaut)
     * @return array ['lignes' => array, 'total' => int, 'page' => int, 'pages' => int]
     */
    public function liste(array $filtres = [], int $page = 1): array
    {
        $filtres = $this->normaliserFiltres($filtres);
        $page = max(1, $page);
        $conditions = ['1 = 1'];
        $valeurs = [];

        if ($filtres['recherche'] !== '') {
            $conditions[] = '(chemin LIKE ? OR uri LIKE ? OR username LIKE ?)';
            $like = '%' . $filtres['recherche'] . '%';
            array_push($valeurs, $like, $like, $like);
        }
        if ($filtres['user_id'] > 0) {
            $conditions[] = 'user_id = ?';
            $valeurs[] = $filtres['user_id'];
        }
        if ($filtres['statut'] !== '') {
            $conditions[] = 'statut >= ?';
            $valeurs[] = (int)$filtres['statut'];
        }
        if ($filtres['depuis'] !== '') {
            $conditions[] = 'cree_le >= ?';
            $valeurs[] = $filtres['depuis'] . ' 00:00:00';
        }
        if ($filtres['jusqua'] !== '') {
            $conditions[] = 'cree_le <= ?';
            $valeurs[] = $filtres['jusqua'] . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);

        try {
            $compte = $this->db->exec('SELECT COUNT(*) AS c FROM request_logs WHERE ' . $where, $valeurs);
            $total = (int)($compte[0]['c'] ?? 0);
            if ($total === 0) {
                return ['lignes' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
            }

            $pages = max(1, (int)ceil($total / self::PAR_PAGE));
            $page = min($page, $pages);
            $lignes = $this->db->exec(
                'SELECT * FROM request_logs WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . self::PAR_PAGE . ' OFFSET '
                    . (($page - 1) * self::PAR_PAGE),
                $valeurs
            );
        } catch (\Throwable $e) {
            return ['lignes' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        return ['lignes' => $lignes, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Nettoie et valide les filtres de la liste.
     *
     * @param array $filtres Filtres bruts
     * @return array Filtres normalises
     */
    private function normaliserFiltres(array $filtres): array
    {
        $date = static function ($valeur): string {
            $valeur = trim((string)$valeur);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
                return '';
            }
            [$annee, $mois, $jour] = array_map('intval', explode('-', $valeur));
            return checkdate($mois, $jour, $annee) ? $valeur : '';
        };

        $statut = trim((string)($filtres['statut'] ?? ''));
        if (!in_array($statut, ['400', '500'], true)) {
            $statut = '';
        }

        return [
            'recherche' => mb_substr(trim((string)($filtres['recherche'] ?? '')), 0, 100),
            'user_id' => max(0, (int)($filtres['user_id'] ?? 0)),
            'statut' => $statut,
            'depuis' => $date($filtres['depuis'] ?? ''),
            'jusqua' => $date($filtres['jusqua'] ?? ''),
        ];
    }

    /**
     * Utilisateurs présents dans le journal, pour le filtre.
     *
     * @return array Liste de ['id' => int, 'username' => string, 'requetes' => int]
     */
    public function listeUtilisateurs(): array
    {
        try {
            return $this->db->exec(
                'SELECT user_id AS id, username, COUNT(*) AS requetes
                 FROM request_logs
                 WHERE user_id IS NOT NULL
                 GROUP BY user_id, username
                 ORDER BY username ASC'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Supprime les entrées plus anciennes qu'un nombre de jours.
     *
     * @param int $jours Nombre de jours à conserver (0 = tout garder)
     * @return int Nombre de lignes supprimées
     */
    public function purger(int $jours = 0): int
    {
        try {
            if ($jours > 0) {
                $limite = date('Y-m-d H:i:s', strtotime('-' . $jours . ' days'));
                $compte = $this->db->exec('SELECT COUNT(*) AS c FROM request_logs WHERE cree_le < ?', [$limite]);
                $supprimees = (int)($compte[0]['c'] ?? 0);
                if ($supprimees > 0) {
                    $this->db->exec('DELETE FROM request_logs WHERE cree_le < ?', [$limite]);
                }
            } else {
                $compte = $this->db->exec('SELECT COUNT(*) AS c FROM request_logs');
                $supprimees = (int)($compte[0]['c'] ?? 0);
                if ($supprimees > 0) {
                    $this->db->exec('DELETE FROM request_logs');
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $supprimees;
    }
}
