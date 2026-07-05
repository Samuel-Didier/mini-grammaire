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
}