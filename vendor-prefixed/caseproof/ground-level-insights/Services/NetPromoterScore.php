<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Insights\Services;

use MemberPress\GroundLevel\Container\Container;
use MemberPress\GroundLevel\Container\Contracts\LoadableDependency;
use MemberPress\GroundLevel\InProductNotifications\Models\Button;
use MemberPress\GroundLevel\InProductNotifications\Services\Store as IPNStore;
use MemberPress\GroundLevel\Support\Concerns\Hookable;
use MemberPress\GroundLevel\Support\Models\Hook;
use MemberPress\GroundLevel\Support\Time;
use MemberPress\GroundLevel\Support\Str;
use MemberPress\GroundLevel\Support\StyleUtil;

class NetPromoterScore implements LoadableDependency
{
    use Hookable;

    /**
     * The ID of the NPS notification.
     */
    public const NOTIFICATION_ID = 'nps_survey';

    public const OPT_KEY_INSTALL_TIME    = 'installTime';
    public const OPT_KEY_LAST_EVENT_TIME = 'lastEventTime';

    /**
     * The hook name for the event action.
     *
     * @var string
     */
    protected string $eventHookName;

    /**
     * The grace period in days after installation to show the NPS notification.
     *
     * @var integer
     */
    protected int $gracePeriodDays;

    /**
     * The IPN store instance.
     *
     * @var \MemberPress\GroundLevel\InProductNotifications\Services\Store
     */
    protected IPNStore $ipnStore;

    /**
     * Option key for NPS data.
     *
     * @var string
     */
    protected string $optionKey;

    /**
     * The name of the product.
     *
     * @var string
     */
    protected string $productName;

    /**
     * The insights service prefix.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * The recurrence interval in days for the NPS notification.
     *
     * @var integer
     */
    protected int $recurrenceDays;

    /**
     * Constructor.
     *
     * @param IPNStore $ipnStore        The IPN store instance.
     * @param string   $prefix          The prefix for the Insights service.
     * @param string   $productName     The product name.
     * @param integer  $gracePeriodDays The grace period in days.
     * @param integer  $recurrenceDays  The recurrence interval in days.
     */
    public function __construct(
        IPNStore $ipnStore,
        string $prefix,
        string $productName,
        int $gracePeriodDays,
        int $recurrenceDays
    ) {
        $this->ipnStore        = $ipnStore;
        $this->gracePeriodDays = $gracePeriodDays;
        $this->recurrenceDays  = $recurrenceDays;
        $this->productName     = $productName;

        $this->prefix        = $prefix;
        $this->eventHookName = Str::toSnakeCase($this->prefix . 'nps_check');
        $this->optionKey     = Str::toSnakeCase($this->prefix . 'nps_data');
    }

    /**
     * Load service dependencies.
     *
     * @param \MemberPress\GroundLevel\Container\Container $container The container.
     */
    public function load(Container $container): void
    {
        $this->addHooks();
    }

    /**
     * Configures the hooks for the service.
     *
     * @return array<int, Hook>
     */
    protected function configureHooks(): array
    {
        return [
            new Hook(
                Hook::TYPE_ACTION,
                'init',
                [$this, 'schedule']
            ),
            new Hook(
                Hook::TYPE_ACTION,
                $this->eventHookName,
                [$this, 'maybeAddNotification']
            ),
        ];
    }

    /**
     * Schedules the NPS check cron job.
     */
    public function schedule(): void
    {
        if (! wp_next_scheduled($this->eventHookName)) {
            wp_schedule_event(Time::now(), 'daily', $this->eventHookName);
        }
    }

    /**
     * Retrieves the NPS data.
     *
     * @return array
     */
    protected function getData(): array
    {
        $data = get_option($this->optionKey, []);
        if (empty($data)) {
            $data = [
                self::OPT_KEY_INSTALL_TIME    => Time::now(),
                self::OPT_KEY_LAST_EVENT_TIME => 0,
            ];
            update_option($this->optionKey, $data);
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Checks if it's time to show the NPS notification and adds it if so.
     */
    public function maybeAddNotification(): bool
    {
        $shouldShow = apply_filters(
            Str::toSnakeCase($this->prefix . 'should_show_nps_notification'),
            $this->shouldShow()
        );

        if (! $shouldShow) {
            return false;
        }

        $store = $this->ipnStore->fetch();
        if ($store->has(self::NOTIFICATION_ID)) {
            return false;
        }

        $notificationData = [
            'id'           => self::NOTIFICATION_ID,
            'subject'      => __('How are we doing?', 'memberpress'),
            'icon'         => $this->getIconHtml(),
            'content'      => $this->getContentHtml(),
            'publishes_at' => Time::now('mysql'),
            'expires_at'   => date(
                Time::FORMAT_MYSQL,
                Time::now() + Time::days((int) ($this->recurrenceDays / 2))
            ),
            'buttons'      => [
                [
                    'label' => __('Submit', 'memberpress'),
                    'url'   => '#nps-submit',
                ],
                [
                    'label'  => __('Remind me later', 'memberpress'),
                    'preset' => Button::PRESET_DISMISS,
                ],
            ],
        ];

        $store->add($notificationData)->persist();
        return true;
    }

    /**
     * Determines if the NPS notification should be shown.
     *
     * @return boolean
     */
    protected function shouldShow(): bool
    {
        $now  = Time::now();
        $data = $this->getData();

        $installTime   = (int) ($data[self::OPT_KEY_INSTALL_TIME] ?? 0);
        $lastEventTime = (int) ($data[self::OPT_KEY_LAST_EVENT_TIME] ?? 0);

        // If there's no event time, show after initial installation grace period.
        if (! $lastEventTime) {
            return $now >= ($installTime + Time::days($this->gracePeriodDays));
        }

        // If there's an event time, show every N days after last event.
        return $now >= ($lastEventTime + Time::days($this->recurrenceDays));
    }

    /**
     * Records completion of an NPS submission.
     *
     * Stores the last event time and deletes the existing notification.
     *
     * @param boolean $isCompleted Whether the submission is fully completed.
     */
    public function recordSubmission(bool $isCompleted = false): void
    {
        $store = $this->ipnStore->fetch();

        if ($isCompleted) {
            // Step 2 completion: delete the notification entirely.
            $store->delete(self::NOTIFICATION_ID);
        } else {
            // Step 1 completion: record event time and reduce the expiration to 7 days from now.
            $now                                 = Time::now();
            $data                                = $this->getData();
            $data[self::OPT_KEY_LAST_EVENT_TIME] = $now;
            update_option($this->optionKey, $data);

            $store->update(
                self::NOTIFICATION_ID,
                [
                    'expiresAt' => date(Time::FORMAT_MYSQL, $now + Time::days(7)),
                ]
            );
        }
        $store->persist();
    }

    /**
     * Retrieves the HTML for the notification icon.
     *
     * @return string
     */
    protected function getIconHtml(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2563eb"><path d="M0 0h24v24H0z" fill="none"></path><path d="M18 11v2h4v-2h-4zm-2 6.61c.96.71 2.21 1.65 3.2 2.39.4-.53.8-1.07 1.2-1.6-.99-.74-2.24-1.68-3.2-2.4-.4.54-.8 1.08-1.2 1.61zM20.4 5.6c-.4-.53-.8-1.07-1.2-1.6-.99.74-2.24 1.68-3.2 2.4.4.53.8 1.07 1.2 1.6.96-.72 2.21-1.65 3.2-2.4zM4 9c-1.1 0-2 .9-2 2v2c0 1.1.9 2 2 2h1v4h2v-4h1l5 3V6L8 9H4zm11.5 3c0-1.33-.58-2.53-1.5-3.35v6.69c.92-.81 1.5-2.01 1.5-3.34z"></path></svg>'; // phpcs:ignore Generic.Files.LineLength.TooLong
    }

    /**
     * Retrieves the HTML for the notification content.
     *
     * @return string
     */
    protected function getContentHtml(): string
    {
        $question = sprintf(
            __(
                'On a scale of 0 to 10, how likely are you to recommend %s to a friend or colleague?',
                'memberpress'
            ),
            '<strong style="font-weight: 500;">' . esc_html($this->productName) . '</strong>'
        );

        $html  = '<p>' . strip_tags($question, ['strong']) . '</p>';
        $html .= '<div style="display: flex; margin: 1rem 0 0.5rem">';
        for ($i = 0; $i <= 10; $i++) {
            $html .= $this->getButtonHtml($i);
        }
        $html .= '</div>';
        $html .= '<div style="display: flex; justify-content: space-between; font-size: 70%; margin-bottom: 1rem">';
        $html .= '<span>' . esc_html__('Not likely') . '</span>';
        $html .= '<span>' . esc_html__('Extremely likely') . '</span>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Retrieves the HTML for a single score button.
     *
     * @param  integer $score The score value.
     * @return string
     */
    protected function getButtonHtml(int $score): string
    {
        $styles = [
            'color'         => '#222',
            'padding'       => '0.75rem 0',
            'text-align'    => 'center',
            'flex'          => '1',
            'border'        => '1px solid #aaa',
            'margin-left'   => $score === 0 ? '0' : '-1px',
            'border-radius' => '0',
            'cursor'        => 'pointer',
        ];

        if ($score === 0) {
            $styles['border-radius'] = '4px 0 0 4px';
        } elseif ($score === 10) {
            $styles['border-radius'] = '0 4px 4px 0';
        }

        return sprintf(
            '<button type="button" class="nps-score-btn" data-score="%1$d" %2$s>%1$d</button>',
            $score,
            StyleUtil::arrToAttr($styles)
        );
    }
}
