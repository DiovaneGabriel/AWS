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
    private ?string $fromUser;

    public function __construct(string $key, string $secret, string $region, string $fromAddress, string $fromUser = null)
    {
        parent::__construct($key, $secret, $region);
        $this->fromAddress = $fromAddress;
        $this->fromUser = $fromUser;

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

    public function sendRaw($toAddress, string $subject, string $body, bool $isHtml = true, string $fromUser = null, $attachContent = null, $attachFilename = null)
    {

        if ($fromUser) {
            $from = $fromUser . " <" . $this->getFromAddress() . ">";
        } else {
            $from = $this->getFromAddress();
        }

        // Boundaries MIME
        $boundaryMixed = 'mixed-' . md5((string) microtime(true));
        $boundaryAlt   = 'alt-'   . md5((string) (microtime(true) + 1));

        $headers  = [];
        $headers[] = "From: {$from}";
        $headers[] = "To: {$toAddress}";
        $headers[] = "Subject: {$subject}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"";

        $mime  = implode("\r\n", $headers) . "\r\n\r\n";
        $mime .= "--{$boundaryMixed}\r\n";
        $mime .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";

        if ($isHtml) {
            $mime .= "--{$boundaryAlt}\r\n";
            $mime .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
            $mime .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $mime .= "{$body}\r\n\r\n";
        } else {
            $mime .= "--{$boundaryAlt}\r\n";
            $mime .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
            $mime .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $mime .= "{$body}\r\n\r\n";
        }

        // Fecha alternativa
        $mime .= "--{$boundaryAlt}--\r\n\r\n";

        if ($attachContent) {
            $base64 = chunk_split(base64_encode($attachContent));
            $filename = $attachFilename ?: "file";
            // Anexo
            $mime .= "--{$boundaryMixed}\r\n";
            $mime .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $mime .= "Content-Description: {$filename}\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . strlen($attachContent) . ";\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= "{$base64}\r\n";
            $mime .= "--{$boundaryMixed}--";
        }

        try {
            $result = $this->ses->sendRawEmail([
                'RawMessage'   => ['Data' => $mime],
                'Source'       => $from,
                'Destinations' => [$toAddress], // opcional; o "To:" do cabeçalho já define
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

    public function send($toAddresses, string $subject, string $body, $replyToAddresses = null, bool $isHtml = true, $fromAddress = null, string $fromUser = null)
    {
        $toAddresses = is_array($toAddresses) ? $toAddresses : [$toAddresses];
        $fromUser = $fromUser ?: $this->getFromUser();

        if ($replyToAddresses) {
            $replyToAddresses = is_array($replyToAddresses) ? $replyToAddresses : [$replyToAddresses];
        }

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

            $mail = [
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
            ];

            if ($fromUser) {
                $mail['Source'] = $fromUser . " <" . ($fromAddress ?: $this->getFromAddress()) . ">";
            } else {
                $mail['Source'] = $fromAddress ?: $this->getFromAddress();
            }
            $replyToAddresses ? $mail['ReplyToAddresses'] = $replyToAddresses : null;

            $result = $this->ses->sendEmail($mail);

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

    /**
     * Get the value of fromUser
     */
    public function getFromUser(): string
    {
        return $this->fromUser;
    }

    /**
     * Set the value of fromUser
     */
    public function setFromUser(string $fromUser): self
    {
        $this->fromUser = $fromUser;

        return $this;
    }
}
