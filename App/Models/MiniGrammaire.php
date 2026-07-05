<?php

namespace App\Models;

/**
 * Modèle MiniGrammaire
 * Gère les interactions avec la table 'mini_grammaire_codes'.
 * Étend \DB\SQL\Mapper pour utiliser l'ORM de Fat-Free Framework.
 */
class MiniGrammaire extends \DB\SQL\Mapper
{
    /**
     * Constructeur
     * Initialise la connexion et mappe la table 'mini_grammaire_codes'.
     * 
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'mini_grammaire_codes');
    }

    /**
     * Récupère tous les codes de mini-grammaire, regroupés par catégorie.
     * Refactorisé pour utiliser le Mapper et un tri moderne.
     * 
     * @return array Tableau associatif des codes, groupés par catégorie.
     */
    public function getAllGroupedByCategory(): array
    {
        // Utilisation du Mapper pour charger tous les enregistrements
        // Tri par catégorie, longueur du code (pour le tri naturel), puis code
        $items = $this->find(
            null,
            ['order' => 'category ASC, LENGTH(code) ASC, code ASC']
        );
        
        $groupedCodes = [];
        if ($items) {
            foreach ($items as $item) {
                $data = $item->cast();
                $groupedCodes[$data['category']][] = $data;
            }
        }
        
        return $groupedCodes;
    }

    /**
     * Met à jour un champ spécifique (detail ou example) pour un code donné.
     * 
     * @param int $id L'ID du code à mettre à jour.
     * @param string $field Le nom du champ à mettre à jour ('detail' ou 'example').
     * @param string $value La nouvelle valeur du champ.
     * @return bool True si la mise à jour a réussi, false sinon.
     */
    public function updateField(int $id, string $field, string $value): bool
    {
        // Validation stricte des champs autorisés
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
            // Journalisation de l'erreur
            error_log("Erreur de mise à jour du champ {$field} pour l'ID {$id}: " . $e->getMessage());
        }
        return false;
    }
}
