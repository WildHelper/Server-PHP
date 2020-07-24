<?php


namespace WildHelper;

if (!function_exists("array_key_last")) {
	function array_key_last($array) {
		if (!is_array($array) || empty($array)) {
			return NULL;
		}
		return array_keys($array)[count($array)-1];
	}
}

class Analyze {

	private string $location;

	private array $generated_data;
	private Storage $storage;

	function __construct(string $l = '/opt/wild'){
		$this->storage = new Storage();
		$this->location = $l;
		$this->generated_data = $this->generate();
		$bulkSet = [];
		foreach ($this->generated_data['courses'] as $courseId => &$course) {
			if (isset($course['count']) || isset($course['redirect'])) {
				$course['time'] = $this->generated_data['time'];
				if (!isset($course['redirect'])) {
					arsort($course['majors_map']);
					foreach ($course['majors_map'] as $major => $count) {
						if ($count > $course['count'] * 0.02 && !in_array($major, $course['majors'])) {
							array_push($course['majors'], $major);
						}
					}
					unset($course['majors_map']);
					$originalCount = count($course['type_full']);
					$course['type_full'] = array_values(array_diff($course['type_full'], ['实践环节（辅）', '学科基础必修课（辅）', '专业必修课（辅）']));
					if ($originalCount > 0 && count($course['type_full']) === 0) {
						$course['type_full'] = ['辅修课程'];
					}
					if (
						count($course['majors']) > 0
						|| in_array('公共基础必修课', $course['type_full'])
						|| in_array('实践环节必修课', $course['type_full'])
					) {
						$course['type_full'] = array_values(array_diff($course['type_full'], ['通识教育任意选修']));
					}
					if (count($course['type_full']) > 1) {
						$course['type_full'] = array_values(array_diff($course['type_full'], ['通识教育选修课']));
					}
					if (substr(end($course['id_full']), 0, 2) === 'ty' || substr(end($course['id_full']), 0, 3) === '0ty') {
						$course['type_full'] = ['体育课'];
						$course['is_elective'] = true;
					} else if (
						count(array_intersect(
							[
								'通识教育选修课', '外语选修课', '通识教育任意选修', '经济管理选修课', '数学与自然科学选修课', '校选修课',
								'经管文法艺术类选修课', '工程自然类选修课'
							], $course['type_full'])) > 0
					) {
						$course['is_elective'] = true;
						if (!in_array('通识教育选修课', $course['type_full'])) {
							array_push($course['type_full'], '通识教育选修课');
						}
					} else {
						if (count($course['type_full']) > 1) {
							$course['type_full'] = array_values(array_diff($course['type_full'], ['任意选修课']));
						}
					}
				}

				if (isset($course['count'])) {
					if ($course['count'] < 100) {
						unset($course['scores']);
					}
					if ($course['count'] < 10) {
						$course['avg'] = '隐藏';
						$course['std'] = '隐藏';
						$course['A'] = '隐藏';
						$course['B'] = '隐藏';
						$course['C'] = '隐藏';
						$course['F'] = '隐藏';
						unset($course['instructors']);
						unset($course['year_term']);
					}
				}

				$bulkSet[$this->location.'/overview/'.$courseId] = $course;
			}
//			else {
//				$this->storage->delete($this->location.'/overview/'.$courseId);
//			}
		}
		$this->storage->pkrset($bulkSet);
		error_log(date('Y-m-d H:i:s').' [Analyze] Finished All Courses!'."\r\n");
		echo 'All Courses!'."\r\n";
		$result = [];
		foreach ($this->generated_data['courses'] as $key => $value) {
			if (isset($value['scores'])) {
				unset($value['scores']);
				unset($value['instructors']);
				unset($value['year_term']);
				unset($value['time']);
			}
			if ( isset($value['count']) && $value['count'] > 10 ) {
				$result[] = $value;
			}
		}
		usort($result, function ($a, $b) {
			if ($a['type_full'][0] === '体育课') {
				$a['count'] /= 2;
			}
			if ($b['type_full'][0] === '体育课') {
				$b['count'] /= 2;
			}
			return $b['count'] <=> $a['count'];
		});
		$this->storage->set($this->location.'/overview/_', $result);
		error_log(date('Y-m-d H:i:s').' [Analyze] Overall!'."\r\n");
		echo 'Overall!'."\r\n";
		foreach ($result as &$value) {
			if (!Utils::whitelistedCourse($value) && $value['type_full'][0] !== '体育课') {
				$value['avg'] = '隐藏';
				$value['std'] = '隐藏';
				$value['A'] = '隐藏';
				$value['B'] = '隐藏';
				$value['C'] = '隐藏';
				$value['F'] = '隐藏';
			}
		}
		$this->storage->set($this->location.'/overview/_h', $result);
		error_log(date('Y-m-d H:i:s').' [Analyze] Overall Hidden!'."\r\n");
		echo 'Overall Hidden!'."\r\n";
	}

	public static function majorBlacklist($courseId) {
		$blacklist = [
			'0007069', // 毛概实践
			'0002784', // 军事理论
			'0003087', // 军事训练
			'0004964', // 物理实验1
			'0004965', // 物理实验2
			'0004746', // 机械工程训练A
		];
		return in_array($courseId, $blacklist);
	}

	private function generate() {

		$courses = [];
		$cets_ana = [];
		$students = ['gpa'=>[0.0=>0], 'wes'=>[0.0=>0], 'qpa'=>[0.0=>0], 'score'=>[0.0=>0], 'count'=>0, 'majors'=>[]];

		$empty_score = [];
		$empty_gpa = [];
		$empty_score['通过'] = 0;
		for ($i = 0; $i <= 100; $i++){
			$empty_score[(int)$i] = 0;
		}
		$students['score'] = $empty_score;
		for ($i = 0; $i <= 80; $i++){
			$empty_gpa[(int)$i] = 0;
		}
		$students['qpa'] = $students['gpa'] = $students['wes'] = $empty_gpa;
		$nameIdMap = [];
		$idNameMap = [];
		$maxTime = 1590472611;
		$courseOpenMap = [];

		$success = 0;
		$ret = $this->storage->pkrget('/opt/wild/scores/', 100);
		$courses_bulk = $this->storage->pkrget($this->location . '/courses/', 100);
		while (true) {
			foreach($ret as $stuId => $content) {
				$success++;
				$cets = $coursesList = $unsetIds = $rawCourses = [];
				if (!$content) {
					$content = [];
				} else {
					$content = json_decode(json_encode($content), true);
				}
//			    if(file_exists($this->location.'/cets/' . $stuId)) {
//			    	$cets = json_decode(file_get_contents($this->location . '/cets/' . $stuId), true);
//			    }

				$stuId_short = substr($stuId, 17);
				$stuId_full = '/opt/wild/courses/'.$stuId_short;

				if (count($courses_bulk) == 100 && substr(array_key_last($courses_bulk), 18) < $stuId_short) {
					$courses_bulk = $this->storage->pkrget('/opt/wild/courses/', 100, array_key_last($courses_bulk));
				}

				if(isset($courses_bulk[$stuId_full]) && is_object($courses_bulk[$stuId_full])) {
					if (property_exists($courses_bulk[$stuId_full], 'terms')) {
						foreach ($courses_bulk[$stuId_full]->terms as $year => $terms) {
							foreach ($terms as $term => $termData) {
								if (property_exists($termData, 'list')) {
									foreach ($termData->list as $course) {
										$rawCourses[$year.'-'.$term.'-'.$course->id] = $course;
										$coursesList[$course->courseSelectId] = $course;
									}
								}
							}
						}
					}
					if (property_exists($courses_bulk[$stuId_full], 'map')) {
						$changed = false;
						foreach ($courses_bulk[$stuId_full]->map as $key => $_) {
							if (strlen($key) >= 29) {
								if (!isset($coursesList[$key])) {
									unset($courses_bulk[$stuId_full]->map->$key);
									$changed = true;
									continue;
								}
								if (!isset($courseOpenMap[$key])) {
									$courseOpenMap[$key] = [
										'course' => $coursesList[$key],
										'users' => [
											$stuId => $courses_bulk[$stuId_full]->open
										]
									];
								} else {
									$courseOpenMap[$key]['users'][$stuId] = $courses_bulk[$stuId_full]->open;
								}
							} else {
								unset($courses_bulk[$stuId_full]->map->$key);
								$changed = true;
							}
						}
						if ($changed) {
							$this->storage->set($this->location.'/courses/' . $stuId, $courses_bulk[$stuId_full]);
						}
					}
				}

//			if(isset($cets['results']) && ($c = count($cets['results'])) > 0) {
//				for ($i = 0; $i < $c; $i++){
//					$cet_scores = $cets['results'][$i];
//					if($cet_scores['total'] == 0){
//						continue;
//					}
//					$name = strtoupper($cet_scores['name']);
//					if(!isset($cets_ana[$name])){
//						$cets_ana[$name] = ['total' => 0, 'number' => 0, 'reading' => 0, 'listening' => 0,
//							'comprehensive' => 0, 'passed' => 0, 'max' => 0];
//					}
//					$cets_ana[$name]['total'] += $cet_scores['total'];
//					$cets_ana[$name]['reading'] += $cet_scores['reading'];
//					$cets_ana[$name]['listening'] += $cet_scores['listening'];
//					$cets_ana[$name]['comprehensive'] += $cet_scores['comprehensive'];
//					$cets_ana[$name]['number']++;
//					if($cet_scores['total'] > $cets_ana[$name]['max']){
//						$cets_ana[$name]['max'] = $cet_scores['total'];
//					}
//					if($cet_scores['total'] >= 425){
//						$cets_ana[$name]['passed']++;
//					}
//				}
//			}

				if(isset($content['institute']) && isset($content['major'])) {
					$major = $content['institute'] . ' - ' . $content['major'];
					if(!isset($students['majors'][$major])){
						$students['majors'][$major] = ['total_score' => 0, 'total_gpa' => 0, 'count' => 0];
					}
					$students['majors'][$major]['total_score'] += $content["average_score_term"];
					$students['majors'][$major]['total_gpa'] += $content["average_GPA_term"];
					$students['majors'][$major]['count']++;
				}

				if(isset($content["grade_term"])) {
					foreach ($content["grade_term"] as $course) {
						if (Utils::skippedCourse($course)) {
							// $this->storage->delete($this->location.'/overview/'.$course['id']);
							continue;
						}
						if (is_numeric($course['makeup_score'])) {
							if ($course['makeup_score'] === $course['score']) {
								$originalScore = preg_replace('/[^0-9.]+/', '', $course['comment']);
								if (is_numeric($originalScore)) {
									$course['score'] = $originalScore;
								} else {
									continue;
								}
							}
						}
						if ($course['minor_maker'] > '0' || $course['retake_maker'] == '1') {
							continue;
						}
						$instructor = '其他教师';
						if (isset($rawCourses[$course['year'].'-'.$course['term'].'-'.$course['id']])) {
							$instructor = $rawCourses[$course['year'].'-'.$course['term'].'-'.$course['id']]->instructor;
						}

						if (substr($course['id'], 0, 2) === 'ty' || substr($course['id'], 0, 3) === '0ty') {
							$course['academy'] = '体育教学部';
						} else if (trim($course['academy']) == '' || $course['academy'] === ' ') {
							$course['academy'] = '未知学院';
						}
						if (mb_substr($course['academy'], 0, 2) === '樊恭') {
							$course['academy'] = '樊恭烋荣誉学院';
						}
						if ($course['type'] === '通识教育选修') {
							$course['type'] = '通识教育选修课';
						}

						if (trim($course['type']) == '' || $course['type'] == ' ') {
							$course['type'] = '未知性质';
						}

						$course['name'] = str_replace(['（', '）'], ['(', ')'], $course['name']);
						// $course['type'] = str_replace('（辅）', '', $course['type']);

						$redirected = false;
						/**
						 * 合并体育课
						 */
						if (
							substr($course['id'], 0, 2) === 'ty' || substr($course['id'], 0, 3) === '0ty'
						) {
							$redirected = true;
							$course['name'] = explode('-', $course['name'])[0];
							if (!isset($idNameMap[$course['id']])) {
								$idNameMap[$course['id']] = $course['name'];
							} else {
								$course['name'] = $idNameMap[$course['id']];
							}
							if(!isset($nameIdMap[$course['name']])) {
								$nameIdMap[$course['name']] = $course['id'];
							} else {
								if ($course['id'] !== $nameIdMap[$course['name']] && !isset($courses[$course['id']])) {
									$courses[$course['id']] = [
										'redirect' => $nameIdMap[$course['name']],
									];
								}
								$course['id'] = $nameIdMap[$course['name']];
							}
						}
						if (isset($rawCourses[$course['year'].'-'.$course['term'].'-'.$course['id']])) {
							$instructor = $rawCourses[$course['year'].'-'.$course['term'].'-'.$course['id']]->instructor;
						} else if (!$redirected) {
							continue;
						}
						if(!isset($courses[$course['id']])){
							$courses[$course['id']] = [
								'scores' => $empty_score,
								'name' => $course['name'],
								'type' => $course['type'],
								'type_full' => [$course['type']],
								'credit' => $course['credit'],
								'id' => $course['id'],
								'id_full' => [$course['id']],
								'belong' => $course['belong'],
								'academy' => $course['academy'],
								'academy_full' => [$course['academy']],
								'instructors' => [],
								'majors_map' => [],
								'majors' => [],
							];
						} else {
							if (trim($course['belong']) != '' && $course['belong'] != ' ' && !in_array($course['belong'], $courses[$course['id']]['type_full'])) {
								array_push($courses[$course['id']]['type_full'], $course['belong']);
							}
							if (!in_array($course['type'], $courses[$course['id']]['type_full'])) {
								array_push($courses[$course['id']]['type_full'], $course['type']);
							}
							if (!in_array($course['academy'], $courses[$course['id']]['academy_full'])) {
								array_push($courses[$course['id']]['academy_full'], $course['academy']);
							}
						}
						$majorCourses = [
							'学科基础必修课',
							'实践环节必修课',
							'学科基础选修课',
							'实践环节选修课',
							'专业任选课',
							'专业限选课'
						];
						if (in_array($course['type'], $majorCourses) && !self::majorBlacklist($course['id'])) {
							if (!isset($courses[$course['id']]['majors_map'][$content['major']])) {
								$courses[$course['id']]['majors_map'][$content['major']] = 1;
							} else {
								++$courses[$course['id']]['majors_map'][$content['major']];
							}
						}
						if ($course['score'] > 0) {
							if($course['score'] >= 85) {
								$course['gpa'] = 4;
							} elseif ($course['score'] >= 70) {
								$course['gpa'] = 3;
							} elseif ($course['score'] >= 60) {
								$course['gpa'] = 2;
							} else {
								$course['gpa'] = 0;
							}
							$yearTerm = substr($course['year'], 2, 2).'-'.substr($course['year'], 7, 2).'-'.$course['term'];
							if ($course['year'] >= '2016-2017') {
								if (!isset($courses[$course['id']]['year_term'][$yearTerm])) {
									$courses[$course['id']]['year_term'][$yearTerm] = [
										'score' => (int)$course['score'],
										'gpa' => (int)$course['gpa'],
										'count' => 1,
									];
								} else {
									$courses[$course['id']]['year_term'][$yearTerm]['score'] += (int)$course['score'];
									$courses[$course['id']]['year_term'][$yearTerm]['gpa'] += (int)$course['gpa'];
									$courses[$course['id']]['year_term'][$yearTerm]['count']++;
								}
							}

							if ($course['year'] == '2019-2020' && $course['term'] == '2') {
								if ($course['academy'] === '体育教学部') {
									continue;
								}
							}

							if (!isset($courses[$course['id']]['instructors'][$instructor])) {
								$courses[$course['id']]['instructors'][$instructor] = [
									'score' => (int)$course['score'],
									'gpa' => (int)$course['gpa'],
									'count' => 1,
								];
							} else {
								$courses[$course['id']]['instructors'][$instructor]['score'] += (int)$course['score'];
								$courses[$course['id']]['instructors'][$instructor]['gpa'] += (int)$course['gpa'];
								$courses[$course['id']]['instructors'][$instructor]['count']++;
							}
						}
						$course['score'] = (int)floor($course['score']);
						if (!isset($courses[$course['id']]['scores'][$course['score']])) {
							$courses[$course['id']]['scores'][$course['score']] = 1;
						} else {
							$courses[$course['id']]['scores'][$course['score']]++;
						}
					}
					if(isset($content['total_value']) && $content['total_value'] > 40) {
						$students['count']++;
						$gpa = floor($content['average_GPA_term'] * 100 / 5);
						if($content['average_GPA_term'] < 2.5) {
							$students['gpa']['0']++;
						} else {
							$students['gpa'][$gpa]++;
						}

						$wes = floor($content['average_GPA_term_WES'] * 100 / 5);
						if($content['average_GPA_term_WES'] < 2.5) {
							$students['wes']['0']++;
						} else {
							$students['wes'][$wes]++;
						}

						if(isset($content['average_QPA_term'])) {
							$qpa = floor($content['average_QPA_term'] * 100 / 5);
							if($content['average_QPA_term'] < 2.5) {
								$students['qpa']['0']++;
							} else {
								$students['qpa'][$qpa]++;
							}
						}

						$score = floor($content["average_score_term"]);
						if($content["average_score_term"] < 70) {
							$students['score'][0]++;
						} else {
							$students['score'][$score]++;
						}
					}
				}

				if(isset($content['time']) && $content['time'] > $maxTime) {
					$maxTime = $content['time'];
				}
			}
			end($ret);
			$start_key = key($ret);
			$i = count($ret);
			if ($i < 100) break;
			$ret = $this->storage->pkrget('/opt/wild/scores/', 100, $start_key);
			error_log( date('Y-m-d H:i:s').' [Analyze] '.$start_key."\r\n" );
		}

		foreach ($courses as $key => $course) {
			if(isset($course['redirect'])) {
				array_push($courses[$course['redirect']]['id_full'], $key);
				sort($courses[$course['redirect']]['id_full']);
				continue;
			}
			$avg = 0;
			$std = 0;
			$max = 0;
			$four = 0;
			$three = 0;
			$two = 0;
			$zero = 0;
			$total = 0;
			foreach ($course['scores'] as $score => $count) {
				if ($score > 0 && $count > 0) {
					if($score > $max) {
						$max = $score;
					}
					if($score >= 85) {
						$four += $count;
					} elseif ($score >= 70) {
						$three += $count;
					} elseif ($score >= 60) {
						$two += $count;
					} else {
						$zero += $count;
					}
					$avg += $count * $score;
					$total += $count;
				}
			}
			$avg /= $total>0 ? $total : 1;
			foreach ($course['scores'] as $score => $count) {
				if ($score > 0 && $count > 0) {
					$std += ($score - $avg) * ($score - $avg) * $count;
				}
			}
			if($total > 1) {
				$std = sqrt($std/($total - 1));
			} else {
				$std = 100.0;
			}
			$courses[$key]['avg'] = round($avg, 2);
			$courses[$key]['std'] = round($std, 2);
			$courses[$key]['max'] = $max;
			$courses[$key]['A'] = $four;
			$courses[$key]['B'] = $three;
			$courses[$key]['C'] = $two;
			$courses[$key]['F'] = $zero;
			$courses[$key]['count'] = $total;
			$courses[$key]['type_full'] = array_diff(array_unique($courses[$key]['type_full']), ['未知性质']);
			$courses[$key]['academy_full'] = array_diff(array_unique($courses[$key]['academy_full']), ['未知学院']);
			sort($courses[$key]['id_full']);
		}
		krsort($students['gpa']);
		krsort($students['qpa']);
		krsort($students['wes']);
		krsort($students['score']);

		$bulkSet = [];
		foreach ($courseOpenMap as $key => $value) {
			if ($value['course']->year.'-'.$value['course']->term < Data::$minYearTerm) {
				continue;
			}
			$filePath = $this->location . '/subscribe/all';
			$filePath .= '/'.$value['course']->year;
			$filePath .= '/' . $value['course']->term;
			$bulkSet[$filePath . '/' . $key] = $value;
		}
		$this->storage->pkrset($bulkSet);

		return ['courses' => $courses, 'students' => $students, 'cet' => $cets_ana, 'time'=>$maxTime];
	}
}