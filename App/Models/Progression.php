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
        try {
            $this->db->begin();
            $this->db->exec(
                'INSERT INTO progression (user_id, niveau_global, score_test_initial, date_test) VALUES (:user_id, :niveau_global, :score_test_initial, :date_test)',
                [
                   ':user_id' => $userId,
                    ':niveau_global' => $niveau,
                    ':score_test_initial' => $score,
                    ':date_test' => date('Y-m-d H:i:s')
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

    /**
     * Récupère la progression d'un utilisateur
     * @param int $userId
     * @return array|false
     */
    public function getByUser(int $userId)
    {
        return $this->db->exec(
            "SELECT * FROM `progression` WHERE ? LIMIT 1",
            [$userId]
        )[0] ?? null;
    }
}