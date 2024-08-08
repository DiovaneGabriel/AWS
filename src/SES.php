<?php

namespace DBarbieri\Aws;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use DBarbieri\Graylog\Graylog;
use Error;
use Exception;

class SES extends AWS
{
    private SesClient $ses;
    private string $fromAddress;

    public function __construct(string $key, string $secret, string $region, string $fromAddress)
    {
        parent::__construct($key, $secret, $region);
        $this->fromAddress = $fromAddress;

        $this->setSESClienteInstance();
    }

    private function setSESClienteInstance()
    {
        $this->ses = new SesClient([
            'version' => 'latest',
            'region'  => $this->getRegion(),
            'credentials' => [
                'key'    => $this->getKey(),
                'secret' => $this->getSecret(),
            ]
        ]);
    }

    public function send($toAddresses, string $subject, string $body, bool $isHtml = true, $fromAddress = null)
    {
        $toAddresses = is_array($toAddresses) ? $toAddresses : [$toAddresses];

        if ($isHtml) {
            $body = [
                'Html' => [
                    'Charset' => 'UTF-8',
                    'Data'    => $body,
                ],
            ];
        } else {
            $body = [
                'Text' => [
                    'Charset' => 'UTF-8',
                    'Data'    => 'This is the body of the email.',
                ],
            ];
        }

        try {
            $result = $this->ses->sendEmail([
                'Destination' => [
                    'ToAddresses' => $toAddresses,
                ],
                'Message' => [
                    'Body' => $body,
                    'Subject' => [
                        'Charset' => 'UTF-8',
                        'Data'    => $subject,
                    ],
                ],
                'Source' => $fromAddress ?: $this->getFromAddress(),
            ]);

            $this->log(json_encode($result));

            return $result->get('MessageId');
        } catch (SesException $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();

            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    /**
     * Get the value of fromAddress
     */
    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    /**
     * Set the value of fromAddress
     */
    public function setFromAddress(string $fromAddress): self
    {
        $this->fromAddress = $fromAddress;

        return $this;
    }
}
