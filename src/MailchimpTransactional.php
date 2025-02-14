<?php
/**
 * Mailchimp Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\mailchimptransactional;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\MailerHelper;
use perfectwebteam\mailchimptransactional\mail\MailchimpTransactionalAdapter;
use perfectwebteam\mailchimptransactional\models\Settings;
use yii\base\Event;

/**
 * Mailchimp Transactional Plugin
 *
 * @author    Perfect Web Team
 * @package   Mailchimp Transactional
 * @since     1.0.0
 * @method Settings getSettings()
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

	    $eventName = defined(sprintf('%s::EVENT_REGISTER_MAILER_TRANSPORT_TYPES', MailerHelper::class))
		    ? MailerHelper::EVENT_REGISTER_MAILER_TRANSPORT_TYPES // Craft 4
		    : MailerHelper::EVENT_REGISTER_MAILER_TRANSPORTS; // Craft 5+

        Event::on(
            MailerHelper::class,
	        $eventName,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = MailchimpTransactionalAdapter::class;
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }
}
