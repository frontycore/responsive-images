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
	 * @return string Empty string when the image has no file, eg. for an attachment ID whose file is gone.
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
	 * Get width and height of the image.
	 * @return array|null [0 => int width, 1 => int height], null if not available.
	 */
	public function getOriginalSize(): ?array
	{
		// The file is read here, so its absence has to be handled: an attachment ID outlives its file
		// and both getimagesize() and simplexml_load_file() are fatal on an empty path.
		// An unknown size is a documented outcome handled by the callers, so it is not reported.
		$path = $this->getPath();
		if ($path === '' || !is_file($path)) return null;

		if ($this->isSvg()) {
			$xml = simplexml_load_file($path);
			if ($xml === false) return null;
			$dim = array_filter([
				intval($xml->attributes()->width),
				intval($xml->attributes()->height),
			], fn(int $val): bool => $val > 0);
			return (count($dim) === 2) ? $dim : null;
		}

		$dim = getimagesize($path);
		return (is_array($dim) && count($dim) === 2) ? $dim : null;
	}

	/**
	 * Get aspect ratio of original image.
	 * @param int $precision Number of decimal places to round to.
	 * @return float|null
	 */
	public function getOriginalRatio(int $precision = 2): ?float
	{
		$size = $this->getOriginalSize();
		if ($size === null) return null;
		return round(100 * $size[1] / $size[0], $precision);
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
	 * @return string SVG code, empty string when the file can't be read.
	 * @throws LogicException If the image is not of SVG type. Check image using static::isSvg().
	 *
	 * @see static::reportIssue()
	 */
	public function getInlineSvg(): string
	{
		if (!$this->isSvg()) {
			throw new LogicException("Image '{$this->getUrl()}' is not SVG.");
		}
		$path = $this->getPath();
		if ($path === '' || !is_file($path)) {
			$this->reportIssue("SVG file of '{$this->getUrl()}' doesn't exist.");
			return '';
		}
		$svg = file_get_contents($path);
		if ($svg === false) {
			$this->reportIssue("SVG file '$path' couldn't be read.");
			return '';
		}
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

	/**
	 * Report a problem, which would otherwise leave the output silently empty.
	 *  Triggers a warning when WP_DEBUG is on, so the problem is loud in development,
	 *  and logs it otherwise. Hook the `fri_image_issue` action to report it elsewhere,
	 *  eg. to a monitoring service.
	 * @param string $message
	 */
	protected function reportIssue(string $message): void
	{
		$message = static::class . ": $message";
		do_action('fri_image_issue', $message, $this);
		if (defined('WP_DEBUG') && WP_DEBUG) {
			trigger_error($message, E_USER_WARNING);
		} else {
			error_log($message);
		}
	}

	/**
	 * Make plain text of image alt.
	 * 	Entities (eg. &nbsp; added by the_title filter) are decoded, because Html escapes attributes itself.
	 * @param string $alt
	 * @return string
	 */
	protected function sanitizeAlt(string $alt): string
	{
		return html_entity_decode(strip_tags($alt), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}