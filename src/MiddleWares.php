<?php

use WildHelper\APIs;
use WildHelper\Data;
use WildHelper\Encryption;
use WildHelper\EncryptionOld;
use WildHelper\Settings;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Headers;
use Slim\Psr7\Response;

define('WILD_CONTENT_TYPE', 'application/json; charset=utf-8');

$ERROR_STATUS_CODE = 200;

/**
 * Example middleware closure
 *
 * @param  Request        $request PSR-7 request
 * @param  RequestHandler $handler PSR-15 request handler
 *
 * @return mixed
 */
$middleware = function ($request, $handler) use ($data, $resp, $ERROR_STATUS_CODE) {
	$resp->data = null;
	$resp->page = false;
	$resp->open = null;
	$uriPath = $request->getUri()->getPath();

	try {
		$authHeader = $request->getHeader('Authorization');
		$authKey = null;
		$decodedAuth = null;
		$realUserId = null;
		$realPassword = null;
		if (isset($_POST['token'])) {
			$authKey = $_POST['token'];
		} else if (is_array($authHeader) && count($authHeader) > 0) {
			$authKey = $authHeader[0];
		}
		$realAuth = $authKey;
		if (!is_null($authKey)) {
			$data->decodeAuth($realAuth, $realUserId, $realPassword, false);
		}
		$userId = $request->getHeader('X-Wild-User');
		$rawOpen = $request->getHeader('X-Wild-Open');

		$auth = null;
		if ( substr($uriPath, 0, 44) === '/v2/actions/'.Settings::CLOUD_FUNCTION_AUTH ) {
			if (!is_null($realUserId) && !APIs::testShareScorePassword($realPassword)) {
				$data->getCache()->set('WildHelper::wait/'.$realUserId, 1, 10);
			}
			$ERROR_STATUS_CODE = 200;
			$auth = $data->auth($authKey);
			if (!is_null($auth)) {
				$userId = [$auth];
			}
		} else if ($uriPath === '/v2/settings/login') {
			set_time_limit(10);
			// 登录页面不要求验证
			$auth = '';
		} else if (substr($uriPath, 0, 13) === '/v2/settings/') {
			set_time_limit(1);
			if (substr($uriPath, 0, 22) === '/v2/settings/endpoint/') {
				// API 请求页不要求验证
				$auth = '';
				$userId = [''];
			} else if (
				$uriPath === '/v2/settings/more' && (
					empty($authHeader) ||
					(is_array($authHeader) && count($authHeader) > 0 && (!$authHeader[0] || $authHeader[0] === '[object Null]' || $authHeader[0] === '[object undefined]'))
				)
			) {
				// 更多页不要求验证
				$auth = '';
			} else {
				if (!APIs::testNoPassword($realPassword) || (count($rawOpen) && $rawOpen[0])) {
					$resp->open = Encryption::decrypt( Data::base64_url_decode($rawOpen[0]), Data::ENCRYPTION_KEY, true );
				}
				$auth = $data->auth($authKey, null, true);
			}
		} else if (substr($uriPath, 0, 12) === '/v2/actions/') {
			set_time_limit(10);
			// action 路径下限制访问频率
			if (
				((count($rawOpen) === 1 && $rawOpen[0]) || APIs::testNoPassword($realPassword) ) && !is_null($realUserId)
			) {
				if (!APIs::testNoPassword($realPassword) || (count($rawOpen) && $rawOpen[0])) {
					$resp->open = Encryption::decrypt( Data::base64_url_decode($rawOpen[0]), Data::ENCRYPTION_KEY, true );
				}

				$currentOpen = hash('sha256', $rawOpen[0], true);

				if (!APIs::testShareScorePassword($realPassword)) {
					$wait = $data->getCache()->get('WildHelper::wait/'.$realUserId);
					while ($wait) {
						sleep(1);
						$wait = $data->getCache()->get('WildHelper::wait/'.$realUserId);
					}
					$data->getCache()->set('WildHelper::wait/'.$realUserId, 1, 10);
				}

				$auth = $data->auth($authKey, null);

				if (!is_null($auth) && is_object($data->getApi()) && !$data->getApi()->isNoPassword() && is_string($auth)) {
					$prevOpen = $data->getStorage()->get('/opt/wild/open/'.$auth);
					if (!$prevOpen) {
						$data->getStorage()->set('/opt/wild/open/'.$auth, $currentOpen);
					} elseif ($currentOpen !== $prevOpen) {
						$data->setStatus(false);
						$data->addError((object)[
							'code' => 1012,
							'message' => '服务器升级，你需要重新登录',
						]);
						$auth = null;
					}
				}
			}
		}
		if(is_null($auth)) {
			if ( substr($uriPath, 0, 44) === '/v2/actions/'.Settings::CLOUD_FUNCTION_AUTH ) {
				$return = (object)[
					'success' => true,
					'errors' => [],
					'messages' => [],
					'result' => [],
					'errors_hidden' => $data->getErrors(),
				];
			} else {
				$return = (object)[
					'success' => $data->getStatus(),
					'errors' => $data->getErrors(),
					'messages' => $data->getMessages(),
					'result' => null
				];
			}
			$response = new Response($ERROR_STATUS_CODE, new Headers(['X-Wild-Errors' => $data->getErrorCodes()]));
			$response->getBody()->write(json_encode($return, JSON_UNESCAPED_UNICODE));
			return $response->withHeader('Content-type', WILD_CONTENT_TYPE);
		}
		if(substr($uriPath, 0, 4) === '/v2/') {
			if (!isset($userId[0]) || $userId[0] === '[object Null]' || $userId[0] === '[object Undefined]') {
				$userId = [''];
			}
			if (count($userId) !== 1 || $userId[0] !== $auth) {
				$return = (object)[
					'success' => false,
					'errors' => [(object)[
						'code' => 1006,
						'message' => '登录的用户名不匹配',
					]],
					'messages' => [],
					'result' => null
				];
				$response = new Response($ERROR_STATUS_CODE, new Headers(['X-Wild-Errors' => '1006']));
				$response->getBody()->write(json_encode($return, JSON_UNESCAPED_UNICODE));
				return $response->withHeader('Content-type', WILD_CONTENT_TYPE);
			}
		}

		if ( $uriPath === '/api/user/periodic' && $data->getApi()->isLoggedIn() ) {
			$ret = $data->getStorage()->get('/opt/wild/scores/'.$auth);
			$courseSelect = $data->getStorage()->get('/opt/wild/courses/' . $auth);
			if (is_object($ret) && is_object($courseSelect) && property_exists($ret, 'grade_term') && property_exists($courseSelect, 'terms')) {
				$existingSemesters = [];
				foreach ($ret->grade_term as $courses) {
					$existingSemesters[$courses->year.'-'.$courses->term] = true;
				}
				$existedSemesters = [];
				foreach ($courseSelect->terms as $year => $yearData) {
					foreach ($yearData as $term => $termData) {
						if (property_exists($termData, 'time') && property_exists($termData, 'list')) {
							if (!property_exists($termData, 'updated_time')) {
								$termData->updated_time = $termData->time;
							}
							$existedSemesters[$year.'-'.$term] = $termData;
						}
					}
				}
				foreach ($existingSemesters as $term => $termData) {
					if (
						count($existedSemesters[$term]->list) === 0 &&
						$existedSemesters[$term]->updated_time + 21600 < time()
					) {
						$terms = explode('-', $term);
						error_log( date('Y-m-d H:i:s').' [Checking] '.$uriPath.': '.$auth.' '.json_encode($terms)."\r\n", 3, "/opt/wild/log/log.txt");
						$data->getCourses($terms[0].'-'.$terms[1], $terms[2]);
					}
				}
			}
		}

		$response = $handler->handle($request);

		if(substr($uriPath, 0, 44) === '/v2/actions/'.Settings::CLOUD_FUNCTION_AUTH) {
			if (!is_null($realUserId) && !APIs::testShareScorePassword($realPassword)) {
				$data->getCache()->set('WildHelper::wait/'.$realUserId, 1, 1);
			}
			$prevOpen = $data->getStorage()->get('/opt/wild/open/'.$auth);
			if (!$prevOpen || $data->getApi()->isNoPassword()) {
				$return = (object)[
					'success' => true,
					'errors' => [],
					'messages' => [],
					'result' => [],
					'errors_hidden' => $data->getErrors(),
				];
			} else {
				$return = (object)[
					'success' => $data->getStatus(),
					'errors' => $data->getErrors(),
					'messages' => $data->getMessages(),
					'result' => [],
					'encrypted' => EncryptionOld::resp(gzdeflate(json_encode($resp->data, JSON_UNESCAPED_UNICODE), 9), $prevOpen),
				];
			}
		} else {
			if (!is_null($realUserId) && !APIs::testShareScorePassword($realPassword)) {
				$data->getCache()->set('WildHelper::wait/'.$realUserId, 1, 1);
			}

			$return = (object)[
				'success' => $data->getStatus(),
				'errors' => $data->getErrors(),
				'messages' => $data->getMessages(),
				'result' => $resp->data,
			];
		}
		if (!$data->getStatus()) {
			$response = new Response($ERROR_STATUS_CODE, new Headers(['X-Wild-Errors' => $data->getErrorCodes()]));
		}

		if ( $resp->page ) {
			$total = is_array($return->result) ? count($return->result) : 0;
			$return->result_info = [
				'total_count' => $total,
				'count' => $total,
				'page' => '1',
				'per_page' => '-1'
			];
		}
	} catch (Exception $exception) {
		$data->addError((object)['message' => $exception->getMessage(), 'code' => $exception->getCode()]);
		if ( substr($uriPath, 0, 44) === '/v2/actions/'.Settings::CLOUD_FUNCTION_AUTH ) {
			$return = (object)[
				'success' => true,
				'errors' => [],
				'messages' => [],
				'result' => [],
				'errors_hidden' => $data->getErrors(),
			];
		} else {
			$errors = $data->getErrors();
			array_unshift($errors, ['code' => 0, 'message' => '如果持续登录失败，您可以试试免密登录']);
			$return = (object)[
				'success' => false,
				'errors' => $errors,
				'messages' => [],
				'result' => null
			];
		}
		$response = new Response($ERROR_STATUS_CODE);
		$response->getBody()->write(json_encode($return, JSON_UNESCAPED_UNICODE));
		return $response->withHeader('Content-type', WILD_CONTENT_TYPE);
	}

	$ret = json_encode($return, JSON_UNESCAPED_UNICODE);
	if (is_string($ret)) {
		$response->getBody()->write($ret);
	} else {
		$response->getBody()->write('{"errors":[{"message":"服务器无法编码JSON，错误编号","code":"'.(550+json_last_error()).'"}],"messages":[],"success":false,"result":null}');
	}

	return $response->withHeader('Content-type', WILD_CONTENT_TYPE);
};
