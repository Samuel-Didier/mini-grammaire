<?php

namespace App\Models;

use DateTimeImmutable;
use DateTimeInterface;

/** Gestion atomique de la série quotidienne et des gels disponibles. */
class Streak
{
    private \DB\SQL $db;

    public function __construct(\DB\SQL $db)
    {
        $this->db = $db;
    }

    /**
     * Enregistre l'activité du jour et retourne streak/freezes/gel_utilise.
     * Une journée manquée peut être couverte par un gel; le gel conserve la série.
     */
    public function recordActivity(int $userId, ?DateTimeInterface $today = null): array
    {
        $today = $today ? DateTimeImmutable::createFromInterface($today) : new DateTimeImmutable('today');
        $user = $this->db->exec(
            'SELECT streak_count, last_activity_date, streak_freezes_available FROM users WHERE id = ?',
            [$userId]
        );
        if (empty($user)) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        $row = $user[0];
        $streak = (int)($row['streak_count'] ?? 0);
        $freezes = (int)($row['streak_freezes_available'] ?? 0);
        $last = $row['last_activity_date'] ?? null;
        $freezeUsed = false;

        if (!$last) {
            $streak = 1;
        } else {
            $diff = (int)(new DateTimeImmutable($last))->diff($today)->format('%r%a');
            if ($diff === 1) {
                $streak++;
            } elseif ($diff === 0) {
                // Activité déjà enregistrée aujourd'hui.
            } elseif ($diff === 2 && $freezes > 0) {
                $freezes--;
                $freezeUsed = true;
            } else {
                $streak = 1;
            }
        }

        $this->db->exec(
            'UPDATE users SET streak_count = ?, last_activity_date = ?, streak_freezes_available = ? WHERE id = ?',
            [$streak, $today->format('Y-m-d'), $freezes, $userId]
        );

        return ['streak' => $streak, 'freezes' => $freezes, 'freeze_used' => $freezeUsed];
    }
}
