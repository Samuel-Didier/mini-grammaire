<?php

namespace App\Controllers;

use App\Models\Favori;

class FavorisController extends BaseController
{
    /**
     * Ajoute ou retire une astuce des favoris (Toggle)
     * Route: POST /favori/toggle/@id
     */
    public function toggle(\Base $f3, $params)
    {
        header('Content-Type: application/json');

        // Vérifier si l'utilisateur est connecté
        if (!$f3->exists('SESSION.user') && !$f3->exists('SESSION.user_id')) {
            echo json_encode([
                'success' => false,
                'error' => 'AUTH_REQUIRED',
                'message' => 'Vous devez être connecté pour effectuer cette action.'
            ]);
            exit;
        }

        // Protection CSRF (jeton envoyé dans l'en-tête X-CSRF-Token)
        if (!$this->csrfValidate($f3)) {
            echo json_encode([
                'success' => false,
                'error' => 'CSRF_INVALID',
                'message' => 'Jeton de sécurité invalide.'
            ]);
            exit;
        }

        $astuce_id = (int)$params['id'];

        // L'utilisateur est déjà chargé par BaseController::beforeroute
        $user = $f3->get('user');
        if (!$user) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
            exit;
        }

        $user_id = (int)$user['id'];

        // Instancier le modèle Favori
        $favoriModel = new Favori($f3->get('DB'));

        // Toggle le favori
        $action = $favoriModel->toggle($user_id, $astuce_id);

        // Retourner la réponse JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'action' => $action, // 'added' ou 'removed'
            'message' => ($action === 'added') ? 'Ajouté aux favoris' : 'Retiré des favoris'
        ]);
        exit;
    }

    /**
     * Affiche la page "Mes Favoris"
     * Route: GET /mes-favoris
     */
    public function mesFavoris(\Base $f3)
    {
        $tpl = \Template::instance();

        // Vérification connexion
        if (!$f3->exists('SESSION.user') && !$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }

        $user = $f3->get('user');
        if (!$user) {
            $f3->reroute('/logout');
            return;
        }

        // Récupérer les astuces favorites
        $favoriModel = new Favori($f3->get('DB'));
        $favoris = $favoriModel->getFavorisByUser((int)$user['id']);

        // Passer les données à la vue
        $f3->set('favoris', $favoris);
        $f3->set('count', count($favoris));
        $f3->set('title', 'Mes Astuces Favorites');

        $content = $tpl->render('pages/astuces/mes-favoris.html');

        // Rendu
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
}