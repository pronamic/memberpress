<?php
/**
 * @license GPL-3.0
 *
 * Modified by Team Caseproof using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace MemberPress\GroundLevel\Support\Contracts;

interface Jsonable
{
    /**
     * Converts the object to a JSON string.
     *
     * @link https://www.php.net/manual/en/function.json-encode.php
     *
     * @param  integer $flags Optional flags passed to {@see json_encode}.
     * @param  integer $depth Maximum depth passed to {@see json_encode}.
     * @return string
     */
    public function toJson(int $flags = 0, int $depth = 512): string;
}
