<?php
namespace App\Models;

class User extends \DB\SQL\Mapper {
    public function __construct(\DB\SQL $db) {
        // Initialisation du Mapper avec la table 'users'
        parent::__construct($db, 'users');
    }

    /**
     * Récupère un utilisateur par son ID
     * Utilise le Mapper natif de F3 (plus sécurisé et propre que du SQL brut)
     * La transition du SQL brut vers le Mapper permet une meilleure abstraction de la base de données,
     * simplifie la gestion des types et réduit les risques d'erreurs de syntaxe manuelle.
     */
    public function getById(int $id) {
        $this->load(['id = ?', $id]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Recherche un utilisateur par son nom d'utilisateur
     * Remplace la requête SQL manuelle par l'équivalent Mapper natif
     * Le Mapper gère automatiquement la protection contre les injections SQL via le mécanisme de binding interne.
     */
    public function findByUsername(string $username) {
        $this->load(['username = ?', $username]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Recherche un utilisateur par son adresse email
     * Sécurisé nativement contre les injections SQL grâce au mécanisme de binding de F3
     */
    public function findByMail(string $email) {
        $this->load(['email = ?', $email]);
        return $this->dry() ? null : $this->cast();
    }

    /**
     * Enregistre un nouvel utilisateur
     * Remplace la requête INSERT SQL brute. On remet à zéro le mapper,
     * on assigne les valeurs aux colonnes comme des propriétés de l'objet, puis save().
     * L'utilisation de save() garantit que l'objet mapper est synchronisé avec la base de données
     * sans avoir à écrire manuellement des requêtes d'insertion complexes.
     */
    public function register($nom, $prenom, $password, $email, $username, $role = 'etudiant') {
        $this->reset(); // Nettoie l'état du mapper pour une nouvelle insertion
        $this->nom = $nom;
        $this->prenom = $prenom;
        // Hachage sécurisé du mot de passe
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->email = $email;
        $this->username = $username;
        $this->role = $role;
        $this->create_at = date('Y-m-d H:i:s');
        $this->save(); // Insère automatiquement en base de données
        return $this->id; // Retourne l'ID généré
    }
}
