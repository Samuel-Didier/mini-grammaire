<?php


namespace App\Models;
class User extends \DB\SQL\Mapper
{
//    private \DB\SQL $db;

    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');

        parent::__construct($this->db, 'users');
    }

    public function findByUsername(string $username)
    {
        return $this->db->exec(
            "SELECT * FROM users WHERE username = ? LIMIT 1",
            [$username]
        )[0] ?? null;
    }

    public function findByMail(string $email)
    {
        return $this->db->exec(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        )[0] ?? null;
    }

    public function findData($user)
    {
        $this->load(array('username = ?', $user));

        if ($this->dry()) {
            return false;
        }

        return $this->cast();
    }

    //register
    public function register(string $name, string $name2, string $password, string $email, string $tel, string $username, string $role = 'locataire'): int
    {
        try {
            $this->db->begin();
            $this->db->exec(
                'INSERT INTO users (nom, prenom, password, email, telephone, username, role) VALUES (:nom, :prenom, :password, :email, :telephone, :username, :role)',
                [
                    ':nom' => $name,
                    ':prenom' => $name2,
                    ':password' => $password,
                    ':email' => $email,
                    ':telephone' => $tel,
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
     * Met à jour les informations d'un utilisateur
     * @param int $id ID de l'utilisateur
     * @param array $data Tableau associatif des données à mettre à jour
     * @return bool Succès ou échec
     */
    public function updateUser(int $id, array $data): bool
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return false;
        }

        // Copie des données dans l'objet mapper
        foreach ($data as $key => $value) {
            if ($this->exists($key)) {
                $this->set($key, $value);
            }
        }

        $this->save(); // Sauvegarde les changements
        return true;
    }
}