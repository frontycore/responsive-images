<?php

namespace Fronty\ResponsiveImages\Sizes;

use Iterator;
use ReflectionClass;
use RuntimeException;
use InvalidArgumentException;

/**
 * List of image sizes appendaple in fluent interface.
 */
class ImageSizeList implements Iterator
{

	/** @var bool */
	private $mobileFirst = true;

	/** @var int|null */
	private $maxWidth;

	/** @var int|null */
	private $maxHeight;

	/** @var array|float[] Pixel densities (DPR), in which to offer every image width in srcset. */
	private $densities = [1.0, 2.0];

	/** @var array|string[]|null Image formats (MIME types) to offer as <picture> sources, in order
	 * 	of preference. Null means nothing is set here, so the `fri_upload_formats` filter decides. */
	private $formats = null;

	/** @var array|ImageSize[] */
	private $sizes = [];

	/** @var array */
	private $breakpoints;

	/** @var int */
	private $counter;

	/**
	 * @param bool $mobileFirst Whether given breakpoints shoud be considered as minimal (true) or maximal (false) viewport widths.
	 * @param int|null $maxWidth Maximal image with, which should not be exceeded in any breakpoint.
	 * @param int|null $maxHeight Maximal image height, which should not be exceeded in any breakpoint.
	 * @param array $densities Pixel densities (DPR), in which to offer every image width in srcset.
	 */
	public function __construct(bool $mobileFirst = true, ?int $maxWidth = null, ?int $maxHeight = null, array $densities = [1, 2])
	{
		$this->mobileFirst = $mobileFirst;
		$this->maxWidth = $maxWidth;
		$this->maxHeight = $maxHeight;
		$this->setDensities($densities);
	}

	/**
	 * Instantiate from array of arguments for ImageSize.
	 * @param array $imageSizes Breakpoints as keys, array of arguments for ImageSize as values.
	 * 	Example:
	 * 	[
	 * 		375 => [300, 250, 'fill', ['quality' => '80', 'gravity' => 'face']], // Full ImageSize arguments list
	 * 		576 => 500, // Only first ImageSize argument $width (others will have default values)
	 * 		...
	 * 	]
	 * @param bool $mobileFirst
	 * @return static
	 */
	public static function fromArray(array $imageSizes, bool $mobileFirst = true): static
	{
		$self = new static($mobileFirst);
		foreach ($imageSizes as $breakpoint => $imageSize) {
			if (is_int($imageSize)) $imageSize = [$imageSize];
			$reflector = new ReflectionClass(ImageSize::class);
			$self->append($breakpoint, $reflector->newInstanceArgs($imageSize));
		}
		return $self;
	}

	/**
	 * Append ImageSize to given breakpoint.
	 * @param int $breakpoint
	 * @param ImageSize $imageSize
	 * @return static
	 */
	public function append(int $breakpoint, ImageSize $imageSize): static
	{
		$imageSize->setMaxWidth($this->maxWidth)->setMaxHeight($this->maxHeight);
		$this->sizes[$breakpoint] = $imageSize;
		return $this;
	}

	/**
	 * Check if sizes are set as mobile-first.
	 * @return bool
	 */
	public function isMobileFirst(): bool
	{
		return $this->mobileFirst;
	}

	/**
	 * Set pixel densities (DPR), in which to offer every image width in the srcset attribute.
	 * 	Eg. [1, 2] offers each declared width both as declared and in it's double for retina
	 * 	displays. Candidates are limited by the original image size, so nothing is upscaled.
	 * @param array $densities Positive multipliers of declared widths, eg. [1, 2] or [1, 1.5, 2].
	 * @return static
	 *
	 * @throws InvalidArgumentException When no positive density is given.
	 */
	public function setDensities(array $densities): static
	{
		$densities = array_filter(array_map('floatval', $densities), fn(float $density): bool => $density > 0);
		$densities = array_values(array_unique($densities));
		if (count($densities) <= 0) {
			throw new InvalidArgumentException("At least one positive pixel density has to be given.");
		}
		sort($densities);
		$this->densities = $densities;
		return $this;
	}

	/**
	 * Get pixel densities (DPR), in which to offer every image width in the srcset attribute.
	 * @return array|float[] Positive multipliers sorted from the smallest one.
	 */
	public function getDensities(): array
	{
		return $this->densities;
	}

	/**
	 * Set image formats, in which to offer the image as <picture> sources.
	 * 	Formats are used by the picture methods only, eg. UploadImage::getResponsivePictureTag(),
	 * 	so setting them never changes the output of the <img> methods.
	 * 	This library generates no derivative: a source is rendered only for a format whose
	 * 	files are confirmed by the `fri_upload_format_src` filter.
	 * @param array $formats Image MIME types in order of preference, eg. ['image/avif', 'image/webp'].
	 * 	An empty array switches the formats off for this list, including the project default
	 * 	of the `fri_upload_formats` filter, which is asked only when nothing is set here.
	 * @return static
	 *
	 * @throws InvalidArgumentException When a value is not an image MIME type.
	 */
	public function setFormats(array $formats): static
	{
		$formats = array_map(fn($format): string => strtolower(trim(strval($format))), $formats);
		foreach ($formats as $format) {
			if (!preg_match('#^image/[a-z0-9][a-z0-9.+-]*$#', $format)) {
				throw new InvalidArgumentException("'$format' is not an image MIME type, eg. 'image/avif'.");
			}
		}
		$this->formats = array_values(array_unique($formats));
		return $this;
	}

	/**
	 * Get image formats, in which to offer the image as <picture> sources.
	 * @return array|string[] Image MIME types in order of preference, empty array when none is set.
	 *
	 * @see static::hasFormats() To tell an empty list from a list which was never set.
	 */
	public function getFormats(): array
	{
		return $this->formats ?? [];
	}

	/**
	 * Check whether image formats were set on this list at all.
	 * 	Tells an explicit `setFormats([])`, ie. no formats wanted here, from a list which was
	 * 	never asked about formats and leaves the decision to the `fri_upload_formats` filter.
	 * @return bool
	 *
	 * @see static::setFormats()
	 */
	public function hasFormats(): bool
	{
		return $this->formats !== null;
	}

	/**
	 * Check if image list is empty.
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return (count($this->sizes) <= 0);
	}

	/**
	 * Get ImageSize with the largest width.
	 * @return ImageSize
	 *
	 * @throws RuntimeException When list is empty.
	 */
	public function getWidest(): ImageSize
	{
		if ($this->isEmpty()) {
			throw new RuntimeException("List of ImageSizes is empty.");
		}

		$sizes = $this->sizes;
		usort($sizes, function(ImageSize $a, ImageSize $b) {
			return $b->getWidth() <=> $a->getWidth();
		});
		return array_values($sizes)[0];
	}


	/** Iterator interface */

	/**
	 * Get current ImageSize
	 * @return ImageSize
	 */
	public function current(): ImageSize
	{
		return $this->sizes[$this->key()];
	}

	/**
	 * Get current breakpoint.
	 * @return int
	 */
	public function key(): int
	{
		return $this->breakpoints[$this->counter];
	}

	/**
	 * Move to next breakpoint.
	 */
	public function next(): void
	{
		$this->counter ++;
	}

	/**
	 * Order breakpoints from largest if $mobileFirst or smallest if not $mobileFirst, reset iterator.
	 */
	public function rewind(): void
	{
		$this->breakpoints = array_keys($this->sizes);
		usort($this->breakpoints, function(int $a, int $b) {
			if ($this->mobileFirst) return $b <=> $a;
			return $a <=> $b;
		});
		$this->counter = 0;
	}

	/**
	 * Check if current iteration is valid.
	 * @return bool
	 */
	public function valid(): bool
	{
		return isset($this->breakpoints[$this->counter]);
	}

	/**
	 * Check if current iteration is last.
	 * @return bool
	 */
	public function isLast(): bool
	{
		return !isset($this->breakpoints[$this->counter + 1]);
	}
}