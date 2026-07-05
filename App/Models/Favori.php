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

    /**
     * Vérifie si une astuce est déjà dans les favoris de l'utilisateur.
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $astuceId ID de l'astuce
     * @return bool True si le favori existe, False sinon
     */
    public function isFavori(int $userId, int $astuceId): bool
    {
        $this->load(['user_id = ? AND astuces_id = ?', $userId, $astuceId]);
        return !$this->dry();
    }

    /**
     * Ajoute une astuce aux favoris de l'utilisateur.
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $astuceId ID de l'astuce
     * @return bool True si ajouté, False si déjà existant
     */
    public function add(int $userId, int $astuceId): bool
    {
        if ($this->isFavori($userId, $astuceId)) {
            return false;
        }

        $this->reset();
        $this->user_id = $userId;
        $this->astuces_id = $astuceId;
        $this->save();
        return true;
    }

    /**
     * Retire une astuce des favoris de l'utilisateur.
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $astuceId ID de l'astuce
     * @return bool True si l'opération a été tentée
     */
    public function remove(int $userId, int $astuceId): bool
    {
        $this->load(['user_id = ? AND astuces_id = ?', $userId, $astuceId]);
        if (!$this->dry()) {
            $this->erase();
        }
        return true;
    }

    /**
     * Bascule l'état de favori : Ajoute OU retire selon l'état actuel.
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $astuceId ID de l'astuce
     * @return string 'added' si ajouté, 'removed' si retiré
     */
    public function toggle(int $userId, int $astuceId): string
    {
        if ($this->isFavori($userId, $astuceId)) {
            $this->remove($userId, $astuceId);
            return 'removed';
        } else {
            $this->add($userId, $astuceId);
            return 'added';
        }
    }

    /**
     * Récupère toutes les astuces favorites d'un utilisateur.
     * 
     * @param int $userId ID de l'utilisateur
     * @return array|null Tableau des astuces favorites avec leurs détails
     */
    public function getFavorisByUser(int $userId): ?array
    {
        return $this->db->exec("
            SELECT 
                a.id,
                a.titre,
                a.description
            FROM favoris f
            INNER JOIN astuces a ON f.astuces_id = a.id
            WHERE f.user_id = ?
            ORDER BY f.id DESC", [$userId]);
    }
}
