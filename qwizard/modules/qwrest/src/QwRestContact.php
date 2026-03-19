<?php

namespace Drupal\qwrest;



use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\QwizardGeneral;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use WhichBrowser;
use Drupal\user\Entity\User;
use Drupal\Core\Site\Settings;
use Detection\MobileDetect;


/**
 * Class QwRestContact.
 */
class QwRestContact {
  private QwizardGeneral $qwizardGeneral;
  private QwRestGeneral $qwRestGeneral;
  private CourseHandler $courseHandler;

  /**
   * The mobile detect manager.
   *
   * @var \Detection\MobileDetect
   */
  private $mobileDetectManager;

  public function __construct(QwizardGeneral $qwizardGeneral, QwRestGeneral $qwRestGeneral, CourseHandler $courseHandler, MobileDetect $mobile_detect_manager) {
    $this->qwizardGeneral = $qwizardGeneral;
    $this->qwRestGeneral = $qwRestGeneral;
    $this->courseHandler = $courseHandler;
    $this->mobileDetectManager = $mobile_detect_manager;
  }

  public static function create(ContainerInterface $container): QwRestContact
  {
    return new static(
      $container->get('qwizard.general'),
      $container->get('qwrest.general'),
      $container->get('qwizard.coursehandler'),
      $container->get('mobile_detect'),
    );
  }


  /**
   * Main function, call this to submit to tester support form
   * Will read from POST
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitToTesterSupport(): array
  {
    $default_params = [
      'message' => '',
      'resultId' => null,
      'questionId' => null,
      'sessionInfo' => null,
      'appVersion' => t('Not Specified'),
      'webform_id' => 'tester_support'
    ];

    $get_params_to_get = [
      'message', 'resultId', 'questionId', 'sessionInfo', 'appVersion',
    ];
    $payload = $this->qwRestGeneral->getInputsParams($get_params_to_get);
    $payload = array_merge($default_params, $payload);


    // Return OS to Detect Phone Device from User Agent

    $result = new WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);
    $os = $result->os->toString();

    if ($this->isRequestFromApp()) {
      if ($os == 'IOS') {
        $indicator = t('Using APP on Apple mobile device. App version: @app_version.', [
          '@app_version' => $payload['appVersion'],
        ]);
      } else {
        $indicator = t('Using APP on Android mobile device. App version: @app_version', [
          '@app_version' => $payload['appVersion'],
        ]);
      }
    } else {
      $indicator = t('Website');
    }

    $user = $this->qwizardGeneral->getAccountInterface();

    $webform = Webform::load($payload['webform_id']);
    $values = [
      'webform_id' => $webform->id(),
      'uid' => $user->id(),
      'data' => [
        'name' => $user->getDisplayName(),
        'email' => $user->getEmail(),
        'tell_us_about_it_' => $payload['message'],
        'user_info' => implode("\n", $this->getUserInfo($user->id())),
        'session_info' => implode("\n", $this->getSessionInfo($payload)),
        'device_info' => implode("\n", $this->getDeviceInfo()),
        'message_source_indicator' => $indicator,

      ],
    ];

    $return_response = ['response' => 'Support Request failed to submit'];
    $return_response['status'] = WebformSubmission::create($values)->save();

    if($return_response['status']){
      $return_response['response'] = 'Support Request Successfully Submitted.';
    }

    return $return_response;
  }



  /**
   * @param $payload
   * @return array
   */
  public static function getSessionInfo($payload = null): array
  {
    $sessioninfo = [];

   // $ip = (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

    $forwarded_for = \Drupal::request()->server->get('HTTP_X_FORWARDED_FOR');
    $remote_addr   = \Drupal::request()->server->get('REMOTE_ADDR');

    $ip = (!empty($forwarded_for)) ? $forwarded_for : $remote_addr;
    $geoPlugin_array = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=' . $ip));

    if (!empty($payload['resultId'])) {
      $sessioninfo['result_id'] = t('Result ID ') . $payload['resultId'];
    }

    if (!empty($payload['questionId'])) {
      $sessioninfo['question_id'] =  t('Question ID ') . $payload['questionId'];
      $sessioninfo['questionedit_link'] = t('Question Tester Mode Link ') .\Drupal::request()->getSchemeAndHttpHost() . '/node/' . $payload['questionId'] . '/tester';
    }

    if (!empty($payload['sessionInfo'])) {
      // We don't need unnecessary arrays to be sent to support.
      unset($payload['sessionInfo']['questions']);
      unset($payload['sessionInfo']['question_summary']);
      $sessioninfo['extra'] = t('Extra information: ') . json_encode($payload['sessionInfo'], JSON_PRETTY_PRINT);
    }

    $sessioninfo['ip']         = t('IP: ') . $ip;
    $sessioninfo['url']        = t('URL: ') . \Drupal::request()->server->get('REQUEST_URI');
    $sessioninfo['country']    = (!empty($geoPlugin_array['geoplugin_countryName']))   ? t('Country: ')   . $geoPlugin_array['geoplugin_countryName']   : '';
    $sessioninfo['city']       = (!empty($geoPlugin_array['geoplugin_city']))          ? t('City: ')      . $geoPlugin_array['geoplugin_city']          : '';
    $sessioninfo['tz']         = (!empty($geoPlugin_array['geoplugin_timezone']))      ? t('Time Zone: ') . $geoPlugin_array['geoplugin_timezone']      : '';
    $sessioninfo['region']     = (!empty($geoPlugin_array['geoplugin_region']))        ? t('Region: ')    . $geoPlugin_array['geoplugin_region'] . t(' - Region Code: ') . $geoPlugin_array['geoplugin_regionCode'] : '';
    $sessioninfo['continent']  = (!empty($geoPlugin_array['geoplugin_continentName'])) ? t('Continent: ') . $geoPlugin_array['geoplugin_continentName'] : '';

    if (!empty(Settings::get('server_code'))) {
      $sessioninfo['server_code'] = t('Server Code: ') . Settings::get('server_code', 'No Server Code');
    }

    return $sessioninfo;
  }


  /**
   * @return array
   */
  public function getDeviceInfo(): array
  {
    $device_info = [];

    if ($this->isRequestFromApp()) {
      $device_info['user_agent'] = \Drupal::request()->server->get('HTTP_USER_AGENT');

      $os = $this->getDeviceOs();
      $device_info['os'] = !empty($os) ? $os : '';

      $browser = $this->getDeviceBrowser();
      $device_info['browser'] = !empty($browser) ? $browser : '';

      $device_name = $this->getDeviceName();
      $device_info['device'] = !empty($device_name) ? $device_name : '';

      $is_mobile = $this->mobileDetectManager->isMobile();
      $is_tablet = $this->mobileDetectManager->isTablet();

      $device_info['device_type'] = ($is_mobile ? ($is_tablet ? 'Tablet' : 'Phone') : 'Other');

      if ($is_mobile) {
        $device_info['device_version'] = $this->mobileDetectManager->version('Mobile');
      }
      else if ($is_tablet) {
        $device_info['device_version'] = $this->mobileDetectManager->version('Tablet');
      }

      $device_info['is_mobile'] = $is_mobile;
      $device_info['is_tablet'] = $is_tablet;

      //\Drupal::logger('qapi_debug')->debug('<pre>' . print_r($device_info, TRUE) . '</pre>');
    } else {
      $result = new WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);

     // $device_info['user_agent']    = $_SERVER['HTTP_USER_AGENT'];
      $device_info['raw']           = (!empty(\Drupal::request()->server->get('HTTP_USER_AGENT'))) ? t('Raw: ') . \Drupal::request()->server->get('HTTP_USER_AGENT') : '';
      $device_info['os']            = (!empty($result->os->toString()))     ? t('OS: ') . $result->os->toString()               : '';
      $device_info['browser']       = (!empty($result->browser->name))      ? t('Browser Name: ') . $result->browser->name . ' ' . $result->browser->version->toString() : '';
      $device_info['type']          = (!empty($result->getType()))          ? t('Type: ') . $result->getType()                  : '';
      $device_info['engine']        = (!empty($result->engine->toString())) ? t('Engine: ') . $result->engine->toString()       : '';
      $device_info['device']        = (!empty($result->device->toString())) ? t('Device: ') . $result->device->toString()       : '';
      $device_info['browes_form']   = (!empty($result->isMobile()))         ? t('Browsing From: ') . t('Mobile device browser') : '';
    }

    return $device_info;
  }

  /**
   * @param $user
   * @return array
   * @throws \Exception
   */
  public function getUserInfo($uid): array
  {
    $user      = User::load($uid);
    $user_info = [];
    $first_name   = $user->get('field_first_name')->isEmpty() ? '' : $user->get('field_first_name')->value;
    $last_name    = $user->get('field_last_name')->isEmpty() ? '' : $user->get('field_last_name')->value;
    $email        = $user->get('mail')->isEmpty() ? '' : $user->get('mail')->value;
    $lang         = $user->get('langcode')->isEmpty() ? '' : $user->get('langcode')->value;

    $user_info['first_name'] = t('First Name: ') . $first_name;
    $user_info['last_name']  = t('Last Name: ') . $last_name;
    $user_info['email']      = t('Email: ') . $email;
    $user_info['lang']       = t('Lang: ') . $lang;
    $user_info['roles']      = t('Roles: [') . implode(" : ", $user->getRoles()) . ']';
    $user_info['id']         = t('Id: ') . $user->id();
    $user_info['login']      = t('Login: ') . $this->qwizardGeneral->formatIsoDate($user->getLastLoginTime());
    $user_info['created']    = t('Created: ') . $this->qwizardGeneral->formatIsoDate($user->getCreatedTime());
    $course = $this->courseHandler->getCurrentCourse();
    if(!empty($course)) {
      $user_info['course'] = t('Course: ') .$course->label();
    }

    $user_info['called'] = 'Called from: App';
    if (!$this->isRequestFromApp() && !empty($_SERVER['HTTP_REFERER'])) {
      $user_info['called']  = 'Called from '.$_SERVER['HTTP_REFERER'];
    }

    return $user_info;
  }


  /**
   * Returns true if the request is coming from app or not
   * @return bool
   */
  public static function isRequestFromApp(): bool
  {
    return _isRequestFromApp();
  }

  /**
   * Returns the OS of the device.
   */
  public function getDeviceOs() {
    $device_os_map = [
      'isAndroidOS', 'isBlackBerryOS', 'isPalmOS', 'isSymbianOS',
      'isWindowsMobileOS', 'isWindowsPhoneOS', 'isiOS', 'isiPadOS',
      'isSailfishOS', 'isMeeGoOS', 'isMaemoOS', 'isJavaOS', 'iswebOS',
      'isbadaOS', 'isBREWOS',
    ];
    $os = '';
    foreach ($device_os_map as $os_function) {
      if (method_exists($this->mobileDetectManager, $os_function)) {
        $os_found = $this->mobileDetectManager->{$os_function}();
        if ($os_found) {
          $os = substr($os_function, 2);
          break;
        }
      }
    }
    return $os;
  }

  /**
   * Returns the browser of the device.
   */
  public function getDeviceBrowser() {
    $device_browser_map = [
      'isChrome', 'isDolfin', 'isOpera', 'isSkyfire', 'isEdge', 'isIE',
      'isFirefox', 'isBolt', 'isTeaShark', 'isBlazer', 'isSafari', 'isWeChat',
      'isUCBrowser', 'isbaiduboxapp', 'isbaidubrowser','isDiigoBrowser',
      'isMercury', 'isObigoBrowser', 'isNetFront', 'isGenericBrowser',
      'isPaleMoon',
    ];
    $browser = '';
    foreach ($device_browser_map as $browser_function) {
      if (method_exists($this->mobileDetectManager, $browser_function)) {
        $browser_found = $this->mobileDetectManager->{$browser_function}();
        if ($browser_found) {
          $browser = substr($browser_function, 2);
          break;
        }
      }
    }
    return $browser;
  }

  /**
   * Returns the name of the device.
   */
  public function getDeviceName() {
    $device_map = [
      'isiPhone', 'isBlackBerry', 'isPixel', 'isHTC', 'isNexus', 'isDell',
      'isMotorola', 'isSamsung', 'isLG', 'isSony', 'isAsus', 'isXiaomi',
      'isNokiaLumia', 'isMicromax', 'isPalm', 'isVertu', 'isPantech', 'isFly',
      'isWiko', 'isiMobile', 'isSimValley', 'isWolfgang', 'isAlcatel',
      'isNintendo', 'isAmoi', 'isINQ', 'isOnePlus', 'isGenericPhone', 'isiPad',
      'isNexusTablet', 'isGoogleTablet', 'isSamsungTablet', 'isKindle',
      'isSurfaceTablet', 'isHPTablet', 'isAsusTablet', 'isBlackBerryTablet',
      'isHTCtablet', 'isMotorolaTablet', 'isNookTablet', 'isAcerTablet',
      'isToshibaTablet', 'isLGTablet', 'isFujitsuTablet', 'isPrestigioTablet',
      'isLenovoTablet', 'isDellTablet', 'isYarvikTablet', 'isMedionTablet',
      'isArnovaTablet', 'isIntensoTablet', 'isIRUTablet', 'isMegafonTablet',
      'isEbodaTablet', 'isAllViewTablet', 'isArchosTablet', 'isAinolTablet',
      'isNokiaLumiaTablet', 'isSonyTablet', 'isPhilipsTablet', 'isCubeTablet',
      'isCobyTablet', 'isMIDTablet', 'isMSITablet', 'isSMiTTablet',
      'isRockChipTablet', 'isFlyTablet', 'isbqTablet', 'isHuaweiTablet',
      'isNecTablet', 'isPantechTablet', 'isBronchoTablet', 'isVersusTablet',
      'isZyncTablet', 'isPositivoTablet', 'isNabiTablet', 'isKoboTablet',
      'isDanewTablet', 'isTexetTablet', 'isPlaystationTablet',
      'isTrekstorTablet', 'isPyleAudioTablet', 'isAdvanTablet',
      'isDanyTechTablet', 'isGalapadTablet', 'isMicromaxTablet',
      'isKarbonnTablet', 'isAllFineTablet', 'isPROSCANTablet', 'isYONESTablet',
      'isChangJiaTablet', 'isGUTablet', 'isPointOfViewTablet',
      'isOvermaxTablet', 'isHCLTablet', 'isDPSTablet', 'isVistureTablet',
      'isCrestaTablet', 'isMediatekTablet', 'isConcordeTablet',
      'isGoCleverTablet', 'isModecomTablet', 'isVoninoTablet', 'isECSTablet',
      'isStorexTablet', 'isVodafoneTablet', 'isEssentielBTablet',
      'isRossMoorTablet', 'isiMobileTablet', 'isTolinoTablet',
      'isAudioSonicTablet', 'isAMPETablet', 'isSkkTablet', 'isTecnoTablet',
      'isJXDTablet', 'isiJoyTablet', 'isFX2Tablet', 'isXoroTablet',
      'isViewsonicTablet', 'isVerizonTablet', 'isOdysTablet', 'isCaptivaTablet',
      'isIconbitTablet', 'isTeclastTablet', 'isOndaTablet', 'isJaytechTablet',
      'isBlaupunktTablet', 'isDigmaTablet', 'isEvolioTablet', 'isLavaTablet',
      'isAocTablet', 'isMpmanTablet', 'isCelkonTablet', 'isWolderTablet',
      'isMediacomTablet', 'isMiTablet', 'isNibiruTablet', 'isNexoTablet',
      'isLeaderTablet', 'isUbislateTablet', 'isPocketBookTablet',
      'isKocasoTablet', 'isHisenseTablet', 'isHudl', 'isTelstraTablet',
      'isGenericTablet',
    ];
    $device = '';
    foreach ($device_map as $device_function) {
      if (method_exists($this->mobileDetectManager, $device_function)) {
        $device_found = $this->mobileDetectManager->{$device_function}();
        if ($device_found) {
          $device = substr($device_function, 2);
          break;
        }
      }
    }
    return $device;
  }

}
