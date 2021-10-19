<?php

namespace Fronty\ResponsiveImages\Sizes;

/**
 * Representation of image size and transformation for one breakpoint.
 */
class ImageSize
{
	/** @var int */
	private $width;

	/** @var int|null */
	private $height;

	/**
	 * @var string|bool
	 */
	private $crop;

	/** @var array  */
	private $cloudinaryTransform;

	/**
	 * @var array Default Cloudinary image transform, used in only with Auto Cloudinary.
	 * @see https://wordpress.org/plugins/auto-cloudinary/
	 * @see https://cloudinary.com/documentation/image_transformations
	 */
	public static $defaultCloudinaryTransform = [
		'quality' => 'auto:eco',
		'fetch_format' => 'auto'
	];


	/**
	 * Instantiate with configuration.
	 * @param int $width Image width
	 * @param int $height Image height, null - proportional height according to width.
	 * @param string|bool|array|null $crop How to crop image.
	 * 	Possible options:
	 * 	- 'fill'|true - crop image
	 * 	- 'fit'|false - do not crop image
	 * 	- other possible crop values for Cloudinary as described on https://cloudinary.com/documentation/resizing_and_cropping#resize_and_crop_modes
	 * 	- array [x, y] - X and Y position of crop for Fly Dynamic Image Resizer plugin (considered as 'fill' and left on Cloudinary AI to crop properly)
	 * 	- null - if $height is null, use 'fit', otherwise use 'fill'
	 * @param array $cloudinaryTransform Cloudinary image transform, used in only with Auto Cloudinary. @see https://cloudinary.com/documentation/image_transformations
	 */
	public function __construct(int $width, int $height = null, $crop = null, array $cloudinaryTransform = [])
	{
		if ($crop === null) $crop = ($height === null) ? 'fit' : 'fill';
		$this->width = $width;
		$this->height = $height;
		$this->crop = $crop;
		$this->cloudinaryTransform = $cloudinaryTransform;
	}

	/**
	 * @return int
	 */
	public function getWidth(): int
	{
		return $this->width;
	}

	/**
	 * @return int|null
	 */
	public function getHeight(): ?int
	{
		return $this->height;
	}

	/**
	 * @return string
	 */
	public function getCropType(): string
	{
		if (is_bool($this->crop)) return ($this->crop) ? 'fill' : 'fit';
		if (is_array($this->crop)) return 'fill';
		return $this->crop;
	}

	/**
	 * @return bool
	 */
	public function hasCrop(): bool
	{
		return in_array($this->getCropType(), ['crop', 'fill', 'lfill', 'fill_pad', 'thumb']);
	}

	/**
	 * Return whether to crop or particular [x, y] crop position.
	 * 	Ideal as 3rd argument for fly_get_attachment_image_src().
	 * @return bool|array
	 */
	public function getCropOrPosition()
	{
		if (is_array($this->crop)) return $this->crop;
		return $this->hasCrop();
	}

	/**
	 * Get input for Auto Cloudinary function `cloudinary_url`.
	 * @return array
	 */
	public function getCloudinaryTransform(): array
	{
		return array_merge(
			self::$defaultCloudinaryTransform,
			$this->cloudinaryTransform,
			[
				'width' => $this->getWidth(),
				'height' => $this->getHeight(),
				'crop' => $this->getCropType()
			]
		);
	}
}