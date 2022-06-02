<?php
/**
 * Mailchimp Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2022 Perfect Web Team
 */

namespace perfectwebteam\mailchimptransactional\mail;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Mailchimp Transactional Transport
 * Based on https://github.com/symfony/mailchimp-mailer
 *
 * @author    Perfect Web Team
 * @package   Mailchimp Transactional
 * @since     1.0.0
 */
class MailchimpTransactionalTransport extends AbstractApiTransport
{
    private const HOST = 'mandrillapp.com';

    private string $key;

    private string $template = '';

    private string $subaccount = '';

    /**
     * @param string $key
     * @param HttpClientInterface|null $client
     * @param EventDispatcherInterface|null $dispatcher
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('mandrill+api://%s', $this->getEndpoint());
    }

    /**
     * @param SentMessage $sentMessage
     * @param Email $email
     * @param Envelope $envelope
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/api/1.0/messages/' . $this->getApiCall() . '.json', [
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: ' . $response->getContent(false) . sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mandrill server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            if ('error' === ($result['status'] ?? false)) {
                throw new HttpTransportException('Unable to send an email: ' . $result['message'] . sprintf(' (code %d).', $result['code']), $response);
            }

            throw new HttpTransportException(sprintf('Unable to send an email (code %d).', $result['code']), $response);
        }

        $firstRecipient = reset($result);
        $sentMessage->setMessageId($firstRecipient['_id']);

        return $response;
    }

    /**
     * @return string|null
     */
    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST) . ($this->port ? ':' . $this->port : '');
    }

    /**
     * @return string|null
     */
    private function getApiCall(): ?string
    {
        return $this->template ? 'send-template' : 'send';
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'key' => $this->key,
            'message' => [
                'html' => $email->getHtmlBody(),
                'text' => $email->getTextBody(),
                'subject' => $email->getSubject(),
                'from_email' => $envelope->getSender()->getAddress(),
                'to' => $this->getRecipients($email, $envelope),
                'subaccount' => $this->subaccount ?: null
            ],
            'template_name' => $this->template ?: null,
            'template_content' => [
                [
                    'name' => 'body',
                    'content' => $email->getHtmlBody()
                ]
            ]
        ];

        if ('' !== $envelope->getSender()->getName()) {
            $payload['message']['from_name'] = $envelope->getSender()->getName();
        }

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'content' => $attachment->bodyToString(),
                'type' => $headers->get('Content-Type')->getBody(),
            ];

            if ($name = $headers->getHeaderParameter('Content-Disposition', 'name')) {
                $att['name'] = $name;
            }

            if ('inline' === $disposition) {
                $payload['message']['images'][] = $att;
            } else {
                $payload['message']['attachments'][] = $att;
            }
        }

        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type'];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $payload['message']['tags'] = array_merge(
                    $payload['message']['tags'] ?? [],
                    explode(',', $header->getValue())
                );

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $payload['message']['metadata'][$header->getKey()] = $header->getValue();

                continue;
            }

            $payload['message']['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $payload;
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $recipients = [];

        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'to';

            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'bcc';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'cc';
            }

            $recipientPayload = [
                'email' => $recipient->getAddress(),
                'type' => $type,
            ];

            if ('' !== $recipient->getName()) {
                $recipientPayload['name'] = $recipient->getName();
            }

            $recipients[] = $recipientPayload;
        }

        return $recipients;
    }

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template): static
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @param string $subaccount
     * @return $this
     */
    public function setSubaccount(string $subaccount): static
    {
        $this->subaccount = $subaccount;

        return $this;
    }
}