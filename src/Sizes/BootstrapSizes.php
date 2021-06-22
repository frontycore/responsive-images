<?php

namespace Fronty\ResponsiveImages\Sizes;

use Nette\Utils\ArrayHash;
use InvalidArgumentException;

/**
 * Generate ImageSizeList based on Bootstrap grid system.
 * 	May be used with different grid system than Bootstrap.
 * @see https://getbootstrap.com/docs/5.0/layout/grid/#variables
 */
class BootstrapSizes
{

	/**
	 * @var int Number of columns in grid system.
	 * Comply with Bootstrap v5 SCSS variable $grid-columns.
	 */
	private $columns = 12;

	/**
	 * @var int Gap between each column in px.
	 * Comply with Bootstrap v5 SCSS variable $grid-gutter-width.
	 */
	private $gutter = 24;

	/**
	 * @var array Viewport breakpoint widths in px.
	 * Comply with Bootstrap v5 SCSS variable $grid-breakpoints without the 0-width one.
	 */
	private $breakpoints = [
		'xs' => 0,
		'sm' => 576,
		'md' => 768,
		'lg' => 992,
		'xl' => 1200,
		'xxl' => 1400
	];

	/**
	 * @var array Container widths in px for each breakpoint.
	 * Comply with Bootstrap v5 SCSS variable $container-max-widths.
	 */
	private $containers = [
		'sm' => 540,
		'md' => 720,
		'lg' => 960,
		'xl' => 1140,
		'xxl' => 1320
	];

	/** @var array|int[] Number of cols taken for breakpoints, eg. ['sm' => 12, 'md' => 10]  */
	private $cols = [];

	/**
	 * Configure object. Use default Bootstrap configuration if null is passed.
	 * @param int $columns
	 * @param int $gutter
	 * @param array $breakpoints
	 * @param array $containers
	 */
	public function __construct(
		int $columns = null,
		int $gutter = null,
		array $breakpoints = null,
		array $containers = null
	) {
		if ($columns !== null) $this->columns = $columns;
		if ($gutter !== null) $this->gutter = $gutter;
		if ($breakpoints !== null) $this->breakpoints = $breakpoints;
		if ($containers !== null) $this->containers = $containers;
	}

	/**
	 * Set columns taken for given breakpoint and ImageSize::__construct() arguments.
	 * @param string $breakpoint Breakpoint name.
	 * @param int $cols Number of cols taken.
	 * @param int $height
	 * @param string|bool|null $crop
	 * @param array $cloudinaryTransform
	 * @return static
	 *
	 * @see ImageSize::__construct()
	 */
	public function col(string $breakpoint, int $cols, int $height = null, $crop = null, array $cloudinaryTransform = []): static
	{
		assert(isset($this->breakpoints[$breakpoint]), new InvalidArgumentException("Breakpoint '$breakpoint' doesn't exist."));
		assert($cols <= $this->columns, new InvalidArgumentException("Number of columns taken has to be lower thank or equal to total columns of {$this->columns}, {$cols} given."));
		$this->cols[$breakpoint] = $this->getColData($cols, $height, $crop, $cloudinaryTransform);
		return $this;
	}

	/**
	 * Set container width for given breakpoint.
	 *	Container width is calculated including gutter, but $with should be given without it (it is added automatically).
	 * 	If given width doesn't contain the gutter, set $givenWithGutter to false.
	 * @param string $breakpoint
	 * @param int|null $width Null for 100% wide container
	 * @param bool $givenWithGutter
	 * @return static
	 */
	public function container(string $breakpoint, ?int $width, bool $givenWithGutter = true): static
	{
		assert(isset($this->breakpoints[$breakpoint]), new InvalidArgumentException("Breakpoint '$breakpoint' doesn't exist."));
		if ($width !== null && !$givenWithGutter) $width += $this->gutter;
		$this->containers[$breakpoint] = $width;
		return $this;
	}

	/**
	 * Get ImageSizeList for previously configured columns and containers.
	 * @return ImageSizeList
	 */
	public function getSizes(): ImageSizeList
	{
		$sizes = new ImageSizeList(true);
		$last = $this->getColData($this->columns);
		foreach ($this->breakpoints as $breakpointName => $breakpointWidth) {
			$col = (isset($this->cols[$breakpointName])) ? $this->cols[$breakpointName] : $last;
			$last = $col;

			$imgWidth = $this->getImageWidth($breakpointName, $col->cols);
			if ($imgWidth !== null) {
				$sizes->append(
					$breakpointWidth,
					new ImageSize($imgWidth, $col->height, $col->crop, $col->cloudinaryTransform)
				);
			}
		}
		return $sizes;
	}

	/**
	 * Calculate image width for given breakpoint and number of columns taken.
	 * @param string $breakpoint
	 * @param int $cols
	 * @return int|null Null - no width for given breakpoint found.
	 */
	private function getImageWidth(string $breakpoint, int $cols): ?int
	{
		$containerWidth = $this->getContainerWidth($breakpoint);
		if ($containerWidth === null) return null;
		return round(($containerWidth / $this->columns) * $cols) - $this->gutter;
	}

	/**
	 * Get outer container width for given breakpoint (including side paddings).
	 * @param string $breakpoint
	 * @return int|null Null - no width for given breakpoint found.
	 */
	private function getContainerWidth(string $breakpoint): ?int
	{
		// Check if container is 100% wide
		$hasFullContainer = (!isset($this->containers[$breakpoint]) || $this->containers[$breakpoint] === null);

		// Return fixed-width container without side paddings
		if (!$hasFullContainer) return $this->containers[$breakpoint];

		// 100% wide containers - return next breakpoint viewport width without side paddings, without 1px for min-width
		$nextBreakpoint = $this->getNextBreakpoint($breakpoint);
		if (isset($this->breakpoints[$nextBreakpoint])) return $this->breakpoints[$nextBreakpoint] - 1;

		// No next breakpoint found
		return null;
	}

	/**
	 * Get name of next breakpoints from given one. Returns null if no next breakpoint.
	 * @param string $breakpoint
	 * @return string|null
	 */
	private function getNextBreakpoint(string $breakpoint): ?string
	{
		$breakpoints = array_keys($this->breakpoints);
		$nextIndex = array_search($breakpoint, $breakpoints) + 1;
		return (isset($breakpoints[$nextIndex])) ? $breakpoints[$nextIndex] : null;
	}

	/**
	 * Create column data structure - columns taken + arguments for ImageSize::__construct().
	 * @param int $cols Number of columns taken.
	 * @param int $height
	 * @param string|bool|null $crop
	 * @param array $cloudinaryTransform
	 * @return ArrayHash
	 *
	 * @see ImageSize::__construct()
	 */
	private function getColData(int $cols, int $height = null, $crop = null, array $cloudinaryTransform = []): ArrayHash
	{
		return ArrayHash::from([
			'cols' => $cols,
			'height' => $height,
			'crop' => $crop,
			'cloudinaryTransform' => $cloudinaryTransform
		], false);
	}
}