-- Journal des requetes (tableau de bord d'administration).
-- Une ligne par requete PHP traitee : qui (utilisateur, role), quoi (methode,
-- chemin, referent), avec quel resultat (statut HTTP, duree) et depuis quelle
-- adresse IP.
--
-- Les requetes sont enregistrees par App\Models\RequestLog (fonction de fin
-- d'execution PHP) : les controleurs qui appellent exit() (API quiz) ou
-- reroute() (redirections) sont donc bien journalises eux aussi.
--
-- Les corps de requete POST ne sont jamais stockes (ils contiendraient des
-- mots de passe) : seules la methode et le chemin sont memorises.
--
-- L'application fonctionne sans cette table (les erreurs d'ecriture sont
-- ignorees), mais /dashboard/logs affiche alors un avertissement.

CREATE TABLE `request_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `role` varchar(20) NOT NULL DEFAULT 'invite',
  `methode` varchar(10) NOT NULL DEFAULT 'GET',
  `chemin` varchar(255) NOT NULL DEFAULT '',
  `uri` varchar(500) NOT NULL DEFAULT '',
  `statut` smallint unsigned NOT NULL DEFAULT 200,
  `duree_ms` int unsigned NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `referer` varchar(500) NOT NULL DEFAULT '',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_logs_date` (`cree_le`),
  KEY `idx_request_logs_user` (`user_id`),
  KEY `idx_request_logs_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
