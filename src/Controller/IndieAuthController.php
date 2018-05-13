<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class IndieAuthController extends ControllerBase {

  /**
   * Routing callback: login redirect callback.
   */
  public function loginRedirect() {

    $config = \Drupal::config('indieweb.indieauth');
    $login_enabled = $config->get('login_enable');

    // Early return when endpoint is not enabled.
    if (!$login_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    // Default message.
    $message = $this->t('Access denied');

    // Verify code.
    if (!empty($_GET['code']) && !empty($_GET['state']) && !empty($_GET['client_id']) && $_GET['client_id'] == \Drupal::request()->getSchemeAndHttpHost() && $_GET['state'] == session_id()) {

      // Validate the code.
      $valid_code = FALSE;
      $domain = '';

      try {
        $client = \Drupal::httpClient();
        $body = [
          'code' => $_GET['code'],
          'client_id' => $_GET['client_id'],
          'redirect_uri' => Url::fromroute('indieweb.indieauth.login.redirect', [], ['absolute' => TRUE])->toString(),
        ];

        $response = $client->post($config->get('login_endpoint'), ['form_params' => $body]);
        $json = json_decode($response->getBody());
        if (isset($json->me) && isset($_SESSION['domain']) && $json->me == $_SESSION['domain']) {
          $domain = $_SESSION['domain'];
          unset($_SESSION['domain']);
          $valid_code = TRUE;
        }
      }
      catch (\Exception $e) {
        $this->getLogger('indieweb_indieauth')->notice('Error validating the code: @message', ['@message' => $e->getMessage()]);
      }

      // We have a valid token. Let's authenticate the user. Create an account
      // in case the user doesn't have an account yet.
      if ($valid_code && !empty($domain)) {

        /** @var \Drupal\externalauth\ExternalAuthInterface $external_auth */
        $external_auth = \Drupal::service('externalauth.externalauth');
        $authname = str_replace(['https://', 'http://'], '', $domain);
        try {
          $account = $external_auth->loginRegister($authname, 'indieweb');
          if ($account) {
            return new RedirectResponse($account->toUrl()->toString(), 302);
          }
          else {
            $message = $this->t('Unknown user, please try again.');
          }
        }
        catch (\Exception $e) {
          $message = $this->t('Unknown user, please try again.');
        }
      }
      else {
        $message = $this->t('Invalid code, please try again.');
      }

    }

    // Default message.
    drupal_set_message($message);

    // Redirect.
    return new RedirectResponse(Url::fromRoute('user.login')->toString(), 302);
  }

}
