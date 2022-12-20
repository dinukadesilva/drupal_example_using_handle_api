<?php

namespace Drupal\drupal_example_using_handle_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Drupal example using handle api form.
 */
class CreateHandle extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_example_using_handle_api_create_handle';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['hdl_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Handle Prefix'),
      '#default_value' => "0.NA/20.500.14233",
      '#required' => TRUE,
    ];

    $form['hdl_admin_handle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Handle Admin Prefix'),
      '#default_value' => "20.500.14233/HANDLE_ADMIN",
      '#required' => TRUE,
    ];

    $form['hdl_admin_index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Handle Admin Index'),
      '#default_value' => "300",
      '#required' => TRUE,
    ];

    $form['hdl_admin_private_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Private Key'),
      '#required' => TRUE,
    ];

    $form['hdl_api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Handle API Url'),
      '#default_value' => "https://js-173-12.jetstream-cloud.org:8000",
      '#required' => TRUE,
    ];

    $form['hdl_permissions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Permissions of the new Handle'),
      '#default_value' => "111111111111",
      '#required' => TRUE,
    ];

    $form['hdl_resolve_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#default_value' => "https://example.com",
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Handle'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $endpoint_url = $form['hdl_api_endpoint']["#value"];
    $admin_handle = $form['hdl_admin_handle']["#value"];
    $handle_admin_index = $form['hdl_admin_index']["#value"];
    $private_key_pem = $form['hdl_admin_private_key']["#value"];
    $url = $form['hdl_resolve_url']["#value"];

    \Drupal::logger("example")->debug("endpoint_url - $endpoint_url");

    try {
      $cnonce = $this->generateRandomString(16);

      $response1 = \Drupal::httpClient()->post($endpoint_url . "/api/sessions", [
        'headers' => [
          'Authorization' => 'Handle cnonce="' . $cnonce . '"',
          'Content-Type' => 'application/json',
          'Accept' => 'application/json'
        ],
      ]);
      $response1_body_array = json_decode($response1->getBody()->getContents(), TRUE);

      $sessionId = $response1_body_array["sessionId"];
      $nonce = $response1_body_array["nonce"];

      $data = base64_decode($nonce) . base64_decode($cnonce);
      openssl_sign($data, $signature, $private_key_pem, OPENSSL_ALGO_SHA256);
      $signature = base64_encode($signature);

      $response2 = \Drupal::httpClient()->PUT($endpoint_url . "/api/sessions/this", [
        'headers' => [
          'Authorization' => 'Handle sessionId="' . $sessionId . '",id="' . $handle_admin_index . ':' . $admin_handle . '",type="HS_PUBKEY",cnonce="' . $cnonce . '",alg="SHA256",signature="' . $signature . '"',
          'Content-Type' => 'application/json',
          'Accept' => 'application/json'
        ],
      ]);
      $response2_body_array = json_decode($response2->getBody()->getContents(), TRUE);

      $handle_json = [
        [
          'index' => 1,
          'type' => "URL",
          'data' => [
            'format' => "string",
            'value' => $url,
          ],
        ]
      ];

      $response3 = \Drupal::httpClient()->PUT($endpoint_url . "/api/handles/" . $admin_handle . "/?overwrite=false&mintNewSuffix=true", [
        'headers' => [
          'Authorization' => 'Handle sessionId="' . $sessionId . '"',
          'Content-Type' => 'application/json',
          'Accept' => 'application/json'
        ],
        'json' => $handle_json
      ]);
      $response3_body_array = json_decode($response3->getBody()->getContents(), TRUE);

      $handle = $response3_body_array["handle"];

      $this->messenger()->addStatus($this->t('New Handle Created - ') . $handle);
      $form_state->setRedirect('<front>');
    } catch (ClientException $e) {
      $this->messenger()->addStatus($this->t('Error - ') . print_r($e, TRUE));
      $form_state->setRedirect('<front>');
      \Drupal::logger('persistent identifiers')->error(print_r($e, TRUE));
      return FALSE;
    } catch (GuzzleHttp\Exception\ConnectionException $e) {
      $this->messenger()->addStatus($this->t('Error - ') . print_r($e, TRUE));
      \Drupal::logger('persistent identifiers')->erorr(print_r($e, TRUE));
    }

  }

  public function generateRandomString($length = 10)
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

}
