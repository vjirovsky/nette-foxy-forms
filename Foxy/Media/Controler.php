<?php

/**
 * @package nette-foxy-forms
 *
 * Generate nette form components using Doctrine entity annotations
 *
 * @author Jiri Dubansky <jiri@dubansky.cz>
 */


namespace Foxy\Media;


class Controler {

	/**
	 * @var string
	 */
	protected $mediaUrl;

	/**
	 * @var IStorage
	 */
	protected $storage;

	/**
	 * @var array
	 */
	protected $imageSizes;


	protected $flagMapping = array(
		\Nette\Image::FIT 			=> 'a',
		\Nette\Image::SHRINK_ONLY 	=> 'b',
		\Nette\Image::STRETCH 		=> 'c',
		\Nette\Image::FILL 			=> 'd',
		\Nette\Image::EXACT 		=> 'e'
	);

	/**
	 * Construct Controler
	 *
	 * @param string $mediaUrl
	 * @param IStorage $storage
	 */
	public function __construct($mediaUrl, IStorage $storage)
	{
		$this->mediaUrl = $mediaUrl;
		$this->storage = $storage;
	}


	/**
	 * Get valid params for resizer
	 *
	 * @param array
	 * @return array
	 */
	protected function getValidParams($params)
	{
		$data = array(
			'width' => NULL,
			'height' => NULL,
			'crop' => \Nette\Image::FIT
		);

		# assoc array
		if (array_keys($params) !== range(0, count($params) - 1)) {
			if (isset($params['height'])) {
				$data['height'] = $params['height'];
			}
			if (isset($params['width'])) {
				$data['width'] = $params['width'];
			}
			if (isset($params['crop'])) {
				$data['crop'] = $params['crop'];
			}
		# non assoc array
		} else {
			foreach(array_keys($data) as $i => $key) {
				$data[$key] = isset($params[$i]) ? $params[$i] : NULL;
			}
		}

		if (is_null($data['crop'])) {
			$data['crop'] = (bool) $data['crop'];
		}
		if (is_bool($data['crop'])) {
			$data['crop'] = ($data['crop']) ? \Nette\Image::EXACT : \Nette\Image::FIT;
		}

		return $data;
	}


	/**
	 * Saves image sizes
	 * @var string $imagepath
	 */
	protected function saveImageSizes($imagepath)
	{
		list($width, $height) = getimagesize($imagepath);

		$this->imageSizes[$imagepath] = array(
			'width' => $width,
			'height' => $height,
		);
	}


	/**
	 * Get image height
	 * @var string $imagepath
	 */
	protected function getImageHeight($imagepath)
	{
		if (array_key_exists($imagepath, $this->imageSizes)) {
			return $this->imageSizes[$imagepath]['height'];
		}
		$this->saveImageSizes($imagepath);
		return $this->getImageHeight($imagepath);
	}


	/**
	 * Get image width
	 * @var string $imagepath
	 */
	protected function getImageWidth($imagepath)
	{
		if (array_key_exists($imagepath, $this->imageSizes)) {
			return $this->imageSizes[$imagepath]['width'];
		}
		$this->saveImageSizes($imagepath);
		return $this->getImageWidth($imagepath);
	}


	/**
	 * Returns completed url
	 *
	 * @param string $filepath
	 * @return string
	 */
	public function getUrl($filepath, $params = NULL)
	{
		if ($params) {
			$data = $this->getValidParams($params);

			# Return if width and height are not specified
			if (is_null($data['width']) && is_null($data['height'])) {
				return $this->mediaUrl . '/' . $filepath;
			}

			$imagepath = $this->storage->getMediaDir() . $filepath;

			# calculate target height
			if (! is_null($data['width']) && is_null($data['height'])) {
				$height = $this->getImageHeight($imagepath);
				$width = $this->getImageWidth($imagepath);
				$data['height'] = $height * ($data['width'] / $width);
				$data['height'] = (int) $data['height'];
			}

			# calculate target width
			if (is_null($data['width']) && ! is_null($data['height'])) {
				$height = $this->getImageHeight($imagepath);
				$width = $this->getImageWidth($imagepath);
				$data['width'] = $width * ($data['height'] / $height);
				$data['width'] = (int) $data['width'];
			}

			$data['resizeType'] = $this->flagMapping[$data['crop']];

			$newFilepath = preg_replace_callback(
				'|(.+)\/(.+)\.(.+)$|',
				function($matches) use($data) {
					$dir = $matches[1] . '/';
					$name = $matches[2] . '_'
							. $data['height'] . 'x'
							. $data['width']
							. $data['resizeType'] . '.';

					return $dir . $name . $matches[3];
				},
				$filepath
			);

			if (! $this->fileExists($newFilepath)) {
				try {
					$image = \Nette\Image::fromFile(
						$this->storage->getMediaDir() . $filepath
					);
					$image->resize($data['width'], $data['height'], $data['crop']);
					$image->save($this->storage->getMediaDir() . $newFilepath);
				} catch(\Nette\UnknownImageFileException $e) {
					return $this->mediaUrl  . '/' . $filepath;
				}
			}

			return $this->mediaUrl . '/' . $newFilepath;
		}

		return $this->mediaUrl . '/' . $filepath;
	}


	/**
	 * Saves file to media directory
	 *
	 * @param \Nette\Http\FileUpload $file
	 * @param string $dest
	 * @return string
	 */
	public function saveFile(\Nette\Http\FileUpload $file, $dest)
	{
		return $this->storage->saveFile(
			$file,
			strftime($dest)
		);
	}


	/**
	 * Checks if file exists
	 *
	 * @param string $dest
	 * @return bool
	 */
	public function fileExists($dest)
	{
		return $this->storage->fileExists($dest);
	}
}
