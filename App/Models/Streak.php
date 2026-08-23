<?php
namespace App\Models;
use DateTimeImmutable;
use DateTimeInterface;
class Streak {
    private \DB\SQL $db;
    public function __construct(\DB\SQL $db){$this->db=$db;}
    public function recordActivity(int $userId, ?DateTimeInterface $today=null): array {
        $today=$today?DateTimeImmutable::createFromInterface($today):new DateTimeImmutable('today');
        $rows=$this->db->exec('SELECT streak_count,last_activity_date,streak_freezes_available FROM users WHERE id = ?',[$userId]);
        if(empty($rows)) throw new \RuntimeException('Utilisateur introuvable.');
        $row=$rows[0];$streak=(int)($row['streak_count']??0);$freezes=(int)($row['streak_freezes_available']??0);$last=$row['last_activity_date']??null;$used=false;
        if(!$last)$streak=1;else{$diff=(int)(new DateTimeImmutable($last))->diff($today)->format('%r%a');if($diff===1)$streak++;elseif($diff===0){}elseif($diff===2&&$freezes>0){$freezes--; $used=true;}else $streak=1;}
        $this->db->exec('UPDATE users SET streak_count = ?, last_activity_date = ?, streak_freezes_available = ? WHERE id = ?',[$streak,$today->format('Y-m-d'),$freezes,$userId]);
        return ['streak'=>$streak,'freezes'=>$freezes,'freeze_used'=>$used];
    }
    public function getStreakInfo(int $userId): array {
        $rows=$this->db->exec('SELECT streak_count,last_activity_date,streak_freezes_available FROM users WHERE id = ?',[$userId]);
        if(empty($rows)) throw new \RuntimeException('Utilisateur introuvable.');
        return ['streak'=>(int)($rows[0]['streak_count']??0),'freezes'=>(int)($rows[0]['streak_freezes_available']??0),'last_activity_date'=>$rows[0]['last_activity_date']??null];
    }
}
