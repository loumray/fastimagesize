<?php

/**
 * fast-image-size image type jpeg
 * @package fast-image-size
 * @copyright (c) Marc Alexander <admin@m-a-styles.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FastImageSize\Image;

class TypeJpeg extends TypeBase
{
	/** @var int JPEG max header size. Headers can be bigger, but we'll abort
	 *			going through the header after this */
	const JPEG_MAX_HEADER_SIZE = 124576;

	/** @var string JPEG header */
	const JPEG_HEADER = "\xFF\xD8";

	/** @var string Start of frame marker */
	const SOF_START_MARKER = "\xFF";

	/** @var array JPEG SOF markers */
	protected $sofMarkers = array(
		"\xC0",
		"\xC1",
		"\xC2",
		"\xC3",
		"\xC5",
		"\xC6",
		"\xC7",
		"\xC8",
		"\xC9",
		"\xCA",
		"\xCB",
		"\xCD",
		"\xCE",
		"\xCF"
	);

	/** @var array JPEG APP markers */
	protected $appMarkers = array(
		"\xE0",
		"\xE1",
		"\xE2",
		"\xE3",
		"\xEC",
		"\xED",
		"\xEE",
	);

	/** @var string|bool $data JPEG data stream */
	protected $data = '';

	/** @var bool Flag whether exif was found */
	protected $foundExif = false;

	/** @var bool Flag whether xmp was found */
	protected $foundXmp = false;

	protected $type = IMAGETYPE_JPEG;
	
	protected $headerlength = self::JPEG_MAX_HEADER_SIZE;

	public function extractSize()
	{
		$data = $this->getHeaderPart();

		// since we check $i + 1 we need to stop one step earlier
		$dataLength = strlen($data) - 1;

		$this->foundExif = $this->foundXmp = false;
		
		// Look through file for SOF marker
		for ($i = 0; $i < $dataLength; $i++) {
			// Only look for EXIF and XMP app marker once, other types more often
			if (!$this->checkForAppMarker($dataLength, $i)) {
				return array();
			}

			if ($this->isSofMarker($data[$i], $data[$i + 1])) {
				// Extract size info from SOF marker
				list(, $unpacked) = unpack("H*", $this->getHeaderPart($i + self::LONG_SIZE + 1, self::LONG_SIZE));

				// Get width and height from unpacked size info
				return array(
					'width'		=> hexdec(substr($unpacked, 4, 4)),
					'height'	=> hexdec(substr($unpacked, 0, 4)),
				);
			}
		}

		return array();
	}


	public function getSignature()
	{
		return $this->getHeaderPart(0, self::SHORT_SIZE);
	}

	public function isSignatureMatch()
	{
		return $this->getSignature() === self::JPEG_HEADER;
	}


	/**
	 * Return if current data point is an SOF marker
	 *
	 * @param string $firstByte First byte to check
	 * @param string $secondByte Second byte to check
	 *
	 * @return bool True if current data point is SOF marker, false if not
	 */
	protected function isSofMarker($firstByte, $secondByte)
	{
		return $firstByte === self::SOF_START_MARKER && in_array($secondByte, $this->sofMarkers);
	}

	/**
	 * Return if current data point is an APP marker
	 *
	 * @param string $firstByte First byte to check
	 * @param string $secondByte Second byte to check
	 *
	 * @return bool True if current data point is APP marker, false if not
	 */
	protected function isAppMarker($firstByte, $secondByte)
	{
		return $firstByte === self::SOF_START_MARKER && in_array($secondByte, $this->appMarkers);
	}

	/**
	 * Return if current data point is a valid APP1 marker (EXIF or XMP)
	 *
	 * @param string $firstByte First byte to check
	 * @param string $secondByte Second byte to check
	 *
	 * @return bool True if current data point is valid APP1 marker, false if not
	 */
	protected function isApp1Marker($firstByte, $secondByte)
	{
		return (!$this->foundExif || !$this->foundXmp) && $firstByte === self::SOF_START_MARKER && $secondByte === $this->appMarkers[1];
	}

	/**
	 * Check for APP marker in data
	 *
	 * @param int $dataLength Length of input data
	 * @param int $index Current data index
	 *
	 * @return bool True if searching through data should be continued, false if not
	 */
	protected function checkForAppMarker($dataLength, &$index)
	{
		if (!$this->isApp1Marker($this->getHeaderPart($index, 1), $this->getHeaderPart($index+1, 1)) &&
			!$this->isAppMarker($this->getHeaderPart($index, 1), $this->getHeaderPart($index+1, 1))
		) {
			return true;
		}

		// Extract length from APP marker
		list(, $unpacked) = unpack("H*", $this->getHeaderPart($index + self::SHORT_SIZE, 2));
		
		$this->setApp1Flags($this->getHeaderPart($index, 1));

		// Skip over length of APP header
		$index += (int) hexdec(substr($unpacked, 0, 4));

		// Make sure we don't exceed the data length
		if ($index >= $dataLength) {
			return false;
		}

		return true;
	}

	/**
	 * Set APP1 flags for specified data point
	 *
	 * @param string $data Data point
	 */
	protected function setApp1Flags($data)
	{
		if (!$this->foundExif) {
			$this->foundExif = $data === $this->appMarkers[1];
		} elseif (!$this->foundXmp) {
			$this->foundXmp = $data === $this->appMarkers[1];
		}
	}
}
