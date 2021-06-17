<?php

namespace Fronty\ResponsiveImages;

use Nette\Utils\ArrayHash;
use Nette\Utils\Html;

interface IImage
{
	/**
	 * Get absolute url of image.
	 * @return string
	 */
	public function getUrl(): string;

	/**
	 * Get absolute path of image.
	 * @return string
	 */
	public function getPath(): string;

	/**
	 * Check if file is SVG.
	 * @return bool
	 */
	public function isSvg(): bool;

	/**
	 * Get image URL's path info.
	 * @return ArrayHash
	 */
	public function getPathinfo(): ArrayHash;

	/**
	 * Get <img> tag as Html.
	 * @param int $width
	 * @param int $height
	 * @param array $attrs
	 * @return Html
	 */
	public function getImgTag(int $width, int $height, array $attrs = []): Html;

	/**
	 * Output <img> tag.
	 * @param int $width
	 * @param int $height
	 * @param array $attrs
	 */
	public function imgTag(int $width, int $height, array $attrs = []);

	/**
	 * Return SVG code.
	 * @return string
	 */
	public function getInlineSvg(): string;

	/**
	 * Output inline SVG (<svg> tag with inner code).
	 */
	public function inlineSvg();
}