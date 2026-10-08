<?php

namespace App\Controllers;

use App\Models\RequestLog;
use App\Models\User;

/**
 * Contrôleur de base pour l'application.
 * Contient des méthodes utilitaires partagées par tous les contrôleurs, notamment pour la gestion des rôles (RBAC).
 */
abstract class BaseController {

    /**
     * Rôles autorisés à modifier le contenu pédagogique (astuces, mini-grammaire, …).
     * Toute autre valeur de rôle est refusée (liste blanche stricte).
     */
    public const EDITOR_ROLES = ['admin', 'enseignant'];

    /**
     * Rôles autorisés à administrer les comptes utilisateurs.
     * Réservé à l'administration : ces droits ne sont jamais délégués aux enseignants.
     */
    public const ADMIN_ROLES = ['admin'];

    /**
     * Nom du cookie portant le jeton de connexion persistante.
     * Le cookie est posé par l'API F3 (`set('COOKIE.…')`) : il hérite
     * automatiquement du chemin, de l'indicateur « secure », de « httponly »
     * et de « samesite » du cookie de session.
     */
    public const SOUVENIR_COOKIE = 'mg_souvenir';

    /**
     * Méthode exécutée automatiquement par F3 avant chaque route.
     * Récupère l'utilisateur en session, définit son rôle globalement
     * et garantit la présence d'un jeton CSRF.
     *
     * @param \Base $f3 Instance du framework
     */
    public function beforeroute(\Base $f3) {
        $user = null;
        $userRole = 'invite'; // Rôle par défaut pour les utilisateurs non connectés

        // Sans session active, le cookie « Se souvenir de moi » permet de
        // retrouver le compte : c'est l'équivalent d'une reconnexion silencieuse.
        if (!$f3->exists('SESSION.user_id') && !$f3->exists('SESSION.user')) {
            $user = $this->restaurerSouvenir($f3);
        }

        // Recherche de l'utilisateur basé sur l'ID ou le nom d'utilisateur en session
        if ($user === null && $f3->exists('SESSION.user_id')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->getById($f3->get('SESSION.user_id'));
        } elseif ($user === null && $f3->exists('SESSION.user')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->findByUsername($f3->get('SESSION.user'));
        }

        if ($user) {
            $userRole = $user['role'] ?? 'etudiant';
            $f3->set('user', $user);

            // Un compte désactivé (suppression logique) ne conserve pas sa session
            if (User::softDeleteSupported() && User::isSoftDeleted($user)) {
                $f3->clear('SESSION.user');
                $f3->clear('SESSION.user_id');
                $userRole = 'invite';
                $f3->set('SESSION.flash', [
                    'type' => 'error',
                    'message' => 'Votre compte a été désactivé par un administrateur.'
                ]);
            }
        }

        // Met le rôle à disposition de tous les contrôleurs et vues
        $f3->set('userRole', $userRole);
        $f3->set('isAdmin', in_array($userRole, self::ADMIN_ROLES, true));
        $f3->set('canDashboard', in_array($userRole, self::EDITOR_ROLES, true));

        // Journal des requêtes : l'utilisateur est mémorisé pour éviter une
        // requête SQL supplémentaire au moment de l'écriture (voir RequestLog).
        RequestLog::setUtilisateur(
            $user ? (int)$user['id'] : null,
            $user ? (string)($user['username'] ?? '') : '',
            $userRole
        );

        // Jeton CSRF partagé (formulaires + requêtes AJAX)
        $f3->set('csrfToken', $this->csrfToken($f3));

        // Message flash : consommé ici pour être affiché une seule fois, sur n'importe quelle page
        $flash = $f3->get('SESSION.flash');
        $f3->clear('SESSION.flash');
        $f3->set('flash', is_array($flash) ? $flash : null);
    }

    /**
     * Réétablit la session d'un visiteur depuis son cookie « Se souvenir de moi ».
     * Appelé avant chaque route lorsque aucune session n'est active : c'est ce qui
     * permet de rester connecté après la fermeture du navigateur. Un cookie absent,
     * inconnu, expiré ou appartenant à un compte désactivé est simplement ignoré
     * (et purgé côté navigateur).
     *
     * @param \Base $f3 Instance du framework
     * @return array|null Données du compte restauré, ou null
     */
    protected function restaurerSouvenir(\Base $f3)
    {
        if (!User::rememberSupported()) {
            return null;
        }

        $token = $f3->get('COOKIE.' . self::SOUVENIR_COOKIE);
        if (!is_string($token) || $token === '') {
            return null;
        }

        $user = (new User($f3->get('DB')))->validerSouvenir($token);

        // Jeton périmé : on retire le cookie pour ne pas le renvoyer à chaque page
        if ($user === null) {
            if (!headers_sent()) {
                $f3->clear('COOKIE.' . self::SOUVENIR_COOKIE);
            }
            return null;
        }

        $f3->set('SESSION.user', $user['username']);
        $f3->set('SESSION.user_id', (int)$user['id']);
        // Nouvel identifiant de session : la reconnexion silencieuse est une
        // authentification à part entière (protection contre la fixation de session)
        self::regenererSession();

        return $user;
    }

    /**
     * Pose le cookie de connexion persistante pour un compte connecté.
     *
     * @param \Base $f3 Instance du framework
     * @param int $userId Identifiant du compte
     * @return void
     */
    protected function poserSouvenir(\Base $f3, int $userId)
    {
        if (!User::rememberSupported()) {
            return;
        }

        $token = (new User($f3->get('DB')))->creerSouvenir($userId);
        if ($token === null || headers_sent()) {
            return;
        }

        $f3->set('COOKIE.' . self::SOUVENIR_COOKIE, $token, User::REMEMBER_JOURS * 86400);
    }

    /**
     * Révoque la connexion persistante : le jeton est effacé en base et le cookie
     * supprimé. Appelé à la déconnexion et à chaque changement de mot de passe.
     *
     * @param \Base $f3 Instance du framework
     * @param int|null $userId Identifiant du compte, si connu
     * @return void
     */
    protected function oublierSouvenir(\Base $f3, $userId = null)
    {
        if ($userId !== null && User::rememberSupported()) {
            (new User($f3->get('DB')))->oublierSouvenir((int)$userId);
        }

        if (!headers_sent()) {
            $f3->clear('COOKIE.' . self::SOUVENIR_COOKIE);
        }
    }

    /**
     * Régénère l'identifiant de session après une authentification.
     * Empêche la réutilisation d'un identifiant de session fixé par un tiers.
     *
     * @return void
     */
    protected static function regenererSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Récupère (ou génère) le jeton CSRF stocké en session.
     *
     * @param \Base $f3 Instance du framework
     * @return string Jeton CSRF
     */
    public static function getSessionCsrfToken(\Base $f3): string {
        $token = $f3->get('SESSION.csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $f3->set('SESSION.csrf_token', $token);
        }
        return $token;
    }

    /**
     * Récupère (ou génère) le jeton CSRF stocké en session.
     *
     * @param \Base $f3 Instance du framework
     * @return string Jeton CSRF
     */
    protected function csrfToken(\Base $f3): string {
        return self::getSessionCsrfToken($f3);
    }

    /**
     * Valide le jeton CSRF reçu (champ POST ou en-tête X-CSRF-Token pour l'AJAX).
     *
     * @param \Base $f3 Instance du framework
     * @return bool True si le jeton est valide.
     */
    protected function csrfValidate(\Base $f3): bool {
        $expected = $f3->get('SESSION.csrf_token');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $sent = $f3->get('POST.csrf_token');
        if (!is_string($sent) || $sent === '') {
            $sent = '';
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strtolower((string)$name) === 'x-csrf-token') {
                        $sent = $value;
                        break;
                    }
                }
            }
        }
        return is_string($sent) && hash_equals($expected, $sent);
    }

    /**
     * Raccourci de validation CSRF pour les actions POST : en cas d'échec,
     * pose un message flash et redirige vers la page de repli indiquée.
     *
     * @param \Base $f3 Instance du framework
     * @param string $redirectTo Route interne de repli (ex: '/dashboard/astuces')
     * @return bool True si le jeton est valide (l'action peut poursuivre)
     */
    protected function csrfGuard(\Base $f3, string $redirectTo): bool
    {
        if ($this->csrfValidate($f3)) {
            return true;
        }
        $f3->set('SESSION.flash', [
            'type' => 'error',
            'message' => 'Jeton de sécurité invalide. Veuillez réessayer.'
        ]);
        $f3->reroute($redirectTo);
        return false;
    }

    /**
     * Valide si l'utilisateur connecté possède l'un des rôles requis.
     * Redirige vers la page de connexion ou les astuces en cas d'échec.
     *
     * @param \Base $f3 Instance du framework
     * @param string|array $roles Rôle(s) requis (ex: 'admin' ou ['admin', 'enseignant'])
     */
    protected function requireRole(\Base $f3, $roles) {
        $roles = (array)$roles;

        // Redirection vers login si aucune session n'est active
        if (!$f3->exists('SESSION.user_id') && !$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        // Vérification du rôle stocké dans le hive de F3
        if (!in_array($f3->get('userRole'), $roles, true)) {
            $f3->set('SESSION.flash', [
                'type' => 'error',
                'message' => 'Accès refusé. Vous n\'avez pas les permissions nécessaires pour effectuer cette action.'
            ]);
            $f3->reroute('/astuces');
        }
    }
}