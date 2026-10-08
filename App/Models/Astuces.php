<?php

namespace App\Models;

/**
 * Modèle Astuces
 * Gère les interactions avec la table 'astuces' en utilisant le Mapper F3.
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
     * Utilise le Mapper find() et cast().
     * @return array Liste des astuces
     */
    public function getAll()
    {
        $results = $this->find();
        if (!$results) {
            return [];
        }
        return array_map(function($astuce) {
            return $astuce->cast();
        }, $results);
    }

    /**
     * Compte le nombre total d'astuces.
     *
     * @return int Nombre d'astuces
     */
    public function countAll(): int
    {
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM ' . $this->table);
        return (int)($rows[0]['c'] ?? 0);
    }

    /**
     * Récupère une astuce spécifique par son ID.
     * Utilise le Mapper load().
     * @param int $id L'ID de l'astuce à récupérer.
     * @return array|null L'astuce trouvée sous forme de tableau associatif, ou null si non trouvée.
     */
    public function getAstuce(int $id)
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return null;
        }
        return $this->cast();
    }

    /**
     * Ajoute une nouvelle astuce à la base de données.
     * Utilise le Mapper reset() et save().
     * @param string $titre Le titre de l'astuce.
     * @param string $description La description de l'astuce.
     * @return int L'ID de la nouvelle astuce insérée.
     */
    public function addAstuce(string $titre, string $description): int
    {
        $this->reset();
        $this->titre = $titre;
        $this->description = $description;
        $this->save();
        return (int)$this->id;
    }

    /**
     * Met à jour une astuce existante.
     *
     * @param int $id L'ID de l'astuce à modifier.
     * @param string $titre Le nouveau titre.
     * @param string $description La nouvelle description.
     * @return bool True si l'astuce a été mise à jour, false si elle n'existe pas.
     */
    public function updateAstuce(int $id, string $titre, string $description): bool
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return false;
        }
        $this->titre = $titre;
        $this->description = $description;
        $this->save();
        return true;
    }

    /**
     * Supprime définitivement une astuce ainsi que les favoris qui la référencent.
     * La table `favoris` n'a pas de clé étrangère : les lignes orphelines sont
     * supprimées explicitement dans la même transaction.
     *
     * @param int $id L'ID de l'astuce à supprimer.
     * @return bool True si l'astuce existait et a été supprimée.
     */
    public function deleteAstuce(int $id): bool
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return false;
        }

        $ownTx = !$this->db->trans();
        if ($ownTx) {
            $this->db->begin();
        }
        try {
            $this->db->exec('DELETE FROM favoris WHERE astuces_id = ?', [$id]);
            $this->erase();
            if ($ownTx) {
                $this->db->commit();
            }
            return true;
        } catch (\PDOException $e) {
            if ($ownTx) {
                $this->db->rollback();
            }
            error_log("Erreur de suppression de l'astuce {$id}: " . $e->getMessage());
            return false;
        }
    }
}