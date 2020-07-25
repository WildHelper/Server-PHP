<?php


namespace WildHelper;


class Settings
{
	/**
	 * 以下均为随机生成字符
	 * ENCRYPTION          加密密钥，随机生成即可
	 * APP_ID              小程序 App ID
	 * APP_SECRET          小程序 App Secret
	 * DEMO                Demo 验证 ID
	 * SUBSCRIBE_IDS       订阅 ID
	 * AD_ID               视频激励广告 ID
	 * AD_ID_BANNER        Banner 广告 ID
	 * CLOUD_FUNCTION_AUTH 云函数验证密钥，需保持和云函数中的一致
	 */
	public const ENCRYPTION = 'hgRtLzDgGGdheCn_Qf7t!nxpqBxL3AvP9esFnzn2E9DX9fVRejtWxro7oabsadeC';
	public const APP_ID = 'wxd4fa4a03f60bbbf5';
	public const APP_SECRET = 'd4fa4a03f60bbbf53eec510b06228d66';
	public const DEMO_UID = 'demo';
	public const SUBSCRIBE_IDS = [
		'bQU97AARGuGDXb8v8W83PEgZigJGxXy9fUVhDqYcsB8'
	];
	public const AD_ID = 'adunit-b6d667b22c8f0e94';
	public const AD_ID_BANNER = 'adunit-b6d667b22c8f0e94';
	public const CLOUD_FUNCTION_AUTH = '8UZRhU9xsRfawoKMK3NjC3Rp3kKXVNZHAJ8uFsJ4gqgywJ2dtThkEbDfaiPahcwM';

	/**
	 * API 地址配置
	 */
	public const API_ENDPOINT = 'https://wild.applinzi.com/v2/';
	public const API_MESSAGE = '服务器永不存储你的密码';
	public const API_LIST = [
		'default' => [
			'name' => '默认',
			'url' => Settings::API_ENDPOINT,
			'message' => Settings::API_MESSAGE,
			'trusted' => true,
		],
	];

	/**
	 * 维护暂停服务
	 */
	public const MAINTENANCE_MESSAGE = '';
	public const MAINTENANCE_CODE = 0;
//	public const MAINTENANCE_MESSAGE = '服务器正在维护，暂不可用';
//	public const MAINTENANCE_CODE = 503;

	/**
	 * 教务地址配置
	 */
	public const VPN_URL = 'https://example.com/prx/000/http/localhost/';
	public const BASE_URL = 'https://example.com/prx/000/http/gdjwgl.example.com/';

	/**
	 * VPN 账户设置
	 */
	public const VPN_USERNAME = 'demo';
	public const VPN_PASSWORD = 'password';

	/**
	 * 分享标题和图片
	 * https://developers.weixin.qq.com/minigame/dev/api/share/wx.onShareAppMessage.html
	 */
	public const SHARE_MESSAGE = [
		'title' => '野生工大助手',
		'imageUrl' => '',
		'imageUrlId' => ''
	];

	/**
	 * @var mixed 小程序前端版本号
	 */
	public const VERSION_NUMBER = [
		'0' => '开发版/体验版/审核版本',
		'devtools' => '开发者工具',
		'1' => '1.0.0'
	];

	/**
	 * @var mixed 首页通知
	 */
	public const MAIN_PAGE_MESSAGES = [];

	/**
	 * 统计数据页通知
	 */
	public const WARNING_MESSAGE = [];

	/**
	 * 课程备注
	 */
	public const COURSE_MESSAGE = [];

	/**
	 * @var mixed 更多页通知
	 */
	public const MORE_PAGE_MESSAGES = [];

	/**
	 * 观看完广告后可订阅的次数
	 */
	public const AD_TIMES = 6;

	/**
	 * 广告观看提示
	 */
	public const AD_MESSAGES = [
		'广告观看提示',
		'需要观看广告以获得 '.self::AD_TIMES.' 次订阅',
		'若点击“拒绝”，您将会返回上一步',
	];
}
