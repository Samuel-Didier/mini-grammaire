<?php

namespace App\Models;

/**
 * Modèle de progression utilisant le Mapper SQL de Fat-Free Framework.
 */
class Progression extends \DB\SQL\Mapper
{
    /**
     * Initialise le Mapper pour la table 'progression'.
     *
     * @param \DB\SQL|null $db Instance de la base de données.
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'progression');
    }

    /**
     * Enregistre le résultat du test de niveau en utilisant la logique du Mapper.
     *
     * @param int $userId Identifiant de l'utilisateur.
     * @param string $niveau Niveau global obtenu.
     * @param int $score Score du test initial.
     * @return bool|null Retourne le résultat de la sauvegarde.
     */
    public function saveTestResult(int $userId, string $niveau, int $score)
    {
        $this->reset();
        $this->user_id = $userId;
        $this->niveau_global = $niveau;
        $this->score_test_initial = $score;
        $this->date_test = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Récupère la progression d'un utilisateur en utilisant la logique du Mapper.
     *
     * @param int $userId Identifiant de l'utilisateur.
     * @return array|null Retourne les données de progression ou null.
     */
    public function getByUser(int $userId)
    {
        $this->load(['user_id = ?', $userId]);
        return $this->dry() ? null : $this->cast();
    }
}