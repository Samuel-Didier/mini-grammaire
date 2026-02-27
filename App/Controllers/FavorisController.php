<?php

namespace App\Controllers;

use App\Models\Favori;
use App\Models\User;

class FavorisController
{
    /**
     * Ajoute ou retire une astuce des favoris (Toggle)
     * Route: POST /favori/toggle/@id
     */
    public function toggle(\Base $f3, $params)
    {
        // Vérifier si l'utilisateur est connecté
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        $astuce_id = (int)$params['id'];
        $username = $f3->get('SESSION.user');

        // Récupérer l'ID de l'utilisateur via son username
        $userModel = new User($f3->get('DB'));
        $user = $userModel->findByUsername($username);

        if (!$user) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
            exit;
        }

        $user_id = $user['id'];

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
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        $username = $f3->get('SESSION.user');
        $userModel = new User($f3->get('DB'));
        $user = $userModel->findByUsername($username);

        if (!$user) {
            $f3->reroute('/logout');
            return;
        }

        // Récupérer les astuces favorites
        $favoriModel = new Favori($f3->get('DB'));
        $favoris = $favoriModel->getFavorisByUser($user['id']);

        // Passer les données à la vue
        $f3->set('favoris', $favoris);
        $f3->set('count', count($favoris));
        $f3->set('title', 'Mes Astuces Favorites');

        $content = $tpl->render('pages/mes-favoris.html');

        // Rendu
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
}
