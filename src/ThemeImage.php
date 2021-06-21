<?php

namespace Fronty\ResponsiveImages;

use Fronty\ResponsiveImages\Sizes\ImageSize;
use Nette\Utils\Html;
use LogicException;

/**
 * Object to manipulate with theme images.
 */
class ThemeImage extends BaseImage
{
	/** @var string */
	private $file;

	/**
	 * Get theme image.
	 * @param string $file Relative image path within the theme directory, eg. svg/icon.svg or img/image.jpg.
	 */
	public function __construct(string $file)
	{
		$this->file = ltrim($file, '/');
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): string
	{
		$file = get_template_directory_uri() . '/' . $file;
		return apply_filters('fri_theme_img_url', $file, $this->file);
	}

	/**
	 * @inheritDoc
	 */
	public function getPath(): string
	{
		$file = get_template_directory() . '/' . $this->file;
		return apply_filters('fri_theme_img_path', $file, $this->file);
	}

	/**
	 * @inheritDoc
	 */
	public function getImgTag(ImageSize $size, array $attrs = []): Html
	{
		$attrs['src'] = esc_url($this->getUrl());
		$attrs['width'] = $size->getWidth();
		$attrs['height'] = $size->getHeight();
		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = esc_attr(strip_tags($attrs['alt']));
		$el = Html::el('img', $attrs);
		apply_filters('fri_theme_img_tag', $el, $size);
		return $el;
	}

	/**
	 * Get img tag wrapped in span ensuring aspect-ratio before image is lazyloaded.
	 * @param int $width
	 * @param int $height
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 * @return Html
	 */
	public function getAspectImgTag(int $width, int $height, array $wrapAttrs = [], array $imgAttrs = []): Html
	{
		$wrap = Html::el('span', $wrapAttrs);
		$wrap->addClass('imgAspect');
		$wrap->addStyle('max-width: ' . $width . 'px');

		$span = $height / $width * 100;
		$wrap->addHtml(Html::el('span', ['class' => 'imgAspect__span', 'style' => "padding-top: $span%"]));

		$img = $this->getImgTag($width, $height, $imgAttrs);
		$img->addClass('imgAspect__img');
		return $wrap->addHtml($img);
	}

	/**
	 * Output img tag wrapped in aspect-ratio ensuring span.
	 * @param int $width
	 * @param int $height
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 *
	 * @see self::getAspectImgTag()
	 */
	public function aspectImgTag(int $width, int $height, array $wrapAttrs = [], array $imgAttrs = []) {
		echo $this->getAspectImgTag($width, $height, $wrapAttrs, $imgAttrs);
	}

	/**
	 * Get default image alt.
	 * @return string
	 */
	private function getDefaultAlt(): string
	{
		return $this->getPathinfo()->filename;
	}
}