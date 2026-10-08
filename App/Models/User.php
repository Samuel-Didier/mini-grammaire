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
     * Rôles autorisés dans l'application (liste blanche stricte).
     * 'invite' n'est pas un rôle stocké : c'est le rôle calculé pour une session absente.
     */
    public const ROLES = ['etudiant', 'enseignant', 'admin'];

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
        $rows = $this->find(['username = ?', $username], ['limit' => 1]);
        return $rows ? reset($rows) : null;
    }

    /**
     * Recherche un utilisateur par son adresse courriel.
     *
     * @param string $email L'adresse courriel à rechercher.
     * @return array|null Les données de l'utilisateur ou null s'il n'existe pas.
     */
    public function findByMail(string $email) {
        $rows = $this->find(['email = ?', $email], ['limit' => 1]);
        return $rows ? reset($rows) : null;
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
            if (self::softDeleteSupported() && self::isSoftDeleted($this->cast())) {
                return "Ce compte a été désactivé par un administrateur.";
            }
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
            $username = trim((string)$data['username']);
            $format = self::validateUsernameFormat($username);
            if ($format !== null) {
                $errors[] = $format;
            } elseif ($this->findByUsername($username)) {
                $errors[] = 'Le nom d\'utilisateur "' . htmlspecialchars($username) . '" existe déjà.';
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

        $nom = trim((string)($data['nom'] ?? ''));
        $prenom = trim((string)($data['prenom'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));

        $errors = [];
        if ($nom === '') $errors[] = 'Le nom est requis.';
        if ($prenom === '') $errors[] = 'Le prénom est requis.';

        if ($username === '') {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } else {
            $format = self::validateUsernameFormat($username);
            if ($format !== null) {
                $errors[] = $format;
            } else {
                // Unicité : on compare les identifiants, pas les chaînes, pour
                // que l'utilisateur puisse conserver son propre nom d'utilisateur.
                $other = $this->findByUsername($username);
                if ($other && (int)$other['id'] !== $id) {
                    $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
                }
            }
        }

        if ($email === '') {
            $errors[] = 'L\'adresse courriel est requise.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse courriel est invalide.';
        } else {
            $other = $this->findByMail($email);
            if ($other && (int)$other['id'] !== $id) {
                $errors[] = 'Cette adresse courriel est déjà utilisée.';
            }
        }

        $password = (string)($data['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== (string)($data['confirm_password'] ?? '')) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }
        }

        if (!empty($errors)) return ['errors' => $errors];

        // Mise à jour des champs
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->username = $username;
        $this->email = $email;

        if ($password !== '') {
            $this->password = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->save();
        $user = $this->cast();

        // Changer de mot de passe révoque les connexions persistantes
        if ($password !== '') {
            $this->oublierSouvenir($id);
        }

        return ['success' => true, 'user' => $user];
    }

    /**
     * Contrôle le format d'un nom d'utilisateur.
     *
     * Seule la présence d'espaces est refusée : la base contient déjà des
     * noms d'utilisateur très courts (« JD », « q »), donc aucune contrainte
     * de longueur ne peut être imposée sans verrouiller des comptes existants.
     *
     * @param string $username Nom d'utilisateur déjà trimé.
     * @return string|null Message d'erreur, ou null si le format est accepté.
     */
    public static function validateUsernameFormat(string $username): ?string
    {
        if (preg_match('/\s/u', $username)) {
            return 'Le nom d\'utilisateur ne doit pas contenir d\'espace.';
        }
        return null;
    }

    /**
     * Génère et enregistre un mot de passe temporaire pour un utilisateur.
     *
     * @param int $id L'identifiant de l'utilisateur.
     * @return string|null Le mot de passe temporaire en clair, ou null si l'utilisateur n'existe pas.
     */
    public function resetPassword(int $id) {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return null;
        }
        $temporary = bin2hex(random_bytes(6));
        $this->password = password_hash($temporary, PASSWORD_DEFAULT);
        $this->save();
        // Un mot de passe réinitialisé révoque les connexions persistantes
        $this->oublierSouvenir($id);
        return $temporary;
    }
    /**
     * Alias pour findByUsername afin de maintenir la compatibilité ou pour des besoins spécifiques.
     *
     * @param string $username Le nom d'utilisateur.
     * @return array|null
     */
    public function findData(?string $username)
    {
        if (empty($username)) {
            return null;
        }

        return $this->findByUsername($username);
    }

    /**
     * Indique si la suppression logique est disponible en base.
     * La colonne `deleted_at` provient d'une migration appliquée manuellement :
     * l'application doit rester fonctionnelle si elle n'a pas été appliquée.
     *
     * @return bool True si la colonne users.deleted_at existe.
     */
    public static function softDeleteSupported(): bool
    {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        try {
            $db = \Base::instance()->get('DB');
            $rows = $db->exec("SHOW COLUMNS FROM users LIKE 'deleted_at'");
            $supported = !empty($rows);
        } catch (\Throwable $e) {
            $supported = false;
        }

        return $supported;
    }

    /**
     * Indique si un utilisateur a été désactivé (suppression logique).
     * Renvoie false si la colonne deleted_at n'existe pas encore.
     *
     * @param array $user Ligne utilisateur (résultat de cast()).
     * @return bool
     */
    public static function isSoftDeleted(array $user): bool
    {
        return !empty($user['deleted_at']);
    }

    /**
     * Liste tous les comptes utilisateurs (tri par identifiant).
     *
     * @param bool $includeSoftDeleted True pour inclure les comptes désactivés.
     * @return array Liste des utilisateurs.
     */
    public function listAll(bool $includeSoftDeleted = true): array
    {
        $conditions = [];
        if (self::softDeleteSupported() && !$includeSoftDeleted) {
            $conditions = ['deleted_at' => null];
        }

        $rows = $this->find($conditions, ['order' => 'id ASC']);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row->cast();
        }
        return $result;
    }

    /**
     * Compte les utilisateurs par rôle.
     *
     * @return array ['total' => int, 'etudiant' => int, 'enseignant' => int, 'admin' => int, 'desactives' => int]
     */
    public function countByRole(): array
    {
        $counts = ['total' => 0, 'etudiant' => 0, 'enseignant' => 0, 'admin' => 0, 'desactives' => 0];
        foreach ($this->listAll() as $user) {
            $counts['total']++;
            $role = (string)($user['role'] ?? '');
            if (isset($counts[$role])) {
                $counts[$role]++;
            }
            if (self::isSoftDeleted($user)) {
                $counts['desactives']++;
            }
        }
        return $counts;
    }

    /**
     * Met à jour un compte depuis le tableau de bord d'administration.
     * Seuls les champs fournis sont modifiés.
     *
     * @param int $id Identifiant du compte à modifier.
     * @param array $data Champs attendus : nom, prenom, username, email, role, password, confirm_password.
     * @return array ['success' => true] ou ['errors' => [...]]
     */
    public function updateByAdmin(int $id, array $data): array
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return ['errors' => ['Utilisateur introuvable.']];
        }

        $errors = [];
        $nom = trim((string)($data['nom'] ?? ''));
        $prenom = trim((string)($data['prenom'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $role = trim((string)($data['role'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($nom === '') $errors[] = 'Le nom est requis.';
        if ($prenom === '') $errors[] = 'Le prénom est requis.';
        if ($username === '') {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif ($username !== $this->username) {
            $other = $this->findByUsername($username);
            if ($other && (int)$other['id'] !== $id) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            }
        }
        if ($email === '') {
            $errors[] = 'L\'adresse courriel est requise.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse courriel est invalide.';
        } else {
            $other = $this->findByMail($email);
            if ($other && (int)$other['id'] !== $id) {
                $errors[] = 'Cette adresse courriel est déjà utilisée.';
            }
        }
        if (!in_array($role, self::ROLES, true)) {
            $errors[] = 'Rôle invalide (valeurs autorisées : ' . implode(', ', self::ROLES) . ').';
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== (string)($data['confirm_password'] ?? '')) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }
        }

        if ($errors) {
            return ['errors' => $errors];
        }

        // Les contrôles d'unicité ont rechargé le mapper : on recharge la cible
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return ['errors' => ['Utilisateur introuvable.']];
        }

        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->username = $username;
        $this->email = $email;
        $this->role = $role;
        if ($password !== '') {
            $this->password = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->save();
        $user = $this->cast();

        // L'administration qui change le mot de passe révoque aussi les
        // connexions persistantes de ce compte
        if ($password !== '') {
            $this->oublierSouvenir($id);
        }

        return ['success' => true, 'user' => $user];
    }

    /**
     * Désactive un compte (suppression logique : la ligne est conservée).
     *
     * @param int $id Identifiant du compte.
     * @return array ['success' => true] ou ['errors' => [...]]
     */
    public function softDelete(int $id): array
    {
        if (!self::softDeleteSupported()) {
            return ['errors' => ['Suppression logique indisponible : appliquer la migration migrations/' . self::SOFT_DELETE_MIGRATION]];
        }

        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return ['errors' => ['Utilisateur introuvable.']];
        }

        $this->set('deleted_at', date('Y-m-d H:i:s'));
        $this->save();

        // Un compte désactivé ne doit plus pouvoir être rétabli par un cookie
        $this->oublierSouvenir($id);

        return ['success' => true];
    }

    /**
     * Réactive un compte précédemment désactivé.
     *
     * @param int $id Identifiant du compte.
     * @return array ['success' => true] ou ['errors' => [...]]
     */
    public function restore(int $id): array
    {
        if (!self::softDeleteSupported()) {
            return ['errors' => ['Suppression logique indisponible : appliquer la migration migrations/' . self::SOFT_DELETE_MIGRATION]];
        }

        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return ['errors' => ['Utilisateur introuvable.']];
        }

        $this->set('deleted_at', null);
        $this->save();

        return ['success' => true];
    }

    /**
     * Nom du fichier de migration ajoutant la colonne users.deleted_at.
     */
    public const SOFT_DELETE_MIGRATION = '2026_09_28_add_user_soft_delete.sql';

    /**
     * Nom du fichier de migration ajoutant les colonnes de connexion persistante.
     */
    public const REMEMBER_MIGRATION = '2026_09_29_add_remember_me.sql';

    /**
     * Durée de validité de la connexion persistante, en jours.
     */
    public const REMEMBER_JOURS = 7;

    /**
     * Catalogue des avatars proposés.
     *
     * Clé : l'identifiant stocké dans `users.image` (nom de fichier historique,
     * ex. 'chat.svg'). Valeur : l'émoji réellement affiché.
     *
     * L'émoji est rendu en texte HTML et non dans une image SVG : chargé par
     * <img>, un SVG ne peut pas utiliser la police émoji du système, et le
     * glyphe s'affiche en monochrome ou vide sur les navigateurs de bureau.
     *
     * @return array<string,string> identifiant => émoji
     */
    public static function catalog(): array
    {
        return [
            'arc-en-ciel.svg' => '🌈',
            'chat.svg' => '🐱',
            'chien.svg' => '🐶',
            'cochon.svg' => '🐷',
            'etoile.svg' => '⭐',
            'fleur.svg' => '🌸',
            'grenouille.svg' => '🐸',
            'hibou.svg' => '🦉',
            'koala.svg' => '🐨',
            'lapin.svg' => '🐰',
            'licorne.svg' => '🦄',
            'lion.svg' => '🦁',
            'lune.svg' => '🌙',
            'ours.svg' => '🐻',
            'panda.svg' => '🐼',
            'papillon.svg' => '🦋',
            'perroquet.svg' => '🦜',
            'pieuvre.svg' => '🐙',
            'renard.svg' => '🦊',
            'singe.svg' => '🐵',
            'souris.svg' => '🐭',
            'tigre.svg' => '🐯',
            'tortue.svg' => '🐢',
            'trefle.svg' => '☘️',
        ];
    }

    /**
     * Catalogue des avatars proposés : la liste des identifiants, triée.
     *
     * @return string[] Identifiants (ex. 'chat.svg').
     */
    public static function avatars(): array
    {
        $names = array_keys(self::catalog());
        sort($names, SORT_STRING);
        return $names;
    }

    /**
     * Émoji correspondant à un identifiant du catalogue.
     * Renvoie une chaîne vide si l'identifiant est inconnu ou absent.
     *
     * @param mixed $value Identifiant stocké dans `users.image`.
     * @return string
     */
    public static function avatarEmoji($value): string
    {
        return is_string($value) && $value !== '' ? (self::catalog()[$value] ?? '') : '';
    }


    /**
     * Valide un nom d'avatar contre le catalogue existant.
     * Rejette tout chemin, tentative de traversal ou fichier inconnu :
     * seule une valeur du catalogue est acceptée.
     *
     * @param mixed $value Valeur brute venue du client.
     * @return string|null Nom de fichier valide, ou null.
     */
    public static function normalizeAvatar($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        // Nom de fichier seul : un chemin (/, \, ..) est refusé
        if (basename($value) !== $value || !preg_match('/^[a-z0-9.-]+\.svg$/i', $value)) {
            return null;
        }
        return in_array($value, self::avatars(), true) ? $value : null;
    }

    /**
     * Enregistre l'avatar choisi par l'utilisateur (nom de fichier du catalogue).
     * Un nom invalide ne modifie rien et renvoie false. Une chaîne vide retire
     * l'avatar (retour aux initiales).
     *
     * @param int $id Identifiant du compte.
     * @param string $fileName Nom de fichier du catalogue, ou '' pour retirer.
     * @return bool True si l'avatar a été enregistré (ou retiré).
     */
    public function setAvatar(int $id, string $fileName): bool
    {
        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return false;
        }

        if ($fileName === '') {
            $this->set('image', null);
            $this->save();
            return true;
        }

        $name = self::normalizeAvatar($fileName);
        if ($name === null) {
            return false;
        }

        $this->set('image', $name);
        $this->save();
        return true;
    }

    /**
     * Indique si la connexion persistante est disponible en base.
     * Les colonnes proviennent d'une migration appliquée manuellement :
     * l'application doit rester fonctionnelle si elle n'a pas été appliquée.
     *
     * @return bool True si les colonnes remember_token et remember_expires existent.
     */
    public static function rememberSupported(): bool
    {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        try {
            $db = \Base::instance()->get('DB');
            $supported = !empty($db->exec("SHOW COLUMNS FROM users LIKE 'remember_token'"))
                && !empty($db->exec("SHOW COLUMNS FROM users LIKE 'remember_expires'"));
        } catch (\Throwable $e) {
            $supported = false;
        }

        return $supported;
    }

    /**
     * Tire un jeton de connexion persistante pour un compte et l'enregistre.
     * Seul le jeton en clair est renvoyé : il n'est stocké que dans le cookie
     * du navigateur, la base ne conservant que son empreinte SHA-256.
     *
     * @param int $id Identifiant du compte.
     * @return string|null Jeton en clair, ou null si la fonctionnalité est indisponible.
     */
    public function creerSouvenir(int $id)
    {
        if (!self::rememberSupported()) {
            return null;
        }

        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->set('remember_token', hash('sha256', $token));
        $this->set('remember_expires', date('Y-m-d H:i:s', time() + self::REMEMBER_JOURS * 86400));
        $this->save();

        return $token;
    }

    /**
     * Retrouve le compte correspondant à un jeton de connexion persistante.
     * Le jeton expiré, inconnu ou appartenant à un compte désactivé est
     * systématiquement purgé : le cookie est alors considéré comme périmé.
     *
     * @param string $token Jeton en clair lu dans le cookie.
     * @return array|null Données du compte, ou null si le jeton n'est pas valide.
     */
    public function validerSouvenir(string $token)
    {
        if ($token === '' || !self::rememberSupported()) {
            return null;
        }

        $this->load(['remember_token = ?', hash('sha256', $token)]);
        if ($this->dry()) {
            return null;
        }

        $user = $this->cast();
        $expire = (string)($user['remember_expires'] ?? '');

        if ($expire === '' || strtotime($expire) < time()) {
            $this->oublierSouvenir((int)$user['id']);
            return null;
        }

        // Un compte désactivé ne peut pas être rétabli par un cookie
        if (self::softDeleteSupported() && self::isSoftDeleted($user)) {
            $this->oublierSouvenir((int)$user['id']);
            return null;
        }

        return $user;
    }

    /**
     * Révoque la connexion persistante d'un compte (déconnexion, changement de
     * mot de passe, désactivation). Sans effet si la migration n'est pas appliquée.
     *
     * @param int $id Identifiant du compte.
     * @return void
     */
    public function oublierSouvenir(int $id)
    {
        if (!self::rememberSupported()) {
            return;
        }

        $this->load(['id = ?', $id]);
        if ($this->dry()) {
            return;
        }

        $this->set('remember_token', null);
        $this->set('remember_expires', null);
        $this->save();
    }
}
