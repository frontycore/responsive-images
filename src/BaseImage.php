<?php

namespace Fronty\ResponsiveImages;

use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;

abstract class BaseImage implements IImage
{
	/** @var ArrayHash|null */
	private $pathinfo;

	/**
	 * @inheritDoc
	 */
	public function isSvg(): bool
	{
		return Strings::lower($this->getPathinfo()->extension) === 'svg';
	}

	/**
	 * @inheritDoc
	 */
	public function getPathinfo(): ArrayHash
	{
		if (!$this->pathinfo instanceof ArrayHash) {
			$this->pathinfo = ArrayHash::from(pathinfo($this->getUrl()));
		}
		return $this->pathinfo;
	}

	/**
	 * @inheritDoc
	 */
	public function imgTag(int $width, int $height, array $attrs = [])
	{
		echo $this->getImgTag($width, $height, $attrs);
	}

	/**
	 * @inheritDoc
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