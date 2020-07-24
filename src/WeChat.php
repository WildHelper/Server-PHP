<?php


namespace WildHelper;


class WeChat
{
	private const APP_ID = Settings::APP_ID;
	private const APP_SECRET = Settings::APP_SECRET;
	public static function getAccessToken()
	{
		$cache = new Cache();
		$r = $cache->get('Wechat::ACCESS_TOKEN');
		if (!$r) {
			$r = json_decode(file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.self::APP_ID.'&secret='.self::APP_SECRET));
			if(property_exists($r, 'access_token')) {
				$cache->set('Wechat::ACCESS_TOKEN', $r->access_token, $r->expires_in);
				return $r->access_token;
			}
		}
		return $r;
	}

	public static function sendScore(string $open, $id, $name, $year, $term, $comment='点击进入立马查分')
	{
		$token = self::getAccessToken();
		$post = [];
		$post['touser'] = $open;
		$post['page'] = '/pages/login/login?year='.$year.'&term='.$term.'&courseId='.$id;

		if ( mb_strlen($name) > 10 ) {
			$name = mb_substr($name, 0, 9).'…';
		}

		$post['template_id'] = Settings::SUBSCRIBE_IDS[0];
		$post['data'] = [
			'number3'=>['value'=>preg_replace('/[^0-9.]+/', '', $id)],
			'thing4'=>['value'=>$name],
			'thing6'=>['value'=>$comment]
		];

		$url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$token;

		return json_decode(self::curl_url($url, $post));
	}

	private static function curl_url($url, $json)
	{
		$body = json_encode($json);
		$headers = array("Content-type: application/json;charset=UTF-8", "Accept: application/json", "Cache-Control: no-cache", "Pragma: no-cache");

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}
}
