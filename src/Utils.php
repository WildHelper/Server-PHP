<?php


namespace WildHelper;


use stdClass;

class Utils
{
	public static function initTerms($terms, $y, $t) {
		$ret = true;
		if(!property_exists($terms, $y)) {
			$terms->$y = new stdClass();
			$ret = false;
		}
		if(!property_exists($terms->$y, $t)) {
			$terms->$y->$t = new stdClass();
			$ret = false;
		}
		if(!property_exists($terms->$y->$t, 'courses')) {
			$terms->$y->$t->courses = [];
			static::setZero($terms->$y->$t, 'term_lesson_count');
			static::setZero($terms->$y->$t, 'average_score_term');
			static::setZero($terms->$y->$t, 'average_score_minor');
			static::setZero($terms->$y->$t, 'average_GPA_term');
			static::setZero($terms->$y->$t, 'average_GPA_minor');
			$ret = false;
		}
		return $ret;
	}

	public static function setZero($obj, $attr) {
		if( !property_exists($obj, $attr) ) {
			$obj->$attr = 0;
		}
	}

	/**
	 * 不计算加权，不显示在选课指导里面的课
	 * @param $course
	 * @return bool
	 */
	public static function skippedCourse( $course ) {
		if (is_object($course)) {
			return !is_numeric($course->score)
				|| $course->belong =='第二课堂'
				|| $course->name == '新生研讨课'
				|| $course->id == '0009287' // 形势与政策
				|| $course->id == '0007143' // 形势与政策
				|| $course->id == '0006794' // 大学生心理适应指导
				|| $course->id == '0006647' // 职业生涯规划
				|| $course->id == '0009380' // 国庆70周年系列活动
				|| $course->id == '0002618';// 就业指导课
		} else {
			return !is_numeric($course['score'])
				|| $course['belong'] =='第二课堂'
				|| $course['name'] == '新生研讨课'
				|| $course['id'] == '0009287'
				|| $course['id'] == '0007143'
				|| $course['id'] == '0006794'
				|| $course['id'] == '0006647'
				|| $course['id'] == '0009380'
				|| $course['id'] == '0002618';
		}
	}

	public static function whitelistedCourse( $course ) {
		if (is_string($course)) {
			$course = ['id' => $course];
		}
		if (is_object($course) && property_exists($course, 'id')) {
			$course = ['id' => $course->id];
		}
		if (is_array($course) && isset($course['id'])) {
			return $course['id'] == '0004311' // 思修
				|| $course['id'] == '0004312' // 近代史
				|| $course['id'] == '0004361' // 马克思
				|| $course['id'] == '0006457' // 毛概
				|| $course['id'] == '0007069' // 毛概实践
				|| $course['id'] == '0007929' // 大英 1
				|| $course['id'] == '0007907' // 大英 2
				|| $course['id'] == '0001903' // 高数 1
				|| $course['id'] == '0001904' // 高数 2
				|| $course['id'] == '0001908' // 线代
				|| $course['id'] == '0000072' // 大物 1
				|| $course['id'] == '0004964' // 大物实验 1
				|| $course['id'] == '0000073' // 大物 2
				|| $course['id'] == '0004965' // 大物实验 2
				|| $course['id'] == '0003333' // 概率统计
				|| $course['id'] == '0003087' // 军事训练
				|| $course['id'] == '0002784' // 军事理论
				|| $course['id'] == '0004746' // 金工课
				|| $course['id'] == '0003790' // 管理学原理
				|| $course['id'] == '0009131' // 会计学原理
				|| $course['id'] == '0003680' // 经济学原理
				|| $course['id'] == '0008724' // 学术英语写作（慕课）
				|| $course['id'] == '0007975' // 学术英语写作
				|| substr($course['id'], 0, 2) === 'ty'
				|| substr($course['id'], 0, 3) === '0ty';
		}
		return false;
	}

	public static function calcGpa( $data, $attr, $fix = true ) {
		$total_score = 0;
		$total_value = 0;
		$total_GPA = 0;
		$number_of_lesson = 0;        //主修总课程数
		//二专业和辅修，$course->minor_maker == 2
		$total_score_minor = 0;
		$total_value_minor = 0;
		$total_GPA_minor = 0;
		$number_of_lesson_minor = 0;  //二专业/辅修课程数
		if ($fix) {
			$existingCourses = [];
			$first = '9999-9999-9';
			foreach ($data->$attr as $course) {
				$course->credit = number_format($course->credit, 1);
				if ($course->year.'-'.$course->term < $first) {
					$first = $course->year.'-'.$course->term;
				}
				if (is_numeric($course->makeup_score)) {
					$course->comment = '补考 | 原' . $course->score . '分';
					$course->score = $course->makeup_score;
				} else if (is_numeric($course->retake_score)) {
					$course->comment = '重修 | 原' . $course->score . '分';
					$course->score = $course->retake_score;
				} else if ($course->retake_maker == '1') {
					$course->comment = '重修';
				} else if ($course->comment == ' ') {
					$course->comment = '';
				}
				if ($course->id == '0009287' && (trim($course->belong) == '' || $course->belong == ' ')) {
					$course->belong = '第二课堂';
				}
				if ($course->name == '新生研讨课') {
					$course->belong = '第二课堂';
				}
				if (!is_numeric($course->minor_maker)) {
					$course->minor_maker = '0';
				}
				if (
					isset($existingCourses[(string)$course->id]) &&
					($existingCourses[(string)$course->id]->minor_maker > '0') == ($course->minor_maker > '0')
				) {
					if ($course->id != '0009287') {
						$existingCourses[(string)$course->id]->credit = '0.0';
						if ($existingCourses[(string)$course->id]->comment) {
							$existingCourses[(string)$course->id]->comment .= ' | 已重修';
						} else if ($course->retake_maker == '1') {
							$existingCourses[(string)$course->id]->comment = '已重修';
						} else {
							$existingCourses[(string)$course->id]->comment = '已重复';
						}
						if ($course->retake_maker == '1') {
							$course->comment = '重修 | 原' . $existingCourses[(string)$course->id]->score . '分';
						} else {
							$course->comment = '重复 | 原' . $existingCourses[(string)$course->id]->score . '分';
						}
					} else {
						$existingCourses[(string)$course->id]->credit = '0.25';
						$course->credit = '0.25';
					}
				} else if ($course->id == '0009287' && $course->year.'-'.$course->term === $first && $first >= '2018-2019-1') {
					$course->credit = '0.25';
				}
				$existingCourses[(string)$course->id] = $course;
			}
			if ($first !== '9999-9999-9') {
				$data->first_semester = $first;
			}
		}

		foreach ($data->$attr as $course) {
			if (!(static::skippedCourse($course) || $course->score < 60)){
				switch ((string)$course->minor_maker) {
					case '1':
					case '2':
						$total_score_minor += ($course->score * $course->credit);  //  累加总分
						$total_value_minor += $course->credit;    //  累加学分(权值)
						$total_GPA_minor += ($course->gpa * $course->credit); //加权总绩点
						$number_of_lesson_minor++;
						break;
					default:
						if ($course->minor_maker !== '0') {
							$course->minor_maker = '0';
						}
						$total_score += ($course->score * $course->credit);  //  累加总分
						$total_value += $course->credit;    //  累加学分(权值)
						$total_GPA += ($course->gpa * $course->credit); //加权总绩点
						$number_of_lesson++;
						break;
				}
			}
		}
		$data->term_lesson_count = count($data->$attr);
		$data->term_lesson_minor = $number_of_lesson_minor;
		$data->average_score_term = $total_value != 0 ? $total_score / $total_value : 0;
		$data->average_score_minor = $total_value_minor != 0 ? $total_score_minor / $total_value_minor : 0;
		$data->average_GPA_term = $total_value != 0 ? $total_GPA / $total_value : 0;
		$data->average_GPA_minor = $total_value_minor != 0 ? $total_GPA_minor / $total_value_minor : 0;
	}
}
