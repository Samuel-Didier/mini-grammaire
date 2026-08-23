<?php
namespace App\Controllers;
use App\Models\User;
use App\Models\Favori;
use App\Models\Progression;
use App\Models\Streak;

class Auth extends BaseController {
    public function login(\Base $f3) {
        $tpl = \Template::instance(); $errors = [];
        if ($f3->get('VERB') === 'POST') {
            $userModel = new User($f3->get('DB'));
            $result = $userModel->login(trim($f3->get('POST.username')), $f3->get('POST.password'));
            if (is_array($result)) {
                $f3->set('SESSION.user', $result['username']);
                $f3->set('SESSION.user_id', $result['id']);
                $f3->reroute('/profile'); return;
            }
            $errors[] = $result;
        }
        $f3->set('errors', $errors); $f3->set('title', 'Connexion');
        $f3->set('content', $tpl->render('pages/auth/login.html')); echo $tpl->render('layout.html');
    }

    public function logout($f3) { $f3->clear('SESSION'); $f3->reroute('/'); }

    public function register(\Base $f3) {
        $tpl = \Template::instance(); $errors = [];
        if ($f3->get('VERB') === 'POST') {
            $userModel = new User($f3->get('DB'));
            $result = $userModel->registerUser([
                'nom'=>trim($f3->get('POST.name')), 'prenom'=>trim($f3->get('POST.name2')),
                'username'=>trim((string)$f3->get('POST.username')), 'email'=>trim($f3->get('POST.email')),
                'password'=>trim((string)$f3->get('POST.password')), 'confirm_password'=>trim((string)$f3->get('POST.confirm_password'))
            ]);
            if (isset($result['user'])) {
                $f3->set('SESSION.user', $result['user']['username']); $f3->set('SESSION.user_id', $result['user']['id']);
                $f3->reroute('/profile'); return;
            }
            $errors = $result['errors'];
        }
        $f3->set('errors',$errors); $f3->set('title', "S'inscrire");
        $f3->set('content',$tpl->render('pages/auth/register.html')); echo $tpl->render('layout.html');
    }

    public function profil(\Base $f3) {
        $tpl = \Template::instance();
        if (!$f3->exists('SESSION.user_id')) { $f3->reroute('/login'); return; }
        $userId = (int)$f3->get('SESSION.user_id');
        $db = $f3->get('DB'); $userData = (new User($db))->getById($userId);
        if (!$userData) { $f3->reroute('/logout'); return; }

        $streakData = (new Streak($db))->recordActivity($userId);
        $f3->set('SESSION.streak', $streakData['streak']);
        $f3->set('SESSION.freezes', $streakData['freezes']);
        $f3->set('streak', $streakData['streak']);
        $f3->set('freezes', $streakData['freezes']);
        $f3->set('freezeUsed', $streakData['freeze_used']);

        $favorisCount = count((new Favori($db))->getFavorisByUser($userId));
        $progression = (new Progression($db))->getByUser($userId);
        $f3->mset(['user'=>$userData['username'], 'client'=>$userData, 'favorisCount'=>$favorisCount,
            'niveau'=>$progression ? $progression['niveau_global'] : 'Non évalué',
            'score'=>$progression ? $progression['score_test_initial'].'/10' : '-', 'title'=>'Mon Profil']);
        if ($f3->exists('SESSION.flash')) { $f3->set('flash',$f3->get('SESSION.flash')); $f3->clear('SESSION.flash'); }
        $f3->set('content',$tpl->render('pages/auth/profile.html')); echo $tpl->render('layout.html');
    }

    public function editProfile(\Base $f3) {
        $tpl=\Template::instance(); if (!$f3->exists('SESSION.user_id')) {$f3->reroute('/login');return;}
        $clientData=(new User($f3->get('DB')))->getById($f3->get('SESSION.user_id'));
        if (!$clientData) {$f3->reroute('/logout');return;}
        $f3->set('client',$clientData);$f3->set('title','Modifier mon Profil');$f3->set('content',$tpl->render('pages/auth/profile_edit.html'));echo $tpl->render('layout.html');
    }

    public function updateProfile(\Base $f3) {
        $tpl=\Template::instance(); if (!$f3->exists('SESSION.user_id')) {$f3->reroute('/login');return;}
        $userId=$f3->get('SESSION.user_id');$userModel=new User($f3->get('DB'));$errors=[];
        if ($f3->get('VERB')==='POST') {
            $result=$userModel->updateProfile($userId,['nom'=>trim($f3->get('POST.nom')),'prenom'=>trim($f3->get('POST.prenom')),'username'=>trim($f3->get('POST.username')),'email'=>trim($f3->get('POST.email')),'password'=>$f3->get('POST.password'),'confirm_password'=>$f3->get('POST.confirm_password')]);
            if (isset($result['success'])) {$f3->set('SESSION.user',$result['user']['username']);$f3->set('SESSION.flash',['type'=>'success','message'=>'Profil mis à jour.']);$f3->reroute('/profile');return;}
            $errors=$result['errors'];
        }
        $f3->set('client',$userModel->getById($userId));$f3->set('errors',$errors);$f3->set('title','Modifier mon Profil');$f3->set('content',$tpl->render('pages/auth/profile_edit.html'));echo $tpl->render('layout.html');
    }
}
