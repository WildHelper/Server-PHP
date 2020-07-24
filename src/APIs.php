<?php


namespace WildHelper;


use ZfSpider\Client;
use stdClass;

class APIs extends Client {
	private string $location;

	private array $updated_data = [];
	private bool $shared;
	private bool $loggedIn = false;
	private Cache $cache;
	private KV $storage;

	/**
	 * @return bool
	 */
	public function isShared(): bool
	{
		return $this->shared;
	}

	/**
	 * @param bool $shared
	 */
	public function setShared(bool $shared): void
	{
		$this->shared = $shared;
	}

	/**
	 * @return mixed
	 */
	public function getStuId()
	{
		return $this->stu_id;
	}

	/**
	 * @return bool
	 */
	public function isLoggedIn(): bool
	{
		return $this->loggedIn;
	}

	public function isNoPassword(): bool
	{
		return !$this->password || $this->password === "\nS" || $this->password === "\nE";
	}

	public static function testNoPassword($password): bool
	{
		return !$password || $password === "\nS" || $password === "\nE";
	}

	public static function testShareScorePassword($password): bool
	{
		return $password === "\nE";
	}

	public function isShareSchedulePassword(): bool
	{
		return $this->password === "\nS";
	}

	public function __construct($user, $loginParam = [], $vpn_url = Settings::VPN_URL, $base_url = Settings::BASE_URL, $request_options = [], $l = '/opt/wild')
	{
		parent::__construct($user, $loginParam, $vpn_url, $base_url, $request_options);
		$this->cache = new Cache();
		$this->storage = new Storage();
		$this->location = $l;
		$this->shared = $this->storage->get($this->location.'/scores/'.$this->stu_id) !== false;
	}

	public function login()
	{
		if ($this->isNoPassword()) {
			$this->loggedIn = false;
			return null;
		}
		$cookie = $this->cache->get( 'WildHelper::cookie2/'.$this->stu_id );
		if ( $cookie ) {
			$this->setCookieJar($cookie);
			$this->loggedIn = true;
			return $cookie;
		}
		$ret = parent::login();
		$this->loggedIn = true;
		$this->cache->set( 'WildHelper::cookie2/'.$this->stu_id, $ret, 300 );
		return $ret;
	}

	public function login_vpn(string $username = Settings::VPN_USERNAME, string $password = Settings::VPN_PASSWORD)
	{
		$cookie = $this->cache->get( 'WildHelper::cookie_vpn' );
		if ( $cookie ) {
			$this->setCookieJar($cookie);
			return $cookie;
		}
		$ret = parent::login_vpn($username, $password);
		$this->cache->set( 'WildHelper::cookie_vpn', $ret, 3600 );
		return $ret;
	}

	public function getGrade($ttl = 10, $fromSchedule = false)
	{
		if ($this->isShareSchedulePassword() && !$fromSchedule) {
			return null;
		}
		$ret = $this->storage->get($this->location.'/scores/'.$this->stu_id);
		if (is_object($ret) && property_exists($ret, 'time') && $ret->time + $ttl > time()) {
			$ret->updated_time = time();
			return $ret;
		}
		$ret = null;
		if ($this->loggedIn) {
			$ret = parent::getGrade();
			if ( is_object($ret) ) {
				Utils::calcGpa( $ret, 'grade_term' );
			}
		}
		return $this->wrapper( $ret, 'scores' );
	}

	public function getCet()
	{
		$ret = $this->storage->get($this->location.'/cets/'.$this->stu_id);
		if (is_object($ret) && property_exists($ret, 'time') && $ret->time + 60 > time()) {
			return $ret;
		}
		$obj = null;
		if ($this->loggedIn) {
			$obj = new stdClass();
			$obj->results = parent::getCet();
		}
		return $this->wrapper( $obj, 'cets' );
	}

	public function getExams()
	{
		$obj = null;
		if ($this->loggedIn) {
			$obj = parent::getExams();
		}
		return $this->wrapper( $obj, 'exams' );
	}

	public function getExams2()
	{
		$ret = null;
		if ($this->loggedIn) {
			$ret = parent::getExams();
		}
		if (!is_null($ret) && count($ret) > 0) {
			return [
				'list' => $ret,
				'time' => time(),
				'updated_time' => time()
			];
		}
		$ret = $this->wrapper( $ret, 'exams' );
		return [
			'list' => $ret,
			'time' => time(),
			'updated_time' => time()
		];
	}

	public function getCourseSelect($year = '', $term = '')
	{
		$ret = null;
		if ($this->loggedIn) {
			$ret = parent::getCourseSelect($year, $term);
		}
		$ret = $this->wrapper( $ret, 'courses', $year, $term );
		return $ret;
	}

	private function wrapper( $obj, $slug='', $year = '', $term = '' ) {
		$file = $this->location.'/'.$slug.'/'.$this->stu_id;

		$prev = null;
		if ( $slug != '' ) {
			$prev = $this->storage->get($file);
			if(!is_null($prev) && is_object($prev)) {
				$prev->updated_time = time();
			}
		}
		if ( $this->loggedIn && is_null($obj) && $slug !== 'courses' ) {
			$this->storage->set($file, $prev);
			return $prev;
		} else {
			if (is_object($obj)) {
				$obj->time = time();
				$obj->updated_time = time();
			}
			if ($this->shared && $slug != '') {
				switch ($slug) {
					case 'scores':
						$this->updated_data = [];
						if (
							is_null($prev) || !is_object($prev) || !property_exists($prev, 'term_lesson_count')
							|| (is_object($obj) && property_exists($obj, 'term_lesson_count') && $obj->term_lesson_count > 0)
						) {
							$count = -1;
							if (is_object($obj) && property_exists($obj, 'term_lesson_count') && is_object($prev) && property_exists($prev, 'term_lesson_count')) {
								$count = $obj->term_lesson_count - $prev->term_lesson_count;
							}
							if ($count > 0 && !is_null($prev) && property_exists($prev, 'term_lesson_count') && $prev->term_lesson_count > 0) {
								$existing_course = [];
								foreach ($prev->grade_term as $course) {
									$existing_course[$course->year.'-'.$course->term.'-'.$course->id] = true;
								}
								foreach ($obj->grade_term as $course) {
									if (!isset($existing_course[$course->year.'-'.$course->term.'-'.$course->id])) {
										$newCourse = new stdClass();
										$newCourse->name = $course->name;
										$newCourse->credit = number_format($course->credit, 1);
										$newCourse->score = $course->score;
										$newCourse->id = $course->id;
										$newCourse->year = $course->year;
										$newCourse->term = $course->term;
										$this->updated_data[] = $newCourse;
									}
								}
								$this->triggerUpdate();
							}
							$this->storage->set($file, $obj);
						} else {
							Utils::calcGpa( $prev, 'grade_term' );
							return $prev;
						}
						break;
					case 'cets':
						if (
							is_null($prev) || (is_object($prev) && !property_exists($prev, 'results')) ||
							(is_object($obj) && property_exists($obj, 'results') && count($obj->results) > 0)
						) {
							$this->storage->set($file, $obj);
						} else {
							return $prev;
						}
						break;
					case 'exams':
						if (is_null($prev) || !is_null($obj)) {
							$this->storage->set($file, $obj);
						} else {
							return $prev;
						}
						break;
					case 'courses':
						if(is_null($prev) || !is_object($prev)) {
							$prev = new stdClass();
						}
						if (!is_null($obj) &&
							property_exists($obj, 'year') && $obj->year &&
							property_exists($obj, 'term') && $obj->term
						) {
							$y = $obj->year;
							$t = $obj->term;
							if ( !property_exists($prev, 'terms') ) {
								$prev->terms = new stdClass();
							}
							if ( !property_exists($prev->terms, $y) ) {
								$prev->terms->$y = new stdClass();
							}
							if ( property_exists($obj, 'list') ) {
								if (count($obj->list) === 0) {
									if (property_exists($prev->terms->$y, $t) && property_exists($prev->terms->$y->$t, 'list')) {
										if (count($prev->terms->$y->$t->list) > 0) {
											$prev->terms->$y->$t->updated_time = time();
										} else {
											$prev->terms->$y->$t = $obj;
										}
									}
									$maxYear = '0000-0000';
									$maxTerm = '0';
									foreach ($prev->terms as $year => $terms) {
										foreach ($terms as $term => $termData) {
											if (count($termData->list) === 0) {
												continue;
											}
											if ($year.'-'.$term > $maxYear.'-'.$maxTerm) {
												$maxYear = $year;
												$maxTerm = $term;
											}
										}
									}
									if (property_exists($prev->terms, $maxYear) && property_exists($prev->terms->$maxYear, $maxTerm)) {
										return $prev->terms->$maxYear->$maxTerm;
									}
								} else {
									$prev->terms->$y->$t = $obj;
								}
							} else {
								return null;
							}
							if ( property_exists($obj, 'time') ) {
								$prev->time = $obj->time;
							}
							$prev->updated_time = time();
							$this->storage->set($file, $prev);
						} else {
							if (property_exists($prev, 'terms')) {
								if (!$year || !$term) {
									$ret = null;
									$maxYear = '0000-0000';
									$maxTerm = '0';
									foreach ($prev->terms as $year => $yearData) {
										foreach ($yearData as $term => $termData) {
											if ($year . '-' . $term > $maxYear . '-' . $maxTerm) {
												$maxYear = $year;
												$maxTerm = $term;
												$ret = $termData;
											}
										}
									}
									if (is_object($ret)) {
										$ret->updated_time = time();
									}
									$this->storage->set($file, $prev);
									return $ret;
								} else {
									if ( !property_exists($prev->terms, $year) ) {
										$prev->terms->$year = new stdClass();
									}
									if ( property_exists($prev->terms->$year, $term) ) {
										$prev->terms->$year->$term->updated_time = time();
										if ( !property_exists($prev->terms->$year->$term, 'list') ) {
											$prev->terms->$year->$term->list = [];
										}
										if ( !property_exists($prev->terms->$year->$term, 'time') ) {
											$prev->terms->$year->$term->time = 0;
										}
									} else {
										$prev->terms->$year->$term = (object)[
											'year' => $year,
											'term' => $term,
											'list' => [],
											'time' => 0,
											'updated_time' => time()
										];
									}
									$this->storage->set($file, $prev);
									return $prev->terms->$year->$term;
								}
							} else {
								return null;
							}
						}
					default:
						// Nothing
				}
			}
		}
		return $obj;
	}

	private function triggerUpdate() {
		$file = $this->location . '/courses/' . $this->stu_id;
		$courseSelectFile = $this->storage->get($file);
		if (is_null($courseSelectFile)) {
			$this->storage->delete($file);
		} else if (is_string($courseSelectFile)) {
			$courseSelectFile = json_decode($courseSelectFile);
		}
		$map = new stdClass();
		if (!is_null($courseSelectFile) && property_exists($courseSelectFile, 'map')) {
			$map = $courseSelectFile->map;
		}
		$coursesWithInstructor = [];
		if (!is_null($courseSelectFile) && property_exists($courseSelectFile, 'open')) {
			foreach ($courseSelectFile->terms as $year => $yearData) {
				foreach ($yearData as $term => $courseData) {
					foreach ($courseData->list as $course) {
						$coursesWithInstructor[$course->year.'-'.$course->term.'-'.$course->id] = $course;
					}
				}
			}
			foreach ($this->updated_data as $course) {
				$newId = $coursesWithInstructor[$course->year.'-'.$course->term.'-'.$course->id]->courseSelectId;
				if(property_exists($map, $newId)) {
					$filePath = $this->location . '/subscribe/started/' . $course->year;
					$filePath .= '/' . $course->term;
					$finished = $this->storage->get($this->location . '/subscribe/finished/' . $course->year . '/' . $course->term . '/' . $newId);
					if (!$finished) {
						$this->storage->set($filePath . '/' . $newId, true);
						error_log( date('Y-m-d H:i:s').' [Subscribe] '.explode('?', $_SERVER['REQUEST_URI'])[0].' by '.$this->stu_id.' '.$course->name.' '.$newId."\r\n", 3, "/opt/wild/log/log.txt");
					}
					$this->storage->set($file, $courseSelectFile);
				}
			}
		}
		$this->updated_data = [];
	}
}
