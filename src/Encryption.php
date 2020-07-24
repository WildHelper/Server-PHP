<?php
// https://stackoverflow.com/a/30189841


namespace WildHelper;

use Exception;

class Encryption
{
	const METHOD = 'aes-256-gcm';
	const IV_LENGTH = 12;
	const TAG_LENGTH = 16;

	/**
	 * Encrypts a message
	 *
	 * @param string $message - plaintext message
	 * @param string $key - encryption key (raw binary expected)
	 * @param boolean $encode - set to TRUE to return a base64-encoded
	 * @param string $method
	 * @param int $iv_length
	 * @param int $tag_length
	 * @return string (raw binary)
	 */
	public static function encrypt(
		string $message, string $key, bool $encode = false, string $method = self::METHOD,
		int $iv_length = self::IV_LENGTH, int $tag_length = self::TAG_LENGTH
	) {
		$nonce = openssl_random_pseudo_bytes($iv_length);

		if ($method === 'aes-256-gcm') {
			$ciphertext = openssl_encrypt(
				$message,
				$method,
				$key,
				OPENSSL_RAW_DATA,
				$nonce,
				$tag,
				'',
				$tag_length
			);
		} else {
			$tag = '';
			$ciphertext = openssl_encrypt(
				$message,
				$method,
				$key,
				OPENSSL_RAW_DATA,
				$nonce
			);
		}


		// Now let's pack the IV and the ciphertext together
		// Naively, we can just concatenate
		if ($encode) {
			return base64_encode($nonce.$ciphertext.$tag);
		}
		return $nonce.$ciphertext.$tag;
	}

	/**
	 * Decrypts a message
	 *
	 * @param string $message - ciphertext message
	 * @param string $key - encryption key (raw binary expected)
	 * @param boolean $encoded - are we expecting an encoded string?
	 * @param string $method
	 * @param int $iv_length
	 * @param int $tag_length
	 * @return string
	 * @throws Exception
	 */
	public static function decrypt(
		string $message, string $key, bool $encoded = false, string $method = self::METHOD,
		int $iv_length = self::IV_LENGTH, int $tag_length = self::TAG_LENGTH
	) {
		if ($encoded) {
			$message = base64_decode($message, true);
			if ($message === false) {
				throw new Exception('加密解码错误', 1100);
			}
		}

		$nonce = mb_substr($message, 0, $iv_length, '8bit');

		if ($method === 'aes-256-gcm') {
			$ciphertext = mb_substr($message, $iv_length, -$tag_length, '8bit');
			$tag = mb_substr($message, -$tag_length, null, '8bit');
			$ret = openssl_decrypt(
				$ciphertext,
				$method,
				$key,
				OPENSSL_RAW_DATA,
				$nonce,
				$tag,
				''
			);
		} else {
			$ciphertext = mb_substr($message, $iv_length, null, '8bit');
			$ret = openssl_decrypt(
				$ciphertext,
				$method,
				$key,
				OPENSSL_RAW_DATA,
				$nonce
			);
		}

		if ($ret === false) {
			throw new Exception('加密验证错误', 1100);
		}
		return $ret;
	}
}

// TODO: This is only for the compatibility. Right now the Crypto JS does not support GCM
class EncryptionOld extends Encryption
{
	public static function encrypt(string $message, string $key, bool $encode = false, string $method = 'aes-256-cbc', int $iv_length = 16, int $tag_length = 0)
	{
		return parent::encrypt($message, $key, $encode, $method, $iv_length, $tag_length);
	}
	public static function resp(string $message, string $key) {
		$ret = EncryptionOld::encrypt($message, $key);
		return (object)[
			'iv' => bin2hex(mb_substr($ret, 0, 16, '8bit')),
			'data' => base64_encode(mb_substr($ret, 16, null, '8bit'))
		];
	}
	public static function decrypt(string $message, string $key, bool $encoded = false, string $method = self::METHOD, int $iv_length = self::IV_LENGTH, int $tag_length = self::TAG_LENGTH)
	{
		throw new Exception('不支持此方法', 1100);
	}
}
