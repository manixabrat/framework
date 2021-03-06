<?php

namespace Manix\Brat\Utility\Users\Controllers;

use Exception;
use Manix\Brat\Components\Forms\Form;
use Manix\Brat\Components\Validation\Ruleset;
use Manix\Brat\Helpers\FormController;
use Manix\Brat\Utility\Captcha\CaptchaManager;
use Manix\Brat\Utility\Users\Models\User;
use Manix\Brat\Utility\Users\Models\UserEmail;
use Project\Traits\Users\UserGatewayFactory;
use Manix\Brat\Utility\Users\Views\RegisterSuccessView;
use Manix\Brat\Utility\Users\Views\RegisterView;
use Manix\Brat\Utility\Users\Models\Auth;

class Register extends FormController {

  use UserGatewayFactory,
      Mailer;

  public $page = RegisterView::class;
  protected $captcha;

  public function before($method) {
    $this->captcha = new CaptchaManager();

    return parent::before($method);
  }

  /**
   * Construct the register form.
   * @return Form
   */
  protected function constructForm(Form $form): Form {
    $form->add('email', 'email');
    $form->add('password', 'password');
    $form->add('name', 'text');
    $form->add('captcha', 'text');
    $form->add('', 'submit', $this->t8('manix/util/users/common', 'register'))
    ->setAttribute('class', 'btn btn-primary');

    return $form;
  }

  /**
   * Define the view to display after successful registration.
   * @return FQCN
   */
  protected function getSuccessView() {
    return RegisterSuccessView::class;
  }

  /**
   * Define the rules for registration.
   * @return Ruleset
   */
  protected function constructRules(Ruleset $rules): Ruleset {
    $rules->add('email')->required()->email();
    $rules->add('password')->required()->length(8, 255);
    $rules->add('name')->required()->alphabeticX('\' -');
    $rules->add('captcha')->required()->callback([$this->captcha, 'validate']);

    return $rules;
  }

  public function get() {
    if (Auth::user()) {
      $url = $_SESSION['backto'] ?? $_GET['b'] ?? SITE_URL;
      unset($_SESSION['backto']);
      new Redirect($url);
    }

    return [
        'form' => $this->getForm(),
        'captcha' => $this->captcha
    ];
  }

  public function post() {

    return $this->validate($_POST, function($data, $v) {
      $egate = $this->getEmailGateway();
      $existing = $egate->find($data['email']);

      if ($existing->count()) {
        $v->setError('email', $this->t8('manix/util/users/common', 'emailTaken'));
      } else {

        $ugate = $this->getUserGateway();

        $user = $ugate->instantiate([$data], false);
        $user->setPassword($data['password']);

        if (!$ugate->persist($user)) {
          throw new Exception('Unexpected', 500);
        }

        $email = $egate->instantiate([
            [
                'user_id' => $user->id,
                'email' => $data['email']
            ]
        ], false);

        $email->invalidate();

        $this->captcha->expire();

        if ($egate->persist($email) && $this->sendActivationMail($email)) {
          $this->page = $this->getSuccessView();

          $this->onUserRegistered($user, $email);

          return true;
        }
      }

      return $this->defaultFailAction($data, $v);
    });
  }

  /**
   * Gets called when a user is registered successfully.
   * @param User $user
   * @param UserEmail $email
   */
  public function onUserRegistered(User $user, UserEmail $email) {
    # Dummy code to keep IDE happy.
    if ($user && $email) {
      return;
    }
  }

}
