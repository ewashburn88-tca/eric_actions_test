<?php

namespace Drupal\Tests\qwsubs\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group qwsubs
 */
class SubscriptionTests extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['qwsubs', 'rest', 'zukurest', 'qwrest'];
  #protected $defaultTheme = 'zukurenew';
  protected $defaultTheme = 'stable';

  // Can leave this at false for run speed and to ignore schema warnings with this module, but should eventually be true
  protected $strictConfigSchema = false;

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  //protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    //$this->user = $this->drupalCreateUser(['administer site configuration']);
    //$this->drupalLogin($this->user);
  }


  /*public function testIncomplete(){
    $this->drupalGet('api-v1/membership', [], ['query' => ['format' => 'json']]);
    //$this->drupalGet('api-v1/site-results', [], ['query' => ['format' => 'json', 'courseId' => '201']]);
    //$response = $this->getRawContent();
    $content_type_header = $this->drupalGetHeader('content-type');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertEqual('application/json', $content_type_header);

    /*$http_kernel = $this->container->get('http_kernel');

    $request = Request::create('/api-v1/membership?format=json');
    $request->headers->set('Accept', 'application/json');

    $response = $http_kernel->handle($request);*//*
    //var_dump($response);
    //$this->assertEqual($response->getStatusCode(), Response::HTTP_FORBIDDEN);
  } */

  public function tearDown() {

  }

  /**
   * Test Utility Functions
   */


}
