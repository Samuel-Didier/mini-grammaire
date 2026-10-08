-- Connexion persistante (« Se souvenir de moi »).
-- Un jeton aléatoire est tiré à la connexion et posé dans un cookie lasting ;
-- seule son empreinte SHA-256 est stockée ici, avec sa date d'expiration.
-- Sans ces colonnes, l'application fonctionne normalement : la case à cocher
-- n'est simplement pas proposée et aucun cookie n'est posé.

ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN remember_expires DATETIME NULL;

-- Index utilisé pour retrouver le compte à partir de l'empreinte du jeton.
ALTER TABLE users ADD KEY idx_users_remember_token (remember_token);
