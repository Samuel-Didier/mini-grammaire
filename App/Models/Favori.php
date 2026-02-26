<?php

namespace App\Models;

/**
 * Modèle Favori
 * Gère les favoris des utilisateurs pour les logements
 * Table : favoris (id, user_id, astuces_id)
 */
class Favori
{
    private \DB\SQL $db;
    /**
     * Constructeur
     * Initialise la connexion à la base de données et mappe la table 'favoris'
     * 
     * @param \DB\SQL|null $db Instance de connexion DB (optionnel)
     */
    public function __construct(?\DB\SQL $db = null)
    {
        // Récupère la connexion DB depuis F3 si non fournie
        $this->db = $db ?: \Base::instance()->get('DB');
        
    }

    /**
     * Vérifie si un logement est déjà dans les favoris de l'utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $astuce_id ID du logement
     * @return bool True si le favori existe, False sinon
     */
    public function isFavori(int $user_id, int $astuce_id): bool
    {
        try {
            $result = $this->db->exec(
                'SELECT id FROM favoris WHERE user_id = :user_id AND astuces_id = :astuces_id',
                [ ':user_id' => $user_id,
                   ':astuces_id' => $astuce_id]);
            if ($result) {
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            // Pas de transaction ici, donc pas de rollback
            throw $e;
        }
    }

    /**
     * Ajoute un logement aux favoris de l'utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $astuce_id ID du logement
     * @return bool True si ajouté, False si déjà existant
     */
    public function add(int $user_id, int $astuce_id): bool
    {
        // Évite les doublons : ne rien faire si déjà en favori
        if ($this->isFavori($user_id, $astuce_id)) {
            return false;
        }

        // Insertion dans la table favoris
        $this->db->exec(
            'INSERT INTO favoris (user_id, astuces_id) VALUES (?, ?)',
            [$user_id, $astuce_id]
        );
        return true;
    }

    /**
     * Retire un logement des favoris de l'utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $astuce_id ID du logement
     * @return bool True si supprimé
     */
    public function remove(int $user_id, int $astuce_id): bool
    {
        // Suppression de l'entrée dans la table favoris
        $this->db->exec(
            'DELETE FROM favoris WHERE user_id = ? AND astuces_id = ?',
            [$user_id, $astuce_id]
        );
        return true;
    }

    /**
     * Toggle : Ajoute OU retire un favori selon l'état actuel
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $astuce_id ID du logement
     * @return string 'added' si ajouté, 'removed' si retiré
     */
    public function toggle(int $user_id, int $astuce_id): string
    {
        // Si déjà en favori → on retire
        if ($this->isFavori($user_id, $astuce_id)) {
            $this->remove($user_id, $astuce_id);
            return 'removed';
        } 
        // Sinon → on ajoute
        else {
            $this->add($user_id, $astuce_id);
            return 'added';
        }
    }

    /**
     * Récupère tous les logements favoris d'un utilisateur
     * Utilise INNER JOIN pour récupérer les détails complets du logement
     * 
     * @param int $user_id ID de l'utilisateur
     * @return array Tableau des logements favoris avec leurs détails
     */
    public function getFavorisByUser(int $user_id): ?array
    {
        $favoris = $this->db->exec( "
            SELECT 
                a.id,
                a.titre,
                a.description
            FROM favoris f
            INNER JOIN astuces a ON f.astuces_id = a.id
            WHERE f.user_id = ?
            ORDER BY f.id DESC ", [$user_id]);
        
        // Exécute la requête et retourne un tableau de résultats
        return $favoris;
    }
}