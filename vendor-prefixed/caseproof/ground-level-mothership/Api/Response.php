<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Mothership\Api;

class Response
{
    use PaginatedResponse;

    /**
     * The data to be stored in the response.
     *
     * @var mixed
     */
    protected $data;

    /**
     * The error message, if any.
     *
     * @var string
     */
    protected $error;

    /**
     * The error code, if any.
     *
     * @var integer
     */
    protected $errorCode;

    /**
     * The Details of the error, if any.
     *
     * @var array
     */
    protected $errors;

    /**
     * Constructor for the Response class.
     *
     * @param mixed $data      The data to be stored in the response.
     * @param mixed $error     The error Message, if any.
     * @param mixed $errorCode The error code, if any.
     * @param array $errors    The errors, if any.
     */
    public function __construct($data = null, $error = null, $errorCode = null, $errors = null)
    {
        $this->data      = $data;
        $this->error     = $error;
        $this->errorCode = $errorCode;
        $this->errors    = $errors;
    }

    /**
     * Magic getter to access response properties.
     * Returns the property if it exists, otherwise it returns the error.
     *
     * @param  string $name The name of the property to get.
     * @return mixed The value of the property or the error.
     */
    public function __get(string $name)
    {
        return $this->returnDataOrError($name);
    }

    /**
     * Check if the response is an error.
     *
     * @return boolean True if the response is an error, false otherwise.
     */
    public function isError(): bool
    {
        return !empty($this->error);
    }

    /**
     * Check if the response is successful.
     *
     * @return boolean True if the response is successful, false otherwise.
     */
    public function isSuccess(): bool
    {
        return empty($this->error);
    }

    /**
     * Return the data or the error.
     *
     * @param  string $name The name of the property to get.
     * @return mixed The value of the property or the error.
     */
    private function returnDataOrError(string $name)
    {
        if ($this->isError()) {
            if ($name === 'errorCode') {
                return $this->errorCode;
            }
            if ($name === 'errors') {
                return $this->errors;
            }
            return $this->error;
        }

        if (!isset($this->data->{$name})) {
            return sprintf(
                // Translators: %s The name of the property that does not exist on the response object.
                esc_html__(
                    'Property %s does not exist on the response object.',
                    'memberpress'
                ),
                $name
            );
        }
        return $this->data->{$name};
    }
}
