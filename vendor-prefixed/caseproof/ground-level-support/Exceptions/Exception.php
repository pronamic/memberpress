<?php
/**
 * @license GPL-3.0
 *
 * Modified by Team Caseproof using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace MemberPress\GroundLevel\Support\Exceptions;

use Exception as BaseException;

/**
 * An exception with support for arbitrary error data.
 */
class Exception extends BaseException
{
    /**
     * Additional data provided to the exception.
     *
     * @var array
     */
    protected array $data;

    /**
     * Constructor.
     *
     * @param string          $message  Error message.
     * @param integer         $code     Error code.
     * @param \Throwable|null $previous Previous exception, if nested.
     * @param array           $data     Additional data.
     */
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null, array $data = [])
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Retrieves the exception data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
