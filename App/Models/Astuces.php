<?php

namespace App\Models;

/**
 * Modèle Astuces
 * Gère les interactions avec la table 'astuces'.
 */
class Astuces extends \DB\SQL\Mapper
{
    /**
     * Constructeur
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'astuces');
    }

    /**
     * Récupère toutes les astuces de la base de données.
     * @return array Liste des astuces
     */
    public function getAll()
    {
        try {
            $astuces = $this->db->exec('SELECT * FROM astuces');
            return $astuces;
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère une astuce spécifique par son ID.
     * @param int $id L'ID de l'astuce à récupérer.
     * @return array|null L'astuce trouvée sous forme de tableau associatif, ou null si non trouvée.
     */
    public function getAstuce(int $id)
    {
        try {
            $astuces = $this->db->exec('SELECT * FROM astuces where id = :id', [':id' => $id]);
            if (!empty($astuces)) {
                return $astuces[0]; // Correction: retourne le premier élément (index 0)
            }
            return null; // Retourne null si aucune astuce n'est trouvée
        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération de l'astuce ID {$id}: " . $e->getMessage());
            return null; // Retourne null en cas d'erreur
        }
    }

    /**
     * Ajoute une nouvelle astuce à la base de données.
     * @param string $titre Le titre de l'astuce.
     * @param string $description La description de l'astuce.
     * @return int L'ID de la nouvelle astuce insérée.
     */
    public function addAstuce(string $titre, string $description): int
    {
        try {
            $this->db->begin();
            $this->db->exec(
                'INSERT INTO astuces (titre, description) VALUES (:titre, :description)',
                [
                    ':titre' => $titre,
                    ':description' => $description
                ]
            );
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (\PDOException $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}