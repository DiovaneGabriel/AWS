<?php

namespace DBarbieri\Aws;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use DBarbieri\Aws\Libraries\Url;
use DBarbieri\Graylog\Graylog;
use Error;
use Exception;

class S3 extends AWS
{
    private S3Client $s3;

    private ?string $bucket;

    public function __construct(string $key, string $secret, string $region, string $bucket = null)
    {
        parent::__construct($key, $secret, $region);
        $this->bucket = $bucket;

        $this->setS3ClienteInstance();
    }

    private function setS3ClienteInstance()
    {
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => $this->getRegion(),
            'credentials' => [
                'key'    => $this->getKey(),
                'secret' => $this->getSecret(),
            ]
        ]);
    }

    /**
     * @NoLog fileContent
     */

    public function send($fileContent, $target, $override = false, $bucket = null)
    {

        if (!$override && $this->doesObjectExists($target, $bucket)) {
            throw new Exception("Target already exists!");
        }

        try {
            $result = $this->s3->putObject([
                'Bucket' => $bucket ?: $this->getBucket(),
                'Key'    => $target,
                'Body'   => $fileContent
            ]);

            $this->log(json_encode($result->get('@metadata')['headers']));

            return $result->get('@metadata')['effectiveUri'];
        } catch (S3Exception $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function get($target, $bucket = null)
    {
        try {
            $result = $this->s3->getObject([
                'Bucket' => $bucket ?: $this->getBucket(),
                'Key'    => $target,
            ]);

            $this->log(json_encode($result->get('@metadata')['headers']));

            return $result['Body']->getContents();
        } catch (S3Exception $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function getSignedUri($target, $expires = '+15 minutes', $bucket = null): string
    {
        try {

            $command = $this->s3->getCommand('GetObject', [
                'Bucket' => $bucket ?: $this->getBucket(),
                'Key' => $target,
            ]);

            $result = $this->s3->createPresignedRequest($command, $expires);

            return (string) $result->getUri();
        } catch (S3Exception $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function doesObjectExists($target, $bucket = null)
    {
        try {
            $result = $this->s3->doesObjectExist($bucket ?: $this->getBucket(), $target);

            $this->log(json_encode($result));

            return $result;
        } catch (S3Exception $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function delete($target, $checkIfExists = false, $bucket = null)
    {

        if ($checkIfExists && !$this->doesObjectExists($target, $bucket)) {
            throw new Exception("Target does not exists!");
        }

        try {
            $result = $this->s3->deleteObject([
                'Bucket' => $bucket ?: $this->getBucket(),
                'Key'    => $target,
            ]);

            $this->log(json_encode($result->get('@metadata')['headers']));

            return true;
        } catch (S3Exception $e) {
            $xmlResponse = $e->getResponse()->getBody()->__toString();
            $this->log($xmlResponse, Graylog::LEVEL_ERROR);

            throw $e;
        } catch (Error | Exception $e) {
            $this->log($e->getMessage(), Graylog::LEVEL_FATAL);
            throw $e;
        }
    }

    public function deleteByUrl(string $url)
    {
        $url = Url::decodeAwsS3Url($url);
        $region = $url->getRegion();

        if ($region && $region != $this->getRegion()) {
            $this->setRegion($region);
            $this->setS3ClienteInstance();
        }

        return $this->delete($url->getTarget(), false, $url->getBucket());
    }

    public function getByUrl(string $url)
    {
        $url = Url::decodeAwsS3Url($url);
        $region = $url->getRegion();

        if ($region && $region != $this->getRegion()) {
            $this->setRegion($region);
            $this->setS3ClienteInstance();
        }

        return $this->get($url->getTarget(), $url->getBucket());
    }

    // public function list($bucket)
    // {
    //     try {
    //         $objects = $this->s3->listObjects([
    //             'Bucket' => $bucket,
    //         ]);

    //         echo '<pre>';
    //         var_dump($objects['Contents']);
    //         die();
    //     } catch (\Throwable $th) {
    //         throw new Exception("Deu ruim");
    //     }
    // }

    /**
     * Get the value of bucket
     */
    public function getBucket(): ?string
    {
        return $this->bucket;
    }

    /**
     * Set the value of bucket
     */
    public function setBucket(?string $bucket): self
    {
        $this->bucket = $bucket;

        return $this;
    }
}
