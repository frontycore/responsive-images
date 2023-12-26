<?php

namespace Fronty\ResponsiveImages\Sizes;

use Iterator;
use ReflectionClass;
use RuntimeException;

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
	 */
	public function __construct(bool $mobileFirst = true, ?int $maxWidth = null, ?int $maxHeight = null)
	{
		$this->mobileFirst = $mobileFirst;
		$this->maxWidth = $maxWidth;
		$this->maxHeight = $maxHeight;
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
		assert(!$this->isEmpty(), new RuntimeException("List of ImageSizes is empty."));

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