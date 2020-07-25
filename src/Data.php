<?php


namespace WildHelper;


use DateTime;
use Exception;
use stdClass;
use ZfSpider\SpiderException;

class Data {
	/**
	 * @var string 最大最小学期
	 */
	public static string $maxYearTerm = '2020-2021-1'; // 最大学期，超过这个学期的课程不在首页显示
	public static string $currentYearTerm = '2019-2020-2'; // 当前学期，用于判断考试周、选课周。大于等于此学期的所有成绩都会置顶在首页
	public static string $minYearTerm = '2019-2020-2'; // 最小学期，只要大于等于这个学期就会在首页里显示

	/**
	 * @var mixed 开学周
	 */
	public const WEEK_NO = [
//		'2018-2019' => [
//			'1' => 36,
//			'2' => 8
//		],
		'2019-2020' => [
//			'1' => 36,
			'2' => 9
		],
		'2020-2021' => [
			'1' => 36,
			'2' => 9, // 估算
		],
		'2021-2022' => [
			'1' => 36, // 估算
			'2' => 9, // 估算
		]
	];

	/**
	 * @var mixed 小程序前端版本号，上线后 24h 可删除老版本
	 */
	public const VERSION_NUMBER = Settings::VERSION_NUMBER;

	/**
	 * @var mixed 考试周
	 * 仅在考试周期间才显示考试Tab
	 */
	public const EXAM_WEEK_NO = [
		'2019-2020-2' => [24, 25], // 9-1+16 ~ 9-1+18
	];

	/**
	 * @var mixed 选课周
	 * 仅在选课周期间才允许查看选课知道
	 */
	public const REGISTRATION_WEEK_NO = [
		'2019-2020-2' => [26, 29],
	];

	/**
	 * @var mixed 首页通知
	 */
	private const MAIN_PAGE_MESSAGES = Settings::MAIN_PAGE_MESSAGES;

	private const WARNING_MESSAGE = Settings::WARNING_MESSAGE;

	public const COURSE_MESSAGE = Settings::COURSE_MESSAGE;

	/**
	 * @var mixed 更多页通知
	 */
	public const MORE_PAGE_MESSAGES = Settings::MORE_PAGE_MESSAGES;
	public const MORE_PAGE_FOOTER = [
		'### 开源软件使用',
		'##### GNU Affero GPL v3.0',
		'+ WildHelper/MiniProgram',
		'##### MIT License',
		'+ Tencent/weui',
		'+ brix/crypto-js',
		'+ nodeca/pako',
		'##### Apache License 2.0',
		'+ apache/incubator-echarts',
		'##### BSD 3-Clause License',
		'+ ecomfe/echarts-for-weixin',
	];

	/**
	 * 分数订阅模版消息 ID 列表（至少一个）
	 */
	private const SUBSCRIBE_IDS = Settings::SUBSCRIBE_IDS;

	/**
	 * @var mixed 各种开关，只控制前端显示
	 */
	private const SWITCHES = [
		'cet' => true,
	];

	private string $location;

	private bool $status;

	private array $errors;

	private array $messages;

	private $score;

	private $user = null;

	private $user_id = null;
	private $user_pass;

	private const AD_TIMES = Settings::AD_TIMES;

	public const ENCRYPTION_KEY = Settings::ENCRYPTION;

	private Cache $cache;
	private KV $storage;

	private array $prevDecoded;

	/**
	 * @var APIs|null
	 */
	private $API = null;

	function __construct(string $l = '/opt/wild'){
		$this->clear();
		$this->location = $l;
		$this->cache = new Cache();
		$this->storage = new Storage();
		$this->prevDecoded = [];
	}

	function clear(): void {
		$this->status = true;
		$this->errors = [];
		$this->messages = [];
	}

	/**
	 * @return bool
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param bool $status
	 */
	public function setStatus(bool $status): void
	{
		$this->status = $status;
	}

	/**
	 * @return string[]
	 */
	public function getErrorMessages() {
		$ret = [];
		foreach ($this->errors as $error) {
			$ret[] = $error->message;
		}
		return $ret;
	}

	/**
	 * @return mixed[]
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param $error
	 */
	public function addError($error): void
	{
		$this->errors[] = $error;
	}

	/**
	 * @return string
	 */
	public function getErrorCodes() {
		$ret = '';
		foreach ($this->errors as $error) {
			$ret .= $error->code.'-';
		}
		if ($ret) {
			$ret = substr($ret, 0, -1);
		}
		return $ret;
	}

	/**
	 * @return mixed
	 */
	public function getMessages() {
		return $this->messages;
	}

	/**
	 * @return null
	 */
	public function getUserId()
	{
		return $this->user_id;
	}

	/**
	 * @return APIs|null
	 */
	public function getApi()
	{
		return $this->API;
	}

	/**
	 * @return KV
	 */
	public function getStorage(): KV
	{
		return $this->storage;
	}

	/**
	 * @return Cache
	 */
	public function getCache(): Cache
	{
		return $this->cache;
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public static function verifyUserId(string $userId): bool {
		return preg_match('/^(TD|\d{2})\d{6}$/', $userId) || $userId === Settings::DEMO_UID;
	}

	/**
	 * @param string $userId
	 * @param string $password
	 * @param string $version
	 * @param string $open
	 * @return mixed
	 * @throws Exception
	 */
	public function verifyUserPassword(string $userId, string $password, string $version = 'v1', string $open = '') {
		if (empty($open)) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 1013,
				'message' => '微信登录错误',
			];
			return null;
		}

		if ($userId === Settings::DEMO_UID) {
			return [
				'authorization' => 'F' . static::base64_url_encode(
						Encryption::encrypt(Settings::DEMO_UID, Data::ENCRYPTION_KEY, true)
					),
				'open' => static::base64_url_encode(Encryption::encrypt($open, Data::ENCRYPTION_KEY, true)),
				'student_id' => Settings::DEMO_UID,
				'tel' => null,
				'time' => 0,
				'tried' => 0,
				'share_score' => true
			];
		}

		switch ($version) {
			case 'v2':
				$SUFFIX = 'F';
				$KEY = static::ENCRYPTION_KEY;
				break;
			default:
				throw new Exception('不支持的登录方式，请更新小程序版本');
		}
		if (!static::verifyUserId($userId)) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 1002,
				'message' => '用户名不正确',
			];
			return null;
		}

		$this->user_id = $userId;
		$this->user_pass = $password;
		if ($password === '') {
			$prevOpen = '';
			if ($userId) {
				$courses = $this->storage->get('/opt/wild/courses/'.$userId);
				if (is_object($courses) && property_exists($courses, 'open')) {
					$prevOpen = $courses->open;
				}
			}
			if (!$prevOpen || $open != $prevOpen) {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => 1011,
					'message' => '您没有绑定过微信号或绑定了其他微信号',
				];
				return null;
			}
			$login = $userId;
			$this->user = new stdClass();
			$this->user->stuNo = $this->user_id;
			$this->user->password = $this->user_pass;
			$this->API = new APIs( ['stu_id' => $this->user_id, 'stu_pwd' => $this->user_pass] );
		} else {
			$login = $this->apiLogin();
		}

		if( is_null($login) || $login === false ) {
			return null;
		}

		$encryptedOpen = static::base64_url_encode(Encryption::encrypt($open, $KEY, true));
		if ($password !== '') {
			$this->storage->set('/opt/wild/open/'.$userId, hash('sha256', $encryptedOpen, true));
		}
		return [
			'authorization' =>
				$SUFFIX . static::base64_url_encode(
					Encryption::encrypt($userId.$password, $KEY, true)
				),
			'open' => $encryptedOpen,
			'student_id' => $this->user->stuNo,
			'tel' => $this->user->tel ?? null,
			'time' => $this->user->time ?? 0,
			'tried' => $this->user->tried ?? 0,
			'share_score' => $this->API->isShared()
		];
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function getScore() {
		return $this->API->getGrade();
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function getExams2() {
		return $this->API->getExams2();
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function getScoreByTerm() {
		$userId = $this->user_id;
		$resp = $this->getScore();

		$this->messages = static::MAIN_PAGE_MESSAGES;
		if (!$this->API->isLoggedIn()) {
			if ($userId === Settings::DEMO_UID) {
				$this->messages[] = '您正在使用体验账号，其功能受限。';
//				$this->errors[] = (object)[
//					'code' => 200,
//					'message' => '您登录了功能受限的体验账号。',
//				];
			} else {
				$this->messages[] = '正在使用缓存数据，点击上方 “设置/关于” 了解更多';
			}
		} else if ( is_null($resp) || !is_object($resp) ) {
			$this->messages[] = '教务接口暂不可用，尚无成绩记录。您现在只能看本学期已选课程';
			$resp = new stdClass();
			Utils::setZero($resp, 'term_lesson_count');
			Utils::setZero($resp, 'average_score_term');
			Utils::setZero($resp, 'average_score_minor');
			Utils::setZero($resp, 'average_GPA_term');
			Utils::setZero($resp, 'average_GPA_minor');
		}
		$map = new stdClass();

		$resp->shared = 'F' . static::base64_url_encode(
				Encryption::encrypt($this->API->getStuId()."\nE", static::ENCRYPTION_KEY, true));
		$resp->has_open = false;
		$resp->is_no_password = $this->API->isNoPassword() && $userId !== Settings::DEMO_UID;
		$courseSelect = null;
//		if ( !$resp->is_no_password && static::is_week(static::REGISTRATION_WEEK_NO)) {
//			$courseSelect = $this->API->getCourseSelect();
//		}

		$courseSelectFile = $this->storage->get($this->location . '/courses/' . $userId);
		if (is_object($courseSelectFile)) {
			if (property_exists($courseSelectFile, 'map')) {
				$map = $courseSelectFile->map;
			}
			if (property_exists($courseSelectFile, 'open') && $courseSelectFile->open) {
				$resp->has_open = true;
			}
		}

		if ($courseSelectFile && property_exists($courseSelectFile, 'terms')) {
			$year1 = '0000-0000';
			$term = '0';
			$max = static::$currentYearTerm;
			foreach ($courseSelectFile->terms as $y=>$data) {
				foreach ($data as $t => $courseData) {
					if (property_exists($courseData, 'list')) {
						if (count($courseData->list) === 0) {
							unset($courseSelectFile->terms->$y->$t);
							continue;
						}
						if ($y.'-'.$t > $max) {
							continue;
						}
						if($year1.'-'.$term < $y.'-'.$t) {
							$year1 = $y;
							$term = $t;
						}
					}
				}
			}
			if (is_null($courseSelect)) {
				if (property_exists($courseSelectFile->terms, $year1) && property_exists($courseSelectFile->terms->$year1, $term)) {
					$courseSelect = $courseSelectFile->terms->$year1->$term;
				}
			}
		}

		$resp->course_time = 0;
		if (!is_null($courseSelect)) {
			if(!property_exists($resp, 'time')) {
				$resp->time = 0;
			}
			$resp->course_time = $courseSelect->time;
			if (!$courseSelectFile) {
				$courseSelectFile = new stdClass();
				$courseSelectFile->terms = new stdClass();
				$y = $courseSelect->year;
				$t = $courseSelect->term;
				$courseSelectFile->terms->$y = new stdClass();
				$courseSelectFile->terms->$y->$t = $courseSelect;
				if (!property_exists($resp, 'terms')) {
					$resp->terms = new stdClass();
				}
				Utils::initTerms($resp->terms, $y, $t);
			}
		} else if (!property_exists($resp, 'name')) {
			return null;
		}

		$allCourses = [];
		$allCoursesTime = [];
		if (is_object($courseSelectFile) && property_exists($courseSelectFile, 'terms')) {
			foreach ($courseSelectFile->terms as $year1 => $terms) {
				foreach ($terms as $term => $termData) {
					$allCoursesTime[$termData->year.'-'.$termData->term] = $termData->time;
					if ($year1.'-'.$term > static::$maxYearTerm) {
						continue;
					}
					if (property_exists($termData, 'list')) {
						foreach ($termData->list as $course) {
							if (trim($course->type) == '' || $course->type == ' ') {
								$course->type = '未知性质';
							}
							$allCourses[$course->id.'-'.$course->year.'-'.$course->term] = $course;
						}
					}
				}
			}
		}

		$resp->terms = new stdClass();
		if (property_exists($resp, 'grade_term')) {
			foreach ($resp->grade_term as $score) {
				$y = $score->year;
				$t = $score->term;
				Utils::initTerms($resp->terms, $y, $t);
				if (isset($allCoursesTime[$y.'-'.$t])) {
					$resp->terms->$y->$t->course_time = $allCoursesTime[$y.'-'.$t];
				}
				if (
					isset($allCourses[$score->id.'-'.$score->year.'-'.$score->term]) &&
					property_exists( $map, $allCourses[$score->id.'-'.$score->year.'-'.$score->term]->courseSelectId )
				) {
					$score->unread = true;
					$score->type = '近期出分课程';
				} else if (trim($score->belong) != '' && $score->belong != ' ') {
					$score->type = $score->belong;
				} else if (trim($score->type) == '' || $score->type == ' ') {
					$score->type = '未知性质';
				}
				if (isset($allCourses[$score->id.'-'.$score->year.'-'.$score->term])) {
					$ungradedCourse = $allCourses[$score->id.'-'.$score->year.'-'.$score->term];
					$score->instructor = $ungradedCourse->instructor;
				}
				$resp->terms->$y->$t->courses[] = $score;
				unset($allCourses[$score->id.'-'.$score->year.'-'.$score->term]);
			}
		}

		foreach ($resp->terms as $y => $t_all) {
			foreach ($t_all as $t => $data) {
				Utils::calcGpa( $data, 'courses', false );
			}
		}

		foreach ($allCourses as $course) {
			$y = $course->year;
			$t = $course->term;
			$existed = Utils::initTerms($resp->terms, $y, $t);
			if (isset($allCoursesTime[$y.'-'.$t])) {
				$resp->terms->$y->$t->course_time = $allCoursesTime[$y.'-'.$t];
			}
			if ($course->id == '0009287') {
				$course->belong = '第二课堂';
				if ($course->year >= '2018-2019') {
					$course->credit = '0.25';
				}
			}
			if (trim($course->belong) != '' && $course->belong != ' ') {
				$course->type = $course->belong;
			}
			if (trim($course->type) == '' || $course->type == ' ') {
				$course->type = '未知性质';
			}
			if ($course->credit != '0.25') {
				$course->credit = number_format($course->credit, 1);
			}
			if ($existed && $y.'-'.$t < static::$minYearTerm) {
				$course->score = '0';
				$course->gpa = '0';
				$course->credit = '0.0';
				$course->type .= ' (无分数)';
				$course->comment = '无分数';
				$course->score = -3;
			} else if ( property_exists( $map, $course->courseSelectId ) ) {
				$course->score = -2;
			}
			$resp->terms->$y->$t->courses[] = $course;
		}

		unset($resp->grade_term);
		unset($resp->grade_total);

		$resp->current_year_term = static::$currentYearTerm;
		$resp->subscribe_ids = static::SUBSCRIBE_IDS;
		$resp->exam_week = static::EXAM_WEEK_NO;
		$resp->registration_week = static::REGISTRATION_WEEK_NO;
		$resp->switches = static::SWITCHES;
		$resp->share_score = $this->API->isShared();
		$resp->ad_times = static::AD_TIMES;
		$resp->ad_messages = Settings::AD_MESSAGES;
		$resp->share_message = Settings::SHARE_MESSAGE;
		$resp->ad_id = Settings::AD_ID;
		$resp->ad_id_banner = Settings::AD_ID_BANNER;

		if($userId === Settings::DEMO_UID) {
			$resp->exam_week = [
				'2019-2020-2' => [0, 54],
				'2020-2021-1' => [0, 54],
			];
			$resp->registration_week = [
				'2019-2020-2' => [0, 54],
				'2020-2021-1' => [0, 54],
			];
		}
		if ($this->cache->get('WildHelper::screenshot/'.$userId)) {
			$resp->registration_week = [];
		}
		return $resp;
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function getScoreCet() {
		$this->messages[] = '四六级成绩从北工大教务处获取，会延迟一学期左右';
		$ret = $this->API->getCet();
		$newRet = [];
		$existingSemester = [];
		if (is_array($ret->results)) {
			foreach ($ret->results as $cet) {
				$cet->name = strtoupper($cet->name);
				if (!isset($existingSemester[$cet->year.'-'.$cet->term.'-'.$cet->name])) {
					$existingSemester[$cet->year.'-'.$cet->term.'-'.$cet->name] = true;
					$newRet[] = $cet;
				}
			}
		}
		$ret->results = $newRet;
		return $ret;
	}

	/**
	 * @param string $year
	 * @param string $term
	 * @return mixed
	 */
	public function getCourses(string $year = '', string $term = '') {
		$ret = $this->API->getCourseSelect($year, $term);
		if (!is_null($ret) && is_object($ret)) {
			$ret->week = static::WEEK_NO;
			$ret->shared = 'F' . static::base64_url_encode(
				Encryption::encrypt($this->API->getStuId()."\nS", static::ENCRYPTION_KEY, true));
			$ret->id = $this->API->getStuId();
			if ($this->API->isShared()) {
				$grade = $this->API->getGrade(2592000, true);
				$allSemesters = [];
				if (is_object($grade)){
					if (property_exists($grade, 'major')) {
						$ret->major = $grade->major;
					}
					if (property_exists($grade, 'name')) {
						$ret->name = $grade->name;
					}
					if ($this->API->getStuId() === Settings::DEMO_UID) {
						$ret->name = '蔬菜俱乐部';
					}
					$this->messages[] = '最新版安卓微信可将此页分享到朋友圈';
					$ret->semesters = [];
					if (property_exists($grade, 'grade_term')) {
						foreach ($grade->grade_term as $course) {
							$semester = $course->year .'/'. $course->term;
							if (!isset($allSemesters[$semester])) {
								$allSemesters[$semester] = true;
							}
						}
						foreach ($allSemesters as $semester => $b) {
							$ret->semesters[] = $semester;
						}
					}
				}
			} else {
				$this->messages[] = '您需要先授权才能分享及查询其他学期课表';
			}
		}
		if ($this->API->isLoggedIn()) {
			if (is_object($ret) && property_exists($ret, 'time') && property_exists($ret, 'updated_time') && $ret->time + 10 < $ret->updated_time) {
				$this->errors[] = (object)[
					'code' => 403,
					'message' => '教务系统尚未开放选课查询，当前数据为系统缓存',
				];
			} else if (is_null($ret) || (is_object($ret) && property_exists($ret, 'list') && count($ret->list) === 0)) {
				$this->errors[] = (object)[
					'code' => 403,
					'message' => '教务系统尚未开放选课查询',
				];
			}
		}
		if (is_object($ret) && property_exists($ret, 'list')) {
			foreach ($ret->list as $course) {
				if (trim($course->belong) != '' && $course->belong != ' ') {
					$course->type = $course->belong;
				}
				if (Utils::skippedCourse($course)) {
					$course->belong = '第二课堂';
				}
				if ($course->id == '0009287') {
					$course->belong = '第二课堂';
					$course->type = '第二课堂';
					if ($course->year >= '2018-2019') {
						$course->credit = '0.25';
					}
				}
				if (trim($course->type) == '' || $course->type == ' ') {
					$course->type = '未知性质';
				}
			}
		}
		return $ret;
	}

	public function decodeAuth(&$auth, &$user, &$password, $addError = true) {
		if ( count($this->prevDecoded) === 3 ) {
			$auth = $this->prevDecoded[0];
			$user = $this->prevDecoded[1];
			$password = $this->prevDecoded[2];
			return true;
		}
		if( !is_string($auth) ){
			if ($addError) {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => 1004,
					'message' => '缺少认证信息，您还没有登录',
				];
			}
			return null;
		}
		switch (substr( $auth, 0, 1 )) {
			case 'F':
				$auth = Encryption::decrypt( static::base64_url_decode(substr( $auth, 1 )), static::ENCRYPTION_KEY, true );
				$user = substr( $auth, 0, 8 );
				$password = substr( $auth, 8 );
				$this->prevDecoded = [$auth, $user, $password];
				return true;
				break;
			default:
				if ($addError) {
					$this->status = false;
					$this->errors[] = (object)[
						'code' => 1005,
						'message' => '认证格式不支持',
					];
				}
				return null;
		}
	}

	/**
	 * @param mixed $auth
	 * @param null $user_id
	 * @param bool $skip
	 * @return string
	 * @throws Exception
	 */
	public function auth($auth, $user_id = null, $skip = false): ?string {
		if (is_null($this->decodeAuth($auth, $user, $password))) {
			return null;
		}

		if(!is_null($user_id) && $user_id != $user) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 1006,
				'message' => '登录的用户名不匹配',
			];
			return null;
		}

		if (!static::verifyUserId($user)) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 1007,
				'message' => '错误的用户名认证信息'.$user,
			];
			return null;
		}

		$this->user_id = $user;
		$this->user_pass = $password;
		if(APIs::testNoPassword($password) || $skip) {
			if (!APIs::testShareScorePassword($password) && !$skip && $user !== Settings::DEMO_UID) {
				$prevOpen = '';
				$courseSelectFile = $this->storage->get($this->location . '/courses/' . $user);
				if (is_object($courseSelectFile) && property_exists($courseSelectFile, 'open') && $courseSelectFile->open) {
					$prevOpen = $courseSelectFile->open;
				}
				$open = '';
				if (isset($_SERVER['HTTP_X_BJUT_OPEN'])) {
					$open = Encryption::decrypt( static::base64_url_decode($_SERVER['HTTP_X_BJUT_OPEN']), Data::ENCRYPTION_KEY, true );
				}
				if (!$prevOpen || !$open || $prevOpen !== $open) {
					$this->status = false;
					$this->errors[] = (object)[
						'code' => 1011,
						'message' => '您没有绑定过微信号或绑定了其他微信号',
					];
					return null;
				}
			}
			$login = $user;
			$this->user = new stdClass();
			$this->user->stuNo = $this->user_id;
			$this->user->password = $this->user_pass;
			$this->API = new APIs( ['stu_id' => $this->user_id, 'stu_pwd' => $this->user_pass] );
		} else {
			$login = $this->apiLogin(false);
		}

		if( is_null($login) ) {
			return null;
		}
		if( $login === false ) {
			$this->clear();
			return $user;
		}
		if ( $password == $this->user->password ) {
//			if ( is_null($this->API) ) {
//				$this->API = new APIs( ['stu_id' => $this->user_id, 'stu_pwd' => $this->user->password] );
//			}
			return $user;
		}
		$this->status = false;
		$this->errors[] = (object)[
			'code' => 1008,
			'message' => '错误的认证信息',
		];
		return null;
	}

	/**
	 * @param bool $flag
	 * @return mixed
	 * @throws Exception
	 */
	public function setUserShareScore(bool $flag) {
		$userId = $this->user_id;
		if ($userId === Settings::DEMO_UID) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 2009,
				'message' => '体验账户暂不支持此功能',
			];
			return null;
		} else if ( $this->API->isNoPassword() ) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 2006,
				'message' => '该模式下不支持此操作',
			];
			return null;
		}
		if( $flag ) {
			$this->storage->set($this->location . '/agreement/' . $userId,
				[
					'agreement' => '20200630'
				]
			);
			if ( !$this->API->isShared() ) {
				$this->API->setShared(true);
				$this->API->getGrade();
				$this->API->getCourseSelect();
			}
		} else {
			$this->setSubscribe('UNSET', '');
			$this->messages[] = '已经取消共享';
			$this->messages[] = '您的所有成绩已经从数据库中删除，新出分的成绩也不会通知和共享。统计信息即将更新';
			$this->API->setShared(false);
			$this->storage->delete($this->location. '/agreement/' . $userId);
			$this->storage->delete($this->location. '/scores/' . $userId);
		}
		return [
			'share_score' => $flag
		];
	}

	/**
	 * @param string $classId
	 * @param string $open
	 * @return mixed
	 * @throws Exception
	 */
	public function setSubscribe(string $classId, string $open) {
		$userId = $this->user_id;
		if ($userId === Settings::DEMO_UID) {
			return null;
			// TODO: 演示后修复
//			$this->status = false;
//			$this->errors[] = (object)[
//				'code' => 2009,
//				'message' => '体验账户暂不支持此功能',
//			];
//			return null;
		} else if ( $this->API->isNoPassword() || !$open || !$this->API->isShared() ) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 2006,
				'message' => '该模式下不支持此操作',
			];
			return null;
		}

		$courseSelect = $this->storage->get($this->location.'/courses/'.$userId);
		if ( $courseSelect ) {
			$courseSelectedIds = [];
			if(is_object($courseSelect) && property_exists($courseSelect, 'terms')) {
				foreach ($courseSelect->terms as $year => $terms) {
					foreach ($terms as $term => $termData) {
						if (property_exists($termData, 'list')) {
							foreach ($termData->list as $course) {
								$courseSelectedIds[(string)$course->courseSelectId] = true;
								$courseSelectedIds[(string)$course->id] = true;
							}
						}
					}
				}
			}
			if (
				is_object($courseSelect) && $classId !== 'UNSET'
			) {
				if (!property_exists( $courseSelect, 'open' ) || $classId === 'RESET') {
					$courseSelect->map = new stdClass();
				} else if (property_exists( $courseSelect, 'open' ) && $courseSelect->open !== $open) {
					$this->status = false;
					$this->errors[] = (object)[
						'code' => 2007,
						'message' => '微信号已经更换，请在设置页重置订阅',
					];
					return null;
				}
			}

			if ($classId === 'UNSET') {
				unset($courseSelect->open);
				unset($courseSelect->map);
			} else {
				if (isset($courseSelectedIds[$classId])) {
					$courseSelect->map->$classId = true;
				}
				$courseSelect->open = $open;
			}
			$this->storage->set($this->location.'/courses/'.$userId, $courseSelect);
			return null;
		} else {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 2002,
				'message' => '您需要先授权才能订阅分数',
			];
			return null;
		}
	}

	private static function is_week($weekNo) {
		$date = new DateTime();
		$week = (int)$date->format("W");
		if (!isset($weekNo[static::$currentYearTerm])) {
			return false;
		}
		$registration_week = $weekNo[static::$currentYearTerm];
		if ($registration_week[0] <= $registration_week[1]) {
			if ($week >= $registration_week[0] && $week <= $registration_week[1]) {
				return true;
			}
		} else {
			if ($week >= $registration_week[0] || $week <= $registration_week[1]) {
				return true;
			}
		}
		return false;
	}

	public function getOverview($course_id, string $open) {
		$stuId = $this->API->getStuId();

		if ( $this->API->isNoPassword() && $stuId !== Settings::DEMO_UID ) {
			$this->messages = ['需登录才可查看课程统计'];
			return null;
		}
		if ( $this->cache->get('WildHelper::screenshot/'.$open) ) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 403,
				'message' => '你因截图了课程统计，已经被屏蔽。请稍后再试',
			];
			return null;
		}
		if ( is_null($course_id) ) {
			$noPermission = (!static::is_week(static::REGISTRATION_WEEK_NO) && $stuId !== Settings::DEMO_UID) || !$this->API->isShared() || $this->API->isNoPassword() || !$this->storage->get($this->location.'/agreement/'.$this->API->getStuId());
			if ($noPermission || $stuId === Settings::DEMO_UID) {
				$overview = $this->storage->get( $this->location.'/overview/_h' );
			} else {
				$overview = $this->storage->get( $this->location.'/overview/_' );
			}
			$this->messages = static::WARNING_MESSAGE;
			return $overview;
		} else {
			$gradedCourses = []; // 已出分课程
			$allowedCourses = []; // 已选课程
			$map = new stdClass();
			$course_select_map = [];
			$scores = $this->storage->get($this->location.'/scores/'.$stuId);
			if (is_object($scores) && property_exists($scores, 'grade_term')) {
				foreach ($scores->grade_term as $course) {
					$gradedCourses[$course->id] = $course;
				}
			}

			$courses = $this->storage->get($this->location.'/courses/'.$stuId);
			if ($courses) {
				if (is_object($courses)) {
					if (property_exists($courses, 'terms')) {
						foreach ($courses->terms as $yearData) {
							foreach ($yearData as $termData) {
								if (property_exists($termData, 'list')) {
									foreach ($termData->list as $course) {
										if (
											!isset($allowedCourses[$course->id]) ||
											$allowedCourses[$course->id]->year.'-'.$allowedCourses[$course->id]->term < $course->year.'-'.$course->term
										) {
											$allowedCourses[$course->id] = $course;
										}
									}
								}
							}
						}
					}
					if (property_exists($courses, 'map')) {
						$map = $courses->map;
						foreach ($map as $key => $_) {
							$course_select_map[$key] = true;
						}
					}
				}
				if (
					isset($allowedCourses[$course_id]) && property_exists($map, $allowedCourses[$course_id]->courseSelectId) &&
					isset($gradedCourses[$course_id]) && $allowedCourses[$course_id]->year === $gradedCourses[$course_id]->year &&
					$allowedCourses[$course_id]->term === $gradedCourses[$course_id]->term
				) {
					unset($map->$course_id);
					$longId = $allowedCourses[$course_id]->courseSelectId;
					unset($map->$longId);
					$this->storage->set($this->location.'/courses/'.$stuId, $courses);
				}
			}
			if ( !$this->API->isShared() || !$this->storage->get($this->location.'/agreement/'.$stuId) ) {
				$course_id = '0007929';
				$this->messages[] = '分数授权协议 (2020.06.29 更新)';
				$this->messages[] = '需授权才可订阅成绩通知、查看选课指导';
				$this->messages[] = '若“接受”授权，您的成绩将被服务提供者收集、存储和使用，并获得查看统计的权限。统计数据属于内部资料，仅限校内学生用于了解课程难度、比对成绩、指导选课，严禁截屏外传或另作他用；若“拒绝”授权，您可以继续使用其他功能。您可以随时取消授权，服务器会删除您的成绩并自动更新统计信息。';
				$this->messages[] = '对统计信息保密并授权我们使用您的成绩 (不含密码)';
			} else {
				$this->messages = static::WARNING_MESSAGE;
				if (isset(static::COURSE_MESSAGE[$course_id])) {
					$this->messages[] = static::COURSE_MESSAGE[$course_id];
				}
				if (substr($course_id, 0, 2) == 'ty' || substr($course_id, 0, 3) == '0ty') {
					$this->messages[] = '由于2019-2020学年第2学期受新冠疫情影响，该学期体育考核标准有变，现已移除该学期统计';
				}
			}

			$course = $this->storage->get($this->location.'/overview/'.$course_id);
			if($course){
				if (property_exists($course, 'redirect')) {
					$course = $this->storage->get($this->location.'/overview/'.$course->redirect);
				}
				if ($course_id !== '0007929') {
					if (
						(!static::is_week(static::REGISTRATION_WEEK_NO) || $this->API->isNoPassword()) &&
						(!isset($allowedCourses[$course_id]) && !isset($gradedCourses[$course_id])) && !Utils::whitelistedCourse($course_id)
					) {
						$this->status = false;
						if ($stuId === Settings::DEMO_UID) {
							$this->errors[] = (object)[
								'code' => 2009,
								'message' => '体验账户不能查看此课程',
							];
						} else if ($this->API->isNoPassword()) {
							$this->errors[] = (object)[
								'code' => 2006,
								'message' => '免密模式不能查看此课程',
							];
						} else {
							$this->errors[] = (object)[
								'code' => 2005,
								'message' => '现在不是选课周，仅限已有此门课程的用户查看',
							];
						}
						return null;
					}
				}
				if ($course->count < 100) {
					$course->scores = null;
				}
				if ( !$this->API->isShared() ) {
					$course->name = '授权后查看真实数据';
				}

				$unknown = [
					'score' => 0,
					'gpa' => 0,
					'count' => 0,
				];
				foreach ($course->instructors as $ins => $data) {
					if ($data->count < 10) {
						$unknown['score'] += $data->score;
						$unknown['gpa'] += $data->gpa;
						$unknown['count'] += $data->count;
						unset($course->instructors->$ins);
					}
				}
				if (!property_exists($course->instructors, '其他教师')) {
					$course->instructors->其他教师 = new stdClass();
					$course->instructors->其他教师->score = $unknown['score'];
					$course->instructors->其他教师->gpa = $unknown['gpa'];
					$course->instructors->其他教师->count = $unknown['count'];
				} else {
					$course->instructors->其他教师->score += $unknown['score'];
					$course->instructors->其他教师->gpa += $unknown['gpa'];
					$course->instructors->其他教师->count += $unknown['count'];
				}
				if ($course->instructors->其他教师->count < $course->count * 0.02 || $course->instructors->其他教师->count < 10) {
					unset($course->instructors->其他教师);
				}

				$instructors = $course->instructors = (array)$course->instructors;
				uksort($course->instructors, function ($a, $b) use (&$instructors){
					return $instructors[$b]->count <=> $instructors[$a]->count;
				});

				$unknown = [
					'score' => 0,
					'gpa' => 0,
					'count' => 0,
				];
				if (is_object($course->year_term)) {
					foreach ($course->year_term as $yearTerm => $data) {
						if ($data->count < 10) {
							$unknown['score'] += $data->score;
							$unknown['gpa'] += $data->gpa;
							$unknown['count'] += $data->count;
							unset($course->year_term->$yearTerm);
						}
					}
					if (!property_exists($course->year_term, '未知学期')) {
						$course->year_term->未知学期 = new stdClass();
						$course->year_term->未知学期->score = $unknown['score'];
						$course->year_term->未知学期->gpa = $unknown['gpa'];
						$course->year_term->未知学期->count = $unknown['count'];
					} else {
						$course->year_term->未知学期->score += $unknown['score'];
						$course->year_term->未知学期->gpa += $unknown['gpa'];
						$course->year_term->未知学期->count += $unknown['count'];
					}
					if (property_exists($course->year_term, '未知学期')) {
						if ($course->year_term->未知学期->count < $course->count * 0.02 || $course->year_term->未知学期->count < 10) {
							unset($course->year_term->未知学期);
						}
					}
				}

				$course->year_term = (array)$course->year_term;
				ksort($course->year_term );

//				if (!static::is_week(static::EXAM_WEEK_NO) && !static::is_week(static::REGISTRATION_WEEK_NO)) {
//					unset($course->instructors);
//				}

				return $course;
			} else {
				$this->status = true;
				$course = new stdClass();
				$course->id = $course_id;
				$course->name = '';
				$course->belong = '';
				$course->score = 100;
				if (Utils::skippedCourse($course)) {
					$this->messages = ['本课程不计入加权，不提供统计数据'];
				} else {
					$this->messages = ['没有本课程的统计数据'];
				}
				return null;
			}
		}
	}

	private function apiLogin( $limit = true ) {
		$loginFailed = $this->cache->get('WildHelper::Data/login/'.$this->user_id);
		if($limit && $loginFailed > 2) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 1009,
				'message' => '密码错误次数过多，您已经被屏蔽，请一小时后再试',
			];
			error_log( date('Y-m-d H:i:s').' '.$this->user_id.' time exceed!'."\r\n", 3, "/opt/wild/log/pwd_wrong.txt");
			return null;
		}

		if( is_null($this->user_id) ) {
			$this->status = false;
			$this->errors[] = (object)[
				'code' => 2001,
				'message' => '内部错误 2001',
			];
			error_log( date('Y-m-d H:i:s').' '.$this->user_id.' error 2001!'."\r\n", 3, "/opt/wild/log/pwd_wrong.txt");
			return null;
		}

		$this->API = new APIs( ['stu_id' => $this->user_id, 'stu_pwd' => $this->user_pass] );
		try {
			if (Settings::MAINTENANCE_CODE) {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => Settings::MAINTENANCE_CODE,
					'message' => Settings::MAINTENANCE_MESSAGE,
				];
				return false;
			}
			$this->API->login_vpn();
		} catch (SpiderException $e) {
			if ( $e->getCode() == 1010 ) {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => 531,
					'message' => '教务网关错误，尚无法通过 VPN 连接。如果持续登录失败，您可以试试免密登录',
				];
				return false;
			} else {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
				];
				return false;
			}
		}
		try {
			$this->API->login();
		} catch (SpiderException $e) {
			if ( $e->getCode() == 1003 ) {
				if ($limit) {
					$this->status = false;
					$loginFailed = $this->cache->get('WildHelper::Data/login/'.$this->user_id);
					$this->errors[] = (object)[
						'code' => 1003,
						'message' => '用户名或密码错误，请输入教务系统的密码。您还剩'.(2-$loginFailed).'次机会。毕业后账户密码会被教务重置，请将密码留空进行免密登录',
					];
					error_log( date('Y-m-d H:i:s').' '.$this->user_id.' pwd wrong!'."\r\n", 3, "/opt/wild/log/pwd_wrong.txt");
					$this->cache->set('WildHelper::Data/login/'.$this->user_id, $loginFailed+1, 3600);
				} else {
					$this->status = false;
					$this->errors[] = (object)[
						'code' => 2008,
						'message' => '密码错误或教务系统崩溃，请稍后刷新重试。如果持续遇到此问题，请尝试重新登录',
					];
					error_log( date('Y-m-d H:i:s').' '.$this->user_id.' pwd wrong! no limit'."\r\n", 3, "/opt/wild/log/pwd_wrong.txt");
				}
				return null;
			} elseif ( $e->getCode() == 1002 ) {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => 1002,
					'message' => '用户名不正确',
				];
				return false;
			} elseif ( $e->getCode() == 1001 ) {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => 502,
					'message' => '教务系统暂时不可用',
				];
				return false;
			} else {
				$this->status = false;
				$this->errors[] = (object)[
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
				];
				return null;
			}
		}
		$this->user = new stdClass();
		$this->user->stuNo = $this->user_id;
		$this->user->password = $this->user_pass;
		$this->user->time = time();
		$this->user->tried = 0;

		return $this->user;
	}

	public static function base64_url_encode($input) {
		return strtr($input, '+/=', '._-');
	}

	public static function base64_url_decode($input) {
		return strtr($input, '._-', '+/=');
	}
}
