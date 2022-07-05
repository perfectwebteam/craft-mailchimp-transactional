<?php
/**
 * Mailchimp Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2022 Perfect Web Team
 */

namespace perfectwebteam\mailchimptransactional\mail;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use craft\mail\transportadapters\BaseTransportAdapter;
use Symfony\Component\Mailer\Transport\AbstractTransport;

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
    public string $apiKey = '';

    /**
     * @var string The subaccount that should be used
     */
    public string $subaccount = '';

    /**
     * @var string The template that should be used
     */
    public string $template = '';

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
    public function defineTransport(): array|AbstractTransport
    {
        $transport = new MailchimpTransactionalTransport(App::parseEnv($this->apiKey));

        if ($this->template) {
            $transport->setTemplate(App::parseEnv($this->template));
        }

        if ($this->subaccount) {
            $transport->setSubaccount(App::parseEnv($this->subaccount));
        }

        return $transport;
    }
}
