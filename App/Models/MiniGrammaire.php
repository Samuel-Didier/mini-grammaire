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
     * Durée de vie du cache des fiches statiques.
     * Les fiches sont lues en lecture seule par les visiteurs et modifiées rarement
     * par les enseignants ; un TTL court évite tout affichage périmé durablement.
     */
    private const CACHE_TTL = 600;

    /**
     * Catégories autorisées pour une fiche de mini-grammaire (liste blanche).
     */
    public const CATEGORIES = [
        'Orthographe grammaticale',
        'Ponctuation et typographie',
        'Syntaxe',
        "Orthographe d'usage",
        'Vocabulaire',
    ];

    /**
     * Format de code accepté par le tableau de bord : une lettre puis des chiffres,
     * éventuellement un sous-niveau, un tiret ou un suffixe alphabétique
     * (ex : G1, G1.1, P1-Virgule fautive).
     */
    public const CODE_PATTERN = '/^[A-Z][A-Z0-9]*(\.[0-9]+)*([ -][A-Za-z0-9]+)*$/';

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
     * Lit une entrée du cache F3 (retourne null si absente).
     */
    private function cacheGet(string $key): ?array
    {
        $value = \Cache::instance()->get($key);
        return $value === false ? null : $value;
    }

    /**
     * Écrit une entrée dans le cache F3.
     */
    private function cacheSet(string $key, array $data): void
    {
        \Cache::instance()->set($key, $data, self::CACHE_TTL);
    }

    /**
     * Invalide toutes les entrées du cache liées aux fiches.
     * À appeler après chaque modification en base par un enseignant.
     */
    private function cacheClearAll(): void
    {
        \Cache::instance()->clear('mgc.parents');
        \Cache::instance()->clear('mgc.all');
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
        $cache = $this->cacheGet('mgc.parents');
        if ($cache !== null) {
            return $cache;
        }

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

        $this->cacheSet('mgc.parents', $grouped);
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
        $cacheKey = 'mgc.byparent.' . $parent;
        $cache = $this->cacheGet($cacheKey);
        if ($cache !== null) {
            return $cache;
        }

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

        $this->cacheSet($cacheKey, $result);
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
        $validCategories = self::CATEGORIES;
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

            $this->cacheClearAll();
            \Cache::instance()->clear('mgc.byparent.' . $oldParent);
            \Cache::instance()->clear('mgc.byparent.' . $newParent);

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
            $oldParent = $this->parentCode($this->code);
            $this->code = $code;
            $this->description = $description;
            $this->save();

            $this->cacheClearAll();
            \Cache::instance()->clear('mgc.byparent.' . $oldParent);
            \Cache::instance()->clear('mgc.byparent.' . $this->parentCode($code));

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
    public function updateField(int $id, string $field, string $value): bool    {
        // Validation stricte des champs autorisés
        if (!in_array($field, ['detail', 'example'])) {
            return false;
        }

        try {
            $this->load(['id = ?', $id]);
            if (!$this->dry()) {
                $parent = $this->parentCode($this->code);
                $this->set($field, $value);
                $this->save();
                \Cache::instance()->clear('mgc.byparent.' . $parent);
                return true;
            }
        } catch (\PDOException $e) {
            // Journalisation de l'erreur
            error_log("Erreur de mise à jour du champ {$field} pour l'ID {$id}: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Compte le nombre total de fiches enregistrées.
     *
     * @return int Nombre de fiches
     */
    public function countAll(): int
    {
        $rows = $this->db->exec('SELECT COUNT(*) AS c FROM ' . $this->table);
        return (int)($rows[0]['c'] ?? 0);
    }

    /**
     * Recherche des fiches pour le tableau de bord (filtre libre sur le code,
     * la catégorie et la description).
     *
     * @param string $q Recherche utilisateur (peut être vide).
     * @param int $limit Nombre maximal de fiches renvoyées.
     * @return array Liste des fiches (cast()), triées par code.
     */    public function search(string $q, int $limit = 200): array
    {
        $q = trim($q);
        $conditions = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions = ['(code LIKE ? OR category LIKE ? OR description LIKE ?)', $like, $like, $like];
        }

        $rows = $this->find($conditions, ['order' => 'id ASC', 'limit' => max(1, $limit)]);
        $result = [];
        foreach ($rows as $row) {
            $data = $row->cast();
            $data['detail'] = (string)($data['detail'] ?? '');
            $data['example'] = (string)($data['example'] ?? '');
            $result[] = $data;
        }

        return $result;
    }

    /**
     * Crée une nouvelle fiche de mini-grammaire depuis le tableau de bord.
     *
     * @param string $code Code de la fiche (ex : G1.1).
     * @param string $category Catégorie (liste blanche CATEGORIES).
     * @param string $description Règle / texte de la fiche.
     * @param string $detail Détail complémentaire (facultatif).
     * @param string $example Exemple (facultatif).
     * @return array ['success' => true, 'id' => int] ou ['errors' => [...]]
     */
    public function createEntry(string $code, string $category, string $description, string $detail = '', string $example = ''): array
    {
        $errors = $this->validateEntry($code, $category, $description);
        if ($errors) {
            return ['errors' => $errors];
        }

        $code = strtoupper(trim($code));
        $description = trim($description);
        $detail = trim($detail);
        $example = trim($example);

        try {
            $this->reset();
            $this->code = $code;
            $this->category = $category;
            $this->description = $description;
            $this->detail = $detail !== '' ? $detail : null;
            $this->example = $example !== '' ? $example : null;
            $this->save();
            $id = (int)$this->id;

            $this->cacheClearAll();
            \Cache::instance()->clear('mgc.byparent.' . $this->parentCode($code));

            return ['success' => true, 'id' => $id];
        } catch (\PDOException $e) {
            error_log("Erreur de création de la fiche {$code}: " . $e->getMessage());
            return ['errors' => ['Erreur lors de l\'enregistrement en base de données.']];
        }
    }

    /**
     * Met à jour une fiche depuis le tableau de bord (tous les champs éditables).
     *
     * @param int $id Identifiant de la fiche.
     * @param string $code Nouveau code.
     * @param string $category Nouvelle catégorie.
     * @param string $description Nouvelle règle.
     * @param string $detail Nouveau détail.
     * @param string $example Nouvel exemple.
     * @return array ['success' => true] ou ['errors' => [...]]
     */
    public function saveEntry(int $id, string $code, string $category, string $description, string $detail = '', string $example = ''): array
    {
        $errors = $this->validateEntry($code, $category, $description);
        if ($errors) {
            return ['errors' => $errors];
        }

        $code = strtoupper(trim($code));
        $description = trim($description);
        $detail = trim($detail);
        $example = trim($example);

        try {
            $this->load(['id = ?', $id]);
            if ($this->dry()) {
                return ['errors' => ['Fiche introuvable.']];
            }

            $oldParent = $this->parentCode($this->code);

            $this->code = $code;
            $this->category = $category;
            $this->description = $description;
            $this->detail = $detail !== '' ? $detail : null;
            $this->example = $example !== '' ? $example : null;
            $this->save();

            $this->cacheClearAll();
            \Cache::instance()->clear('mgc.byparent.' . $oldParent);
            \Cache::instance()->clear('mgc.byparent.' . $this->parentCode($code));

            return ['success' => true];
        } catch (\PDOException $e) {
            error_log("Erreur de mise à jour de la fiche {$id}: " . $e->getMessage());
            return ['errors' => ['Erreur lors de l\'enregistrement en base de données.']];
        }
    }

    /**
     * Supprime définitivement une fiche de mini-grammaire.
     *
     * @param int $id Identifiant de la fiche.
     * @return array ['success' => true] ou ['errors' => [...]]
     */
    public function deleteEntry(int $id): array
    {
        try {
            $this->load(['id = ?', $id]);
            if ($this->dry()) {
                return ['errors' => ['Fiche introuvable.']];
            }
            $parent = $this->parentCode($this->code);
            $this->erase();

            $this->cacheClearAll();
            \Cache::instance()->clear('mgc.byparent.' . $parent);

            return ['success' => true];
        } catch (\PDOException $e) {
            error_log("Erreur de suppression de la fiche {$id}: " . $e->getMessage());
            return ['errors' => ['Erreur lors de la suppression en base de données.']];
        }
    }

    /**
     * Valide les champs d'une fiche saisie dans le tableau de bord.
     *
     * Le code n'est volontairement pas contrôlé comme unique : la table contient
     * plusieurs fiches pour un même code (une règle par catégorie, ex. G1.1) et
     * l'index idx_mgc_code n'est pas unique.
     *
     * @param string $code Code à contrôler.
     * @param string $category Catégorie à contrôler.
     * @param string $description Règle à contrôler.
     * @return array Liste des erreurs (vide si tout est valide).
     */
    private function validateEntry(string $code, string $category, string $description): array
    {
        $errors = [];
        $code = strtoupper(trim($code));
        $description = trim($description);

        if ($code === '') {
            $errors[] = 'Le code est requis.';
        } elseif (strlen($code) > 20) {
            $errors[] = 'Le code ne doit pas dépasser 20 caractères.';
        } elseif (!preg_match(self::CODE_PATTERN, $code)) {
            $errors[] = 'Code invalide : utilisez un format du type G1, G1.1 ou P1-Virgule fautive.';
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $errors[] = 'Catégorie invalide (valeurs autorisées : ' . implode(', ', self::CATEGORIES) . ').';
        }
        if ($description === '') {
            $errors[] = 'La règle (description) est requise.';
        }

        return $errors;
    }
}
