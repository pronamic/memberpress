<?php

defined('ABSPATH') || exit;

class MeprGatewayDomainMismatchException extends MeprGatewayException
{
    /**
     * The expected domain.
     *
     * @var string
     */
    private string $expected_domain;

    /**
     * The actual domain.
     *
     * @var string
     */
    private string $actual_domain;

    /**
     * Constructor for domain mismatch exception.
     *
     * @param string $expected_domain The expected domain.
     * @param string $actual_domain   The actual domain.
     */
    public function __construct(string $expected_domain, string $actual_domain)
    {
        $this->expected_domain = $expected_domain;
        $this->actual_domain   = $actual_domain;

        parent::__construct(sprintf(
            'Domain mismatch detected: expected=%s, actual=%s',
            $expected_domain,
            $actual_domain
        ));
    }

    /**
     * Get the expected domain.
     *
     * @return string The expected domain.
     */
    public function get_expected_domain(): string
    {
        return $this->expected_domain;
    }

    /**
     * Get the actual domain.
     *
     * @return string The actual domain.
     */
    public function get_actual_domain(): string
    {
        return $this->actual_domain;
    }
}
