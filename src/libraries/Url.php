<?php

namespace DBarbieri\Aws\Libraries;

class Url
{
    private ?string $protocol;
    private ?string $domain;
    private ?string $bucket;
    private ?string $region;
    private ?string $target;

    public static function decodeAwsS3Url(string $url)
    {
        $explodedUrl = explode('/', $url);

        $protocol = $explodedUrl[0];
        $domain = $explodedUrl[2];
        $bucket = explode('.', $domain)[0];
        $region = explode('.', $domain)[2];
        $target = explode($domain . '/', $url)[1];

        $url = new self();
        $url
            ->setProtocol($protocol)
            ->setDomain($domain)
            ->setBucket($bucket)
            ->setRegion($region)
            ->setTarget($target);

        return $url;
    }

    /**
     * Get the value of protocol
     */
    public function getProtocol(): ?string
    {
        return $this->protocol;
    }

    /**
     * Set the value of protocol
     */
    public function setProtocol(?string $protocol): self
    {
        $this->protocol = $protocol;

        return $this;
    }

    /**
     * Get the value of domain
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Set the value of domain
     */
    public function setDomain(?string $domain): self
    {
        $this->domain = $domain;

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

    /**
     * Get the value of region
     */
    public function getRegion(): ?string
    {
        return $this->region;
    }

    /**
     * Set the value of region
     */
    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    /**
     * Get the value of target
     */
    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * Set the value of target
     */
    public function setTarget(?string $target): self
    {
        $this->target = $target;

        return $this;
    }
}
