<?php
namespace App\Controllers;
use App\Models\User;
use App\Models\Favori;
use App\Models\Progression;

/**
 * Classe Auth
 *
 * Cette classe gère l'authentification et la gestion des comptes utilisateurs.
 * Elle délègue la logique métier au modèle User.
 *
 * @package App\Controllers
 */
class Auth extends BaseController {

    /**
     * Gère la connexion des utilisateurs.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function login(\Base $f3)
    {
        $tpl = \Template::instance();
        $errors = [];

        if ($f3->get('VERB') === 'POST') {
            $identifier = trim($f3->get('POST.username')); // nom d'utilisateur ou courriel
            $password = $f3->get('POST.password');
            
            $userModel = new User($f3->get('DB'));
            $result = $userModel->login($identifier, $password);

            if (is_array($result)) {
                // Succès : initialisation de la session
                $f3->set('SESSION.user', $result['username']);
                $f3->set('SESSION.user_id', $result['id']);
                $f3->reroute('/profile');
                return;
            } else {
                // Erreur : message retourné par le modèle
                $errors[] = $result;
            }
        }

        $f3->set('errors', $errors);
        $f3->set('title', 'Connexion');
        $f3->set('content', $tpl->render('pages/auth/login.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Gère la déconnexion de l'utilisateur.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function logout($f3) {
        $f3->clear('SESSION');
        $f3->reroute('/');
    }

    /**
     * Gère l'inscription de nouveaux utilisateurs.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function register(\Base $f3)
    {
        $tpl = \Template::instance();
        $errors = [];

        if ($f3->get('VERB') === 'POST') {
            $userModel = new User($f3->get('DB'));
            $registrationData = [
                'nom' => trim($f3->get('POST.name')),
                'prenom' => trim($f3->get('POST.name2')),
                'username' => trim((string)$f3->get('POST.username')),
                'email' => trim($f3->get('POST.email')),
                'password' => trim((string)$f3->get('POST.password')),
                'confirm_password' => trim((string)$f3->get('POST.confirm_password'))
            ];

            $result = $userModel->registerUser($registrationData);

            if (isset($result['user'])) {
                // Inscription réussie : connexion automatique
                $f3->set('SESSION.user', $result['user']['username']);
                $f3->set('SESSION.user_id', $result['user']['id']);
                $f3->reroute('/profile');
                return;
            } else {
                $errors = $result['errors'];
            }
        }

        $f3->set('errors', $errors);
        $f3->set('title', "S'inscrire");
        $f3->set('content', $tpl->render('pages/auth/register.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le profil de l'utilisateur connecté.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function profil(\Base $f3) {
        $tpl = \Template::instance();
        
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }

        $userId = $f3->get('SESSION.user_id');
        $userModel = new User($f3->get('DB'));
        $userData = $userModel->getById($userId);

        if (!$userData) {
            $f3->reroute('/logout');
            return;
        }

        // Récupération des favoris et progression
        $favoriModel = new Favori($f3->get('DB'));
        $favorisCount = count($favoriModel->getFavorisByUser($userId));

        $progressionModel = new Progression($f3->get('DB'));
        $progression = $progressionModel->getByUser($userId);

        $f3->mset([
            'user' => $userData['username'],
            'client' => $userData,
            'favorisCount' => $favorisCount,
            'niveau' => $progression ? $progression['niveau_global'] : 'Non évalué',
            'score' => $progression ? $progression['score_test_initial'] . '/10' : '-',
            'title' => 'Mon Profil'
        ]);

        if ($f3->exists('SESSION.flash')) {
            $f3->set('flash', $f3->get('SESSION.flash'));
            $f3->clear('SESSION.flash');
        }

        $f3->set('content', $tpl->render('pages/auth/profile.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le formulaire d'édition du profil.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function editProfile(\Base $f3) {
        $tpl = \Template::instance();
        
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }

        $userModel = new User($f3->get('DB'));
        $clientData = $userModel->getById($f3->get('SESSION.user_id'));

        if (!$clientData) {
            $f3->reroute('/logout');
            return;
        }

        $f3->set('client', $clientData);
        $f3->set('title', 'Modifier mon Profil');
        $f3->set('content', $tpl->render('pages/auth/profile_edit.html'));
        echo $tpl->render('layout.html');
    }

    /**
     * Traite la mise à jour du profil.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function updateProfile(\Base $f3) {
        $tpl = \Template::instance();
        
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }

        $userId = $f3->get('SESSION.user_id');
        $userModel = new User($f3->get('DB'));
        $errors = [];

        if ($f3->get('VERB') === 'POST') {
            $updateData = [
                'nom' => trim($f3->get('POST.nom')),
                'prenom' => trim($f3->get('POST.prenom')),
                'username' => trim($f3->get('POST.username')),
                'email' => trim($f3->get('POST.email')),
                'password' => $f3->get('POST.password'),
                'confirm_password' => $f3->get('POST.confirm_password')
            ];

            $result = $userModel->updateProfile($userId, $updateData);

            if (isset($result['success'])) {
                $f3->set('SESSION.user', $result['user']['username']);
                $f3->set('SESSION.flash', ['type' => 'success', 'message' => 'Profil mis à jour.']);
                $f3->reroute('/profile');
                return;
            } else {
                $errors = $result['errors'];
            }
        }

        $f3->set('client', $userModel->getById($userId));
        $f3->set('errors', $errors);
        $f3->set('title', 'Modifier mon Profil');
        $f3->set('content', $tpl->render('pages/auth/profile_edit.html'));
        echo $tpl->render('layout.html');
    }
}
