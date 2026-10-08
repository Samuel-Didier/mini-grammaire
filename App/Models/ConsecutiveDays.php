<?php
namespace App\Models;
use DateTimeImmutable;
use DateTimeInterface;

class ConsecutiveDays {
    private \DB\SQL $db;

    public function __construct(\DB\SQL $db)
    {
        $this->db = $db;
    }

    public function recordActivity(int $userId, ?DateTimeInterface $today = null): int
    {
        $today = $today ? DateTimeImmutable::createFromInterface($today) : new DateTimeImmutable('today');
        $ownTx = !$this->db->trans();
        if ($ownTx) {
            $this->db->begin();
        }
        try {
            $rows = $this->db->exec('SELECT consecutive_days, last_activity_date FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if (!$rows) {
                throw new \RuntimeException('Utilisateur introuvable.');
            }
            $days = (int)($rows[0]['consecutive_days'] ?? 0);
            $last = $rows[0]['last_activity_date'] ?? null;
            if (!$last) {
                $days = 1;
            } else {
                $diff = (int)(new DateTimeImmutable($last))->diff($today)->format('%r%a');
                if ($diff === 1) {
                    $days++;
                } elseif ($diff > 1) {
                    $days = 1;
                }
            }
            $this->db->exec('UPDATE users SET consecutive_days = ?, last_activity_date = ? WHERE id = ?', [$days, $today->format('Y-m-d'), $userId]);
            if ($ownTx) {
                $this->db->commit();
            }
            return $days;
        } catch (\PDOException $e) {
            if ($ownTx) {
                $this->db->rollback();
            }
            if (strpos($e->getMessage(), 'consecutive_days') !== false || strpos($e->getMessage(), 'last_activity_date') !== false) {
                return 1;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($ownTx) {
                $this->db->rollback();
            }
            throw $e;
        }
    }
}