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
     * Construit la clé de tri naturel d'un code.
     * Les parties numériques sont zéro-paddées pour un ordre logique :
     * G1.1 < G2 < G10.1 (au lieu du tri alphabétique G10.1 < G2).
     *
     * @param string $code Le code à transformer.
     * @return string La clé de tri.
     */
    private function naturalKey(string $code): string
    {
        return preg_replace_callback('/\d+/', function ($m) {
            return str_pad((string)(int)$m[0], 6, '0', STR_PAD_LEFT);
        }, $code);
    }

    /**
     * Récupère tous les codes de mini-grammaire, regroupés par catégorie.
     *
     * @return array Tableau associatif des codes, groupés par catégorie.
     */
    public function getAllGroupedByCategory(): array
    {
        // Utilisation du Mapper pour charger tous les enregistrements (regroupés ensuite côté PHP)
        $items = $this->find(null, ['order' => 'category ASC']);

        $groupedCodes = [];
        if ($items) {
            foreach ($items as $item) {
                $data = $item->cast();
                $groupedCodes[$data['category']][] = $data;
            }
            // Tri naturel de chaque catégorie : G1.1 avant G2, G10 après G9
            foreach ($groupedCodes as &$codes) {
                usort($codes, function ($a, $b) {
                    return strcmp($this->naturalKey($a['code']), $this->naturalKey($b['code']));
                });
            }
            unset($codes);
        }

        return $groupedCodes;
    }

    /**
     * Récupère le code "parent" d'un code (avant le premier point).
     * Ex : G1.1 → G1 ; G2 (sans point) → G2.
     *
     * @param string $code Le code complet.
     * @return string Le code parent.
     */
    private function parentCode(string $code): string
    {
        return explode('.', $code)[0];
    }

    /**
     * Récupère les codes parents uniques, regroupés par catégorie.
     * Ex : G1.1 et G1.2 → un seul parent G1.
     *
     * @return array Tableau associatif [catégorie => [codes parents triés]].
     */
    public function getParentCodesGroupedByCategory(): array
    {
        $items = $this->find(null, ['order' => 'category ASC']);

        $grouped = [];
        if ($items) {
            foreach ($items as $item) {
                $data = $item->cast();
                $parent = $this->parentCode($data['code']);
                if (!isset($grouped[$data['category']])) {
                    $grouped[$data['category']] = [];
                }
                if (!in_array($parent, $grouped[$data['category']], true)) {
                    $grouped[$data['category']][] = $parent;
                }
            }
            // Tri naturel de chaque catégorie
            foreach ($grouped as &$codes) {
                usort($codes, function ($a, $b) {
                    return strcmp($this->naturalKey($a), $this->naturalKey($b));
                });
            }
            unset($codes);
        }

        return $grouped;
    }

    /**
     * Récupère tous les codes d'un parent donné (parent + ses sous-codes).
     * Ex : G1 → G1, G1.1, G1.2 ; G2 (sans sous-code) → G2.
     *
     * @param string $parent Le code parent.
     * @return array Liste triée naturellement des codes du parent.
     */
    public function getByParentCode(string $parent): array
    {
        $items = $this->find(
            ['code = ? OR code LIKE ?', $parent, $parent . '.%'],
            ['order' => 'category ASC']
        );

        $result = [];
        if ($items) {
            foreach ($items as $item) {
                $result[] = $item->cast();
            }
            usort($result, function ($a, $b) {
                return strcmp($this->naturalKey($a['code']), $this->naturalKey($b['code']));
            });
        }

        return $result;
    }

    /**
     * Renomme un code parent et/ou change sa catégorie, pour toute la famille.
     * Ex : G1 → G9 renomme aussi G1.1/G1.2 → G9.1/G9.2.
     *
     * @param string $oldParent L'ancien code parent (ex : G1).
     * @param string $newParent Le nouveau code parent (ex : G9).
     * @param string $newCategory La catégorie (liste blanche).
     * @return array ['success' => true] ou ['errors' => [...]].
     */
    public function updateParentGroup(string $oldParent, string $newParent, string $newCategory): array
    {
        $oldParent = strtoupper(trim($oldParent));
        $newParent = strtoupper(trim($newParent));

        // Validation du nouveau code parent (une lettre + chiffres, ex : G1)
        if (!preg_match('/^[A-Z][0-9]+$/', $newParent)) {
            return ['errors' => ['Le code doit ressembler à G1 (une lettre suivie de chiffres).']];
        }

        // Liste blanche des catégories
        $validCategories = [
            'Orthographe grammaticale',
            'Ponctuation et typographie',
            'Syntaxe',
            "Orthographe d'usage",
            'Vocabulaire',
        ];
        if (!in_array($newCategory, $validCategories, true)) {
            return ['errors' => ['Catégorie invalide.']];
        }

        try {
            // Vérifier qu'aucune autre famille n'utilise déjà le nouveau code
            $existing = $this->find(['code = ? OR code LIKE ?', $newParent, $newParent . '.%']);
            foreach ($existing as $item) {
                $data = $item->cast();
                if ($this->parentCode($data['code']) !== $oldParent) {
                    return ['errors' => ["Le code {$newParent} existe déjà."]];
                }
            }

            // Collecter les identifiants de la famille concernée
            $rows = $this->find(['code = ? OR code LIKE ?', $oldParent, $oldParent . '.%'], ['order' => 'id ASC']);
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = $row->cast()['id'];
            }
            if (empty($ids)) {
                return ['errors' => ['Aucun code à modifier.']];
            }

            foreach ($ids as $id) {
                $this->load(['id = ?', $id]);
                $data = $this->cast();
                // Renommer le préfixe : G1.2 → G9.2 (reste = '.2' ou '')
                $rest = substr($data['code'], strlen($oldParent));
                $this->code = $newParent . $rest;
                $this->category = $newCategory;
                $this->save();
            }

            return ['success' => true];
        } catch (\PDOException $e) {
            error_log("Erreur mise à jour famille {$oldParent}: " . $e->getMessage());
            return ['errors' => ['Échec de la mise à jour.']];
        }
    }

    /**
     * Modifie le code et/ou la description d'un sous-code précis.
     * Ex : G1.1 → G1.3 ou changement du texte de la règle.
     * 
     * @param int $id L'ID du sous-code à mettre à jour.
     * @param string $code Le nouveau code (format ex : G1.1).
     * @param string $description La nouvelle description/règle.
     * @return array ['success' => true] ou ['errors' => [...]].
     */
    public function updateCodeEntry(int $id, string $code, string $description): array
    {
        $code = strtoupper(trim($code));

        // Validation du format du code (lettre + chiffres, éventuellement sous-niveau)
        if (!preg_match('/^[A-Z][0-9]+(\.[0-9]+)?$/', $code)) {
            return ['errors' => ['Le code doit ressembler à G1.1 (lettre + chiffres, éventuellement un sous-niveau).']];
        }

        try {
            // Vérifier que ce code n'est pas déjà utilisé par une autre ligne
            $existing = $this->find(['code = ? AND id != ?', $code, $id]);
            if ($existing && count($existing) > 0) {
                return ['errors' => ["Le code {$code} existe déjà."]];
            }

            $this->load(['id = ?', $id]);
            if ($this->dry()) {
                return ['errors' => ['Sous-code introuvable.']];
            }
            $this->code = $code;
            $this->description = $description;
            $this->save();
            return ['success' => true];
        } catch (\PDOException $e) {
            error_log("Erreur de mise à jour du sous-code ID {$id}: " . $e->getMessage());
            return ['errors' => ['Échec de la mise à jour.']];
        }
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
