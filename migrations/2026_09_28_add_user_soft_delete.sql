-- Suppression logique des comptes utilisateurs (tableau de bord d'administration).
-- Le compte n'est jamais effacé : deleted_at est renseigné à la désactivation
-- et remis à NULL à la réactivation. Sans cette colonne, l'application
-- fonctionne mais les boutons de désactivation sont masqués.

ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL;

-- Index utile au filtre « comptes actifs » du tableau de bord.
ALTER TABLE users ADD KEY idx_users_deleted_at (deleted_at);
