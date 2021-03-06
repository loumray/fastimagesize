<?php

/**
 * fast-image-size image type base
 * @package fast-image-size
 * @copyright (c) Marc Alexander <admin@m-a-styles.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FastImageSize\Image;

class Factory 
{
	//In order of which they will be tried if type is unknown
	//Changing order might have performance impact and/or type detection mismatch
	private static $supportedTypes = array(
		'jpeg'	=> array(
			'jpeg',
			'jpg',
			'jpe',
			'jif',
			'jfif',
			'jfi',
		),
		'png'	=> array('png'),
		'gif'	=> array('gif'),
		'jp2'	=> array(
			'jp2',
			'j2k',
			'jpf',
			'jpg2',
			'jpx',
			'jpm',
		),
		'psd'	=> array(
			'psd',
			'photoshop',
		),
		'bmp'	=> array('bmp'),
		'tif'	=> array(
			'tif',
			'tiff',
		),
		'iff'	=> array(
			'iff',
			'x-iff',
		),
		'ico'	=> array(
			'ico',
			'vnd.microsoft.icon',
			'x-icon',
			'icon',
		),
		'wbmp'	=> array(
			'wbm',
			'wbmp',
			'vnd.wap.wbmp',
		)
	);

	/**
	 * Create image object from filepath
	 *
	 * @param string $imageType
	 *
	 * @return string classname
	 */
	public static function getClassname($imageType)
	{
		return __NAMESPACE__.'\\Type'.ucfirst($imageType);
	}

	/**
	 * Get type from filepath extension
	 *
	 * @param string $filepath
	 *
	 * @return string|false imagetype or false if not found
	 */
	public static function getExtensionType($filepath)
	{
		$extension = strtolower(pathinfo(parse_url($filepath, PHP_URL_PATH), PATHINFO_EXTENSION));

		if (empty($extension)) {
			return false;
		}

		//Find approriate type of extension and instantiate Type object
		foreach (self::$supportedTypes as $imageType => $extensions) {
			if (in_array($extension, $extensions)) {
				return $imageType;
			}
		}

		return false;
	}

	/**
	 * Create image object from filepath
	 *
	 * @param string $filepath
	 *
	 * @return TypeInterface|null
	 */
	public static function create($filepath)
	{
		$imageType = self::getExtensionType($filepath);

		if ($imageType !== false) {
			$className = self::getClassname($imageType);
			return new $className($filepath);
		}
		
		//Could not find type, try matching type
		return self::detectAndCreate($filepath);
	}

	/**
	 * Detect image type by trials and create appropriate TypeInterface object
	 *
	 * @param string $filepath
	 *
	 * @return TypeInterface|null
	 */
	public static function detectAndCreate($filepath)
	{
		// Jpeg type uses the most bytes, so grab the maximum image bytes we could need
		$image = new TypeJpeg($filepath);
		$maxedSizedHeader = $image->getHeaderPart();

		foreach (self::$supportedTypes as $imageType => $extensions) {

			$className = self::getClassname($imageType);
			$image = new $className($filepath);

			//Set header from the maxed header to prevent multiple byte fetching of same file
			$image->setHeader($maxedSizedHeader);
			
			if ($image->isTypeMatch()) {
				return $image;
			}
		}
		//Unsupported extension and/or could not match the type signature
		return null;
	}
}
