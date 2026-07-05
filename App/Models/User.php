<?php
namespace App\Models;

/**
 * Modèle User
 * 
 * Cette classe gère les interactions avec la table 'users' en utilisant le Mapper SQL de Fat-Free Framework.
 * Elle encapsule toute la logique métier liée aux utilisateurs, y compris l'authentification et l'inscription.
 */
class User extends \DB\SQL\Mapper {
    /**
     * Constructeur de la classe User.
     * 
     * @param \DB\SQL $db Instance de connexion à la base de données.
     */
    public function __construct(\DB\SQL $db) {
        // Initialisation du Mapper avec la table 'users'
        parent::__construct($db, 'users');
    }

    /**
     * Récupère un utilisateur par son ID.
     * 
     * @param int $id L'identifiant de l'utilisateur.
     * @return array|null Les données de l'utilisateur ou null s'il n'existe pas.
     */
    public function getById(int $id) {
        $this->load(['id = ?', $id]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Recherche un utilisateur par son nom d'utilisateur.
     * 
     * @param string $username Le nom d'utilisateur à rechercher.
     * @return array|null Les données de l'utilisateur ou null s'il n'existe pas.
     */
    public function findByUsername(string $username) {
        $this->load(['username = ?', $username]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Recherche un utilisateur par son adresse courriel.
     * 
     * @param string $email L'adresse courriel à rechercher.
     * @return array|null Les données de l'utilisateur ou null s'il n'existe pas.
     */
    public function findByMail(string $email) {
        $this->load(['email = ?', $email]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Tente de connecter un utilisateur.
     * 
     * Vérifie si l'identifiant (nom d'utilisateur ou courriel) existe et si le mot de passe correspond.
     * 
     * @param string $identifier Le nom d'utilisateur ou l'adresse courriel.
     * @param string $password Le mot de passe en clair.
     * @return array|string Retourne les données de l'utilisateur en cas de succès, ou un message d'erreur.
     */
    public function login(string $identifier, string $password) {
        // Recherche par nom d'utilisateur ou par courriel
        $this->load(['username = ? OR email = ?', $identifier, $identifier]);
        
        if ($this->dry()) {
            return "Identifiants invalides : utilisateur non trouvé.";
        }

        if (password_verify($password, $this->password)) {
            return $this->cast();
        }

        return "Mot de passe incorrect.";
    }

    /**
     * Enregistre un nouvel utilisateur après validation.
     * 
     * @param array $data Les données d'inscription (nom, prenom, username, email, password).
     * @return array|array Retourne un tableau ['user' => data] en cas de succès, ou ['errors' => [...]] en cas d'échec.
     */
    public function registerUser(array $data) {
        $errors = [];

        // Validation de base
        if (empty($data['nom'])) $errors[] = 'Le nom est requis.';
        if (empty($data['prenom'])) $errors[] = 'Le prénom est requis.';
        if (empty($data['username'])) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } else {
            if ($this->findByUsername($data['username'])) {
                $errors[] = 'Le nom d\'utilisateur "' . htmlspecialchars($data['username']) . '" existe déjà.';
            }
        }

        if (empty($data['email'])) {
            $errors[] = 'L\'adresse courriel est requise.';
        } elseif ($this->findByMail($data['email'])) {
            $errors[] = 'L\'adresse courriel est déjà utilisée.';
        }

        if (empty($data['password'])) {
            $errors[] = 'Le mot de passe est requis.';
        } elseif (strlen($data['password']) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
        }

        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        // Création de l'utilisateur
        $this->reset();
        $this->nom = $data['nom'];
        $this->prenom = $data['prenom'];
        $this->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $this->email = $data['email'];
        $this->username = $data['username'];
        $this->role = $data['role'] ?? 'etudiant';
        $this->create_at = date('Y-m-d H:i:s');
        $this->save();

        return ['user' => $this->cast()];
    }

    /**
     * Met à jour le profil d'un utilisateur.
     * 
     * @param int $id L'ID de l'utilisateur.
     * @param array $data Les données à mettre à jour.
     * @return array Retourne un tableau avec 'success' ou 'errors'.
     */
    public function updateProfile(int $id, array $data) {
        $this->load(['id = ?', $id]);
        if ($this->dry()) return ['errors' => ['Utilisateur non trouvé.']];

        $errors = [];
        if (empty($data['nom'])) $errors[] = 'Le nom est requis.';
        if (empty($data['prenom'])) $errors[] = 'Le prénom est requis.';
        
        // Vérification de l'unicité du username
        if ($this->username !== $data['username'] && $this->findByUsername($data['username'])) {
            $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
        }

        // Vérification de l'unicité de l'email
        if ($this->email !== $data['email'] && $this->findByMail($data['email'])) {
            $errors[] = 'Cette adresse courriel est déjà utilisée.';
        }

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($data['password'] !== $data['confirm_password']) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }
        }

        if (!empty($errors)) return ['errors' => $errors];

        // Mise à jour des champs
        $this->nom = $data['nom'];
        $this->prenom = $data['prenom'];
        $this->username = $data['username'];
        $this->email = $data['email'];

        if (!empty($data['password'])) {
            $this->password = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->save();
        return ['success' => true, 'user' => $this->cast()];
    }

    /**
     * Alias pour findByUsername afin de maintenir la compatibilité ou pour des besoins spécifiques.
     * 
     * @param string $username Le nom d'utilisateur.
     * @return array|null
     */
    public function findData(string $username) {
        return $this->findByUsername($username);
    }
}
