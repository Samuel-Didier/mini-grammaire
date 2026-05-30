<?php

namespace App\Models;

/**
 * Modèle MiniGrammaire
 * Gère les interactions avec la table 'mini_grammaire_codes'.
 */
class MiniGrammaire extends \DB\SQL\Mapper
{
    /**
     * Constructeur
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'mini_grammaire_codes');
    }

    /**
     * Récupère tous les codes de mini-grammaire, regroupés par catégorie.
     * @return array Tableau associatif des codes, clés par catégorie.
     */
    public function getAllGroupedByCategory(): array
    {
        // Correction du tri pour gérer les codes alphanumériques (ex: G1, G10, G2)
        // Trie d'abord par catégorie, puis par la longueur du code, puis par le code lui-même.
        $codes = $this->db->exec('SELECT * FROM mini_grammaire_codes ORDER BY category, LENGTH(code), code');
        
        $groupedCodes = [];
        foreach ($codes as $code) {
            $groupedCodes[$code['category']][] = $code;
        }
        return $groupedCodes;
    }

    /**
     * Met à jour un champ spécifique (detail ou example) pour un code donné.
     * @param int $id L'ID du code à mettre à jour.
     * @param string $field Le nom du champ à mettre à jour ('detail' ou 'example').
     * @param string $value La nouvelle valeur du champ.
     * @return bool True si la mise à jour a réussi, false sinon.
     */
    public function updateField(int $id, string $field, string $value): bool
    {
        // Valider que le champ est bien 'detail' ou 'example' pour éviter les injections SQL
        if (!in_array($field, ['detail', 'example'])) {
            return false;
        }

        try {
            $this->load(['id = ?', $id]);
            if (!$this->dry()) {
                $this->set($field, $value);
                $this->save();
                return true;
            }
        } catch (\PDOException $e) {
            // Log l'erreur ou la gérer
            error_log("Erreur de mise à jour du champ {$field} pour l'ID {$id}: " . $e->getMessage());
        }
        return false;
    }
}