<?php

namespace DBarbieri\Aws;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use DBarbieri\Aws\Libraries\Url;
use DBarbieri\Graylog\Graylog;
use Error;
use Exception;
use ReflectionMethod;

class S3
{
    private S3Client $s3;
    private ?Graylog $graylog = null;

    private ?string $bucket;
    private string $key;
    private string $region;
    private string $secret;

    public function __construct(string $key, string $secret, string $region, string $bucket = null)
    {
        $this->bucket = $bucket;
        $this->key = $key;
        $this->region = $region;
        $this->secret = $secret;

        $this->setS3ClienteInstance();
    }

    private function setS3ClienteInstance()
    {
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => $this->region,
            'credentials' => [
                'key'    => $this->key,
                'secret' => $this->secret,
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

        if ($region && $region != $this->region) {
            $this->region = $region;
            $this->setS3ClienteInstance();
        }

        return $this->delete($url->getTarget(), false, $url->getBucket());
    }

    public function getByUrl(string $url)
    {
        $url = Url::decodeAwsS3Url($url);
        $region = $url->getRegion();

        if ($region && $region != $this->region) {
            $this->region = $region;
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

    private function log(string $response, string $level = Graylog::LEVEL_INFO)
    {
        if (!$this->graylog) {
            return true;
        }

        $trace = debug_backtrace();
        $step = $trace[1];

        $function = $step['function'];
        $args = $step['args'];

        $reflectionMethod = new ReflectionMethod($this, $function);
        $reflectionParameters = $reflectionMethod->getParameters();
        $annotations = $reflectionMethod->getDocComment();

        foreach ($reflectionParameters as $i => $param) {
            if (isset($args[$i])) {
                $value = $args[$i];
                if (strpos($annotations, 'NoLog ' . $param->getName()) !== false) {
                    $value = "This parameter isn't loggable!";
                }
                $params[$param->getName()] = $value;
            }
        }

        $request = [$function => $params];

        $class = "AWSS3";
        $message = $level . " " . $class . " " . $function;

        $logContent = [
            "class" => $class,
            "message" => $message,
            "method" => $function,
            "level" => $level,
            "request" => $request,
            "response" => $response
        ];

        return $this->getGraylog()->send($logContent);
    }

    /**
     * Get the value of graylog
     */
    public function getGraylog(): Graylog
    {
        return $this->graylog;
    }

    /**
     * Set the value of graylog
     */
    public function setGraylog(Graylog $graylog): self
    {
        $this->graylog = $graylog;

        return $this;
    }

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
