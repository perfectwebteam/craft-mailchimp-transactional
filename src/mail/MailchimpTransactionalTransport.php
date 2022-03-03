<?php
/**
 * Mailchimp Transactional plugin for Craft CMS 3.x
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2022 Perfect Web Team
 */

namespace perfectwebteam\mailchimptransactional\mail;

use MailchimpTransactional\ApiClient;
use ReflectionClass;
use Swift_Attachment;
use Swift_Events_EventDispatcher;
use Swift_Events_EventListener;
use Swift_Events_SendEvent;
use Swift_Image;
use Swift_Mime_Header;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;
use Swift_Transport;
use Swift_TransportException;

/**
 * Mailchimp Transactional Transport
 *
 * @author    Perfect Web Team
 * @package   Mailchimp Transactional
 * @since     1.0.0
 */
class MailchimpTransactionalTransport implements Swift_Transport
{
    /**
     * @type Swift_Events_EventDispatcher
     */
    protected Swift_Events_EventDispatcher $dispatcher;

    /**
     * @var string|null
     */
    protected ?string $apiKey;

    /**
     * @var bool|null
     */
    protected ?bool $async;

    /**
     * @var array|null
     */
    protected ?array $resultApi;

    /**
     * @var string|null
     */
    protected ?string $subAccount;

    /**
     * @var string|null
     */
    protected ?string $template;

    /**
     * @param Swift_Events_EventDispatcher $dispatcher
     */
    public function __construct(Swift_Events_EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->apiKey = null;
        $this->async = false;
        $this->subAccount = null;
        $this->template = null;
    }

    /**
     * Not used
     */
    public function isStarted(): bool
    {
        return false;
    }

    /**
     * Not used
     */
    public function start(): void
    {
    }

    /**
     * Not used
     */
    public function stop(): void
    {
    }

    /**
     * Not used
     */
    public function ping(): void
    {
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey(string $apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * @param bool $async
     * @return $this
     */
    public function setAsync(bool $async)
    {
        $this->async = $async;

        return $this;
    }

    /**
     * @return null|bool
     */
    public function getAsync(): ?bool
    {
        return $this->async;
    }

    /**
     * @param string|null $subAccount
     * @return $this
     */
    public function setSubAccount(?string $subAccount)
    {
        $this->subAccount = $subAccount;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSubAccount(): ?string
    {
        return $this->subAccount;
    }

    /**
     * @param string|null $template
     * @return $this
     */
    public function setTemplate(?string $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @return ApiClient
     * @throws Swift_TransportException
     */
    protected function createMailchimpTransactional()
    {
        if ($this->apiKey === null) {
            throw new Swift_TransportException('Cannot create instance of Mailchimp Transactional while API key is NULL');
        }

        return (new ApiClient())->setApiKey($this->apiKey);
    }

    /**
     * Send mail via Mailchimp Transactional
     *
     * @param Swift_Mime_SimpleMessage $message
     * @param null $failedRecipients
     * @return int Number of messages sent
     * @throws Swift_TransportException
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->resultApi = null;

        if ($event = $this->dispatcher->createSendEvent($this, $message)) {
            $this->dispatcher->dispatchEvent($event, 'beforeSendPerformed');

            if ($event->bubbleCancelled()) {
                return 0;
            }
        }

        $sendCount = 0;

        $mailchimpTransactionalMessage = $this->getMailchimpTransactionalMessage($message);
        $mailchimpTransactional = $this->createMailchimpTransactional();

        //  Use template if configured
        if ($this->template) {
            $this->resultApi = $mailchimpTransactional->messages->sendTemplate([
                'template_name' => $this->template,
                'template_content' => [
                    [
                        'name' => 'body',
                        'content' => $mailchimpTransactionalMessage['html']
                    ]
                ],
                'message' => $mailchimpTransactionalMessage,
                'async' => $this->async
            ]);
        } else {
            $this->resultApi = $mailchimpTransactional->messages->send([
                'message' => $mailchimpTransactionalMessage,
                'async' => $this->async
            ]);
        }

        foreach ($this->resultApi as $item) {
            if ($item->status === 'sent' || $item->status === 'queued') {
                $sendCount++;
            } else {
                $failedRecipients[] = $item->email;
            }
        }

        if ($event) {
            if ($sendCount > 0) {
                $event->setResult(Swift_Events_SendEvent::RESULT_SUCCESS);
            } else {
                $event->setResult(Swift_Events_SendEvent::RESULT_FAILED);
            }

            $this->dispatcher->dispatchEvent($event, 'sendPerformed');
        }

        return $sendCount;
    }

    /**
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin): void
    {
        $this->dispatcher->bindEventListener($plugin);
    }

    /**
     * @return array
     */
    protected function getSupportedContentTypes(): array
    {
        return [
            'text/plain',
            'text/html'
        ];
    }

    /**
     * @param string $contentType
     * @return bool
     */
    protected function supportsContentType(string $contentType): bool
    {
        return in_array($contentType, $this->getSupportedContentTypes());
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return string|null
     */
    protected function getMessagePrimaryContentType(Swift_Mime_SimpleMessage $message): ?string
    {
        $contentType = $message->getContentType();

        if ($this->supportsContentType($contentType)) {
            return $contentType;
        }

        // SwiftMailer hides the content type set in the constructor of Swift_Mime_SimpleMessage as soon
        // as you add another part to the message. We need to access the protected property
        // userContentType to get the original type.
        $messageRef = new ReflectionClass($message);

        if ($messageRef->hasProperty('userContentType')) {
            $propRef = $messageRef->getProperty('userContentType');
            $propRef->setAccessible(true);
            $contentType = $propRef->getValue($message);
        }

        return $contentType;
    }

    /**
     * Format message for Mailchimp Transactional
     *
     * https://mailchimp.com/developer/transactional/api/messages/send-new-message/
     *
     * @author https://github.com/AccordGroup/MandrillSwiftMailer
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array Mailchimp Transactional Send Message
     */
    public function getMailchimpTransactionalMessage(Swift_Mime_SimpleMessage $message): array
    {
        $contentType = $this->getMessagePrimaryContentType($message);

        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);

        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ?: [];
        $bccAddresses = $message->getBcc() ?: [];
        $replyToAddresses = $message->getReplyTo() ?: [];

        $to = [];
        $attachments = [];
        $images = [];
        $headers = [];
        $tags = [];

        foreach ($toAddresses as $toEmail => $toName) {
            $to[] = [
                'email' => $toEmail,
                'name' => $toName,
                'type' => 'to'
            ];
        }

        foreach ($replyToAddresses as $replyToEmail => $replyToName) {
            if ($replyToName) {
                $headers['Reply-To'] = sprintf('%s <%s>', $replyToEmail, $replyToName);
            } else {
                $headers['Reply-To'] = $replyToEmail;
            }
        }

        foreach ($ccAddresses as $ccEmail => $ccName) {
            $to[] = [
                'email' => $ccEmail,
                'name' => $ccName,
                'type' => 'cc'
            ];
        }

        foreach ($bccAddresses as $bccEmail => $bccName) {
            $to[] = [
                'email' => $bccEmail,
                'name' => $bccName,
                'type' => 'bcc'
            ];
        }

        $bodyHtml = $bodyText = null;

        if ($contentType === 'text/plain') {
            $bodyText = $message->getBody();
        } elseif ($contentType === 'text/html') {
            $bodyHtml = $message->getBody();
        } else {
            $bodyHtml = $message->getBody();
        }

        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Image) {
                $images[] = [
                    'type' => $child->getContentType(),
                    'name' => $child->getId(),
                    'content' => base64_encode($child->getBody()),
                ];
            } elseif ($child instanceof Swift_Attachment && !($child instanceof Swift_Image)) {
                $attachments[] = [
                    'type' => $child->getContentType(),
                    'name' => $child->getFilename(),
                    'content' => base64_encode($child->getBody())
                ];
            } elseif ($child instanceof Swift_MimePart && $this->supportsContentType($child->getContentType())) {
                if ($child->getContentType() === 'text/html') {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() === 'text/plain') {
                    $bodyText = $child->getBody();
                }
            }
        }

        $mailchimpTransactionalMessage = [
            'html' => $bodyHtml,
            'text' => $bodyText,
            'subject' => $message->getSubject(),
            'from_email' => $fromEmails[0],
            'from_name' => $fromAddresses[$fromEmails[0]],
            'to' => $to,
            'headers' => $headers,
            'tags' => $tags,
            'inline_css' => null
        ];

        if (count($attachments) > 0) {
            $mailchimpTransactionalMessage['attachments'] = $attachments;
        }

        if (count($images) > 0) {
            $mailchimpTransactionalMessage['images'] = $images;
        }

        foreach ($message->getHeaders()->getAll() as $header) {
            if ($header->getFieldType() === Swift_Mime_Header::TYPE_TEXT) {
                switch ($header->getFieldName()) {
                    case 'List-Unsubscribe':
                        $headers['List-Unsubscribe'] = $header->getValue();
                        $mailchimpTransactionalMessage['headers'] = $headers;
                        break;
                    case 'X-MC-InlineCSS':
                        $mailchimpTransactionalMessage['inline_css'] = $header->getValue();
                        break;
                    case 'X-MC-Tags':
                        $tags = $header->getValue();
                        if (!is_array($tags)) {
                            $tags = explode(',', $tags);
                        }
                        $mailchimpTransactionalMessage['tags'] = $tags;
                        break;
                    case 'X-MC-Autotext':
                        $autoText = $header->getValue();
                        if (in_array($autoText, ['true', 'on', 'yes', 'y', true], true)) {
                            $mailchimpTransactionalMessage['auto_text'] = true;
                        }
                        if (in_array($autoText, ['false', 'off', 'no', 'n', false], true)) {
                            $mailchimpTransactionalMessage['auto_text'] = false;
                        }
                        break;
                    case 'X-MC-GoogleAnalytics':
                        $analyticsDomains = explode(',', $header->getValue());
                        if (is_array($analyticsDomains)) {
                            $mailchimpTransactionalMessage['google_analytics_domains'] = $analyticsDomains;
                        }
                        break;
                    case 'X-MC-GoogleAnalyticsCampaign':
                        $mailchimpTransactionalMessage['google_analytics_campaign'] = $header->getValue();
                        break;
                    case 'X-MC-TrackingDomain':
                        $mailchimpTransactionalMessage['tracking_domain'] = $header->getValue();
                        break;
                    default:
                        if (strncmp($header->getFieldName(), 'X-', 2) === 0) {
                            $headers[$header->getFieldName()] = $header->getValue();
                            $mailchimpTransactionalMessage['headers'] = $headers;
                        }
                        break;
                }
            }
        }

        if ($this->getSubaccount()) {
            $mailchimpTransactionalMessage['subaccount'] = $this->getSubaccount();
        }

        return $mailchimpTransactionalMessage;
    }

    /**
     * @return null|array
     */
    public function getResultApi(): ?array
    {
        return $this->resultApi;
    }
}
