<?php

namespace App\Models;

/**
 * Modèle Favori
 * Gère les favoris des utilisateurs pour les astuces.
 * Étend \DB\SQL\Mapper pour utiliser les fonctionnalités ORM du framework.
 * Table : favoris (id, user_id, astuces_id)
 */
class Favori extends \DB\SQL\Mapper
{
    /**
     * Constructeur
     * Initialise la connexion à la base de données et mappe la table 'favoris'.
     *
     * @param \DB\SQL|null $db Instance de connexion DB (optionnel)
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $db = $db ?: \Base::instance()->get('DB');
        parent::__construct($db, 'favoris');
    }

    public function isFavori(int $userId, int $astuceId): bool
    {
        $this->load(['user_id = ? AND astuces_id = ?', $userId, $astuceId]);
        return !$this->dry();
    }

    /**
     * Compte le nombre total de favoris enregistrés.
     *
     * @return int Nombre de lignes dans la table favoris
     */
    public function countAll(): int
    {
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM favoris');
        return (int)($rows[0]['c'] ?? 0);
    }

    /**
     * Compte le nombre d'utilisateurs ayant mis une astuce en favori.
     *
     * @param int $astuceId ID de l'astuce
     * @return int Nombre de favoris
     */
    public function countFavorisForAstuce(int $astuceId): int
    {
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM favoris WHERE astuces_id = ?', [$astuceId]);
        return (int)($rows[0]['c'] ?? 0);
    }

    /**
     * Récupère en une seule requête tous les identifiants d'astuces favoris d'un utilisateur.
     * Évite une requête par astuce lors de l'affichage de la liste.
     *
     * @param int $userId ID de l'utilisateur
     * @return array<int> Liste des identifiants d'astuces favorites
     */
    public function getAstucesIdsByUser(int $userId): array
    {
        $rows = $this->db->exec(
            'SELECT astuces_id FROM favoris WHERE user_id = ?',
            [$userId]
        );

        $ids = [];
        foreach ((array)$rows as $row) {
            $ids[] = (int)$row['astuces_id'];
        }
        return $ids;
    }

    public function add(int $userId, int $astuceId): bool
    {
        if ($this->isFavori($userId, $astuceId)) {
            return false;
        }

        $this->reset();
        $this->user_id = $userId;
        $this->astuces_id = $astuceId;
        try {
            $this->save();
            return true;
        } catch (\PDOException $e) {
            // L'index unique uq_favoris_user_astuce empêche le doublon : on reste en échec silencieux
            return false;
        }
    }

    public function remove(int $userId, int $astuceId): bool
    {
        $this->load(['user_id = ? AND astuces_id = ?', $userId, $astuceId]);
        if (!$this->dry()) {
            $this->erase();
        }
        return true;
    }

    public function toggle(int $userId, int $astuceId): string
    {
        if ($this->isFavori($userId, $astuceId)) {
            $this->remove($userId, $astuceId);
            return 'removed';
        }

        $this->add($userId, $astuceId);
        return 'added';
    }

    /**
     * Récupère les favoris avec les champs de la table astuces.
     *
     * Les propriétés virtuelles titre/description ne sont pas des colonnes de
     * favoris. Les déclarer sur le Mapper puis les inclure dans select() fait
     * générer un SELECT titre FROM favoris, ce qui provoque SQLSTATE[42S22].
     * La jointure explicite garantit que les champs proviennent d'astuces.
     *
     * @param int $userId ID de l'utilisateur
     * @return array Tableau des astuces favorites avec leurs détails
     */
    public function getFavorisByUser(int $userId): array
    {
        $rows = $this->db->exec(
            'SELECT f.id, f.user_id, f.astuces_id, a.titre, a.description
             FROM favoris AS f
             INNER JOIN astuces AS a ON a.id = f.astuces_id
             WHERE f.user_id = ?
             ORDER BY f.id DESC',
            [$userId]
        );

        return is_array($rows) ? $rows : [];
    }
}
