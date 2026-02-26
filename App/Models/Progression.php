<?php

namespace App\Models;

class Progression extends \DB\SQL\Mapper
{
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'progression');
    }

    /**
     * Enregistre le résultat du test de niveau
     * @param int $userId
     * @param string $niveau
     * @param int $score
     * @return bool
     */
    public function saveTestResult(int $userId, string $niveau, int $score): bool
    {
        // Vérifier si une progression existe déjà pour cet utilisateur
        $this->load(['user_id = ?', $userId]);
        
        $this->set('user_id', $userId);
        $this->set('niveau_global', $niveau);
        $this->set('score_test_initial', $score);
        $this->set('date_test', date('Y-m-d H:i:s'));
        
        $this->save();
        return true;
    }

    /**
     * Récupère la progression d'un utilisateur
     * @param int $userId
     * @return array|false
     */
    public function getByUser(int $userId)
    {
        $this->load(['user_id = ?', $userId]);
        return $this->dry() ? false : $this->cast();
    }
}