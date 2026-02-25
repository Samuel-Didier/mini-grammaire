<?php

namespace App\Controllers;

use App\Models\Astuces;

class AstucesController{
    public function getAstuces($f3) {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/astuces.html');
        // On récupère l'instance de la BD enregistrée dans F3
        $astucesModel = new Astuces($f3->get('DB'));

        $astuces = $astucesModel->getAll();
        $f3->set('astuces', $astuces);
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');


//        echo json_encode($astuces);
    }
    // Page des astuces
    public function astuces(\Base $f3)
    {
        $tpl = \Template::instance();
        $content = $tpl->render('pages/astuces.html');
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
}
