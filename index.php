<?php

use WildHelper\Data;
use WildHelper\Encryption;
use WildHelper\ResponseData;
use WildHelper\Settings;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollectorProxy;
use WildHelper\Utils;


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Data.php';
require_once __DIR__ . '/src/APIs.php';
require_once __DIR__ . '/src/ResponseData.php';
require_once __DIR__ . '/src/Encryption.php';
require_once __DIR__ . '/src/KV.php';
require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/config/Settings.php';
require_once __DIR__ . '/config/Storage.php';
require_once __DIR__ . '/config/Cache.php';


$app = AppFactory::create();
$app->addRoutingMiddleware();

$data = new Data();
$resp = new ResponseData();
require_once __DIR__ . '/src/MiddleWares.php';

$app->group('/v2', function (RouteCollectorProxy $app) use ($data, $resp) {
	$app->group('/settings', function (RouteCollectorProxy $app) use ($data, $resp) {

		$app->post('/login', function (Request $request, Response $response) use ($data, $resp) {
			set_time_limit(5);
			$parsedBody = json_decode($request->getBody()->getContents(), true);
			$ret = json_decode(file_get_contents('https://api.weixin.qq.com/sns/jscode2session?appid='. Settings::APP_ID . '&secret='. Settings::APP_SECRET . '&js_code='. $parsedBody['code'] .'&grant_type=authorization_code'));
			if (is_object($ret) && property_exists($ret, 'openid')) {
				$resp->data = $data->verifyUserPassword( $parsedBody['no'], $parsedBody['pass'], 'v2', $ret->openid );
			} else if (is_object($ret) && property_exists($ret, 'errcode')) {
				$data->setStatus(false);
				$data->addError((object)[
					'code' => $ret->errcode,
					'message' => $ret->errmsg,
				]);
			} else {
				$data->setStatus(false);
				$data->addError((object)[
					'code' => -2,
					'message' => '请求失败',
				]);
			}
			return $response;
		});

		$app->post('/subscribe/{course_id}', function (Request $request, Response $response, array $args) use ($data, $resp) {
			set_time_limit(5);
			$open = '';
			$rawOpen = $request->getHeader('X-Wild-Open');
			if (count($rawOpen) === 1 && $rawOpen[0] && $rawOpen[0] !== '[object Null]' && $rawOpen[0] !== '[object Undefined]') {
				$open = Encryption::decrypt( Data::base64_url_decode($rawOpen[0]), Data::ENCRYPTION_KEY, true );
			}
			$resp->data = $data->setSubscribe($args['course_id'], $open);
			return $response;
		});

		$app->post('/screenshot', function (Request $request, Response $response, array $args) use ($data, $resp) {
			$API = $data->getApi();
			$stuId = $API->getStuId();
			$resp->data = new stdClass();
			$resp->data->title = '截屏已经上报';
			if ($stuId === Settings::DEMO_UID) {
				$data->getCache()->set('WildHelper::screenshot/' . $resp->open, 1, 60);
				$resp->data->content = '统计数据禁止截屏，你因违反协议已被屏蔽使用课程统计 1 分钟。多次截屏将会被系统封号';
			} else {
				error_log( date('Y-m-d H:i:s').' '.$stuId.' '.$resp->open."\r\n", 3, "/opt/wild/log/screenshot.txt");
				$data->getCache()->set('WildHelper::screenshot/' . $resp->open, 1, 3600);
				$resp->data->content = '统计数据禁止截屏，你因违反协议已被屏蔽使用课程统计 1 小时。多次截屏将会被系统封号';
			}

			return $response;
		});

		$app->delete('/share_score', function (Request $request, Response $response, array $args) use ($data, $resp) {
			$resp->data = $data->setUserShareScore(false);
			return $response;
		});

		$app->group('/overview', function (RouteCollectorProxy $app) use ($data, $resp) {

			$app->get('/scores', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getOverview(null, $resp->open);
				$_GET['order'] = $_GET['order'] ?? 'count';
				$_GET['order'] = ($_GET['order'] === 'id' && !isset($_GET['direction'])) ? 'count' : $_GET['order'];
				$_GET['direction'] = $_GET['direction'] ?? 'DESC';
				$resp->page = true;
				return $response;
			});

			$app->get('/scores/{course_id}', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getOverview( $args['course_id'], $resp->open );
				return $response;
			});

		});

		$app->get('/endpoint', function (Request $request, Response $response, array $args) use ($data, $resp) {
			$resp->data = [
				'default' => [
					'name' => '默认',
					'url' => Settings::API_ENDPOINT,
					'message' => Settings::API_MESSAGE,
				]
			];
			return $response;
		});

		$app->get('/more', function (Request $request, Response $response, array $args) use ($data, $resp) {
			$resp->data = new stdClass();
			$resp->data->more_messages = Data::MORE_PAGE_MESSAGES;
			$resp->data->footer_messages = Data::MORE_PAGE_FOOTER;
			$resp->data->footer_messages[] = '### 版本信息';
			$version_data_obj = new stdClass();
			if (isset($_GET['wechat_version'])) {
				@$version_data_obj = json_decode($_GET['wechat_version']);
			}
			if (is_object($version_data_obj) && property_exists($version_data_obj, 'miniProgram') && property_exists($version_data_obj->miniProgram, 'envVersion')){
				$version_data = $version_data_obj->miniProgram->envVersion;
			} else {
				$version_data = 'release';
			}

			switch ($version_data) {
				case 'release':
					$append = '正式版';
					break;
				case 'trial':
					$append = '体验版';
					break;
				case 'develop':
					$append = '开发版';
					break;
				default:
					$append = '未知版本';
					break;
			}
			$resp->data->footer_messages[] = '+ 本机版本类型：'.$append;
			if (is_object($version_data_obj) && property_exists($version_data_obj, 'miniProgram')){
				if (property_exists($version_data_obj->miniProgram, 'version') && $version_data_obj->miniProgram->version) {
					$resp->data->footer_messages[] = '+ 线上版本号：'.$version_data_obj->miniProgram->version;
				}
			} else {
				$resp->data->footer_messages[] = '+ 未获取到版本号信息';
			}

			$version = Utils::getMiniProgramVersion();
			if (isset(Data::VERSION_NUMBER[$version])) {
				$resp->data->footer_messages[] = '+ 本机版本号：'.Data::VERSION_NUMBER[$version];
			}
			$resp->data->footer_messages[] = '+ 本机版本序号：'.$version;

			$resp->data->open_status = -1; // 需要重新登录
			$open = '';
			$prevOpen = '';
			$subscribeCount = '你目前没有订阅任何未出分课程';
			$rawOpen = $request->getHeader('X-Wild-Open');
			if (count($rawOpen) === 1 && $rawOpen[0] && $rawOpen[0] !== '[object Null]' && $rawOpen[0] !== '[object Undefined]') {
				$resp->data->open_status = 0; // 未绑定微信号
				$open = Encryption::decrypt( Data::base64_url_decode($rawOpen[0]), Data::ENCRYPTION_KEY, true );
			}
			$userId = $data->getUserId();
			if ($userId) {
				$courses = $data->getStorage()->get('/opt/wild/courses/'.$userId);
				if (is_object($courses)) {
					if (property_exists($courses, 'open')) {
						$prevOpen = $courses->open;
					}
					if (property_exists($courses, 'map') && ($count = count(get_object_vars($courses->map))) > 0 ) {
						$subscribeCount = '你订阅了 ' . $count . ' 门未出分或未读课程';
					}
				}
			}
			if ($open && $prevOpen) {
				if ($open === $prevOpen) {
					$resp->data->open_status = 1; // 绑定了当前微信号
				} else {
					$resp->data->open_status = 2; // 绑定了其他微信号
				}
			}
			$resp->data->status_messages = [
				'1' => [
					'type' => 'success',
					'desc' => '你已经成功绑定此微信号，此微信号将用于免密登录、接收分数通知。' . $subscribeCount,
				],
				'2' => [
					'type' => 'info',
					'desc' => '你已绑定了其他微信号，分数通知不会下发到此设备上。是否要重置所有订阅并重新绑定此微信号？' . $subscribeCount,
					'button' => '重置订阅',
				],
				'0' => [
					'type' => 'warn',
					'title' => '尚未绑定教务账户',
					'desc' => '绑定后，即使毕业后教务账户被回收，也依然可以使用同微信号设备免密登录',
					'button' => '立即绑定',
				],
				'default' => [
					'type' => 'warn',
					'desc' => '你需要重新登录才能使用最新的功能',
					'button' => '退出登录',
				],
				'share' => [
					'type' => 'info',
					'title' => '尚未授权',
					'desc' => '授权我们使用你的成绩后方可订阅成绩通知、查看选课指导、毕业后免密登录',
					'button' => '查看协议',
				],
				'is_no_password' => [
					'type' => 'info',
					'desc' => '免密登录模式只会缓存数据。退出登录后输入密码可使用完整功能。' . $subscribeCount,
				],
//				'demo' => [
//					'button' => '体验小程序',
//					'id' => Settings::DEMO_UID
//				]
			];
			if ($userId === Settings::DEMO_UID) {
				$resp->data->open_status = -1;
				$resp->data->status_messages['default'] = [
					'type' => 'warn',
					'desc' => '你正在使用体验账户，退出登录并使用自己的账户登录以体验全部功能',
					'button' => '退出登录',
				];
			}

			return $response;
		});

		$app->group('/courses', function (RouteCollectorProxy $app) use ($data, $resp) {
			$app->get('/', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getCourses('', '');
				return $response;
			});

			$app->get('/term/{year}/{term}', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getCourses($args['year'], $args['term']);
				return $response;
			});
		});

		$app->group('/scores', function (RouteCollectorProxy $app) use ($data, $resp) {

			$app->get('/term', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getScoreByTerm();
				return $response;
			});

		});
	});

	$app->group('/actions', function (RouteCollectorProxy $app) use ($data, $resp) {

		$app->group('/'.Settings::CLOUD_FUNCTION_AUTH, function (RouteCollectorProxy $app) use ($data, $resp) {
			$app->post('/pre', function (Request $request, Response $response) use ($data, $resp) {
				$resp->data = [
					'score' => $data->getScoreByTerm()
				];
				return $response;
			});

			$app->post('/periodic', function (Request $request, Response $response) use ($data, $resp) {
				$resp->data = [
					'score' => $data->getScoreByTerm()
				];
				return $response;
			});
		});

		$app->post('/share_score', function (Request $request, Response $response, array $args) use ($data, $resp) {
			$resp->data = $data->setUserShareScore(true);
			return $response;
		});

		$app->group('/scores', function (RouteCollectorProxy $app) use ($data, $resp) {

			$app->get('/', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getScore();
				return $response;
			});

			$app->get('/term', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getScoreByTerm();
				return $response;
			});

			$app->get('/term/{year}/{term}', function (Request $request, Response $response, array $args) use ($data, $resp) {
				if ($args['year'] == '0') {
					$args['year'] = '';
				}
				if ($args['term'] == '0') {
					$args['term'] = '';
				}
				$data->getCourses($args['year'], $args['term']);
				$resp->data = $data->getScoreByTerm();
				return $response;
			});

			$app->get('/cet', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getScoreCet();
				return $response;
			});

		});

		$app->group('/courses', function (RouteCollectorProxy $app) use ($data, $resp) {
			$app->get('/', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getCourses();
				return $response;
			});

			$app->get('/exams', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getExams2();
				return $response;
			});

			$app->get('/term/{year}/{term}', function (Request $request, Response $response, array $args) use ($data, $resp) {
				$resp->data = $data->getCourses($args['year'], $args['term']);
				return $response;
			});
		});

	});
});

$app->add($middleware);
$app->run();
