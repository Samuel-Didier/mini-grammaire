<?php

namespace App\Controllers;

use App\Models\Astuces;
use App\Models\Favori;
use App\Models\User;

class AstucesController {

    public function getAstuces(\Base $f3) {
        $tpl = \Template::instance();
        // 1. Récupérer les données depuis le modèle
        $astucesModel = new Astuces($f3->get('DB'));
        $allAstuces = $astucesModel->getAll();

        // 2. Vérifier si l'utilisateur est connecté pour marquer les favoris
        if ($f3->exists('SESSION.user')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->findByUsername($f3->get('SESSION.user'));

            if ($user) {
                $favoriModel = new Favori($f3->get('DB'));
                foreach ($allAstuces as &$astuce) {
                    $astuce['is_favori'] = $favoriModel->isFavori($user['id'], $astuce['id']);
                }
            }
        }

        // 3. Passer les données récupérées au framework
        $f3->set('astuces', $allAstuces);
        $content = $tpl->render('pages/astuces.html');

        // 4. Définir le titre de la page
        $f3->set('title', 'Astuces de Français');

        // 5. Définir le fichier de contenu à utiliser dans le layout
        $f3->set('content', $content);

        // 6. Rendre le layout principal
        echo $tpl->render('layout.html');
    }
}
