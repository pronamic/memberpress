<?php
/**
 * @license GPL-3.0
 *
 * Modified by caseproof on 12-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace MemberPress\Caseproof\GrowthTools;

class Config
{
    /**
     * The product ID ie memberpress, easy-affiliate
     *
     * @var string
     */
    protected $instanceId;

    /**
     * The path to the plugin main file.
     *
     * @var string
     */
    protected $file;

    /**
     * The plugin path.
     *
     * @var string
     */
    protected $path;

    /**
     * The menu path.
     *
     * @var string
     */
    protected $menuSlug;

    /**
     * The full URL to the plugin's assets path.
     *
     * @var string
     */
    protected $assetsUrl;

    /**
     * Denotes the slug of the admin menu's parent menu.
     *
     * @var string
     */
    protected $parentMenuSlug = 'tools.php';

    /**
     * List of css classes for action buttons.
     *
     * @var string[]
     */
    protected $buttonCSSClasses;

    /**
     * Base URl for images
     *
     * @var string
     */
    protected $imageBaseUrl = 'https://cspf-growth-tools.s3.us-east-2.amazonaws.com/img';

    /**
     * Url to configuration file
     *
     * @var string
     */
    protected $configFileUrl = 'https://cspf-growth-tools.s3.us-east-2.amazonaws.com/tools.json';

    /**
     * Constructor
     *
     * @param array $params An array of configuration parameters.{.
     *
     * @type string $file Required. The path to the plugin main file.
     * @type string $path Optional. The plugin path. Defaults to dirname($file)
     *                                  if not supplied.
     * @type string $assetsUrl Optional. The full URL to the plugin's assets
     *                                  path. Defaults to plugins_url('dist', $path)
     *                                  if not supplied.
     * @type string $parentMenuSlug Optional. Denotes the slug of the admin
     *                                  menu's parent menu. Defaults to 'tools.php'
     *                                  if not supplied.
     * }.
     */
    public function __construct(array $params)
    {
        $this->file = dirname(__FILE__) . '/growth-tools.php';
        foreach ($params as $key => $value) {
            $this->$key = $value;
        }

        if (empty($this->path)) {
            $this->path = dirname($this->file);
        }

        if (empty($this->assetsUrl)) {
            $this->assetsUrl = plugins_url('src/dist', $this->path);
        }

        if (empty($this->buttonCSSClasses)) {
            $this->buttonCSSClasses = ['button', 'button-primary'];
        }
    }

    /**
     * Retrieves a configuration value.
     *
     * @param  string $key The configuration key.
     * @return mixed Returns the configuration value if it exists or null if the
     *               configuration value does not exist.
     */
    public function __get(string $key)
    {
        return isset($this->$key) ? $this->$key : null;
    }
}
