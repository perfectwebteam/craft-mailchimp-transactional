<?php
/**
 * Mailchimp Transactional plugin for Craft CMS 3.x
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2022 Perfect Web Team
 */

namespace perfectwebteam\mailchimptransactional\mail;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\mail\transportadapters\BaseTransportAdapter;
use Swift_Events_SimpleEventDispatcher;
use Swift_Transport;

/**
 * Mailchimp Transactional Adaptor
 *
 * @author    Perfect Web Team
 * @package   Mailchimp Transactional
 * @since     1.0.0
 *
 * @property-read mixed $settingsHtml
 */
class MailchimpTransactionalAdapter extends BaseTransportAdapter
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Mailchimp Transactional';
    }

    /**
     * @var string The API key that should be used
     */
    public $apiKey;

    /**
     * @var string The subaccount that should be used
     */
    public $subaccount;

    /**
     * @var string The template that should be used
     */
    public $template;

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'apiKey' => Craft::t('mailchimp-transactional', 'API Key'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'apiKey',
                'subaccount',
                'template'
            ],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['apiKey'], 'required'];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('mailchimp-transactional/settings', [
            'adapter' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function defineTransport(): array
    {
        return [
            'class' => MailchimpTransactionalTransport::class,
            'constructArgs' => [
                [
                    'class' => Swift_Events_SimpleEventDispatcher::class
                ]
            ],
            'apiKey' => Craft::parseEnv($this->apiKey),
            'subAccount' => Craft::parseEnv($this->subaccount) ?: null,
            'template' => Craft::parseEnv($this->template) ?: null
        ];
    }
}
