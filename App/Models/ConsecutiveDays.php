<?php
namespace App\Models;
use DateTimeImmutable;
use DateTimeInterface;
class ConsecutiveDays {
 private \DB\SQL $db;
 public function __construct(\DB\SQL $db){$this->db=$db;}
 public function recordActivity(int $userId, ?DateTimeInterface $today=null): int {
  $today=$today?DateTimeImmutable::createFromInterface($today):new DateTimeImmutable('today');
  $rows=$this->db->exec('SELECT consecutive_days, last_activity_date FROM users WHERE id = ?',[$userId]);
  if(!$rows) throw new \RuntimeException('Utilisateur introuvable.');
  $days=(int)($rows[0]['consecutive_days']??0);$last=$rows[0]['last_activity_date']??null;
  if(!$last)$days=1;else{$diff=(int)(new DateTimeImmutable($last))->diff($today)->format('%r%a');if($diff===1)$days++;elseif($diff>1)$days=1;}
  $this->db->exec('UPDATE users SET consecutive_days = ?, last_activity_date = CURDATE() WHERE id = ?',[$days,$userId]);return $days;
 }
}
