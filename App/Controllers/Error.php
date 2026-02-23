<?php

namespace App\Controllers;
final class Error
{
    public function handle(\Base $f3): void
    {
// 1) Statut HTTP (sans renvoyer d’en-têtes en double)
        $code = (int)($f3->get('ERROR.code') ?: 500);
        $status = (string)($f3->get('ERROR.status') ?: 'Error');
        $text = trim((string)$f3->get('ERROR.text'));
        if (!headers_sent()) {
            http_response_code($code);
        }
// 2) Log minimal (évite tout nouveau rendu)
        error_log(sprintf('[%s] %d %s — %s',
            date('c'), $code, $status, $text
        ));
// 4) HTML : composer un fragment + layout
        $view = \Template::instance();
// Choix du fragment (404 dédié, sinon générique)
        $fragment = ($code === 404) ? 'pages/404.html' :
            'pages/error.html';
// Variables exposées à la vue
        $f3->set('title', "$code – $status");
        $f3->set('error_code', $code);
        $f3->set('error_status', $status);
        $f3->set('error_text', $text);
// Rendu “safe” du fragment (sans includes lourds)
        $content = '';
        if (is_file($f3->get('UI') . $fragment)) {
            $content = $view->render($fragment, 'text/html');
        } else {
// Filet de sécurité si le fichier manque
            $content = '<section class="wrapper"><div class="inner">'
                . "<h1>$status ($code)</h1><p>" .
                htmlspecialchars($text) . '</p>'
                . '</div></section>';
        }
        $f3->set('content', $content);
// Un seul rendu du layout
        echo $view->render('layout.html', 'text/html');
    }
}
