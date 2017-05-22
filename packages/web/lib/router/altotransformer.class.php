<?php
/**
 * Interface to create AltoTransformer
 *
 * PHP Version 5
 *
 * @category AltoTransformer
 * @package  AltoRouter
 * @author   Mattsah <no@email.given>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     https://github.com/dannyvankooten/AltoRouter
 */
/**
 * Interface to create AltoTransformer
 *
 * @category AltoTransformer
 * @package  AltoRouter
 * @author   Mattsah <no@email.given>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     https://github.com/dannyvankooten/AltoRouter
 */
interface AltoTransformer
{
    /**
     * Transform a parameter headed from a URL (i.e. during matching)
     *
     * @param mixed $value The value to transform.
     *
     * @return mixed
     */
    public function fromUrl($value);
    /**
     * Transform a parameter headed to a URL (.ie. during generation)
     *
     * @param mixed $value The value being transformed.
     *
     * @return mixed
     */
    public function toUrl($value);
}
