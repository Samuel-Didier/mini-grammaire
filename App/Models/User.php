<?php

namespace App\Models;

/**
 * Modèle Utilisateur
 * Gère les interactions avec la table 'users'.
 */
class User extends \DB\SQL\Mapper
{
    /**
     * Constructeur
     * @param \DB\SQL|null $db Instance de connexion DB
     */
    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');
        parent::__construct($this->db, 'users');
    }

    /**
     * Trouve un utilisateur par son ID.
     * @param int $id
     * @return array|null
     */
    public function getById(int $id)
    {
        $this->load(['id = ?', $id]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Trouve un utilisateur par son nom d'utilisateur.
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username)
    {
        return $this->db->exec(
            "SELECT * FROM users WHERE username = ? LIMIT 1",
            [$username]
        )[0] ?? null;
    }

    /**
     * Trouve un utilisateur par son email.
     * @param string $email
     * @return array|null
     */
    public function findByMail(string $email)
    {
        return $this->db->exec(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        )[0] ?? null;
    }

    /**
     * Récupère les données d'un utilisateur (cast en tableau).
     * @param string $user Username
     * @return array|false
     */
    public function findData($user)
    {
        $this->load(array('username = ?', $user));
        if ($this->dry()) {
            return false;
        }
        return $this->cast();
    }

    /**
     * Enregistre un nouvel utilisateur.
     * @param string $name Nom
     * @param string $name2 Prénom
     * @param string $password Mot de passe haché
     * @param string $email Email
     * @param string $username Nom d'utilisateur
     * @param string $role Rôle (défaut: 'etudiant')
     * @return int ID du nouvel utilisateur
     */
    public function register(string $name, string $name2, string $password, string $email, string $username, string $role = 'etudiant'): int
    {
        try {
            $this->db->begin();
            $this->db->exec(
                'INSERT INTO users (nom, prenom, password, email, username, role, create_at) VALUES (:nom, :prenom, :password, :email, :username, :role, NOW())',
                [
                    ':nom' => $name,
                    ':prenom' => $name2,
                    ':password' => $password,
                    ':email' => $email,
                    ':username' => $username,
                    ':role' => $role
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
     * Met à jour les informations d'un utilisateur.
     * @param int $id L'ID de l'utilisateur à mettre à jour.
     * @param array $data Un tableau associatif des champs à mettre à jour (ex: ['nom' => 'NouveauNom']).
     * @return bool True si la mise à jour a réussi, false sinon.
     */
    public function updateUser(int $id, array $data): bool
    {
        $this->load(['id = ?', $id]); // Charge l'utilisateur par son ID
        if ($this->dry()) {
            return false; // Utilisateur non trouvé
        }

        // Met à jour les champs fournis dans $data
        foreach ($data as $key => $value) {
            // Vérifie si le champ existe dans le mapper pour éviter d'ajouter des colonnes inexistantes
            if ($this->exists($key)) {
                $this->set($key, $value);
            }
        }
        
        $this->save(); // Sauvegarde les modifications
        return true;
    }
}