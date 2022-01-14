<?php
/**
 * Mailchimp Transactional plugin for Craft CMS 3.x
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2022 Perfect Web Team
 */

namespace perfectwebteam\mailchimptransactional;

use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\MailerHelper;
use perfectwebteam\mailchimptransactional\mail\MailchimpTransactionalAdapter;
use yii\base\Event;

/**
 * Mailchimp Transactional Plugin
 *
 * @author    Perfect Web Team
 * @package   Mailchimp Transactional
 * @since     1.0.0
 */
class MailchimpTransactional extends Plugin
{
    /**
     * @var MailchimpTransactional
     */
    public static MailchimpTransactional $plugin;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        Event::on(
            MailerHelper::class,
            MailerHelper::EVENT_REGISTER_MAILER_TRANSPORT_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = MailchimpTransactionalAdapter::class;
            }
        );
    }
}
