-- Ajout d'index pour éviter les scans complets sur les tables les plus sollicitées.
-- Vérifier l'absence de doublons avant d'appliquer (cf. SELECT GROUP BY ... HAVING COUNT(*)>1).

-- users : connexion et beforeroute passent par username ; la réinit. mot de passe par email
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
ALTER TABLE users ADD UNIQUE KEY uq_users_email (email(191));

-- favoris : isFavori/toggle filtrent sur (user_id, astuces_id) ; empêche aussi le doublon
ALTER TABLE favoris ADD UNIQUE KEY uq_favoris_user_astuce (user_id, astuces_id);

-- mini_grammaire_codes : filtres par code (GET /mini_grammaire/@code) et catégorie
ALTER TABLE mini_grammaire_codes ADD KEY idx_mgc_code (code);
ALTER TABLE mini_grammaire_codes ADD KEY idx_mgc_category (category);