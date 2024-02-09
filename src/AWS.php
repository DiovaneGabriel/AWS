<?php

namespace DBarbieri\Aws;

use DBarbieri\Graylog\Graylog;
use ReflectionMethod;

class AWS
{
    private ?Graylog $graylog = null;

    private string $key;
    private string $region;
    private string $secret;

    public function __construct(string $key, string $secret, string $region)
    {
        $this->key = $key;
        $this->region = $region;
        $this->secret = $secret;
    }

    protected function log(string $response, string $level = Graylog::LEVEL_INFO)
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

        $class = "";

        if ($this instanceof S3) {
            $class = "AWSS3";
        } elseif ($this instanceof SQS) {
            $class = "AWSSQS";
        }

        $message = $level . " " . $class . " " . $function;

        $logContent = [
            "class" => $class,
            "message" => $message,
            "method" => $function,
            "level" => $level,
            "request" => $request,
            "response" => $response
        ];

        return $this->graylog->send($logContent);
    }


    /**
     * Get the value of graylog
     */
    public function getGraylog(): ?Graylog
    {
        return $this->graylog;
    }

    /**
     * Set the value of graylog
     */
    public function setGraylog(?Graylog $graylog): self
    {
        $this->graylog = $graylog;

        return $this;
    }

    /**
     * Get the value of key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Set the value of key
     */
    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get the value of region
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Set the value of region
     */
    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    /**
     * Get the value of secret
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Set the value of secret
     */
    public function setSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }
}
