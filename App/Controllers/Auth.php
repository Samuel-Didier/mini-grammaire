<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Favori;
use App\Models\Progression;
use App\Models\ConsecutiveDays;

class Auth extends BaseController
{
    public function login(\Base $f3)
    {
        $tpl = \Template::instance();
        $errors = [];
        if ($f3->get('VERB') === 'POST') {
            $r = (new User($f3->get('DB')))->login(trim($f3->get('POST.username')), $f3->get('POST.password'));
            if (is_array($r)) {
                $f3->set('SESSION.user', $r['username']);
                $f3->set('SESSION.user_id', $r['id']);
                // Connexion persistante demandée : le cookie permet de retrouver
                // le compte après la fermeture du navigateur
                if (!empty($f3->get('POST.souvenir'))) {
                    $this->poserSouvenir($f3, (int)$r['id']);
                }
                self::regenererSession();
                $f3->reroute('/profile');
                return;
            }
            $errors[] = $r;
        }
        $f3->set('errors', $errors);
        $f3->set('souvenirDispo', User::rememberSupported());

        // Messages flash de réinitialisation de mot de passe
        $forgot_success = $f3->get('SESSION.forgot_success');
        $forgot_errors  = $f3->get('SESSION.forgot_errors');
        $f3->clear('SESSION.forgot_success');
        $f3->clear('SESSION.forgot_errors');
        $f3->set('forgot_success', $forgot_success);
        $f3->set('forgot_errors', $forgot_errors);

        $f3->set('title', 'Connexion');
        $f3->set('content', $tpl->render('pages/auth/login.html'));
        echo $tpl->render('layout.html');
    }

    public function logout($f3)
    {
        // La déconnexion révoque aussi la connexion persistante : le cookie
        // « Se souvenir de moi » ne doit pas ressusciter la session
        $this->oublierSouvenir($f3, $f3->get('SESSION.user_id'));
        $f3->clear('SESSION');
        $f3->reroute('/');
    }

    public function forgotPasswordProcess(\Base $f3)
    {
        $errors = [];
        if ($f3->get('VERB') === 'POST') {
            $email = trim($f3->get('POST.email'));
            if ($email === '') {
                $errors[] = "L'adresse email est requise.";
            } else {
                $userModel = new User($f3->get('DB'));
                $user = $userModel->findByMail($email);
                if ($user) {
                    $temporary = $userModel->resetPassword((int)$user['id']);
                    if ($temporary !== null) {
                        $f3->set('SESSION.forgot_success', 'Votre mot de passe temporaire : <strong>' . htmlspecialchars($temporary) . '</strong>. Connectez-vous puis changez-le dans votre profil.');
                        $f3->reroute('/login');
                        return;
                    }
                }
                $errors[] = "Aucun compte ne correspond à cette adresse email.";
            }
        }
        $f3->set('SESSION.forgot_errors', $errors);
        $f3->reroute('/login');
    }

    public function register(\Base $f3)
    {
        $tpl = \Template::instance();
        $errors = [];
        $errors_password = [];
        if ($f3->get('VERB') === 'POST') {
            $r = (new User($f3->get('DB')))->registerUser(['nom' => trim($f3->get('POST.name')), 'prenom' => trim($f3->get('POST.name2')), 'username' => trim((string)$f3->get('POST.username')), 'email' => trim($f3->get('POST.email')), 'password' => trim((string)$f3->get('POST.password')), 'confirm_password' => trim((string)$f3->get('POST.confirm_password'))]);
            if (isset($r['user'])) {
                $f3->set('SESSION.user', $r['user']['username']);
                $f3->set('SESSION.user_id', $r['user']['id']);
                $f3->reroute('/profile');
                return;
            }
            $errors = is_array($r['errors'] ?? null) ? $r['errors'] : [];
            $errors_password = is_array($r['errors_password'] ?? null) ? $r['errors_password'] : [];
        }
        $f3->set('errors', $errors);
        $f3->set('errors_password', $errors_password);
        $f3->set('title', "S'inscrire");
        $f3->set('content', $tpl->render('pages/auth/register.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Catalogue d'avatars enrichi pour les vues : chaque entrée associe
     * l'identifiant stocké en base et l'émoji à afficher.
     *
     * @return array<int,array{id:string,emoji:string}>
     */
    private static function avatarsForView(): array
    {
        $out = [];
        foreach (User::avatars() as $id) {
            $out[] = ['id' => $id, 'emoji' => User::avatarEmoji($id)];
        }
        return $out;
    }

    /**
     * Initiales affichées quand aucun avatar n'est choisi.
     *
     * @param array $client Ligne utilisateur.
     * @return string
     */
    private static function initialsFor(array $client): string
    {
        return strtoupper(substr($client['prenom'] ?? '', 0, 1) . substr($client['nom'] ?? '', 0, 1)) ?: '👤';
    }

    public function profil(\Base $f3)
    {
        $tpl = \Template::instance();
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }
        $id = (int)$f3->get('SESSION.user_id');
        $db = $f3->get('DB');
        $client = (new User($db))->getById($id);
        if (!$client) {
            $f3->reroute('/logout');
            return;
        }
        try {
            $days = (new ConsecutiveDays($db))->recordActivity($id);
        } catch (\Throwable $e) {
            $days = 1;
        }
        $f3->set('SESSION.consecutive_days', $days);
        $favorisCount = count((new Favori($db))->getFavorisByUser($id));
        $p = (new Progression($db))->getByUser($id);
        $f3->mset(['user' => $client['username'], 'client' => $client, 'favorisCount' => $favorisCount, 'niveau' => $p ? $p['niveau_global'] : 'Non évalué', 'score' => $p ? $p['score_test_initial'] . '/10' : '-', 'title' => 'Mon Profil', 'avatars' => self::avatarsForView()]);
        $f3->set('initials', self::initialsFor($client));
        $f3->set('avatarEmoji', User::avatarEmoji($client['image'] ?? ''));
        $f3->set('client', $client);
        $f3->set('content', $tpl->render('pages/auth/profile.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Met à jour l'avatar choisi dans le catalogue, sans rechargement de page.
     * Accessible à tout utilisateur connecté ; uniquement un nom de fichier du
     * catalogue (liste blanche côté serveur) peut être enregistré — jamais un
     * chemin ou un fichier fourni par le client.
     *
     * @param \Base $f3 Instance du framework
     */
    public function updateAvatar(\Base $f3)
    {
        if (!$f3->exists('SESSION.user_id')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Non connecté.']);
            return;
        }
        if (!$this->csrfValidate($f3)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Jeton de sécurité invalide.']);
            return;
        }

        $fileName = (string)$f3->get('POST.avatar');
        $ok = (new User($f3->get('DB')))->setAvatar((int)$f3->get('SESSION.user_id'), $fileName);
        if (!$ok) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Avatar invalide.']);
            return;
        }

        if ($fileName === '') {
            echo json_encode(['success' => true, 'avatar' => null]);
            return;
        }

        $name = User::normalizeAvatar($fileName);
        echo json_encode([
            'success' => true,
            'avatar' => $name,
            'emoji' => User::avatarEmoji($name),
        ]);
    }

    public function editProfile(\Base $f3)
    {
        $tpl = \Template::instance();
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }
        $c = (new User($f3->get('DB')))->getById($f3->get('SESSION.user_id'));
        if (!$c) {
            $f3->reroute('/logout');
            return;
        }
        $f3->set('client', $c);
        $f3->set('initials', self::initialsFor($c));
        $f3->set('avatars', self::avatarsForView());
        $f3->set('title', 'Modifier mon Profil');
        $f3->set('content', $tpl->render('pages/auth/profile_edit.html'));
        echo $tpl->render('layout.html');
    }

    public function updateProfile(\Base $f3)
    {
        $tpl = \Template::instance();
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }
        $id = $f3->get('SESSION.user_id');
        $m = new User($f3->get('DB'));
        $errors = [];
        if ($f3->get('VERB') === 'POST') {
            if (!$this->csrfValidate($f3)) {
                $f3->set('SESSION.flash', [
                    'type' => 'error',
                    'message' => 'Jeton de sécurité invalide. Veuillez réessayer.'
                ]);
                $f3->reroute('/profile/edit');
                return;
            }
            $avatar = (new User($f3->get('DB')))->setAvatar((int)$id, (string)$f3->get('POST.avatar'));
            $r = $m->updateProfile($id, ['nom' => trim($f3->get('POST.nom')), 'prenom' => trim($f3->get('POST.prenom')), 'username' => trim($f3->get('POST.username')), 'email' => trim($f3->get('POST.email')), 'password' => $f3->get('POST.password'), 'confirm_password' => $f3->get('POST.confirm_password')]);
            if (isset($r['success'])) {
                // Le mot de passe a changé : le modèle a révoqué le jeton en base,
                // on retire donc aussi le cookie (la session, elle, reste valide)
                if (!empty($f3->get('POST.password'))) {
                    $this->oublierSouvenir($f3, null);
                }
                $f3->set('SESSION.user', $r['user']['username']);
                $f3->reroute('/profile');
                return;
            }
            $errors = $r['errors'];
        }
        $client = $m->getById($id);
        $f3->set('client', $client);
        $f3->set('errors', $errors);
        // La vue contient la grille d'avatars et la modale (partial commun) :
        // ces variables doivent être fournies aussi sur cette page de
        // ré-affichage, après une erreur de validation.
        $f3->set('avatars', self::avatarsForView());
        $f3->set('initials', self::initialsFor((array)$client));
        $f3->set('title', 'Modifier mon Profil');
        $f3->set('content', $tpl->render('pages/auth/profile_edit.html'));
        echo $tpl->render('layout.html');
    }
}