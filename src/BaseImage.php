<?php

namespace Fronty\ResponsiveImages;

use Fronty\ResponsiveImages\Sizes\ImageSize;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use Nette\Utils\Html;
use LogicException;

abstract class BaseImage
{
	/** @var ArrayHash|null */
	private $pathinfo;

	/**
	 * Get absolute url of original image.
	 * @return string
	 */
	abstract public function getUrl(): string;

	/**
	 * Get absolute path of original image.
	 * @return string
	 */
	abstract public function getPath(): string;

	/**
	 * Get <img> tag as Html.
	 * @param ImageSize $size
	 * @param array $attrs
	 * @return Html
	 */
	abstract public function getImgTag(ImageSize $size, array $attrs = []): Html;

	/**
	 * Check if file has SVG extension.
	 * @return bool
	 */
	public function isSvg(): bool
	{
		return isset($this->getPathinfo()->extension) && Strings::lower($this->getPathinfo()->extension) === 'svg';
	}

	/**
	 * Get pathinfo from image URL.
	 * @return ArrayHash
	 */
	public function getPathinfo(): ArrayHash
	{
		if (!$this->pathinfo instanceof ArrayHash) {
			$this->pathinfo = ArrayHash::from(pathinfo($this->getUrl()));
		}
		return $this->pathinfo;
	}

	/**
	 * Output <img> tag as Html.
	 * @param ImageSize $size
	 * @param array $attrs
	 * @return Html
	 */
	public function imgTag(ImageSize $size, array $attrs = [])
	{
		echo $this->getImgTag($size, $attrs);
	}

	/**
	 * Returns SVG code.
	 * @return string
	 * @throws LogicException If the image is not of SVG type. Check image using static::isSvg().
	 */
	public function getInlineSvg(): string
	{
		assert($this->isSvg(), new LogicException("Image '{$this->getUrl()}' is not SVG."));
		$svg = file_get_contents($this->getPath());
		return $this->svgUniqueClasses($svg);
	}

	/**
	 * Output inline SVG code.
	 *
	 * @see static::getInlineSvg()
	 */
	public function inlineSvg()
	{
		echo $this->getInlineSvg();
	}

	/**
	 * Make all SVG classes unique. Handy before SVG inline output.
	 * @param string $svg
	 * @return string
	 */
	protected function svgUniqueClasses(string $svg): string
	{
		if (!preg_match('#\<style(.*)/style>#iUs', $svg, $match)) return $svg;
		$style = $match[1];
		if (!preg_match_all('/\.([a-zA-Z0-9\-]+)/', $style, $match)) return $svg;

		$unique = sha1($svg);
		$classes = array_unique($match[1]);
		$replace = array_map(function ($cls) use ($unique) {
			return $cls . "-$unique";
		}, $classes);
		$svg = str_replace($classes, $replace, $svg);
		return $svg;
	}
}