<?php

namespace Drupal\qwarchive\Plugin\QwArchiveStoragePlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\qwarchive\Plugin\QwArchiveStoragePluginBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a CDN storage plugin.
 *
 * @QwArchiveStoragePlugin(
 *   id = "cdn_storage",
 *   label = @Translation("CDN (Not ready yet)"),
 *   description = @Translation("Store files on a CDN via API.")
 * )
 */
class CdnStorage extends QwArchiveStoragePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a CdnStorage object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')->get('qwarchive')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'cdn_endpoint' => '',
      'api_key' => '',
      'bucket' => '',
      'region' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['cdn_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('CDN Endpoint URL'),
      '#description' => $this->t('Enter the CDN endpoint URL (e.g., https://cdn.example.com/api/upload).'),
      '#default_value' => $this->configuration['cdn_endpoint'],
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Enter the API key for authentication.'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['bucket'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bucket/Container Name'),
      '#description' => $this->t('Enter the bucket or container name where files will be stored.'),
      '#default_value' => $this->configuration['bucket'],
      '#required' => TRUE,
    ];

    $form['region'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Region'),
      '#description' => $this->t('Enter the region (e.g., us-east-1). Leave empty if not applicable.'),
      '#default_value' => $this->configuration['region'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $endpoint = $form_state->getValue('cdn_endpoint');
    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('cdn_endpoint', $this->t('Please enter a valid URL for the CDN endpoint.'));
    }

    $api_key = $form_state->getValue('api_key');
    if (empty(trim($api_key))) {
      $form_state->setErrorByName('api_key', $this->t('API Key cannot be empty.'));
    }

    $bucket = $form_state->getValue('bucket');
    if (empty(trim($bucket))) {
      $form_state->setErrorByName('bucket', $this->t('Bucket name cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['cdn_endpoint'] = $form_state->getValue('cdn_endpoint');
    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['bucket'] = $form_state->getValue('bucket');
    $this->configuration['region'] = $form_state->getValue('region');
  }

  /**
   * {@inheritdoc}
   */
  public function storeJsonFile($filename, $json_data, $sub_dir = NULL) {
    $endpoint = $this->configuration['cdn_endpoint'];
    $api_key = $this->configuration['api_key'];
    $bucket = $this->configuration['bucket'];
    $region = $this->configuration['region'];

    if (!str_ends_with($filename, '.json')) {
      $filename .= '.json';
    }

    try {
      // Prepare request options.
      $options = [
        'headers' => [
          'Authorization' => 'Bearer ' . $api_key,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'filename' => $filename,
          'data' => $json_data,
          'bucket' => $bucket,
          'region' => $region,
        ],
      ];

      // Send request to CDN.
      $response = $this->httpClient->request('POST', $endpoint, $options);

      if ($response->getStatusCode() == 200 || $response->getStatusCode() == 201) {
        $body = json_decode($response->getBody(), TRUE);
        $file_url = $body['url'] ?? $body['file_url'] ?? ($endpoint . '/' . $filename);

        $this->logger->info('Stored JSON file on CDN: @url', ['@url' => $file_url]);
        return $file_url;
      }

      $this->logger->error('CDN API returned status code: @code', ['@code' => $response->getStatusCode()]);
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Exception while uploading to CDN: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function readJsonFile($filename, $sub_dir = NULL) {
    // @todo method to implement yet.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteJsonFile($filename, $sub_dir = NULL) {
    // @todo method to implement yet.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteJsonDirectory($sub_dir) {
    // @todo method to implement yet.
  }

}
