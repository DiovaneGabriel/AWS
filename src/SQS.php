<?php

namespace DBarbieri\Aws;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use DBarbieri\Graylog\Graylog;
use Error;
use Exception;

class SQS extends AWS
{
    private SqsClient $sqs;

    private ?string $queueUrl;

    public function __construct(string $key, string $secret, string $region, string $queueUrl = null)
    {
        parent::__construct($key, $secret, $region);
        $this->queueUrl = $queueUrl;

        $this->setSQSClienteInstance();
    }

    private function setSQSClienteInstance()
    {
        $this->sqs = new SqsClient([
            'version' => 'latest',
            'region'  => $this->getRegion(),
            'credentials' => [
                'key'    => $this->getKey(),
                'secret' => $this->getSecret(),
            ]
        ]);
    }

    public function send(string $messageBody, string $queueUrl = null)
    {

        try {

            $params = [
                'QueueUrl'    => $queueUrl ?: $this->getQueueUrl(),
                'MessageBody' => $messageBody,
            ];

            $result = $this->sqs->sendMessage($params);

            $this->log(json_encode($result->get('@metadata')));

            return $result['MessageId'];
        } catch (SqsException $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function receive(string $queueUrl = null, int $maxMessages = 10)
    {

        try {

            $params = [
                'QueueUrl'    => $queueUrl ?: $this->getQueueUrl(),
                'MaxNumberOfMessages' => $maxMessages,
                'VisibilityTimeout' => 60,
                'WaitTimeSeconds' => 10
            ];

            $result = $this->sqs->receiveMessage($params);

            $messages = [];

            if (!empty($result['Messages'])) {
                foreach ($result['Messages'] as $message) {
                    $messages[] = $message["Body"];
                }
            }

            $this->log(json_encode([
                'metadata' => $result->get('@metadata'),
                'messages' => $messages
            ]));

            if (!empty($result['Messages'])) {
                return $result['Messages'];
            } else {
                return false;
            }
        } catch (SqsException $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function delete(string $receiptHandle = null, string $queueUrl = null)
    {

        try {

            $params = [
                'QueueUrl'    => $queueUrl ?: $this->getQueueUrl(),
                'ReceiptHandle' => $receiptHandle
            ];

            $result = $this->sqs->deleteMessage($params);

            $this->log(json_encode($result->get('@metadata')));

            return true;
        } catch (SqsException $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    // public function get($target, $bucket = null)
    // {
    //     try {
    //         $result = $this->s3->getObject([
    //             'Bucket' => $bucket ?: $this->getBucket(),
    //             'Key'    => $target,
    //         ]);

    //         $this->log(json_encode($result->get('@metadata')['headers']));

    //         return $result['Body']->getContents();
    //     } catch (S3Exception $e) {
    //         $xmlResponse = $e->getResponse()->getBody()->__toString();
    //         $this->log($xmlResponse, Graylog::LEVEL_ERROR);

    //         throw $e;
    //     } catch (Error | Exception $e) {
    //         $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
    //         throw $e;
    //     }
    // }

    // public function doesObjectExists($target, $bucket = null)
    // {
    //     try {
    //         $result = $this->s3->doesObjectExist($bucket ?: $this->getBucket(), $target);

    //         $this->log(json_encode($result));

    //         return $result;
    //     } catch (S3Exception $e) {
    //         $xmlResponse = $e->getResponse()->getBody()->__toString();
    //         $this->log($xmlResponse, Graylog::LEVEL_ERROR);

    //         throw $e;
    //     } catch (Error | Exception $e) {
    //         $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
    //         throw $e;
    //     }
    // }

    // public function delete($target, $checkIfExists = false, $bucket = null)
    // {

    //     if ($checkIfExists && !$this->doesObjectExists($target, $bucket)) {
    //         throw new Exception("Target does not exists!");
    //     }

    //     try {
    //         $result = $this->s3->deleteObject([
    //             'Bucket' => $bucket ?: $this->getBucket(),
    //             'Key'    => $target,
    //         ]);

    //         $this->log(json_encode($result->get('@metadata')['headers']));

    //         return true;
    //     } catch (S3Exception $e) {
    //         $xmlResponse = $e->getResponse()->getBody()->__toString();
    //         $this->log($xmlResponse, Graylog::LEVEL_ERROR);

    //         throw $e;
    //     } catch (Error | Exception $e) {
    //         $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
    //         throw $e;
    //     }
    // }

    // public function deleteByUrl(string $url)
    // {
    //     $url = Url::decodeAwsS3Url($url);
    //     $region = $url->getRegion();

    //     if ($region && $region != $this->region) {
    //         $this->region = $region;
    //         $this->setS3ClienteInstance();
    //     }

    //     return $this->delete($url->getTarget(), false, $url->getBucket());
    // }

    // public function getByUrl(string $url)
    // {
    //     $url = Url::decodeAwsS3Url($url);
    //     $region = $url->getRegion();

    //     if ($region && $region != $this->region) {
    //         $this->region = $region;
    //         $this->setS3ClienteInstance();
    //     }

    //     return $this->get($url->getTarget(), $url->getBucket());
    // }

    // // public function list($bucket)
    // // {
    // //     try {
    // //         $objects = $this->s3->listObjects([
    // //             'Bucket' => $bucket,
    // //         ]);

    // //         echo '<pre>';
    // //         var_dump($objects['Contents']);
    // //         die();
    // //     } catch (\Throwable $th) {
    // //         throw new Exception("Deu ruim");
    // //     }
    // // }

    /**
     * Get the value of bucket
     */
    public function getQueueUrl(): ?string
    {
        return $this->queueUrl;
    }

    /**
     * Set the value of bucket
     */
    public function setQueueUrl(?string $queueUrl): self
    {
        $this->queueUrl = $queueUrl;

        return $this;
    }
}
