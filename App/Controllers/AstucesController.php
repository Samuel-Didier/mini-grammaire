<?php

namespace App\Controllers;

use App\Models\Astuces;

class AstucesController {

    public function getAstuces(\Base $f3) {
        // 1. Récupérer les données depuis le modèle
        $astucesModel = new Astuces($f3->get('DB'));
        $allAstuces = $astucesModel->getAll();

        // 2. Passer les données récupérées au framework pour qu'elles soient accessibles dans la vue
        $f3->set('astuces', $allAstuces);
        
        // 3. Définir le titre de la page
        $f3->set('title', 'Astuces de Français');

        // 4. Définir le fichier de contenu à utiliser dans le layout
        $f3->set('content', 'pages/astuces.html');

        // 5. Rendre le layout principal, qui inclura le contenu avec les données
        echo \Template::instance()->render('layout.html');
    }

    // Cette méthode semble être un doublon, je la laisse commentée pour l'instant
    /*
    public function astuces(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/astuces.html');
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
    */
}
